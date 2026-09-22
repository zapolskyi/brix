<?php
/**
 * REST кошика: додавання товару без перезавантаження.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Реєструє маршрут додавання в кошик.
 *
 * @return void
 */
function brix_register_cart_route(): void {
	register_rest_route(
		'brix/v1',
		'/cart/add',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'brix_cart_add',

			/*
			 * Тут дія, а не читання, тож маршрут захищений nonce:
			 * WordPress перевіряє його сам, коли скрипт шле заголовок
			 * X-WP-Nonce. Без цього чужа сторінка могла б класти
			 * товари в кошик відвідувача.
			 */
			'permission_callback' => static function (): bool {
				return wp_verify_nonce(
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Саме це й перевіряємо.
					isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '',
					'wp_rest'
				) > 0;
			},
			'args'                => array(
				'id'       => array(
					'type'     => 'integer',
					'required' => true,
					'minimum'  => 1,
				),
				'quantity' => array(
					'type'    => 'integer',
					'default' => 1,
					'minimum' => 1,
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'brix_register_cart_route' );

/**
 * Кладе товар у кошик і віддає оновлений лічильник.
 *
 * @param WP_REST_Request $request Запит.
 * @return WP_REST_Response|WP_Error
 */
function brix_cart_add( WP_REST_Request $request ) {
	/*
	 * У REST-запиті кошика ще немає: WooCommerce вантажить сесію й
	 * WC()->cart лише для фронтенду, а REST під це визначення не
	 * підпадає. wc_load_cart() — рівно той гачок, який для цього й
	 * призначений.
	 */
	if ( brix_has_woocommerce() && ! WC()->cart && function_exists( 'wc_load_cart' ) ) {
		wc_load_cart();
	}

	/*
	 * Самого wc_load_cart() мало. Новий WC_Cart чекає на `wp_loaded`,
	 * щоб підтягнути вміст із сесії, а в REST цей хук уже відпрацював —
	 * тож кошик лишався порожнім, і кожне додавання давало «1 товар»
	 * незалежно від того, скільки разів натиснути. get_cart() читає
	 * сесію явно.
	 */
	if ( WC()->cart ) {
		WC()->cart->get_cart();
	}

	if ( ! brix_has_woocommerce() || ! WC()->cart ) {
		return new WP_Error( 'brix_no_cart', __( 'Кошик недоступний.', 'brix' ), array( 'status' => 503 ) );
	}

	$id      = (int) $request->get_param( 'id' );
	$product = wc_get_product( $id );

	if ( ! $product instanceof WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		return new WP_Error( 'brix_not_purchasable', __( 'Цей лот зараз купити не можна.', 'brix' ), array( 'status' => 409 ) );
	}

	$added = WC()->cart->add_to_cart( $id, max( 1, (int) $request->get_param( 'quantity' ) ) );

	if ( ! $added ) {
		// WooCommerce уже поклав пояснення в сповіщення — беремо його,
		// щоб покупець побачив причину, а не загальне «щось пішло не так».
		$notices = wc_get_notices( 'error' );
		wc_clear_notices();

		return new WP_Error(
			'brix_not_added',
			$notices ? wp_strip_all_tags( (string) ( $notices[0]['notice'] ?? '' ) ) : __( 'Не вдалося додати лот у кошик.', 'brix' ),
			array( 'status' => 409 )
		);
	}

	/*
	 * Вміст кошика лягає в сесію на хуку woocommerce_after_calculate_totals.
	 * Без цього виклику додавання жило рівно до кінця запиту: наступне
	 * знову бачило порожній кошик, і лічильник назавжди показував «1».
	 */
	WC()->cart->calculate_totals();

	ob_start();
	brix_bag_badge();
	$badge = (string) ob_get_clean();

	return rest_ensure_response(
		array(
			'count' => brix_bag_count(),
			'badge' => $badge,
			'name'  => $product->get_name(),
		)
	);
}
