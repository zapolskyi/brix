<?php
/**
 * Архів гайдів заварювання.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="brix-section brix-section--tight">
	<div class="brix-wrap">
		<header class="brix-catalog__head">
			<p class="brix-label"><?php esc_html_e( 'Без снобізму', 'brix' ); ?></p>
			<h1><?php esc_html_e( 'Гайди заварювання', 'brix' ); ?></h1>
			<p class="brix-lead brix-muted">
				<?php esc_html_e( 'Пропорції, температура й кроки для кожного способу. Почніть із того, що вже стоїть у вас на кухні.', 'brix' ); ?>
			</p>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="brix-tiles">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/guide/tile', null, array( 'guide' => get_post() ) );
				endwhile;
				?>
			</div>

			<?php the_posts_pagination( array( 'mid_size' => 1 ) ); ?>
		<?php else : ?>
			<?php get_template_part( 'template-parts/content/content', 'none' ); ?>
		<?php endif; ?>
	</div>
</div>

<?php
get_footer();
