<?php
/**
 * Картка товару.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	global $product;

	if ( ! $product instanceof WC_Product ) {
		$product = wc_get_product( get_the_ID() );
	}

	$brix_lot = brix_lot( $product );
	?>

	<article <?php wc_product_class( 'brix-product', $product ); ?>>
		<div class="brix-section brix-section--tight">
			<div class="brix-wrap">
				<?php
				woocommerce_breadcrumb(
					array(
						'wrap_before' => '<nav class="brix-breadcrumb">',
						'wrap_after'  => '</nav>',
						'delimiter'   => ' / ',
					)
				);
				?>

				<div class="brix-product__top">
					<?php get_template_part( 'template-parts/product/gallery', null, array( 'product' => $product ) ); ?>
					<?php
					get_template_part(
						'template-parts/product/buy',
						null,
						array(
							'product' => $product,
							'lot'     => $brix_lot,
						)
					);
					?>
				</div>
			</div>
		</div>

		<?php if ( $brix_lot && $brix_lot->has_passport() ) : ?>
			<?php
			get_template_part(
				'template-parts/product/passport',
				null,
				array(
					'product' => $product,
					'lot'     => $brix_lot,
				)
			);
			?>
		<?php endif; ?>

		<?php get_template_part( 'template-parts/product/farm', null, array( 'lot' => $brix_lot ) ); ?>
		<?php get_template_part( 'template-parts/product/recipe', null, array( 'lot' => $brix_lot ) ); ?>

		<?php
		// Схожі лоти. Стандартний хук Woo — дає і upsell, і related.
		woocommerce_output_related_products();
		?>
	</article>

	<?php
endwhile;

get_footer();
