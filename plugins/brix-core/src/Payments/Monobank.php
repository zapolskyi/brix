<?php
/**
 * Оплата карткою через monobank Acquiring.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Спосіб оплати monobank.
 *
 * Картку покупець вводить на сторінці monobank, а не в нас: ми лише
 * створюємо інвойс і відправляємо туди. Платіжних даних на нашому
 * сервері не буває взагалі — це знімає і питання PCI, і половину
 * роботи (рішення 18).
 *
 * Статус замовлення міняє не повернення покупця, а вебхук: покупець
 * може закрити вкладку одразу після оплати, і тоді редіректу не буде.
 */
final class Monobank extends \WC_Payment_Gateway {

	/**
	 * Базова адреса API.
	 */
	private const API = 'https://api.monobank.ua/api/merchant';

	/**
	 * Код гривні за ISO 4217.
	 */
	private const UAH = 980;

	/**
	 * Скільки живе інвойс, секунд.
	 */
	private const VALIDITY = 3600;

	/**
	 * Готує спосіб оплати.
	 */
	public function __construct() {
		$this->id                 = 'brix_monobank';
		$this->method_title       = __( 'monobank', 'brix-core' );
		$this->method_description = __( 'Оплата карткою на стороні monobank. Платіжні дані на сайт не потрапляють.', 'brix-core' );
		$this->has_fields         = false;
		$this->supports           = array( 'products', 'refunds' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = (string) $this->get_option( 'title' );
		$this->description = (string) $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
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
				'label'   => __( 'Приймати оплату карткою через monobank', 'brix-core' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'   => __( 'Назва для покупця', 'brix-core' ),
				'type'    => 'text',
				// Назва мусить відрізнятись від інших карткових
				// способів: два однакові «Картка онлайн» у checkout
				// виглядають як помилка сайту.
				'default' => __( 'Картка · Apple Pay · Google Pay', 'brix-core' ),
			),
			'description' => array(
				'title'   => __( 'Опис', 'brix-core' ),
				'type'    => 'textarea',
				'default' => __( 'Оплата карткою на захищеній сторінці monobank. Apple Pay і Google Pay теж працюють.', 'brix-core' ),
			),
			'token'       => array(
				'title'       => __( 'Токен продавця', 'brix-core' ),
				'type'        => 'password',
				'description' => sprintf(
					/* translators: %s — адреса сторінки з токеном. */
					__( 'Тестовий токен видають на %s. Без токена спосіб оплати не показується покупцям.', 'brix-core' ),
					'<a href="https://api.monobank.ua/" target="_blank" rel="noopener">api.monobank.ua</a>'
				),
				'default'     => '',
			),
		);
	}

	/**
	 * Спосіб оплати без токена не показується.
	 *
	 * Інакше покупець обрав би оплату, яка гарантовано впаде.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return parent::is_available() && '' !== trim( (string) $this->get_option( 'token' ) );
	}

	/**
	 * Створює інвойс і відправляє покупця на сторінку оплати.
	 *
	 * @param int $order_id Ідентифікатор замовлення.
	 * @return array<string, string>
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return array( 'result' => 'failure' );
		}

		$invoice = $this->create_invoice( $order );

		if ( is_wp_error( $invoice ) ) {
			// Помилку API покупцю показувати нема сенсу — вона
			// англійською і про наш бекенд. У лог, а покупцю по-людськи.
			$order->add_order_note(
				sprintf(
					/* translators: %s — текст помилки. */
					__( 'monobank не створив інвойс: %s', 'brix-core' ),
					$invoice->get_error_message()
				)
			);

			wc_add_notice( __( 'Не вдалося перейти до оплати. Спробуйте ще раз або оберіть інший спосіб.', 'brix-core' ), 'error' );

			return array( 'result' => 'failure' );
		}

		$order->update_meta_data( '_brix_monobank_invoice', $invoice['invoiceId'] );
		$order->update_status( 'pending', __( 'Очікуємо оплату через monobank.', 'brix-core' ) );
		$order->save();

		return array(
			'result'   => 'success',
			'redirect' => $invoice['pageUrl'],
		);
	}

	/**
	 * Запит на створення інвойсу.
	 *
	 * @param \WC_Order $order Замовлення.
	 * @return array{invoiceId: string, pageUrl: string}|\WP_Error
	 */
	private function create_invoice( \WC_Order $order ) {
		$body = array(
			// API рахує в копійках: 620,00 ₴ — це 62000.
			'amount'           => (int) round( (float) $order->get_total() * 100 ),
			'ccy'              => self::UAH,
			'merchantPaymInfo' => array(
				'reference'   => (string) $order->get_id(),
				'destination' => sprintf(
					/* translators: %s — номер замовлення. */
					__( 'Замовлення №%s у BRIX 22°', 'brix-core' ),
					$order->get_order_number()
				),
				'basketOrder' => $this->basket( $order ),
			),
			'redirectUrl'      => $this->get_return_url( $order ),
			'webHookUrl'       => rest_url( 'brix/v1/monobank' ),
			'validity'         => self::VALIDITY,
		);

		$response = wp_remote_post(
			self::API . '/invoice/create',
			array(
				'timeout' => 20,
				'headers' => array(
					'X-Token'      => (string) $this->get_option( 'token' ),
					'Content-Type' => 'application/json',
				),
				'body'    => (string) wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $data ) || empty( $data['pageUrl'] ) ) {
			return new \WP_Error(
				'brix_monobank_invoice',
				is_array( $data ) && isset( $data['errText'] ) ? (string) $data['errText'] : 'HTTP ' . $code
			);
		}

		return array(
			'invoiceId' => (string) $data['invoiceId'],
			'pageUrl'   => (string) $data['pageUrl'],
		);
	}

	/**
	 * Склад замовлення для сторінки оплати.
	 *
	 * @param \WC_Order $order Замовлення.
	 * @return array<int, array<string, mixed>>
	 */
	private function basket( \WC_Order $order ): array {
		$basket = array();

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$basket[] = array(
				'name' => $item->get_name(),
				'qty'  => (int) $item->get_quantity(),
				'sum'  => (int) round( (float) $item->get_total() * 100 ),
				'unit' => __( 'шт', 'brix-core' ),
				'code' => (string) $item->get_product_id(),
			);
		}

		return $basket;
	}

	/**
	 * Питає в API справжній статус інвойсу.
	 *
	 * Вебхуку самого по собі мало: його може підробити будь-хто, хто
	 * знає адресу. Тож статус ми не приймаємо з тіла запиту, а
	 * перепитуємо в monobank за ідентифікатором інвойсу.
	 *
	 * @param string $invoice Ідентифікатор інвойсу.
	 * @return string|\WP_Error
	 */
	public function fetch_status( string $invoice ) {
		$response = wp_remote_get(
			self::API . '/invoice/status?invoiceId=' . rawurlencode( $invoice ),
			array(
				'timeout' => 20,
				'headers' => array( 'X-Token' => (string) $this->get_option( 'token' ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || empty( $data['status'] ) ) {
			return new \WP_Error( 'brix_monobank_status', 'HTTP ' . wp_remote_retrieve_response_code( $response ) );
		}

		return (string) $data['status'];
	}
}
