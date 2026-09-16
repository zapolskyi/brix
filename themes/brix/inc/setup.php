<?php
/**
 * Реєстрація можливостей теми: підтримки, меню, розміри зображень.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Базові можливості теми.
 *
 * @return void
 */
function brix_setup(): void {
	load_theme_textdomain( 'brix', BRIX_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'align-wide' );

	// Тема сама друкує валідний HTML5 — без обгорток і <p> навколо картинок.
	add_theme_support(
		'html5',
		array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);

	// Логотип — словесний знак, тож ширший за висоту.
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 40,
			'width'       => 160,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);

	register_nav_menus(
		array(
			'primary' => __( 'Головне меню', 'brix' ),
			'shop'    => __( 'Підвал · Магазин', 'brix' ),
			'brand'   => __( 'Підвал · Бренд', 'brix' ),
			'help'    => __( 'Підвал · Допомога', 'brix' ),
			'legal'   => __( 'Підвал · Правова інформація', 'brix' ),
		)
	);

	/*
	 * Розміри під сітку каталогу з макетів: картка товару 3:4,
	 * герой 16:9. Обрізаємо жорстко, щоб пачки в сітці стояли рівно.
	 */
	add_image_size( 'brix-card', 520, 693, true );
	add_image_size( 'brix-card-2x', 1040, 1386, true );
	add_image_size( 'brix-hero', 1600, 900, true );
}
add_action( 'after_setup_theme', 'brix_setup' );

/**
 * Ширина вмісту для вбудованих медіа.
 *
 * @return void
 */
function brix_content_width(): void {
	$GLOBALS['content_width'] = apply_filters( 'brix_content_width', 760 );
}
add_action( 'after_setup_theme', 'brix_content_width', 0 );

/**
 * Прибирає з <head> те, чим тема не користується.
 *
 * Дрібниця за обсягом, але це зайві запити й дані про версію WordPress
 * у розмітці кожної сторінки.
 *
 * @return void
 */
function brix_clean_head(): void {
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
}
add_action( 'init', 'brix_clean_head' );

/**
 * Вимикає емодзі-скрипти ядра.
 *
 * Сайт двомовний UA/EN і емодзі в контенті не використовує,
 * а скрипт важить близько 15 КБ і додає запит на кожній сторінці.
 *
 * @return void
 */
function brix_disable_emoji(): void {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
}
add_action( 'init', 'brix_disable_emoji' );

/**
 * Додає клас і aria-current посиланням головного меню.
 *
 * WordPress вішає класи на <li>, а фарбувати треба саме <a>. Без цього
 * пункти успадковують колір посилання із скидання і все меню стає
 * вишневим. Заразом позначаємо поточний розділ для скрінрідера:
 * підкреслення кольором — єдина візуальна ознака, і сама по собі
 * вона нікому, крім зрячих, нічого не каже.
 *
 * @param array<string, string> $atts Атрибути тега <a>.
 * @param WP_Post               $item Пункт меню.
 * @param stdClass              $args Аргументи wp_nav_menu().
 * @return array<string, string>
 */
function brix_nav_link_attributes( array $atts, $item, $args ): array {
	if ( 'primary' !== ( $args->theme_location ?? '' ) ) {
		return $atts;
	}

	$atts['class'] = trim( ( $atts['class'] ?? '' ) . ' brix-nav__link' );

	if ( ! empty( $item->current ) || ! empty( $item->current_item_ancestor ) ) {
		$atts['aria-current'] = 'page';
	}

	return $atts;
}
add_filter( 'nav_menu_link_attributes', 'brix_nav_link_attributes', 10, 3 );
