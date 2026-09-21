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
		return rest_ensure_response(
			array(
				'configured' => Api::configured(),
				'items'      => ( new Api() )->settlements( (string) $request->get_param( 'q' ) ),
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
		return rest_ensure_response(
			array(
				'configured' => Api::configured(),
				'items'      => ( new Api() )->warehouses( (string) $request->get_param( 'city' ) ),
			)
		);
	}
}
