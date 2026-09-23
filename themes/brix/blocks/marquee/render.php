<?php
/**
 * Рендер блока «Рядок смакових нот».
 *
 * Бігуча стрічка без JavaScript: доріжка — дві однакові половини, і
 * анімація зсуває її рівно на одну половину. У мить, коли перша
 * половина виїжджає, на її місці стоїть друга, ідентична, — стрибка
 * не видно, і стрічка біжить нескінченно.
 *
 * Кожна половина — ноти, повторені кілька разів: половина мусить бути
 * ширшою за найширший екран, інакше перед стиком лишався б порожній
 * хвіст.
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

// Скільки разів повторити ноти в половині, щоб вона була ширшою за
// 4K-екран: у ноти в середньому ~110 px разом із роздільником.
$brix_repeat = max( 2, (int) ceil( 40 / count( $brix_notes ) ) );
$brix_half   = array();

for ( $brix_i = 0; $brix_i < $brix_repeat; $brix_i++ ) {
	$brix_half = array_merge( $brix_half, $brix_notes );
}

// Швидкість стала, незалежно від кількості нот: тривалість росте разом
// із довжиною половини.
$brix_duration = count( $brix_half ) * 2;
?>

<div <?php echo get_block_wrapper_attributes( array( 'class' => 'brix-marquee brix-grain' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-hidden="true">
	<div class="brix-marquee__track" style="--brix-marquee-duration: <?php echo esc_attr( (string) $brix_duration ); ?>s">
		<?php for ( $brix_copy = 0; $brix_copy < 2; $brix_copy++ ) : ?>
			<div class="brix-marquee__half">
				<?php foreach ( $brix_half as $brix_index => $brix_note ) : ?>
					<span class="brix-marquee__item<?php echo 1 === $brix_index % 3 ? ' is-accent' : ''; ?>"><?php echo esc_html( $brix_note ); ?></span>
					<span class="brix-marquee__sep">/</span>
				<?php endforeach; ?>
			</div>
		<?php endfor; ?>
	</div>
</div>
