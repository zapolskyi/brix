<?php
/**
 * Рекомендований рецепт заварювання.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_lot   = $args['lot'] ?? null;
$brix_guide = $brix_lot ? $brix_lot->brew_guide() : null;

if ( ! $brix_guide ) {
	return;
}

$brix_dose  = (int) get_post_meta( $brix_guide->ID, 'brix_guide_dose', true );
$brix_water = (int) get_post_meta( $brix_guide->ID, 'brix_guide_water', true );
$brix_temp  = (int) get_post_meta( $brix_guide->ID, 'brix_guide_temperature', true );
$brix_grind = (string) get_post_meta( $brix_guide->ID, 'brix_guide_grind', true );
?>

<section class="brix-section brix-section--tight">
	<div class="brix-wrap brix-recipe">
		<div>
			<p class="brix-label"><?php esc_html_e( 'Рецепт під цей лот', 'brix' ); ?></p>
			<h2><?php echo esc_html( get_the_title( $brix_guide ) ); ?></h2>

			<dl class="brix-recipe__specs">
				<?php if ( $brix_dose && $brix_water ) : ?>
					<div>
						<dt class="brix-label"><?php esc_html_e( 'Пропорція', 'brix' ); ?></dt>
						<dd class="brix-mono"><?php echo esc_html( $brix_dose . __( ' г', 'brix' ) . ' / ' . $brix_water . __( ' мл', 'brix' ) ); ?></dd>
					</div>
				<?php endif; ?>

				<?php if ( $brix_temp ) : ?>
					<div>
						<dt class="brix-label"><?php esc_html_e( 'Температура', 'brix' ); ?></dt>
						<dd class="brix-mono"><?php echo esc_html( $brix_temp . ' °C' ); ?></dd>
					</div>
				<?php endif; ?>

				<?php if ( '' !== $brix_grind ) : ?>
					<div>
						<dt class="brix-label"><?php esc_html_e( 'Помел', 'brix' ); ?></dt>
						<dd><?php echo esc_html( $brix_grind ); ?></dd>
					</div>
				<?php endif; ?>
			</dl>

			<p>
				<a class="brix-btn brix-btn--outline" href="<?php echo esc_url( (string) get_permalink( $brix_guide ) ); ?>">
					<?php esc_html_e( 'Відкрити рецепт', 'brix' ); ?>
					<?php echo brix_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			</p>
		</div>
	</div>
</section>
