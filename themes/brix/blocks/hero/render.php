<?php
/**
 * Рендер блока «Герой».
 *
 * @package BRIX
 *
 * @var array<string, mixed> $attributes Налаштування блока.
 */

defined( 'ABSPATH' ) || exit;

$brix_heading = brix_t( (string) ( $attributes['heading'] ?? '' ) );
$brix_accent  = brix_t( (string) ( $attributes['accent'] ?? '' ) );
$brix_product = ! empty( $attributes['showLot'] ) ? brix_featured_lot() : null;
$brix_lot     = $brix_product ? brix_lot( $brix_product ) : null;

/*
 * Акцентна частина заголовка підсвічується вишневим. Шукаємо її
 * як підрядок, а не зберігаємо окремим полем: редактор пише
 * заголовок цілим реченням і не має ділити його на шматки.
 */
$brix_title_html = esc_html( $brix_heading );

if ( '' !== $brix_accent && str_contains( $brix_heading, $brix_accent ) ) {
	$brix_title_html = str_replace(
		esc_html( $brix_accent ),
		'<span class="brix-hero__accent">' . esc_html( $brix_accent ) . '</span>',
		esc_html( $brix_heading )
	);
}
?>

<section <?php echo get_block_wrapper_attributes( array( 'class' => 'brix-hero' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="brix-hero__text">
		<?php if ( ! empty( $attributes['label'] ) ) : ?>
			<p class="brix-label"><?php echo esc_html( brix_t( $attributes['label'] ) ); ?></p>
		<?php endif; ?>

		<h1 class="brix-hero__title brix-display">
			<?php echo wp_kses_post( $brix_title_html ); ?>
		</h1>

		<?php if ( ! empty( $attributes['text'] ) ) : ?>
			<p class="brix-lead brix-hero__lead"><?php echo esc_html( brix_t( $attributes['text'] ) ); ?></p>
		<?php endif; ?>

		<div class="brix-hero__actions">
			<?php if ( ! empty( $attributes['primaryLabel'] ) ) : ?>
				<?php $brix_primary_url = ! empty( $attributes['primaryUrl'] ) ? brix_local_url( $attributes['primaryUrl'] ) : (string) wc_get_page_permalink( 'shop' ); ?>
				<a class="brix-btn brix-btn--xl" href="<?php echo esc_url( $brix_primary_url ); ?>">
					<?php echo esc_html( brix_t( $attributes['primaryLabel'] ) ); ?>
					<?php echo brix_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			<?php endif; ?>

			<?php if ( ! empty( $attributes['secondaryLabel'] ) ) : ?>
				<?php $brix_secondary_url = ! empty( $attributes['secondaryUrl'] ) ? brix_local_url( $attributes['secondaryUrl'] ) : brix_page_url( 'quiz' ); ?>
				<a class="brix-btn brix-btn--xl brix-btn--outline" href="<?php echo esc_url( $brix_secondary_url ); ?>">
					<?php echo esc_html( brix_t( $attributes['secondaryLabel'] ) ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $brix_product ) : ?>
		<div class="brix-hero__visual">
			<?php $brix_style = brix_pack_style( $brix_product ); ?>
			<span class="brix-hero__plate <?php echo esc_attr( 'brix-card__stage--' . ( '' !== $brix_style ? $brix_style : 'core' ) ); ?>"></span>

			<a class="brix-hero__pack" href="<?php echo esc_url( $brix_product->get_permalink() ); ?>">
				<?php brix_the_pack( $brix_product, '100%' ); ?>
				<span class="brix-visually-hidden"><?php echo esc_html( $brix_product->get_name() ); ?></span>
			</a>

			<?php if ( $brix_lot && null !== $brix_lot->brix ) : ?>
				<div class="brix-hero__meter">
					<span class="brix-label"><?php esc_html_e( 'Рефрактометр, станція', 'brix' ); ?></span>
					<b class="brix-mono"><?php echo esc_html( $brix_lot->brix . ' °Bx' ); ?></b>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>
