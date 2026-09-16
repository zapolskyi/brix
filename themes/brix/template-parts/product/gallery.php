<?php
/**
 * Галерея товару: велика пачка або фото.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_product = $args['product'] ?? null;

if ( ! $brix_product instanceof WC_Product ) {
	return;
}

$brix_style = brix_pack_style( $brix_product );
?>

<div class="brix-product__gallery">
	<div class="brix-product__stage <?php echo $brix_style ? esc_attr( 'brix-card__stage--' . $brix_style ) : ''; ?>">
		<?php brix_the_product_visual( $brix_product, '62%' ); ?>
	</div>

	<?php
	$brix_gallery = $brix_product->get_gallery_image_ids();

	if ( $brix_gallery ) :
		?>
		<div class="brix-product__thumbs">
			<?php foreach ( $brix_gallery as $brix_image_id ) : ?>
				<?php echo wp_kses_post( wp_get_attachment_image( $brix_image_id, 'woocommerce_thumbnail' ) ); ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
