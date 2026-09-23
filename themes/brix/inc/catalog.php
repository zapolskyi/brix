<?php
/**
 * Каталог: фільтри, сортування, стан у URL.
 *
 * Фільтри працюють на звичайних посиланнях і GET-параметрах. Ніякого
 * JavaScript тут немає навмисно: спершу каталог має працювати без
 * нього, а AJAX ляже поверх на фазі 4 і лише прискорить те, що вже є.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Опис фільтрів каталогу.
 *
 * Порядок і склад — з артборда Catalog. Кислотність тут не таксономія,
 * а діапазон по мета-полю: у макеті це два варіанти, «м'яка» і
 * «яскрава», і заводити заради них терміни було б надмірно.
 *
 * @return array<string, array<string, mixed>>
 */
function brix_catalog_filters(): array {
	return array(
		'brew'       => array(
			'label'    => __( 'Спосіб заварювання', 'brix' ),
			'taxonomy' => 'brix_brew_method',
		),
		'processing' => array(
			'label'    => __( 'Обробка', 'brix' ),
			'taxonomy' => 'brix_processing',
		),
		'country'    => array(
			'label'    => __( 'Країна', 'brix' ),
			'taxonomy' => 'brix_country',
		),
		'note'       => array(
			'label'       => __( 'Смакові ноти', 'brix' ),
			'taxonomy'    => 'brix_note',
			// У фільтрі показуємо лише групи: конкретні ноти живуть
			// у картці товару, а тут їх було б кількадесят.
			'parent_only' => true,
		),
		'acidity'    => array(
			'label'   => __( 'Кислотність', 'brix' ),
			'options' => array(
				'soft'   => __( 'м’яка', 'brix' ),
				'bright' => __( 'яскрава', 'brix' ),
			),
		),
	);
}

/**
 * Джерело стану каталогу.
 *
 * Зазвичай це адресний рядок. Але той самий набір фільтрів приходить
 * і REST-запитом, тож усі читачі беруть параметри звідси, а не з $_GET
 * напряму: інакше логіка фільтрації роздвоїлась би на дві реалізації,
 * які з часом розійдуться.
 *
 * Виклик з масивом підміняє джерело до кінця запиту, без аргументу —
 * читає поточне.
 *
 * @param array<string, mixed>|null $params Параметри запиту або null.
 * @return array<string, mixed>
 */
function brix_catalog_request( ?array $params = null ): array {
	static $override = null;

	if ( null !== $params ) {
		$override = $params;
	}

	if ( null !== $override ) {
		return $override;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Читання GET-фільтрів каталогу, не обробка форми.
	return wp_unslash( $_GET );
}

/**
 * Обрані значення фільтра з адресного рядка.
 *
 * @param string $key Ключ фільтра.
 * @return array<int, string>
 */
function brix_active_filter( string $key ): array {
	$request = brix_catalog_request();
	$raw     = isset( $request[ 'f_' . $key ] ) ? sanitize_text_field( (string) $request[ 'f_' . $key ] ) : '';

	if ( '' === $raw ) {
		return array();
	}

	return array_values( array_filter( array_map( 'sanitize_title', explode( ',', $raw ) ) ) );
}

/**
 * Чи задіяний хоч один фільтр.
 *
 * @return bool
 */
function brix_has_active_filters(): bool {
	foreach ( array_keys( brix_catalog_filters() ) as $key ) {
		if ( brix_active_filter( $key ) ) {
			return true;
		}
	}

	return brix_price_filter() !== array() || brix_in_stock_only();
}

/**
 * Межі фільтра ціни.
 *
 * @return array<string, int>
 */
function brix_price_filter(): array {
	$range = array();

	$request = brix_catalog_request();

	foreach ( array( 'min', 'max' ) as $bound ) {
		$value = isset( $request[ 'price_' . $bound ] ) ? (int) $request[ 'price_' . $bound ] : 0;

		if ( $value > 0 ) {
			$range[ $bound ] = $value;
		}
	}

	return $range;
}

/**
 * Чи показувати лише те, що є в наявності.
 *
 * @return bool
 */
function brix_in_stock_only(): bool {
	$request = brix_catalog_request();

	return isset( $request['in_stock'] ) && '1' === (string) $request['in_stock'];
}

/**
 * Адреса каталогу з перемкнутим значенням фільтра.
 *
 * Саме ця функція робить фільтри робочими без JavaScript: кожен чип —
 * звичайне посилання на той самий каталог з іншим набором параметрів.
 * Стан повністю лежить в URL, тож посиланням можна поділитись.
 *
 * @param string $key   Ключ фільтра.
 * @param string $value Значення, яке вмикаємо або вимикаємо.
 * @return string
 */
function brix_filter_toggle_url( string $key, string $value ): string {
	$active = brix_active_filter( $key );
	$index  = array_search( $value, $active, true );

	if ( false !== $index ) {
		unset( $active[ $index ] );
	} else {
		$active[] = $value;
	}

	$args = brix_current_filter_args();

	if ( $active ) {
		$args[ 'f_' . $key ] = implode( ',', $active );
	} else {
		unset( $args[ 'f_' . $key ] );
	}

	return brix_catalog_url( $args );
}

/**
 * Поточний набір параметрів фільтрації.
 *
 * @return array<string, string>
 */
function brix_current_filter_args(): array {
	$args = array();

	foreach ( array_keys( brix_catalog_filters() ) as $key ) {
		$active = brix_active_filter( $key );

		if ( $active ) {
			$args[ 'f_' . $key ] = implode( ',', $active );
		}
	}

	foreach ( brix_price_filter() as $bound => $value ) {
		$args[ 'price_' . $bound ] = (string) $value;
	}

	if ( brix_in_stock_only() ) {
		$args['in_stock'] = '1';
	}

	$request = brix_catalog_request();

	/*
	 * Саме непорожнє: REST-маршрут задає параметрам значення за
	 * замовчуванням, тож `isset()` там істинний завжди — і в адресі
	 * лишався б хвіст «?orderby» без значення.
	 */
	if ( ! empty( $request['orderby'] ) ) {
		$args['orderby'] = sanitize_key( (string) $request['orderby'] );
	}

	return $args;
}

/**
 * Базова адреса каталогу з параметрами.
 *
 * @param array<string, string> $args Параметри.
 * @return string
 */
function brix_catalog_url( array $args = array() ): string {
	$base = brix_catalog_base();

	return $args ? add_query_arg( $args, $base ) : $base;
}

/**
 * Таксономії, на архівах яких каталог працює як каталог.
 *
 * Той самий перелік потрібен і для бази адреси, і для перевірки
 * параметра REST-запиту, тож живе в одному місці.
 *
 * @return array<int, string>
 */
function brix_catalog_taxonomies(): array {
	return array( 'product_cat', 'brix_country', 'brix_processing', 'brix_note', 'brix_brew_method' );
}

/**
 * Базова адреса каталогу — магазин або архів таксономії.
 *
 * REST-запит не має ні is_tax(), ні запитаного обʼєкта, тож ендпоінт
 * підміняє базу перевіреною адресою терміна. Без цього всі посилання
 * у відповіді вели б на /shop/ і архів країни мовчки втрачав би себе
 * з першим же кліком по фільтру.
 *
 * @param string|null $url Адреса для підміни або null для читання.
 * @return string
 */
function brix_catalog_base( ?string $url = null ): string {
	static $override = null;

	if ( null !== $url ) {
		$override = $url;
	}

	if ( null !== $override ) {
		return $override;
	}

	$base = is_tax( brix_catalog_taxonomies() )
		? get_term_link( get_queried_object() )
		: wc_get_page_permalink( 'shop' );

	if ( is_wp_error( $base ) ) {
		$base = wc_get_page_permalink( 'shop' );
	}

	return (string) $base;
}

/**
 * Застосовує фільтри до головного запиту каталогу.
 *
 * @param WP_Query $query Запит.
 * @return void
 */
function brix_apply_catalog_filters( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( ! is_shop() && ! is_product_taxonomy() ) {
		return;
	}

	brix_filter_query( $query );
}
add_action( 'pre_get_posts', 'brix_apply_catalog_filters' );

/**
 * Накладає фільтри каталогу на довільний запит.
 *
 * Сама фільтрація навмисно не знає, звідки прийшов запит: головний
 * запит сторінки й запит REST-ендпоінта проходять через один і той
 * самий код, інакше AJAX і звичайні посилання почали б давати різні
 * набори товарів.
 *
 * @param WP_Query $query Запит.
 * @return void
 */
function brix_filter_query( WP_Query $query ): void {
	$tax_query = (array) $query->get( 'tax_query' );

	foreach ( brix_catalog_filters() as $key => $filter ) {
		$active = brix_active_filter( $key );

		if ( ! $active || empty( $filter['taxonomy'] ) ) {
			continue;
		}

		$tax_query[] = array(
			'taxonomy' => $filter['taxonomy'],
			'field'    => 'slug',
			'terms'    => $active,
			// Кілька значень одного фільтра — це «або»: покупець, який
			// обрав Ефіопію і Кенію, хоче бачити обидві, а не порожнечу.
			'operator' => 'IN',
		);
	}

	if ( brix_in_stock_only() ) {
		$tax_query[] = array(
			'taxonomy' => 'product_visibility',
			'field'    => 'name',
			'terms'    => 'outofstock',
			'operator' => 'NOT IN',
		);
	}

	if ( count( $tax_query ) > 1 ) {
		$tax_query['relation'] = 'AND';
	}

	if ( $tax_query ) {
		$query->set( 'tax_query', $tax_query );
	}

	brix_apply_acidity_filter( $query );
	brix_apply_price_filter( $query );
}

/**
 * Фільтр кислотності: діапазон по мета-полю, а не таксономія.
 *
 * @param WP_Query $query Запит.
 * @return void
 */
function brix_apply_acidity_filter( WP_Query $query ): void {
	$active = brix_active_filter( 'acidity' );

	if ( ! $active || ! brix_has_core() ) {
		return;
	}

	$key    = \Brix\Core\Product\LotMeta::key( 'taste_acidity' );
	$ranges = array();

	if ( in_array( 'soft', $active, true ) ) {
		$ranges[] = array(
			'key'     => $key,
			'value'   => array( 1, 2 ),
			'type'    => 'NUMERIC',
			'compare' => 'BETWEEN',
		);
	}

	if ( in_array( 'bright', $active, true ) ) {
		$ranges[] = array(
			'key'     => $key,
			'value'   => array( 4, 5 ),
			'type'    => 'NUMERIC',
			'compare' => 'BETWEEN',
		);
	}

	if ( ! $ranges ) {
		return;
	}

	$ranges['relation'] = 'OR';

	$meta_query   = (array) $query->get( 'meta_query' );
	$meta_query[] = $ranges;

	$query->set( 'meta_query', $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
}

/**
 * Фільтр ціни.
 *
 * @param WP_Query $query Запит.
 * @return void
 */
function brix_apply_price_filter( WP_Query $query ): void {
	$range = brix_price_filter();

	if ( ! $range ) {
		return;
	}

	$meta_query = (array) $query->get( 'meta_query' );

	/*
	 * Кожну межу ставимо окремою умовою, а не BETWEEN з PHP_INT_MAX:
	 * MySQL зводить значення до DECIMAL, і PHP_INT_MAX там переповнює
	 * тип — умова мовчки перестає відсікати будь-що.
	 */
	/*
	 * Покупець вводить суму тією валютою, яку бачить, а в базі
	 * лежить гривня. Англійською «до 15» означає 15 євро — без
	 * переведення фільтр відсік би все, що дорожче за 15 гривень.
	 */
	if ( isset( $range['min'] ) ) {
		$meta_query[] = array(
			'key'     => '_price',
			'value'   => brix_store_amount( (float) $range['min'] ),
			'type'    => 'NUMERIC',
			'compare' => '>=',
		);
	}

	if ( isset( $range['max'] ) ) {
		$meta_query[] = array(
			'key'     => '_price',
			'value'   => brix_store_amount( (float) $range['max'] ),
			'type'    => 'NUMERIC',
			'compare' => '<=',
		);
	}

	$query->set( 'meta_query', $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
}

/**
 * Підпис із кількістю знайдених товарів.
 *
 * Українська має три форми множини, і `_n()` без .po-файлів відкочується
 * на англійське правило з двома — звідси власний `brix_plural()`.
 *
 * @param int $total Кількість товарів.
 * @return string
 */
function brix_catalog_count_text( int $total ): string {
	return sprintf(
		/* translators: %s — кількість товарів. */
		brix_plural( $total, __( '%s товар', 'brix' ), __( '%s товари', 'brix' ), __( '%s товарів', 'brix' ) ),
		brix_has_core() ? \Brix\Core\Support\Format::number( $total ) : number_format_i18n( $total )
	);
}

/**
 * Скільки товарів під цим терміном, разом з дочірніми.
 *
 * Власний `$term->count` рахує лише товари, приписані самому терміну.
 * У нот це завжди нуль: товару проставляють конкретну ноту «черешня»,
 * а у фільтрі стоїть група «ягоди». Тож для ієрархічних таксономій
 * рахуємо разом з нащадками.
 *
 * @param WP_Term $term Термін.
 * @return int
 */
function brix_term_count( WP_Term $term ): int {
	if ( ! is_taxonomy_hierarchical( $term->taxonomy ) ) {
		return (int) $term->count;
	}

	$children = get_term_children( $term->term_id, $term->taxonomy );

	if ( is_wp_error( $children ) || ! $children ) {
		return (int) $term->count;
	}

	$cache_key = 'brix_term_count_' . $term->term_id;
	$cached    = wp_cache_get( $cache_key, 'brix' );

	if ( false !== $cached ) {
		return (int) $cached;
	}

	$objects = get_objects_in_term( array_merge( array( $term->term_id ), $children ), $term->taxonomy );
	$count   = is_wp_error( $objects ) ? 0 : count( array_unique( $objects ) );

	wp_cache_set( $cache_key, $count, 'brix', HOUR_IN_SECONDS );

	return $count;
}

/**
 * Сторінки з фільтрами не мають потрапляти в індекс окремо.
 *
 * Канонічною лишається чиста адреса каталогу: інакше пошуковик набере
 * сотні майже однакових сторінок, які відрізняються лише набором чипів.
 *
 * @return void
 */
function brix_filters_noindex(): void {
	if ( ! is_shop() && ! is_product_taxonomy() ) {
		return;
	}

	if ( ! brix_has_active_filters() ) {
		return;
	}

	echo '<meta name="robots" content="noindex, follow">' . "\n";
	printf( '<link rel="canonical" href="%s">' . "\n", esc_url( brix_catalog_url() ) );
}
add_action( 'wp_head', 'brix_filters_noindex', 2 );

/**
 * Прибирає стандартний canonical там, де ми друкуємо власний.
 *
 * @param string $url Адреса.
 * @return string
 */
function brix_cancel_default_canonical( string $url ): string {
	if ( ( is_shop() || is_product_taxonomy() ) && brix_has_active_filters() ) {
		return '';
	}

	return $url;
}
add_filter( 'get_canonical_url', 'brix_cancel_default_canonical' );
