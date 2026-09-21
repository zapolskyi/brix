<?php
/**
 * Точка входу теми BRIX 22°.
 *
 * Тут немає логіки — лише константи й підключення модулів з inc/.
 * Кожен модуль відповідає за одну частину, щоб файл не розростався
 * у звалище хуків, як це буває з functions.php.
 *
 * Бізнес-логіка магазину (паспорт лоту, фільтри, квіз, доставка)
 * живе в окремому плагіні brix-core — тема лише показує дані.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

define( 'BRIX_VERSION', '0.1.0' );
define( 'BRIX_DIR', get_template_directory() );
define( 'BRIX_URI', get_template_directory_uri() );

require_once BRIX_DIR . '/inc/helpers.php';
require_once BRIX_DIR . '/inc/setup.php';
require_once BRIX_DIR . '/inc/enqueue.php';
require_once BRIX_DIR . '/inc/blocks.php';
require_once BRIX_DIR . '/inc/products.php';
require_once BRIX_DIR . '/inc/class-brix-plain-walker.php';
require_once BRIX_DIR . '/inc/template-tags.php';
require_once BRIX_DIR . '/inc/search.php';

if ( brix_has_woocommerce() ) {
	require_once BRIX_DIR . '/inc/woocommerce.php';
	require_once BRIX_DIR . '/inc/catalog.php';
	require_once BRIX_DIR . '/inc/catalog-rest.php';
	require_once BRIX_DIR . '/inc/variations.php';
	require_once BRIX_DIR . '/inc/cart.php';
	require_once BRIX_DIR . '/inc/checkout.php';
	require_once BRIX_DIR . '/inc/home.php';
	require_once BRIX_DIR . '/inc/quiz.php';
	require_once BRIX_DIR . '/inc/club.php';
	require_once BRIX_DIR . '/inc/transparency.php';
	require_once BRIX_DIR . '/inc/emails.php';
}
