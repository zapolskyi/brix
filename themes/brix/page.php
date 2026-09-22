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
			<?php
			/*
			 * Сторінка подяки малює власний заголовок — змістовніший
			 * за «Замовлення отримано». Дві <h1> на сторінці збивають
			 * і читалку, і структуру документа.
			 */
			?>
			<?php if ( ! brix_is_order_received_page() ) : ?>
				<header class="brix-section__head">
					<h1><?php the_title(); ?></h1>
				</header>
			<?php endif; ?>

			<div class="<?php echo brix_is_shop_ui_page() ? 'brix-page-ui' : 'brix-prose'; ?>">
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
