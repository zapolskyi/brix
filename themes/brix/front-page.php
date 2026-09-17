<?php
/**
 * Головна сторінка.
 *
 * Розмітки тут немає навмисно: головна зібрана з блоків і правиться
 * в редакторі. Шаблон лише віддає вміст сторінки без обгорток —
 * секції самі несуть свої відступи й фон.
 *
 * Якщо сторінку ще не наповнили, показуємо стартову збірку з
 * template-parts/home/ — інакше на чистій інсталяції головна порожня.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();

		if ( has_blocks( get_the_content() ) ) {
			the_content();
		} else {
			get_template_part( 'template-parts/home/fallback' );
		}
	}
} else {
	get_template_part( 'template-parts/home/fallback' );
}

get_footer();
