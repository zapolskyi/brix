<?php
/**
 * Фото в секціях і плитках.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Друкує фото — або плашку, якщо фото немає.
 *
 * Один хелпер на всі слоти: плитки виробників, врізки головної, блок
 * B2B, «звідки кава» на сторінці лота. Джерел два:
 *
 * - вкладення з медіатеки — фото виробників і товарів. Це контент:
 *   його завантажує `wp brix demo`, а власник міняє в адмінці;
 * - файл теми в assets/img — фото секцій. Вони частина дизайну й
 *   їдуть на хостинг разом з темою.
 *
 * Без фото лишається кольорова плашка з підписом — як було до того, як
 * фото з'явились. Зламаної картинки не буде ніколи.
 *
 * Alt передається явно, а не береться з вкладення: там він українською
 * назавжди, а назву виробника й секції на /en/ перекладено.
 *
 * Ключі $args: class — класи слота («brix-photo--warm brix-tile__photo»);
 * attachment — ID вкладення; asset — ім'я файлу в assets/img без
 * розширення; alt — опис; caption — підпис плашки; sizes — атрибут
 * sizes; tag — div або span (усередині посилань).
 *
 * @param array<string, mixed> $args Налаштування слота.
 * @return void
 */
function brix_photo( array $args ): void {
	$class      = (string) ( $args['class'] ?? '' );
	$attachment = (int) ( $args['attachment'] ?? 0 );
	$asset      = (string) ( $args['asset'] ?? '' );
	$alt        = (string) ( $args['alt'] ?? '' );
	$tag        = 'span' === ( $args['tag'] ?? 'div' ) ? 'span' : 'div';
	$sizes      = (string) ( $args['sizes'] ?? '(min-width: 1024px) 50vw, 100vw' );
	$image      = '';

	if ( $attachment && wp_attachment_is_image( $attachment ) ) {
		$image = wp_get_attachment_image(
			$attachment,
			'large',
			false,
			array(
				'class'    => 'brix-photo__img',
				'alt'      => $alt,
				'sizes'    => $sizes,
				'loading'  => 'lazy',
				'decoding' => 'async',
			)
		);
	} elseif ( '' !== $asset ) {
		$image = brix_asset_image( $asset, $alt );
	}

	printf( '<%1$s class="%2$s">', esc_attr( $tag ), esc_attr( trim( 'brix-photo ' . $class . ( '' !== $image ? ' has-image' : '' ) ) ) );

	if ( '' !== $image ) {
		echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Зібрано wp_get_attachment_image() або brix_asset_image(), обидва екранують самі.
	} else {
		printf( '<span class="brix-photo__caption">%s</span>', esc_html( (string) ( $args['caption'] ?? $alt ) ) );
	}

	printf( '</%s>', esc_attr( $tag ) );
}

/**
 * <img> для файлу з assets/img.
 *
 * Розміри читаються з файлу: без width і height браузер не знає
 * пропорцій до завантаження, і сторінка стрибає.
 *
 * @param string $name Ім'я без розширення.
 * @param string $alt  Опис.
 * @return string Порожньо, якщо файлу немає.
 */
function brix_asset_image( string $name, string $alt ): string {
	$name = sanitize_file_name( $name );
	$path = BRIX_DIR . '/assets/img/' . $name . '.webp';

	if ( ! is_readable( $path ) ) {
		return '';
	}

	$size = wp_getimagesize( $path );

	return sprintf(
		'<img class="brix-photo__img" src="%1$s" width="%2$d" height="%3$d" alt="%4$s" loading="lazy" decoding="async">',
		esc_url( brix_asset_uri( 'assets/img/' . $name . '.webp' ) ),
		(int) ( $size[0] ?? 0 ),
		(int) ( $size[1] ?? 0 ),
		esc_attr( $alt )
	);
}

/**
 * Фото виробника для плиток і врізок.
 *
 * @param WP_Post|int $farm  Виробник.
 * @param string      $classes Класи слота.
 * @param string      $tag   div або span.
 * @return void
 */
function brix_farm_photo( $farm, string $classes, string $tag = 'div' ): void {
	$farm = get_post( $farm );

	if ( ! $farm instanceof WP_Post ) {
		return;
	}

	$title = get_the_title( $farm );

	brix_photo(
		array(
			'class'      => $classes,
			'attachment' => (int) get_post_thumbnail_id( $farm ),
			'alt'        => $title,
			'caption'    => $title,
			'tag'        => $tag,
			'sizes'      => '(min-width: 1024px) 25vw, (min-width: 600px) 50vw, 100vw',
		)
	);
}

/**
 * Картинка для превʼю посилань — мовою сторінки.
 *
 * Для сторінок без власного фото: головна, каталог, тексти. Товари й
 * виробники віддають своє фото самі.
 *
 * @return string
 */
function brix_default_og_image(): string {
	$file = 'assets/img/og-' . ( brix_is_en() ? 'en' : 'uk' ) . '.jpg';

	return is_readable( BRIX_DIR . '/' . $file ) ? brix_asset_uri( $file ) : '';
}
add_filter( 'brix_default_og_image', 'brix_default_og_image' );
