<?php
/**
 * Доставка Новою Поштою: довідники міст і відділень.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Shipping;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Підказки міста й відділення в checkout.
 *
 * Власних полів модуль не додає навмисно. У WooCommerce уже є «Місто»
 * й «Адреса», і тема перейменувала друге на «Адреса або відділення»
 * саме під Нову Пошту. Додати поруч ще два поля означало б питати те
 * саме двічі — а правило checkout протилежне: менше полів, більше
 * оформлених замовлень.
 *
 * Тому модуль дає лише довідники, а підказки чіпляються до наявних
 * полів. Без ключа API вони лишаються звичайними текстовими — покупець
 * пише адресу руками, і замовлення проходить як раніше.
 */
final class NovaPoshta implements Module {

	/**
	 * Вішає хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'add_routes' ) );
		add_filter( 'woocommerce_general_settings', array( $this, 'add_key_setting' ) );
		add_action( 'admin_notices', array( $this, 'directory_notice' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Перевіряє, що обране місто й відділення справді існують.
	 *
	 * Перевірка вмикається лише тоді, коли скрипт поклав у форму
	 * ідентифікатори. Без JavaScript полів із ідентифікаторами немає,
	 * і покупець пише адресу руками — вимагати від нього вибору зі
	 * списку, якого він не бачить, було б знущанням.
	 *
	 * @return void
	 */
	public function validate(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce перевіряє сам WooCommerce у woocommerce_checkout_process.
		$totals    = isset( $_POST['woocommerce_checkout_update_totals'] );
		$city      = isset( $_POST['brix_np_city_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['brix_np_city_ref'] ) ) : '';
		$warehouse = isset( $_POST['brix_np_warehouse_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['brix_np_warehouse_ref'] ) ) : '';
		// phpcs:enable

		// Натиснули «Оновити» в блоці способу отримання — покупець ще
		// не закінчив заповнювати форму, і зауваження зараз були б
		// доріканням за незроблене.
		if ( $totals ) {
			return;
		}

		// Самовивіз відділення не має. Поля з ним не показані, а старі
		// ідентифікатори могли лишитись у формі з попереднього вибору.
		if ( Pickup::chosen() ) {
			return;
		}

		/*
		 * Нова Пошта возить Україною. Для закордонного замовлення в
		 * полях звичайна адреса, а ідентифікатори могли лишитись від
		 * того моменту, коли покупець ще збирався на українську
		 * адресу, — перевіряти їх означало б вимагати відділення там,
		 * де його не питали.
		 */
		if ( ! Destination::is_home() ) {
			return;
		}

		if ( ! Directory::has( Directory::CITY ) ) {
			return;
		}

		if ( '' === $city && '' === $warehouse ) {
			return;
		}

		if ( '' === $city ) {
			wc_add_notice( __( 'Оберіть місто зі списку — так ми точно знаємо, куди відправляти.', 'brix-core' ), 'error' );

			return;
		}

		if ( '' === $warehouse ) {
			wc_add_notice( __( 'Оберіть відділення зі списку.', 'brix-core' ), 'error' );

			return;
		}

		if ( ! Directory::warehouse_in_city( $warehouse, $city ) ) {
			wc_add_notice( __( 'Це відділення не належить обраному місту. Оберіть зі списку ще раз.', 'brix-core' ), 'error' );
		}
	}

	/**
	 * Зберігає ідентифікатори в замовленні.
	 *
	 * Назва відділення може змінитись, а ідентифікатор — ні. Саме за
	 * ним колись створюватиметься накладна.
	 *
	 * @param \WC_Order            $order Замовлення.
	 * @param array<string, mixed> $data  Дані checkout.
	 * @return void
	 */
	public function save( \WC_Order $order, array $data ): void {
		unset( $data );

		if ( Pickup::chosen() || ! Destination::is_home() ) {
			return;
		}

		$fields = array(
			'brix_np_city_ref'      => '_brix_np_city_ref',
			'brix_np_warehouse_ref' => '_brix_np_warehouse_ref',
		);

		foreach ( $fields as $field => $meta ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce перевіряє сам WooCommerce у процесі оформлення.
			$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';

			if ( '' !== $value ) {
				$order->update_meta_data( $meta, $value );
			}
		}
	}

	/**
	 * Нагадує синхронізувати довідник.
	 *
	 * @return void
	 */
	public function directory_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'woocommerce_page_wc-settings' !== $screen->id || Directory::has( Directory::CITY ) ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>%s</p></div>',
			esc_html__( 'Довідник Нової Пошти порожній. Запустіть wp brix np-sync там, де є ключ API — після цього підказки працюватимуть без звернень до Нової Пошти.', 'brix-core' )
		);
	}

	/**
	 * Поле ключа API в налаштуваннях WooCommerce.
	 *
	 * @param array<int, array<string, mixed>> $settings Налаштування.
	 * @return array<int, array<string, mixed>>
	 */
	public function add_key_setting( array $settings ): array {
		$settings[] = array(
			'title' => __( 'Нова Пошта', 'brix-core' ),
			'type'  => 'title',
			'id'    => 'brix_np_options',
		);

		$settings[] = array(
			'title'   => __( 'Ключ API', 'brix-core' ),
			'desc'    => __( 'Без ключа місто й відділення лишаються звичайними текстовими полями: замовлення оформлюється, просто без підказок.', 'brix-core' ),
			'id'      => Api::OPTION_KEY,
			'type'    => 'password',
			'default' => '',
		);

		$settings[] = array(
			'type' => 'sectionend',
			'id'   => 'brix_np_options',
		);

		return $settings;
	}

	/**
	 * Маршрути довідників.
	 *
	 * @return void
	 */
	public function add_routes(): void {
		$text = array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => static function ( $value ): string {
				return sanitize_text_field( (string) $value );
			},
		);

		register_rest_route(
			'brix/v1',
			'/np/cities',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'cities' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'q' => $text ),
			)
		);

		register_rest_route(
			'brix/v1',
			'/np/warehouses',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'warehouses' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'city' => $text ),
			)
		);
	}

	/**
	 * Міста за запитом.
	 *
	 * @param \WP_REST_Request $request Запит.
	 * @return \WP_REST_Response
	 */
	public function cities( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! $this->allowed() ) {
			return new \WP_REST_Response(
				array(
					'configured' => false,
					'items'      => array(),
				),
				429
			);
		}

		$query = (string) $request->get_param( 'q' );

		// Спершу власний довідник: він не витрачає квоту акаунта і
		// відповідає швидше за раунд-тріп до чужого сервера.
		if ( Directory::has( Directory::CITY ) ) {
			return rest_ensure_response(
				array(
					'configured' => true,
					'items'      => mb_strlen( trim( $query ) ) < 2 ? array() : Directory::cities( $query ),
				)
			);
		}

		return rest_ensure_response(
			array(
				'configured' => Api::configured(),
				'items'      => ( new Api() )->settlements( $query ),
			)
		);
	}

	/**
	 * Відділення в місті.
	 *
	 * @param \WP_REST_Request $request Запит.
	 * @return \WP_REST_Response
	 */
	public function warehouses( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! $this->allowed() ) {
			return new \WP_REST_Response(
				array(
					'configured' => false,
					'items'      => array(),
				),
				429
			);
		}

		$city = (string) $request->get_param( 'city' );

		if ( Directory::has( Directory::WAREHOUSE ) ) {
			return rest_ensure_response(
				array(
					'configured' => true,
					'items'      => '' === trim( $city ) ? array() : Directory::warehouses( $city ),
				)
			);
		}

		return rest_ensure_response(
			array(
				'configured' => Api::configured(),
				'items'      => ( new Api() )->warehouses( $city ),
			)
		);
	}

	/**
	 * Чи не забагато запитів з однієї адреси.
	 *
	 * Маршрут відкритий — інакше гість не заповнив би checkout. Тож
	 * єдиний захист від перебору це частота.
	 *
	 * Спершу тут стояло двадцять запитів на хвилину — і цього не
	 * вистачало живому покупцю. Поки він дописував «Відділення №12»,
	 * кожна літера означала окремий запит, ліміт спрацьовував, а
	 * список мовчки порожнів. Скрипт тепер питає відділення міста
	 * один раз, але запас усе одно потрібен: людина буває в дорозі,
	 * за спільним IP і з кількома вкладками. Довідник лежить у нашій
	 * базі й квоти Нової Пошти не витрачає, тож шістдесят запитів —
	 * не витрата, а просто стеля для перебирача.
	 *
	 * @return bool
	 */
	private function allowed(): bool {
		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		if ( '' === $ip ) {
			return true;
		}

		$key   = 'brix_np_rate_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= 60 ) {
			return false;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

		return true;
	}
}
