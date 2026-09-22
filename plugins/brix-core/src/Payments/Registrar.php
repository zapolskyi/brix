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

		$this->apply_liqpay_status( $order, (string) ( $payload['status'] ?? '' ), (string) ( $payload['payment_id'] ?? '' ) );

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

	/**
	 * Переводить замовлення у стан за статусом LiqPay.
	 *
	 * @param \WC_Order $order   Замовлення.
	 * @param string    $status  Статус LiqPay.
	 * @param string    $payment Ідентифікатор платежу.
	 * @return void
	 */
	private function apply_liqpay_status( \WC_Order $order, string $status, string $payment ): void {
		// sandbox — той самий успіх, тільки без грошей.
		if ( in_array( $status, array( 'success', 'sandbox' ), true ) ) {
			$order->payment_complete( $payment );

			if ( 'sandbox' === $status ) {
				$order->add_order_note( __( 'LiqPay: тестовий платіж, гроші не списані.', 'brix-core' ) );
			}

			return;
		}

		$map = array(
			'failure'  => 'failed',
			'error'    => 'failed',
			'reversed' => 'refunded',
			'expired'  => 'cancelled',
		);

		if ( isset( $map[ $status ] ) && ! $order->has_status( $map[ $status ] ) ) {
			$order->update_status(
				$map[ $status ],
				sprintf(
					/* translators: %s — статус від LiqPay. */
					__( 'LiqPay повідомив статус «%s».', 'brix-core' ),
					$status
				)
			);
		}
	}
}
