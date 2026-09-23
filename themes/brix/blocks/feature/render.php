<?php
/**
 * Рендер блока «Секція з текстом».
 *
 * Один блок покриває три схожі секції макета: пояснення шкали Brix,
 * врізки квізу й клубу, блок B2B. Вони відрізняються лише тоном,
 * стороною й тим, що стоїть праворуч — фото чи шкала.
 *
 * @package BRIX
 *
 * @var array<string, mixed> $attributes Налаштування блока.
 */

defined( 'ABSPATH' ) || exit;

$brix_dark     = ! empty( $attributes['dark'] );
$brix_reversed = ! empty( $attributes['reversed'] );
$brix_media    = (string) ( $attributes['media'] ?? 'photo' );
$brix_tone     = (string) ( $attributes['tone'] ?? 'warm' );

/*
 * Фото секції — файл теми, а не вкладення медіатеки: секції частина
 * дизайну, і їхні фото їдуть на хостинг разом з темою, без імпорту.
 * Alt — сталий опис кожного знімка, перекладений для /en/.
 */
$brix_photos = array(
	'quiz'      => __( 'Способи заварювання поруч: V60, аеропрес, турка, френч-прес', 'brix' ),
	'club'      => __( 'Три пачки кави в коробці доставки', 'brix' ),
	'wholesale' => __( 'Бариста готує еспресо за баром кав’ярні', 'brix' ),
);
$brix_photo  = (string) ( $attributes['photo'] ?? '' );

$brix_section = 'brix-section brix-section--tight';

if ( $brix_dark ) {
	$brix_section .= ' brix-section--dark brix-grain';
}

$brix_button_class = $brix_dark ? 'brix-btn brix-btn--light' : 'brix-btn brix-btn--outline';
?>

<section <?php echo get_block_wrapper_attributes( array( 'class' => $brix_section ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="brix-wrap brix-teaser <?php echo $brix_reversed ? 'brix-teaser--reverse' : ''; ?>">
		<div class="brix-teaser__text">
			<?php if ( ! empty( $attributes['label'] ) ) : ?>
				<p class="brix-label"><?php echo esc_html( brix_t( $attributes['label'] ) ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $attributes['heading'] ) ) : ?>
				<h2><?php echo esc_html( brix_t( $attributes['heading'] ) ); ?></h2>
			<?php endif; ?>

			<?php if ( ! empty( $attributes['text'] ) ) : ?>
				<p class="brix-lead <?php echo $brix_dark ? '' : 'brix-muted'; ?>"><?php echo esc_html( brix_t( $attributes['text'] ) ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $attributes['buttonLabel'] ) ) : ?>
				<p>
					<a class="<?php echo esc_attr( $brix_button_class ); ?>" href="<?php echo esc_url( $attributes['buttonUrl'] ? brix_local_url( $attributes['buttonUrl'] ) : home_url( '/' ) ); ?>">
						<?php echo esc_html( brix_t( $attributes['buttonLabel'] ) ); ?>
						<?php echo brix_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				</p>
			<?php endif; ?>
		</div>

		<?php if ( 'brix-scale' === $brix_media ) : ?>
			<?php
			$brix_band = class_exists( \Brix\Core\Product\Lot::class ) ? \Brix\Core\Product\Lot::ripe_band() : array(
				'left'  => 42.86,
				'width' => 28.57,
			);
			?>
			<div class="brix-explainer__scale">
				<div class="brix-scale">
					<div class="brix-scale__track">
						<span class="brix-scale__band" style="--brix-left:<?php echo esc_attr( (string) $brix_band['left'] ); ?>%;--brix-width:<?php echo esc_attr( (string) $brix_band['width'] ); ?>%"></span>

						<?php for ( $brix_tick = 14; $brix_tick <= 28; $brix_tick += 2 ) : ?>
							<span class="brix-scale__tick" style="--brix-left:<?php echo esc_attr( (string) round( ( $brix_tick - 14 ) / 14 * 100, 2 ) ); ?>%">
								<span><?php echo esc_html( (string) $brix_tick ); ?></span>
							</span>
						<?php endfor; ?>

						<span class="brix-scale__mark" style="--brix-left:57.14%"><b>22°</b><i></i></span>
					</div>
				</div>
				<p class="brix-small brix-muted"><?php esc_html_e( 'Зелена смуга — вікно стиглості, 20–24 °Bx.', 'brix' ); ?></p>
			</div>
		<?php else : ?>
			<?php
			brix_photo(
				array(
					'class'   => 'brix-photo--' . $brix_tone . ' brix-teaser__photo',
					'asset'   => isset( $brix_photos[ $brix_photo ] ) ? 'section-' . $brix_photo : '',
					'alt'     => $brix_photos[ $brix_photo ] ?? '',
					'caption' => brix_t( (string) ( $attributes['heading'] ?? '' ) ),
				)
			);
			?>
		<?php endif; ?>
	</div>
</section>
