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
	 * @return \WC_Payment_Gateway|null
	 */
	private function gateway(): ?\WC_Payment_Gateway {
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();

		foreach ( $gateways as $gateway ) {
			if ( $gateway instanceof Monobank ) {
				return $gateway;
			}
		}

		return null;
	}
}
