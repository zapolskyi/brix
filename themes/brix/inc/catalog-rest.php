<?php
/**
 * REST-ендпоінт каталогу: фільтрація без перезавантаження.
 *
 * Ендпоінт нічого не вирішує самостійно. Фільтри він накладає тією
 * самою `brix_filter_query()`, що й головний запит сторінки, а розмітку
 * збирає тими самими шаблонними частинами. Це навмисне обмеження:
 * щойно ендпоінт почне будувати HTML власним кодом, AJAX-версія
 * каталогу почне повільно розходитися зі звичайною, і розійдеться
 * саме там, куди ніхто не дивиться.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Реєструє маршрут каталогу.
 *
 * @return void
 */
function brix_register_catalog_route(): void {
	register_rest_route(
		'brix/v1',
		'/catalog',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'brix_catalog_rest_response',
			// Каталог публічний: те саме віддає звичайний перехід за
			// посиланням, тож перевіряти тут нема чого.
			'permission_callback' => '__return_true',
			'args'                => brix_catalog_route_args(),
		)
	);
}
add_action( 'rest_api_init', 'brix_register_catalog_route' );

/**
 * Параметри маршруту — ті самі, що в адресному рядку каталогу.
 *
 * @return array<string, array<string, mixed>>
 */
function brix_catalog_route_args(): array {
	$args = array(
		'tax'       => array(
			'type'              => 'string',
			'default'           => '',
			'validate_callback' => static function ( $value ): bool {
				return '' === $value || in_array( $value, brix_catalog_taxonomies(), true );
			},
		),
		'term'      => array(
			'type'              => 'string',
			'default'           => '',

			/*
			 * Не `'sanitize_title'` рядком: REST передає в callback три
			 * аргументи, а другий у sanitize_title() — це fallback.
			 * На порожньому значенні функція повернула б туди сам
			 * обʼєкт запиту, і наступне приведення до рядка падає.
			 */
			'sanitize_callback' => static function ( $value ): string {
				return sanitize_title( (string) $value );
			},
		),
		'paged'     => array(
			'type'    => 'integer',
			'default' => 1,
			'minimum' => 1,
		),
		'orderby'   => array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_key',
		),
		'in_stock'  => array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_key',
		),
		'price_min' => array(
			'type'    => 'integer',
			'default' => 0,
			'minimum' => 0,
		),
		'price_max' => array(
			'type'    => 'integer',
			'default' => 0,
			'minimum' => 0,
		),
	);

	foreach ( array_keys( brix_catalog_filters() ) as $key ) {
		$args[ 'f_' . $key ] = array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		);
	}

	return $args;
}

/**
 * Відповідь ендпоінта: шматки розмітки під заміну.
 *
 * @param WP_REST_Request $request Запит.
 * @return WP_REST_Response|WP_Error
 */
function brix_catalog_rest_response( WP_REST_Request $request ) {
	if ( ! brix_has_woocommerce() ) {
		return new WP_Error(
			'brix_catalog_unavailable',
			__( 'Каталог недоступний.', 'brix' ),
			array( 'status' => 503 )
		);
	}

	$term = brix_catalog_rest_term( $request );

	if ( is_wp_error( $term ) ) {
		return $term;
	}

	// Далі всі читачі фільтрів беруть стан звідси, а не з $_GET.
	brix_catalog_request( $request->get_params() );
	brix_catalog_base( brix_catalog_rest_base( $term ) );

	$paged = max( 1, (int) $request->get_param( 'paged' ) );
	$query = brix_catalog_rest_query( $paged, (string) $request->get_param( 'orderby' ), $term );

	$response = brix_catalog_rest_fragments( $query, $paged );

	$response['total'] = (int) $query->found_posts;
	$response['pages'] = (int) $query->max_num_pages;
	$response['url']   = brix_catalog_paged_url( $paged );

	return rest_ensure_response( $response );
}

/**
 * Термін архіву, на якому відкритий каталог.
 *
 * Адресу бази не можна брати з запиту готовою: інакше будь-хто міг би
 * підсунути чужий домен, і всі посилання у відповіді повели б туди.
 * Тому приходять таксономія й слаг, а адресу будує сервер.
 *
 * @param WP_REST_Request $request Запит.
 * @return WP_Term|null|WP_Error
 */
function brix_catalog_rest_term( WP_REST_Request $request ) {
	$taxonomy = (string) $request->get_param( 'tax' );
	$slug     = (string) $request->get_param( 'term' );

	if ( '' === $taxonomy || '' === $slug ) {
		return null;
	}

	$term = get_term_by( 'slug', $slug, $taxonomy );

	if ( ! $term instanceof WP_Term ) {
		return new WP_Error(
			'brix_catalog_unknown_term',
			__( 'Такого розділу каталогу немає.', 'brix' ),
			array( 'status' => 404 )
		);
	}

	return $term;
}

/**
 * Базова адреса каталогу для відповіді.
 *
 * @param WP_Term|null $term Термін архіву.
 * @return string
 */
function brix_catalog_rest_base( ?WP_Term $term ): string {
	if ( $term instanceof WP_Term ) {
		$link = get_term_link( $term );

		if ( ! is_wp_error( $link ) ) {
			return (string) $link;
		}
	}

	return (string) wc_get_page_permalink( 'shop' );
}

/**
 * Запит товарів під поточні фільтри.
 *
 * @param int          $paged   Сторінка.
 * @param string       $orderby Сортування.
 * @param WP_Term|null $term    Термін архіву.
 * @return WP_Query
 */
function brix_catalog_rest_query( int $paged, string $orderby, ?WP_Term $term ): WP_Query {
	/*
	 * Хук чужий — це фільтр WooCommerce, яким теми задають розмір
	 * сторінки каталогу. Ендпоінт мусить його поважати, інакше AJAX
	 * почав би видавати іншу кількість товарів, ніж сама сторінка.
	 */
	$per_page = (int) apply_filters( 'loop_shop_per_page', wc_get_default_products_per_row() * wc_get_default_product_rows_per_page() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

	$ordering  = WC()->query->get_catalog_ordering_args( $orderby );
	$tax_query = WC()->query->get_tax_query();

	if ( $term instanceof WP_Term ) {
		$tax_query[] = array(
			'taxonomy' => $term->taxonomy,
			'field'    => 'slug',
			'terms'    => $term->slug,
		);
	}

	$args = array(
		'post_type'           => 'product',
		'post_status'         => 'publish',
		'ignore_sticky_posts' => true,
		'posts_per_page'      => $per_page,
		'paged'               => $paged,
		'orderby'             => $ordering['orderby'],
		'order'               => $ordering['order'],
		'tax_query'           => $tax_query,   // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		'meta_query'          => WC()->query->get_meta_query(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	);

	if ( ! empty( $ordering['meta_key'] ) ) {
		$args['meta_key'] = $ordering['meta_key']; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	}

	/*
	 * Спершу складаємо змінні запиту, потім накладаємо фільтри тим
	 * самим кодом, що й на сторінці, і лише тоді виконуємо. Хук
	 * pre_get_posts тут не спрацює: він відсіює все, що не головний
	 * запит, — інакше фільтри лягли б на кожен запит товарів у проєкті.
	 */
	$query = new WP_Query();

	foreach ( $args as $key => $value ) {
		$query->set( $key, $value );
	}

	brix_filter_query( $query );
	$query->query( $query->query_vars );

	return $query;
}

/**
 * Малює частини каталогу поверх підміненого глобального запиту.
 *
 * Шаблонні частини працюють з `$wp_query` і циклом — так само, як на
 * сторінці. Тому тут глобальний запит тимчасово підміняється нашим,
 * а не переписуються шаблони під передачу даних аргументами.
 *
 * @param WP_Query $query Запит.
 * @param int      $paged Сторінка.
 * @return array<string, string>
 */
function brix_catalog_rest_fragments( WP_Query $query, int $paged ): array {
	global $wp_query;

	$previous = $wp_query;
	$total    = (int) $query->found_posts;

	$wp_query = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

	wc_setup_loop(
		array(
			'is_shortcode' => false,
			'is_search'    => false,
			'is_paginated' => true,
			'is_filtered'  => brix_has_active_filters(),
			'total'        => $total,
			'total_pages'  => (int) $query->max_num_pages,
			'per_page'     => (int) $query->get( 'posts_per_page' ),
			'current_page' => $paged,
		)
	);

	/*
	 * Базу пагінації доводиться підміняти фільтром, а не властивістю
	 * циклу: `woocommerce_pagination()` властивості для бази ігнорує
	 * й будує адресу з `get_pagenum_link()`, а це в REST адреса самого
	 * ендпоінта. Посилання «2» вели б на /wp-json/.
	 */
	$base       = brix_catalog_pagination_base();
	$fix_paging = static function ( array $args ) use ( $base ): array {
		$args['base']   = $base;
		$args['format'] = '';

		return $args;
	};

	add_filter( 'woocommerce_pagination_args', $fix_paging );

	$fragments = array(
		'count'   => brix_catalog_count_text( $total ),
		'chips'   => brix_render_part( 'template-parts/catalog/active-filters' ),
		'filters' => brix_render_part( 'template-parts/catalog/filters' ),
		'results' => brix_render_part( 'template-parts/catalog/results' ),
	);

	remove_filter( 'woocommerce_pagination_args', $fix_paging );
	wc_reset_loop();

	$wp_query = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

	wp_reset_postdata();

	return $fragments;
}

/**
 * Збирає шаблонну частину в рядок.
 *
 * @param string $slug Шлях частини.
 * @return string
 */
function brix_render_part( string $slug ): string {
	ob_start();
	get_template_part( $slug );

	return (string) ob_get_clean();
}

/**
 * Адреса каталогу з номером сторінки.
 *
 * @param int $paged Сторінка.
 * @return string
 */
function brix_catalog_paged_url( int $paged ): string {
	$args = brix_current_filter_args();
	$base = brix_catalog_base();

	if ( $paged > 1 ) {
		$base = get_option( 'permalink_structure' )
			? trailingslashit( $base ) . user_trailingslashit( 'page/' . $paged, 'paged' )
			: add_query_arg( 'paged', $paged, $base );
	}

	return $args ? add_query_arg( $args, $base ) : $base;
}

/**
 * Шаблон адреси для пагінації.
 *
 * `woocommerce_pagination()` бере базу з властивостей циклу, а типова
 * база будується з поточної адреси — в REST це була б адреса самого
 * ендпоінта. Тому підставляємо адресу каталогу.
 *
 * @return string
 */
function brix_catalog_pagination_base(): string {
	return str_replace( '999999999', '%#%', brix_catalog_paged_url( 999999999 ) );
}
