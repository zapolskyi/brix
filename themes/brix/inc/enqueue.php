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
 * Кирилична підмножина Unbounded 800 і Onest 400 — це логотип, перший
 * заголовок і навігація, тобто все, що впирається в LCP. Решта
 * накреслень довантажиться за unicode-range уже після першого малювання.
 *
 * @return void
 */
function brix_preload_fonts(): void {
	$critical = array( 'unbounded-800-cyrillic.woff2', 'onest-400-cyrillic.woff2' );
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
}
