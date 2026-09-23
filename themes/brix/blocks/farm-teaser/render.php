<?php
/**
 * Рендер блока «Виробники».
 *
 * @package BRIX
 *
 * @var array<string, mixed> $attributes Налаштування блока.
 */

defined( 'ABSPATH' ) || exit;

$brix_farms = brix_home_farms( max( 1, (int) ( $attributes['limit'] ?? 4 ) ) );

if ( ! $brix_farms ) {
	return;
}
?>

<section <?php echo get_block_wrapper_attributes( array( 'class' => 'brix-section brix-section--tight' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="brix-wrap">
		<header class="brix-section__head">
			<div>
				<?php if ( ! empty( $attributes['label'] ) ) : ?>
					<p class="brix-label"><?php echo esc_html( brix_t( $attributes['label'] ) ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $attributes['heading'] ) ) : ?>
					<h2><?php echo esc_html( brix_t( $attributes['heading'] ) ); ?></h2>
				<?php endif; ?>
			</div>

			<a class="brix-section__more" href="<?php echo esc_url( (string) get_post_type_archive_link( 'brix_farm' ) ); ?>">
				<?php esc_html_e( 'Усі виробники', 'brix' ); ?>
				<?php echo brix_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</a>
		</header>

		<div class="brix-tiles">
			<?php foreach ( $brix_farms as $brix_farm ) : ?>
				<?php $brix_country = get_the_terms( $brix_farm->ID, 'brix_country' ); ?>
				<a class="brix-tile" href="<?php echo esc_url( (string) get_permalink( $brix_farm ) ); ?>">
					<span class="brix-photo brix-photo--warm brix-tile__photo">
						<span class="brix-photo__caption"><?php echo esc_html( get_the_title( $brix_farm ) ); ?></span>
					</span>
					<span class="brix-tile__title"><?php echo esc_html( get_the_title( $brix_farm ) ); ?></span>
					<span class="brix-tile__meta brix-label">
						<?php echo esc_html( is_array( $brix_country ) ? $brix_country[0]->name : '' ); ?>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
