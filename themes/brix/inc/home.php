<?php
/**
 * Дані для секцій головної.
 *
 * Секції винесені в окремі частини шаблону навмисно: у фазі 3В вони
 * стануть render-колбеками власних блоків Gutenberg, і переписувати
 * розмітку не доведеться — блок лише обгорне ту саму частину.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Лот тижня, а якщо не позначений — найсвіжіший.
 *
 * @return WC_Product|null
 */
function brix_featured_lot(): ?WC_Product {
	$args = array(
		'post_type'      => 'product',
		'posts_per_page' => 1,
		'post_status'    => 'publish',
		'fields'         => 'ids',
	);

	if ( brix_has_core() ) {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Один товар на головну, результат кешується.
		$args['meta_query'] = array(
			array(
				'key'   => \Brix\Core\Product\LotMeta::key( 'lot_of_week' ),
				'value' => '1',
			),
		);
	}

	$ids = get_posts( $args );

	if ( ! $ids ) {
		unset( $args['meta_query'] );
		$args['orderby'] = 'date';
		$ids             = get_posts( $args );
	}

	$product = $ids ? wc_get_product( (int) $ids[0] ) : null;

	return $product instanceof WC_Product ? $product : null;
}

/**
 * Лоти для сітки на головній.
 *
 * @param int $limit Скільки.
 * @return array<int, WC_Product>
 */
function brix_home_lots( int $limit = 4 ): array {
	$ids = get_posts(
		array(
			'post_type'      => 'product',
			'posts_per_page' => $limit,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => array( 'origin', 'lab', 'core' ),
				),
			),
		)
	);

	$products = array_values( array_filter( array_map( 'wc_get_product', $ids ) ) );

	// Лот тижня йде першим — так у макеті, і це єдина причина, чому
	// плашка «Лот тижня» взагалі щось означає.
	$featured = brix_featured_lot();

	if ( ! $featured ) {
		return $products;
	}

	$products = array_values(
		array_filter(
			$products,
			static fn( WC_Product $item ): bool => $item->get_id() !== $featured->get_id()
		)
	);

	array_unshift( $products, $featured );

	return array_slice( $products, 0, $limit );
}

/**
 * Смакові ноти для рухомого рядка під героєм.
 *
 * @return array<int, string>
 */
function brix_marquee_notes(): array {
	$terms = get_terms(
		array(
			'taxonomy'     => 'brix_note',
			'hide_empty'   => true,
			'number'       => 14,
			'parent'       => 0,
			'exclude_tree' => array(),
		)
	);

	if ( is_wp_error( $terms ) || ! $terms ) {
		return array();
	}

	// Показуємо конкретні ноти, а не групи: «черешня» звучить смачніше
	// за «ягоди», а рядок під героєм саме про смак.
	$children = get_terms(
		array(
			'taxonomy'   => 'brix_note',
			'hide_empty' => true,
			'number'     => 14,
			'childless'  => false,
		)
	);

	$names = array();

	foreach ( is_wp_error( $children ) ? array() : $children as $term ) {
		if ( $term->parent > 0 ) {
			$names[] = $term->name;
		}
	}

	return $names ? $names : wp_list_pluck( $terms, 'name' );
}

/**
 * Виробники для секції на головній.
 *
 * @param int $limit Скільки.
 * @return array<int, WP_Post>
 */
function brix_home_farms( int $limit = 4 ): array {
	return get_posts(
		array(
			'post_type'      => 'brix_farm',
			'posts_per_page' => $limit,
			'post_status'    => 'publish',
			'orderby'        => 'menu_order date',
			'order'          => 'ASC',
		)
	);
}

/**
 * Гайди для секції на головній.
 *
 * @param int $limit Скільки.
 * @return array<int, WP_Post>
 */
function brix_home_guides( int $limit = 4 ): array {
	return get_posts(
		array(
			'post_type'      => 'brix_guide',
			'posts_per_page' => $limit,
			'post_status'    => 'publish',
			'orderby'        => 'menu_order date',
			'order'          => 'ASC',
		)
	);
}

/**
 * Посилання на сторінку за slug — для кнопок головної.
 *
 * @param string $slug Slug сторінки.
 * @return string
 */
function brix_page_url( string $slug ): string {
	$page = get_page_by_path( $slug );

	return $page ? (string) get_permalink( $page ) : home_url( '/' . $slug . '/' );
}
