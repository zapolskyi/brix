<?php
/**
 * Іконка сайту.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Іконки теми в <head>.
 *
 * Іконка живе в темі, а не в «Іконці сайту» з Налаштувань: та лежить
 * у базі й медіатеці, а сайт на хостинг ставиться з нуля — без
 * жодного ручного кроку в адмінці вкладка показувала б логотип
 * WordPress. Якщо власник колись задасть «Іконку сайту» сам,
 * WordPress виведе її, а ця мовчки поступиться.
 *
 * Набір мінімальний і достатній: SVG для сучасних браузерів (гостра
 * на будь-якому екрані), ICO для решти, квадрат 180 px для iOS, який
 * система заокруглює сама, і маніфест для Android.
 *
 * Гліф «B» — контури зі шрифту логотипа Unbounded 800, а не текст:
 * у SVG-іконці браузер шрифтів не вантажить.
 *
 * @return void
 */
function brix_favicon(): void {
	if ( has_site_icon() ) {
		return;
	}

	$dir = get_template_directory_uri() . '/assets/img/favicon/';
	?>
	<link rel="icon" href="<?php echo esc_url( $dir . 'favicon.ico' ); ?>" sizes="32x32">
	<link rel="icon" href="<?php echo esc_url( $dir . 'favicon.svg' ); ?>" type="image/svg+xml">
	<link rel="apple-touch-icon" href="<?php echo esc_url( $dir . 'apple-touch-icon.png' ); ?>">
	<link rel="manifest" href="<?php echo esc_url( $dir . 'site.webmanifest' ); ?>">
	<meta name="theme-color" content="#1D1A19">
	<?php
}
add_action( 'wp_head', 'brix_favicon', 2 );
add_action( 'login_head', 'brix_favicon' );
add_action( 'admin_head', 'brix_favicon' );

/**
 * Адреса /favicon.ico.
 *
 * Браузери й боти питають /favicon.ico напряму, не читаючи <head>.
 * Без іконки сайту WordPress відповідає на цю адресу своїм логотипом
 * «W» — тож перенаправляємо на іконку теми.
 *
 * @return void
 */
function brix_favicon_ico(): void {
	if ( has_site_icon() ) {
		return;
	}

	wp_safe_redirect( get_template_directory_uri() . '/assets/img/favicon/favicon.ico', 301 );
	exit;
}
add_action( 'do_faviconico', 'brix_favicon_ico', 5 );
