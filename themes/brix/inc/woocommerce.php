<?php
/**
 * Інтеграція з WooCommerce.
 *
 * Тут — лише те, що стосується подачі: підтримки, розміри зображень,
 * прибирання зайвих стилів. Дані товару (паспорт лоту, свіжість,
 * фільтри) — зона відповідальності плагіна brix-core.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Оголошує підтримку WooCommerce.
 *
 * Галерею підключаємо не всю: лайтбокс і слайдер тягнуть flexslider
 * та photoswipe, а в макеті галерея своя. Лишаємо тільки зум.
 *
 * @return void
 */
function brix_woocommerce_support(): void {
	add_theme_support(
		'woocommerce',
		array(
			'thumbnail_image_width' => 520,
			'single_image_width'    => 1040,
			'product_grid'          => array(
				'default_rows'    => 3,
				'min_rows'        => 1,
				'default_columns' => 4,
				'min_columns'     => 2,
				'max_columns'     => 4,
			),
		)
	);

	add_theme_support( 'wc-product-gallery-zoom' );
}
add_action( 'after_setup_theme', 'brix_woocommerce_support' );

/**
 * Вимикає стандартні стилі WooCommerce.
 *
 * Уся верстка магазину — власна, за макетами. Стилі Woo лише
 * перебивалися б з нашими і додавали три файли на кожну сторінку.
 *
 * @param array<string, mixed> $styles Стилі Woo.
 * @return array<string, mixed>
 */
function brix_dequeue_woocommerce_styles( array $styles ): array {
	unset( $styles['woocommerce-general'], $styles['woocommerce-layout'], $styles['woocommerce-smallscreen'] );

	return $styles;
}
add_filter( 'woocommerce_enqueue_styles', 'brix_dequeue_woocommerce_styles' );

/**
 * Прибирає скрипти й стилі Woo зі сторінок, де магазину немає.
 *
 * WooCommerce за замовчуванням вантажить cart-fragments і блокові
 * стилі всюди, включно з блогом і контентними сторінками. На них
 * це чистий баласт у критичному шляху.
 *
 * @return void
 */
function brix_dequeue_woocommerce_on_content_pages(): void {
	if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) {
		return;
	}

	wp_dequeue_script( 'wc-cart-fragments' );
	wp_dequeue_style( 'wc-blocks-style' );
}
add_action( 'wp_enqueue_scripts', 'brix_dequeue_woocommerce_on_content_pages', 99 );

/**
 * Прибирає обгортки розкладки Woo — тема ставить свої.
 *
 * @return void
 */
function brix_remove_woocommerce_wrappers(): void {
	remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
	remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
}
add_action( 'init', 'brix_remove_woocommerce_wrappers' );
