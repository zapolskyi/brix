<?php
/**
 * Рендер блока «Рядок смакових нот».
 *
 * @package BRIX
 *
 * @var array<string, mixed> $attributes Налаштування блока.
 */

defined( 'ABSPATH' ) || exit;

$brix_notes = array_slice( brix_marquee_notes(), 0, max( 1, (int) ( $attributes['limit'] ?? 12 ) ) );

if ( ! $brix_notes ) {
	return;
}
?>

<div <?php echo get_block_wrapper_attributes( array( 'class' => 'brix-marquee brix-grain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-hidden="true">
	<div class="brix-marquee__track">
		<?php foreach ( $brix_notes as $brix_index => $brix_note ) : ?>
			<span class="brix-marquee__item<?php echo 1 === $brix_index % 3 ? ' is-accent' : ''; ?>"><?php echo esc_html( $brix_note ); ?></span>
			<span class="brix-marquee__sep">/</span>
		<?php endforeach; ?>
	</div>
</div>
