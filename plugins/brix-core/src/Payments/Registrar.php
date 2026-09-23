<?php
/**
 * Реєстрація способів оплати й прийом вебхуків.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Payments;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Підключає власні способи оплати до WooCommerce.
 */
final class Registrar implements Module {

	/**
	 * Вішає хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
		add_action( 'rest_api_init', array( $this, 'add_routes' ) );
		add_action( 'woocommerce_before_thankyou', array( $this, 'confirm_liqpay' ), 5 );
		add_action( 'template_redirect', array( $this, 'skip_receipt' ) );
	}

	/**
	 * Веде неоплачене замовлення LiqPay одразу на сторінку оплати.
	 *
	 * Кнопка «Оплатити» з подяки, кабінету чи листа веде на
	 * «Оплатити замовлення», а там форма з автовідправкою: покупець
	 * бачив на мить нашу сторінку, яка тут же змінювалась чужою.
	 * Перенаправлення сервером прибирає цей проміжний кадр. Сама
	 * сторінка лишається запасним шляхом — для браузера, що
	 * перенаправлення не виконав.
	 *
	 * @return void
	 */
	public function skip_receipt(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Доступ до замовлення перевіряє його ключ.
		if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-pay' ) || isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$order = wc_get_order( absint( get_query_var( 'order-pay' ) ) );

		if ( ! $order instanceof \WC_Order || ! hash_equals( $order->get_order_key(), $key ) ) {
			return;
		}

		if ( 'brix_liqpay' !== $order->get_payment_method() || ! $order->needs_payment() ) {
			return;
		}

		$gateway = $this->gateway( LiqPay::class );

		if ( ! $gateway instanceof LiqPay || ! $gateway->is_available() ) {
			return;
		}

		// Зовнішня адреса, тож wp_redirect, а не wp_safe_redirect.
		wp_redirect( $gateway->checkout_url( $order ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Адреса LiqPay, збудована нами.
		exit;
	}

	/**
	 * Перепитує LiqPay, коли покупець повертається на подяку.
	 *
	 * Покупець повертається з LiqPay раніше, ніж callback встигає
	 * дійти, — а локальний сайт callback не отримує взагалі. Тож
	 * статус уточнюємо самі, щойно покупець відкрив сторінку подяки:
	 * він має бачити «оплачено», а не «чекаємо».
	 *
	 * @param int $order_id Замовлення.
	 * @return void
	 */
	public function confirm_liqpay( $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order || 'brix_liqpay' !== $order->get_payment_method() || ! $order->needs_payment() ) {
			return;
		}

		$gateway = $this->gateway( LiqPay::class );

		if ( ! $gateway instanceof LiqPay ) {
			return;
		}

		$status = $gateway->fetch_status( $order );

		/*
		 * «error» тут означає не збій оплати, а «платежу ще немає»:
		 * так LiqPay відповідає про замовлення, яке покупець не почав
		 * оплачувати. Позначити таке невдалим — означало б відібрати
		 * в покупця можливість оплатити його пізніше.
		 */
		if ( is_wp_error( $status ) || 'error' === $status['status'] ) {
			return;
		}

		LiqPay::apply_status( $order, (string) $status['status'], (string) ( $status['payment_id'] ?? '' ) );
	}

	/**
	 * Додає monobank у список способів оплати.
	 *
	 * @param array<int, string> $gateways Способи.
	 * @return array<int, string>
	 */
	public function add_gateways( array $gateways ): array {
		$gateways[] = Monobank::class;
		$gateways[] = LiqPay::class;

		return $gateways;
	}

	/**
	 * Маршрут вебхука monobank.
	 *
	 * @return void
	 */
	public function add_routes(): void {
		register_rest_route(
			'brix/v1',
			'/liqpay',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_liqpay' ),

				/*
				 * Автентифікації тут теж немає, але захист сильніший
				 * за monobank: LiqPay підписує відповідь нашим
				 * приватним ключем, і підпис перевіряється локально.
				 */
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'brix/v1',
			'/monobank',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_webhook' ),

				/*
				 * Відкритий маршрут: monobank не має наших ключів і не
				 * може автентифікуватись. Захист інший — тіло запиту
				 * ми не приймаємо на віру, а перепитуємо статус у API.
				 */
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Обробляє вебхук про зміну статусу оплати.
	 *
	 * @param \WP_REST_Request $request Запит.
	 * @return \WP_REST_Response
	 */
	public function handle_webhook( \WP_REST_Request $request ): \WP_REST_Response {
		$body      = (array) $request->get_json_params();
		$invoice   = isset( $body['invoiceId'] ) ? sanitize_text_field( (string) $body['invoiceId'] ) : '';
		$reference = isset( $body['reference'] ) ? absint( $body['reference'] ) : 0;

		if ( '' === $invoice || ! $reference ) {
			return new \WP_REST_Response( array( 'ok' => false ), 400 );
		}

		$order = wc_get_order( $reference );

		if ( ! $order instanceof \WC_Order ) {
			return new \WP_REST_Response( array( 'ok' => false ), 404 );
		}

		// Інвойс має належати саме цьому замовленню: інакше знання
		// чужого номера дозволяло б міняти статус будь-якого з них.
		if ( $order->get_meta( '_brix_monobank_invoice' ) !== $invoice ) {
			return new \WP_REST_Response( array( 'ok' => false ), 403 );
		}

		$gateway = $this->gateway();

		if ( ! $gateway instanceof Monobank ) {
			return new \WP_REST_Response( array( 'ok' => false ), 503 );
		}

		$status = $gateway->fetch_status( $invoice );

		if ( is_wp_error( $status ) ) {
			// Відповідаємо помилкою навмисно: monobank повторить
			// вебхук, і замовлення не зависне через збій мережі.
			return new \WP_REST_Response( array( 'ok' => false ), 502 );
		}

		$this->apply_status( $order, $status );

		return new \WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Переводить замовлення у стан, що відповідає статусу оплати.
	 *
	 * @param \WC_Order $order  Замовлення.
	 * @param string    $status Статус monobank.
	 * @return void
	 */
	private function apply_status( \WC_Order $order, string $status ): void {
		if ( 'success' === $status ) {
			// payment_complete сам не зробить нічого вдруге, якщо
			// вебхук прийде повторно, — а він прийде.
			$order->payment_complete( (string) $order->get_meta( '_brix_monobank_invoice' ) );

			return;
		}

		$map = array(
			'failure'  => 'failed',
			'expired'  => 'cancelled',
			'reversed' => 'refunded',
		);

		if ( isset( $map[ $status ] ) && ! $order->has_status( $map[ $status ] ) ) {
			$order->update_status(
				$map[ $status ],
				sprintf(
					/* translators: %s — статус від monobank. */
					__( 'monobank повідомив статус «%s».', 'brix-core' ),
					$status
				)
			);
		}
	}

	/**
	 * Налаштований спосіб оплати monobank.
	 *
	 * @param string $wanted Клас потрібного способу оплати.
	 * @return \WC_Payment_Gateway|null
	 */
	private function gateway( string $wanted = Monobank::class ): ?\WC_Payment_Gateway {
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();

		foreach ( $gateways as $gateway ) {
			if ( $gateway instanceof $wanted ) {
				return $gateway;
			}
		}

		return null;
	}

	/**
	 * Обробляє callback LiqPay.
	 *
	 * @param \WP_REST_Request $request Запит.
	 * @return \WP_REST_Response
	 */
	public function handle_liqpay( \WP_REST_Request $request ): \WP_REST_Response {
		$data      = (string) $request->get_param( 'data' );
		$signature = (string) $request->get_param( 'signature' );

		if ( '' === $data || '' === $signature ) {
			return new \WP_REST_Response( array( 'ok' => false ), 400 );
		}

		$gateway = $this->gateway( LiqPay::class );

		if ( ! $gateway instanceof LiqPay ) {
			return new \WP_REST_Response( array( 'ok' => false ), 503 );
		}

		// Підпис перевіряємо до розбору даних: невірно підписане тіло
		// не варто навіть декодувати.
		if ( ! $gateway->verify( $data, $signature ) ) {
			return new \WP_REST_Response( array( 'ok' => false ), 403 );
		}

		$payload = json_decode( (string) base64_decode( $data, true ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Формат, який вимагає LiqPay.

		if ( ! is_array( $payload ) || empty( $payload['order_id'] ) ) {
			return new \WP_REST_Response( array( 'ok' => false ), 400 );
		}

		$order = $this->order_from_reference( (string) $payload['order_id'] );

		if ( ! $order instanceof \WC_Order ) {
			return new \WP_REST_Response( array( 'ok' => false ), 404 );
		}

		LiqPay::apply_status( $order, (string) ( $payload['status'] ?? '' ), (string) ( $payload['payment_id'] ?? '' ) );

		return new \WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Замовлення за посиланням «ідентифікатор-ключ».
	 *
	 * Сам номер замовлення в order_id був би підказкою для перебору,
	 * тож поруч із ним їде ключ замовлення — той самий, що WooCommerce
	 * використовує для доступу до сторінки подяки.
	 *
	 * @param string $reference Посилання з платежу.
	 * @return \WC_Order|null
	 */
	private function order_from_reference( string $reference ): ?\WC_Order {
		$parts = explode( '-', $reference, 2 );
		$order = wc_get_order( absint( $parts[0] ?? 0 ) );

		if ( ! $order instanceof \WC_Order ) {
			return null;
		}

		return hash_equals( $order->get_order_key(), (string) ( $parts[1] ?? '' ) ) ? $order : null;
	}
}
