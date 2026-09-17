<?php
/**
 * Стартова збірка головної.
 *
 * Показується, доки сторінку не наповнили блоками: тема має виглядати
 * зібраною одразу після активації, а не порожньою.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

get_template_part( 'template-parts/home/hero' );
get_template_part( 'template-parts/home/marquee' );
get_template_part( 'template-parts/home/lots' );
get_template_part( 'template-parts/home/brix-explainer' );
get_template_part( 'template-parts/home/teasers' );
get_template_part( 'template-parts/home/farms' );
get_template_part( 'template-parts/home/guides' );
get_template_part( 'template-parts/home/wholesale' );
