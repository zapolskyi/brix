<?php
/**
 * Пошук: результати, підказки й REST для них.
 *
 * Як і в каталозі, розмітка підказок одна на два шляхи: її малює
 * шаблонна частина, а REST-ендпоінт лише віддає готовий шматок. Тож
 * випадаючий список не може розійтися зі сторінкою результатів.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Типи записів, серед яких шукаємо, у порядку показу.
 *
 * @return array<string, string>
 */
function brix_search_types(): array {
	$types = array();

	if ( brix_has_woocommerce() ) {
		$types['product'] = __( 'Лоти', 'brix' );
	}

	if ( post_type_exists( 'brix_guide' ) ) {
		$types['brix_guide'] = __( 'Гайди', 'brix' );
	}

	if ( post_type_exists( 'brix_farm' ) ) {
		$types['brix_farm'] = __( 'Виробники', 'brix' );
	}

	return $types;
}

/**
 * Записи під пошуковий запит.
 *
 * @param string $term      Запит.
 * @param string $post_type Тип запису.
 * @param int    $limit     Скільки.
 * @return array<int, WP_Post>
 */
function brix_search_posts( string $term, string $post_type, int $limit ): array {
	$term = trim( $term );

	if ( '' === $term ) {
		return array();
	}

	$args = array(
		's'                   => $term,
		'post_type'           => $post_type,
		'post_status'         => 'publish',
		'posts_per_page'      => $limit,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		// Порядок релевантності WordPress ставить збіги в заголовку
		// вище за збіги в тексті — для підказок це саме те, що треба.
		'orderby'             => 'relevance',
	);

	if ( 'product' === $post_type ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array( 'exclude-from-search' ),
				'operator' => 'NOT IN',
			),
		);
	}

	$found = get_posts( $args );

	if ( 'product' !== $post_type ) {
		return $found;
	}

	/*
	 * Назви лотів англійські — «Ethiopia Guji Hambela». Українське
	 * «Ефіопія» живе тільки в таксономії, тож пошук по заголовку й
	 * тексту не знаходив жодного ефіопського лоту на запит «ефіоп».
	 * Для магазину з українським інтерфейсом це не дрібниця, тож
	 * добираємо товари ще й за збігом у країні, обробці, нотах і
	 * способі заварювання.
	 */
	return brix_merge_unique( $found, brix_search_by_terms( $term, $limit ), $limit );
}

/**
 * Товари, у яких із запитом збігається термін таксономії.
 *
 * @param string $term  Запит.
 * @param int    $limit Скільки.
 * @return array<int, WP_Post>
 */
function brix_search_by_terms( string $term, int $limit ): array {
	$ids = get_terms(
		array(
			'taxonomy'   => array_merge( brix_catalog_taxonomies(), array( 'brix_note' ) ),
			'name__like' => $term,
			'hide_empty' => true,
			'fields'     => 'tt_ids',
		)
	);

	if ( is_wp_error( $ids ) || ! $ids ) {
		return array();
	}

	return get_posts(
		array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			// term_taxonomy_id унікальний у межах усієї бази, тож
			// одна умова накриває всі чотири таксономії одразу,
			// і ключ taxonomy тут не потрібен.
			'tax_query'           => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'field' => 'term_taxonomy_id',
					'terms' => $ids,
				),
			),
		)
	);
}

/**
 * Зливає два набори записів, лишаючи перший у пріоритеті.
 *
 * @param array<int, WP_Post> $first  Основний набір.
 * @param array<int, WP_Post> $second Додатковий.
 * @param int                 $limit  Обмеження.
 * @return array<int, WP_Post>
 */
function brix_merge_unique( array $first, array $second, int $limit ): array {
	$seen   = array();
	$merged = array();

	foreach ( array_merge( $first, $second ) as $post ) {
		if ( isset( $seen[ $post->ID ] ) ) {
			continue;
		}

		$seen[ $post->ID ] = true;
		$merged[]          = $post;

		if ( count( $merged ) >= $limit ) {
			break;
		}
	}

	return $merged;
}

/**
 * Адреса сторінки результатів.
 *
 * @param string $term Запит.
 * @return string
 */
function brix_search_url( string $term ): string {
	return add_query_arg( 's', rawurlencode( $term ), home_url( '/' ) );
}

/**
 * Реєструє маршрут підказок.
 *
 * @return void
 */
function brix_register_search_route(): void {
	register_rest_route(
		'brix/v1',
		'/search',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'brix_search_rest_response',
			'permission_callback' => '__return_true',
			'args'                => array(
				'q' => array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => static function ( $value ): string {
						return sanitize_text_field( (string) $value );
					},
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'brix_register_search_route' );

/**
 * Підказки пошуку одним шматком розмітки.
 *
 * @param WP_REST_Request $request Запит.
 * @return WP_REST_Response
 */
function brix_search_rest_response( WP_REST_Request $request ): WP_REST_Response {
	$term = trim( (string) $request->get_param( 'q' ) );

	ob_start();
	get_template_part( 'template-parts/search/suggestions', null, array( 'term' => $term ) );
	$html = (string) ob_get_clean();

	return rest_ensure_response(
		array(
			'suggestions' => $html,
			'url'         => '' === $term ? '' : brix_search_url( $term ),
		)
	);
}
