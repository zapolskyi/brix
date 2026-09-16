<?php
/**
 * Головна сторінка.
 *
 * Секції винесені в template-parts/home/: у фазі 3В вони стануть
 * render-колбеками власних блоків Gutenberg, і розмітку переписувати
 * не доведеться.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

get_header();

get_template_part( 'template-parts/home/hero' );
get_template_part( 'template-parts/home/marquee' );
get_template_part( 'template-parts/home/lots' );
get_template_part( 'template-parts/home/brix-explainer' );
get_template_part( 'template-parts/home/teasers' );
get_template_part( 'template-parts/home/farms' );
get_template_part( 'template-parts/home/guides' );
get_template_part( 'template-parts/home/wholesale' );

get_footer();
