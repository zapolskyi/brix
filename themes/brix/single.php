<?php
/**
 * Окремий запис.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>

	<article <?php post_class( 'brix-section brix-section--tight' ); ?>>
		<div class="brix-wrap">
			<header class="brix-section__head">
				<div>
					<p class="brix-label"><?php echo esc_html( get_the_date() ); ?></p>
					<h1><?php the_title(); ?></h1>
				</div>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="brix-post__cover">
					<?php the_post_thumbnail( 'brix-hero' ); ?>
				</figure>
			<?php endif; ?>

			<div class="brix-prose">
				<?php the_content(); ?>
			</div>
		</div>
	</article>

	<?php
endwhile;

get_footer();
