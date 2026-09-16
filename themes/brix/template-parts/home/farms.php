<?php
/**
 * Виробники на головній.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_farms = brix_home_farms( 4 );

if ( ! $brix_farms ) {
	return;
}
?>

<section class="brix-section brix-section--tight">
	<div class="brix-wrap">
		<header class="brix-section__head">
			<div>
				<p class="brix-label"><?php esc_html_e( 'Прямі стосунки', 'brix' ); ?></p>
				<h2><?php esc_html_e( 'Виробники, з якими працюємо', 'brix' ); ?></h2>
			</div>
			<a class="brix-section__more" href="<?php echo esc_url( (string) get_post_type_archive_link( 'brix_farm' ) ); ?>">
				<?php esc_html_e( 'Усі виробники', 'brix' ); ?>
				<?php echo brix_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</a>
		</header>

		<div class="brix-tiles">
			<?php foreach ( $brix_farms as $brix_farm ) : ?>
				<?php
				$brix_since = (int) get_post_meta( $brix_farm->ID, 'brix_farm_since_year', true );
				$brix_terms = get_the_terms( $brix_farm->ID, 'brix_country' );
				?>
				<a class="brix-tile" href="<?php echo esc_url( (string) get_permalink( $brix_farm ) ); ?>">
					<span class="brix-photo brix-photo--warm brix-tile__photo">
						<span class="brix-photo__caption"><?php echo esc_html( get_the_title( $brix_farm ) ); ?></span>
					</span>
					<span class="brix-tile__title"><?php echo esc_html( get_the_title( $brix_farm ) ); ?></span>
					<span class="brix-tile__meta brix-label">
						<?php
						$brix_bits = array();

						if ( is_array( $brix_terms ) ) {
							$brix_bits[] = $brix_terms[0]->name;
						}

						if ( $brix_since ) {
							/* translators: %d — рік початку співпраці. */
							$brix_bits[] = sprintf( __( 'з %d', 'brix' ), $brix_since );
						}

						echo esc_html( implode( ' · ', $brix_bits ) );
						?>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
