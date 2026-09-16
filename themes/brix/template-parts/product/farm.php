<?php
/**
 * Блок виробника на картці товару.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_lot  = $args['lot'] ?? null;
$brix_farm = $brix_lot ? $brix_lot->farm() : null;

if ( ! $brix_farm ) {
	return;
}

$brix_headline = (string) get_post_meta( $brix_farm->ID, 'brix_farm_headline', true );
?>

<section class="brix-section brix-section--tight brix-section--dark brix-grain">
	<div class="brix-wrap brix-farm-teaser">
		<div class="brix-farm-teaser__text">
			<p class="brix-label"><?php esc_html_e( 'Виробник', 'brix' ); ?></p>
			<h2><?php echo esc_html( get_the_title( $brix_farm ) ); ?></h2>

			<?php if ( '' !== $brix_headline ) : ?>
				<p class="brix-lead"><?php echo esc_html( $brix_headline ); ?></p>
			<?php endif; ?>

			<p>
				<a class="brix-btn brix-btn--ghost" href="<?php echo esc_url( (string) get_permalink( $brix_farm ) ); ?>">
					<?php esc_html_e( 'Про станцію', 'brix' ); ?>
					<?php echo brix_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			</p>
		</div>

		<div class="brix-photo brix-photo--warm brix-farm-teaser__photo">
			<span class="brix-photo__caption"><?php esc_html_e( 'Фото · станція', 'brix' ); ?></span>
		</div>
	</div>
</section>
