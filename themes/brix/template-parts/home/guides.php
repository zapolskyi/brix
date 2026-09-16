<?php
/**
 * Гайди заварювання на головній.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_guides = brix_home_guides( 4 );

if ( ! $brix_guides ) {
	return;
}
?>

<section class="brix-section brix-section--tight">
	<div class="brix-wrap">
		<header class="brix-section__head">
			<div>
				<p class="brix-label"><?php esc_html_e( 'Без снобізму', 'brix' ); ?></p>
				<h2><?php esc_html_e( 'Заварити вдома', 'brix' ); ?></h2>
			</div>
			<a class="brix-section__more" href="<?php echo esc_url( (string) get_post_type_archive_link( 'brix_guide' ) ); ?>">
				<?php esc_html_e( 'Усі рецепти', 'brix' ); ?>
				<?php echo brix_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</a>
		</header>

		<div class="brix-tiles">
			<?php foreach ( $brix_guides as $brix_guide ) : ?>
				<?php
				$brix_dose  = (int) get_post_meta( $brix_guide->ID, 'brix_guide_dose', true );
				$brix_water = (int) get_post_meta( $brix_guide->ID, 'brix_guide_water', true );
				$brix_temp  = (int) get_post_meta( $brix_guide->ID, 'brix_guide_temperature', true );
				?>
				<a class="brix-tile brix-tile--guide" href="<?php echo esc_url( (string) get_permalink( $brix_guide ) ); ?>">
					<span class="brix-tile__title"><?php echo esc_html( get_the_title( $brix_guide ) ); ?></span>
					<span class="brix-tile__meta brix-mono">
						<?php
						$brix_bits = array();

						if ( $brix_dose ) {
							$brix_bits[] = $brix_dose . ' г';
						}

						if ( $brix_water ) {
							$brix_bits[] = $brix_water . ' мл';
						}

						if ( $brix_temp ) {
							$brix_bits[] = $brix_temp . ' °C';
						}

						echo esc_html( implode( ' · ', $brix_bits ) );
						?>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
