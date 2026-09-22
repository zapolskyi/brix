<?php
/**
 * Оплата карткою через LiqPay.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Спосіб оплати LiqPay.
 *
 * Як і monobank, картку покупець вводить на боці платіжної системи.
 * Різниця в механіці: LiqPay не має API створення інвойсу — замість
 * нього форма з двох полів, `data` і `signature`, яку браузер
 * надсилає на сторінку оплати.
 *
 * Підпис — це base64 від SHA-1 склейки «приватний ключ + дані +
 * приватний ключ». Той самий підпис приходить назад у callback, і
 * саме він доводить, що статус прийшов від LiqPay, а не від того,
 * хто вгадав адресу.
 */
final class LiqPay extends \WC_Payment_Gateway {

	/**
	 * Сторінка оплати.
	 */
	private const CHECKOUT = 'https://www.liqpay.ua/api/3/checkout';

	/**
	 * Версія API.
	 */
	private const VERSION = 3;

	/**
	 * Готує спосіб оплати.
	 */
	public function __construct() {
		$this->id                 = 'brix_liqpay';
		$this->method_title       = __( 'LiqPay', 'brix-core' );
		$this->method_description = __( 'Оплата карткою на стороні LiqPay. Платіжні дані на сайт не потрапляють.', 'brix-core' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = (string) $this->get_option( 'title' );
		$this->description = (string) $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt' ) );
	}

	/**
	 * Поля налаштувань.
	 *
	 * @return void
	 */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Увімкнено', 'brix-core' ),
				'type'    => 'checkbox',
				'label'   => __( 'Приймати оплату карткою через LiqPay', 'brix-core' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'    => __( 'Назва для покупця', 'brix-core' ),
				'type'     => 'text',
				'default'  => __( 'Картка онлайн', 'brix-core' ),
				'desc_tip' => __( 'Якщо ввімкнено кілька карткових способів, назви мають відрізнятись.', 'brix-core' ),
			),
			'description' => array(
				'title'   => __( 'Опис', 'brix-core' ),
				'type'    => 'textarea',
				'default' => __( 'Visa, Mastercard, Apple Pay і Google Pay на захищеній сторінці LiqPay.', 'brix-core' ),
			),
			'public_key'  => array(
				'title'       => __( 'Публічний ключ', 'brix-core' ),
				'type'        => 'text',
				'description' => __( 'З кабінету LiqPay. Тестовий ключ починається на sandbox_.', 'brix-core' ),
				'default'     => '',
			),
			'private_key' => array(
				'title'   => __( 'Приватний ключ', 'brix-core' ),
				'type'    => 'password',
				'default' => '',
			),
			'sandbox'     => array(
				'title'   => __( 'Тестовий режим', 'brix-core' ),
				'type'    => 'checkbox',
				'label'   => __( 'Платежі не списують справжні гроші', 'brix-core' ),
				'default' => 'yes',
			),
		);
	}

	/**
	 * Без ключів спосіб оплати не показується.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return parent::is_available()
			&& '' !== trim( (string) $this->get_option( 'public_key' ) )
			&& '' !== trim( (string) $this->get_option( 'private_key' ) );
	}

	/**
	 * Відправляє покупця на сторінку оплати.
	 *
	 * @param int $order_id Замовлення.
	 * @return array<string, string>
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return array( 'result' => 'failure' );
		}

		$order->update_status( 'pending', __( 'Очікуємо оплату через LiqPay.', 'brix-core' ) );

		/*
		 * LiqPay приймає не редірект, а POST із формою, тож ведемо
		 * покупця на проміжну сторінку «Оплата» — її малює WooCommerce
		 * хуком woocommerce_receipt.
		 */
		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Форма, що веде на сторінку LiqPay.
	 *
	 * @param int $order_id Замовлення.
	 * @return void
	 */
	public function receipt( $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$data      = $this->encode( $this->payload( $order ) );
		$signature = $this->sign( $data );

		printf(
			'<p>%s</p>',
			esc_html__( 'Переходимо на захищену сторінку LiqPay…', 'brix-core' )
		);

		printf(
			'<form id="brix-liqpay" method="post" action="%s" accept-charset="utf-8">
				<input type="hidden" name="data" value="%s">
				<input type="hidden" name="signature" value="%s">
				<button class="brix-btn brix-btn--dark brix-btn--xl" type="submit">%s</button>
			</form>',
			esc_url( self::CHECKOUT ),
			esc_attr( $data ),
			esc_attr( $signature ),
			esc_html__( 'Перейти до оплати', 'brix-core' )
		);

		/*
		 * Кнопка лишається видимою навмисно: без JavaScript
		 * автовідправки не буде, і покупець мусить мати що натиснути.
		 */
		wp_add_inline_script( 'brix-app', 'document.getElementById("brix-liqpay").submit();', 'after' );
	}

	/**
	 * Дані платежу.
	 *
	 * @param \WC_Order $order Замовлення.
	 * @return array<string, mixed>
	 */
	private function payload( \WC_Order $order ): array {
		return array(
			'public_key'  => (string) $this->get_option( 'public_key' ),
			'version'     => self::VERSION,
			'action'      => 'pay',
			'amount'      => round( (float) $order->get_total(), 2 ),
			'currency'    => $order->get_currency(),
			'description' => sprintf(
				/* translators: %s — номер замовлення. */
				__( 'Замовлення №%s у BRIX 22°', 'brix-core' ),
				$order->get_order_number()
			),
			'order_id'    => (string) $order->get_id() . '-' . $order->get_order_key(),
			'sandbox'     => 'yes' === $this->get_option( 'sandbox' ) ? 1 : 0,
			'server_url'  => rest_url( 'brix/v1/liqpay' ),
			'result_url'  => $this->get_return_url( $order ),
			'language'    => 'uk',
		);
	}

	/**
	 * Кодує дані так, як цього чекає LiqPay.
	 *
	 * @param array<string, mixed> $payload Дані.
	 * @return string
	 */
	public function encode( array $payload ): string {
		return base64_encode( (string) wp_json_encode( $payload ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Формат, який вимагає LiqPay.
	}

	/**
	 * Підпис даних.
	 *
	 * @param string $data Закодовані дані.
	 * @return string
	 */
	public function sign( string $data ): string {
		$private = (string) $this->get_option( 'private_key' );

		return base64_encode( sha1( $private . $data . $private, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Формат підпису LiqPay.
	}

	/**
	 * Чи збігається підпис із даними.
	 *
	 * @param string $data      Закодовані дані.
	 * @param string $signature Підпис із запиту.
	 * @return bool
	 */
	public function verify( string $data, string $signature ): bool {
		// hash_equals, а не ==: порівняння рядків із раннім виходом
		// піддається атаці на час відповіді.
		return hash_equals( $this->sign( $data ), $signature );
	}
}
