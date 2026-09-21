<?php
/**
 * REST для картки товару: вибір ваги й помелу без перезавантаження.
 *
 * Ендпоінт не рахує ціну сам і не збирає розмітку власним кодом: він
 * підставляє вибір у те саме джерело, з якого читає сторінка, і малює
 * той самий блок покупки. Тож AJAX-варіант не може розійтися зі
 * звичайним переходом за посиланням.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Реєструє маршрут блоку покупки.
 *
 * @return void
 */
function brix_register_product_route(): void {
	register_rest_route(
		'brix/v1',
		'/product',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'brix_product_rest_response',
			'permission_callback' => '__return_true',
			'args'                => array(
				'id' => array(
					'type'     => 'integer',
					'required' => true,
					'minimum'  => 1,
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'brix_register_product_route' );

/**
 * Блок покупки під обрану комбінацію.
 *
 * @param WP_REST_Request $request Запит.
 * @return WP_REST_Response|WP_Error
 */
function brix_product_rest_response( WP_REST_Request $request ) {
	if ( ! brix_has_woocommerce() ) {
		return new WP_Error( 'brix_no_shop', __( 'Магазин недоступний.', 'brix' ), array( 'status' => 503 ) );
	}

	$product = wc_get_product( (int) $request->get_param( 'id' ) );

	if ( ! $product instanceof WC_Product || 'publish' !== $product->get_status() ) {
		return new WP_Error( 'brix_no_product', __( 'Такого лоту немає.', 'brix' ), array( 'status' => 404 ) );
	}

	/*
	 * Вибір іде в те саме джерело, з якого його читає сторінка.
	 * Значення перевіряє brix_selected_attributes(): усе, чого немає
	 * серед справжніх варіантів атрибута, просто відкидається.
	 */
	brix_variation_request( $request->get_params() );

	$selected = brix_selected_attributes( $product );

	return rest_ensure_response(
		array(
			'buy' => brix_render_buy_block( $product ),
			'url' => brix_variation_permalink( $product, $selected ),
		)
	);
}

/**
 * Малює блок покупки в рядок.
 *
 * Шаблон друкує заголовок через the_title(), тож йому потрібен
 * глобальний запис — у REST його немає, і ми підставляємо свій.
 *
 * @param WC_Product $product Товар.
 * @return string
 */
function brix_render_buy_block( WC_Product $product ): string {
	global $post;

	$previous = $post;
	$post     = get_post( $product->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

	setup_postdata( $post );

	ob_start();
	get_template_part(
		'template-parts/product/buy',
		null,
		array(
			'product' => $product,
			'lot'     => brix_lot( $product ),
		)
	);
	$html = (string) ob_get_clean();

	$post = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

	wp_reset_postdata();

	return $html;
}

/**
 * Адреса товару з обраною комбінацією.
 *
 * @param WC_Product            $product  Товар.
 * @param array<string, string> $selected Обрані атрибути.
 * @return string
 */
function brix_variation_permalink( WC_Product $product, array $selected ): string {
	$url = (string) $product->get_permalink();

	return $selected ? add_query_arg( $selected, $url ) : $url;
}
