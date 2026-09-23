<?php
/**
 * Стилі, скрипти та шрифти фронтенду.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Підключає стилі та скрипти.
 *
 * @return void
 */
function brix_enqueue_assets(): void {
	wp_enqueue_style(
		'brix-main',
		brix_asset_uri( 'assets/css/main.css' ),
		array(),
		brix_asset_version( 'assets/css/main.css' )
	);

	// Основний файл вантажиться не блокуючи промальовку — перший
	// екран малює критичний CSS, вбудований у <head>.
	wp_style_add_data( 'brix-main', 'brix_defer', true );

	wp_enqueue_script(
		'brix-app',
		brix_asset_uri( 'assets/js/app.js' ),
		array(),
		brix_asset_version( 'assets/js/app.js' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);

	wp_localize_script(
		'brix-app',
		'brixData',
		array(
			'restUrl' => esc_url_raw( rest_url( 'brix/v1/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'strings' => array(
				'menuOpen'  => __( 'Відкрити меню', 'brix' ),
				'menuClose' => __( 'Закрити меню', 'brix' ),
			),
		)
	);

	brix_enqueue_catalog_script();
	brix_enqueue_product_script();
	brix_enqueue_checkout_script();
	brix_enqueue_quiz_script();
}
add_action( 'wp_enqueue_scripts', 'brix_enqueue_assets' );

/**
 * Скрипт каталогу — лише там, де є каталог.
 *
 * Фільтри без перезавантаження потрібні на сторінці магазину й на
 * архівах таксономій товару. На решті сторінок цей файл був би
 * зайвими кілобайтами, які нічого не роблять.
 *
 * @return void
 */
function brix_enqueue_catalog_script(): void {
	if ( ! brix_has_woocommerce() || ( ! is_shop() && ! is_product_taxonomy() ) ) {
		return;
	}

	wp_enqueue_script(
		'brix-catalog',
		brix_asset_uri( 'assets/js/catalog.js' ),
		array( 'brix-app' ),
		brix_asset_version( 'assets/js/catalog.js' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
}

/**
 * Попередньо завантажує шрифти, потрібні першому екрану.
 *
 * Кирилична підмножина Geologica і Manrope — це логотип, перший
 * заголовок і навігація, тобто все, що впирається в LCP. Решта
 * накреслень довантажиться за unicode-range уже після першого малювання.
 *
 * @return void
 */
function brix_preload_fonts(): void {
	$critical = array( 'geologica-var-cyrillic.woff2', 'manrope-var-cyrillic.woff2' );
	$faces    = wp_list_pluck( brix_font_faces(), 'file' );

	foreach ( $critical as $file ) {
		if ( ! in_array( $file, $faces, true ) ) {
			continue;
		}

		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( brix_asset_uri( 'assets/fonts/' . $file ) )
		);
	}
}
add_action( 'wp_head', 'brix_preload_fonts', 1 );

/**
 * Прибирає стилі блокового редактора там, де вони не потрібні.
 *
 * Тема верстає власними класами й wp-block-library на фронтенді не
 * використовує, окрім сторінок і записів, де редактор реально працює.
 * Це мінус близько 30 КБ CSS на сторінках магазину.
 *
 * @return void
 */
function brix_dequeue_block_library(): void {
	if ( is_singular( array( 'post', 'page' ) ) ) {
		return;
	}

	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'classic-theme-styles' );
	wp_dequeue_style( 'global-styles' );
}
add_action( 'wp_enqueue_scripts', 'brix_dequeue_block_library', 100 );

/**
 * Скрипт картки товару — лише на сторінці товару.
 *
 * @return void
 */
function brix_enqueue_product_script(): void {
	if ( ! brix_has_woocommerce() || ! is_product() ) {
		return;
	}

	wp_enqueue_script(
		'brix-product',
		brix_asset_uri( 'assets/js/product.js' ),
		array( 'brix-app' ),
		brix_asset_version( 'assets/js/product.js' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
}

/**
 * Скрипт checkout — лише на сторінці оформлення.
 *
 * @return void
 */
function brix_enqueue_checkout_script(): void {
	if ( ! brix_has_woocommerce() || ! is_checkout() ) {
		return;
	}

	wp_enqueue_script(
		'brix-checkout',
		brix_asset_uri( 'assets/js/checkout.js' ),
		array( 'brix-app' ),
		brix_asset_version( 'assets/js/checkout.js' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);

	/*
	 * Підказки поля відділення мають що сказати, коли список не
	 * приходить. Мовчазний порожній список покупець читає як «тут
	 * нічого немає», хоч насправді сталася помилка.
	 */
	wp_localize_script(
		'brix-checkout',
		'brixCheckout',
		array(
			'home'    => class_exists( '\\Brix\\Core\\Shipping\\Destination' )
				? \Brix\Core\Shipping\Destination::HOME
				: 'UA',
			'strings' => array(
				'loading'  => __( 'Шукаємо…', 'brix' ),
				'pickCity' => __( 'Спершу оберіть місто зі списку.', 'brix' ),
				'noCity'   => __( 'Такого міста не знайшли. Перевірте написання.', 'brix' ),
				'noPlace'  => __( 'Нічого не знайшли. Можна вписати адресу вручну — звіримо її під час обробки.', 'brix' ),
				'failed'   => __( 'Не вдалося завантажити список. Впишіть адресу вручну — звіримо її під час обробки.', 'brix' ),
				'tooMany'  => __( 'Забагато запитів поспіль. Зачекайте хвилину або впишіть адресу вручну.', 'brix' ),
			),
		)
	);
}

/**
 * Скрипт квізу — лише на його сторінці.
 *
 * @return void
 */
function brix_enqueue_quiz_script(): void {
	if ( ! is_page_template( 'page-quiz.php' ) && ! is_page( 'quiz' ) ) {
		return;
	}

	wp_enqueue_script(
		'brix-quiz',
		brix_asset_uri( 'assets/js/quiz.js' ),
		array( 'brix-app' ),
		brix_asset_version( 'assets/js/quiz.js' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
}

/**
 * Вбудовує критичний CSS у <head>.
 *
 * 14 КБ інлайном замість очікування 92 КБ зовнішнього файлу: перший
 * екран малюється одразу, решта стилів доганяє. Файл збирається
 * командою npm run css:critical з окремого точкового входу.
 *
 * @return void
 */
function brix_inline_critical(): void {
	$path = BRIX_DIR . '/assets/css/critical.css';

	if ( ! is_readable( $path ) ) {
		return;
	}

	$css = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Локальний файл теми, не віддалений ресурс.

	if ( ! $css ) {
		return;
	}

	printf( "<style id=\"brix-critical\">%s</style>\n", $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Власний CSS теми.
}
add_action( 'wp_head', 'brix_inline_critical', 2 );

/**
 * Перетворює основний стиль на відкладений.
 *
 * `rel="preload"` не блокує промальовку, а `onload` перемикає його на
 * звичайний стиль. Без JavaScript спрацьовує <noscript> — тож
 * сторінка лишається оформленою в будь-якому випадку.
 *
 * @param string $tag    Тег.
 * @param string $handle Ідентифікатор стилю.
 * @return string
 */
function brix_defer_style( string $tag, string $handle ): string {
	if ( ! wp_styles()->get_data( $handle, 'brix_defer' ) || is_admin() ) {
		return $tag;
	}

	$deferred = str_replace(
		"rel='stylesheet'",
		"rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"",
		$tag
	);

	return $deferred . '<noscript>' . $tag . '</noscript>' . "\n";
}
add_filter( 'style_loader_tag', 'brix_defer_style', 10, 2 );
