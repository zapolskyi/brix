<?php
/**
 * Рядок смакових нот під героєм.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_notes = brix_marquee_notes();

if ( ! $brix_notes ) {
	return;
}
?>

<div class="brix-marquee brix-grain" aria-hidden="true">
	<div class="brix-marquee__track">
		<?php foreach ( $brix_notes as $brix_index => $brix_note ) : ?>
			<span class="brix-marquee__item<?php echo 1 === $brix_index % 3 ? ' is-accent' : ''; ?>">
				<?php echo esc_html( $brix_note ); ?>
			</span>
			<span class="brix-marquee__sep">/</span>
		<?php endforeach; ?>
	</div>
</div>
