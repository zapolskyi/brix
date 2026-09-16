<?php
/**
 * Рендер блока «Гайди заварювання».
 *
 * @package BRIX
 *
 * @var array<string, mixed> $attributes Налаштування блока.
 */

defined( 'ABSPATH' ) || exit;

$brix_guides = brix_home_guides( max( 1, (int) ( $attributes['limit'] ?? 4 ) ) );

if ( ! $brix_guides ) {
	return;
}
?>

<section <?php echo get_block_wrapper_attributes( array( 'class' => 'brix-section brix-section--tight' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="brix-wrap">
		<header class="brix-section__head">
			<div>
				<?php if ( ! empty( $attributes['label'] ) ) : ?>
					<p class="brix-label"><?php echo esc_html( $attributes['label'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $attributes['heading'] ) ) : ?>
					<h2><?php echo esc_html( $attributes['heading'] ); ?></h2>
				<?php endif; ?>
			</div>

			<a class="brix-section__more" href="<?php echo esc_url( (string) get_post_type_archive_link( 'brix_guide' ) ); ?>">
				<?php esc_html_e( 'Усі рецепти', 'brix' ); ?>
				<?php echo brix_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</a>
		</header>

		<div class="brix-tiles">
			<?php foreach ( $brix_guides as $brix_guide ) : ?>
				<?php get_template_part( 'template-parts/guide/tile', null, array( 'guide' => $brix_guide ) ); ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>
