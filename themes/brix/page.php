<?php
/**
 * Окрема сторінка.
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
				<h1><?php the_title(); ?></h1>
			</header>

			<div class="brix-prose">
				<?php
				the_content();

				wp_link_pages(
					array(
						'before' => '<nav class="brix-pagelinks">',
						'after'  => '</nav>',
					)
				);
				?>
			</div>
		</div>
	</article>

	<?php
endwhile;

get_footer();
