<?php
/**
 * Паспорт лоту: таблиця даних, шкала °Bx, профіль смаку.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_lot = $args['lot'] ?? null;

if ( ! $brix_lot ) {
	return;
}

$brix_farm = $brix_lot->farm();
$brix_rows = array();

if ( $brix_farm ) {
	$brix_country = get_the_terms( $brix_lot->product_id, 'brix_country' );

	if ( is_array( $brix_country ) ) {
		$brix_rows[ __( 'Країна', 'brix' ) ] = $brix_country[0]->name;
	}
}

if ( '' !== $brix_lot->region ) {
	$brix_rows[ __( 'Регіон', 'brix' ) ] = $brix_lot->region;
}

if ( $brix_farm ) {
	$brix_rows[ __( 'Станція', 'brix' ) ] = get_the_title( $brix_farm );
}

if ( '' !== $brix_lot->altitude_label() ) {
	$brix_rows[ __( 'Висота', 'brix' ) ] = $brix_lot->altitude_label();
}

if ( '' !== $brix_lot->variety ) {
	$brix_rows[ __( 'Різновид', 'brix' ) ] = $brix_lot->variety;
}

if ( '' !== $brix_lot->processing_label() ) {
	$brix_rows[ __( 'Обробка', 'brix' ) ] = $brix_lot->processing_label();
}

if ( null !== $brix_lot->sca ) {
	$brix_rows[ __( 'Оцінка SCA', 'brix' ) ] = (string) $brix_lot->sca;
}

if ( null !== $brix_lot->farmer_price ) {
	$brix_rows[ __( 'Ціна фермеру', 'brix' ) ] = '$' . number_format( $brix_lot->farmer_price, 2, ',', ' ' ) . __( ' / кг', 'brix' );
}

if ( '' !== $brix_lot->harvest ) {
	$brix_rows[ __( 'Урожай', 'brix' ) ] = $brix_lot->harvest;
}

$brix_band = \Brix\Core\Product\Lot::ripe_band();
?>

<section class="brix-section brix-passport">
	<div class="brix-wrap">
		<header class="brix-section__head">
			<div>
				<p class="brix-label">
					<?php
					printf(
						/* translators: %s — код лоту. */
						esc_html__( 'Паспорт лоту · %s', 'brix' ),
						esc_html( $brix_lot->code )
					);
					?>
				</p>
				<h2><?php esc_html_e( 'Усе про цю каву', 'brix' ); ?></h2>
			</div>
			<p class="brix-small brix-muted brix-passport__note">
				<?php esc_html_e( 'Дані зібрані на станції під час збору й підтверджені при закупівлі.', 'brix' ); ?>
			</p>
		</header>

		<?php if ( $brix_rows ) : ?>
			<div class="brix-data-grid">
				<?php foreach ( $brix_rows as $brix_key => $brix_value ) : ?>
					<div class="brix-data-grid__cell">
						<span class="brix-data-grid__label"><?php echo esc_html( $brix_key ); ?></span>
						<b class="brix-data-grid__value"><?php echo esc_html( $brix_value ); ?></b>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="brix-passport__scales">
			<?php if ( null !== $brix_lot->brix ) : ?>
				<div class="brix-passport__block">
					<h3 class="brix-passport__subtitle"><?php esc_html_e( 'Цукристість при зборі', 'brix' ); ?></h3>

					<div class="brix-scale">
						<div class="brix-scale__track">
							<span class="brix-scale__band" style="--brix-left:<?php echo esc_attr( (string) $brix_band['left'] ); ?>%;--brix-width:<?php echo esc_attr( (string) $brix_band['width'] ); ?>%"></span>

							<?php for ( $brix_tick = \Brix\Core\Product\Lot::SCALE_MIN; $brix_tick <= \Brix\Core\Product\Lot::SCALE_MAX; $brix_tick += 2 ) : ?>
								<span class="brix-scale__tick" style="--brix-left:<?php echo esc_attr( (string) \Brix\Core\Product\Lot::scale_position( $brix_tick ) ); ?>%">
									<span><?php echo esc_html( (string) $brix_tick ); ?></span>
								</span>
							<?php endfor; ?>

							<span class="brix-scale__mark" style="--brix-left:<?php echo esc_attr( (string) \Brix\Core\Product\Lot::scale_position( $brix_lot->brix ) ); ?>%">
								<b><?php echo esc_html( (string) $brix_lot->brix ); ?></b><i></i>
							</span>
						</div>
					</div>

					<p class="brix-small brix-muted">
						<?php
						printf(
							/* translators: 1: нижня межа стиглості, 2: верхня межа. */
							esc_html__( 'Пік стиглості — від %1$d до %2$d °Bx. Нижче ягода недостигла, вище переспіла.', 'brix' ),
							absint( \Brix\Core\Product\Lot::RIPE_MIN ),
							absint( \Brix\Core\Product\Lot::RIPE_MAX )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( $brix_lot->taste_bars() ) : ?>
				<div class="brix-passport__block">
					<h3 class="brix-passport__subtitle"><?php esc_html_e( 'Профіль смаку', 'brix' ); ?></h3>

					<div class="brix-taste">
						<?php foreach ( $brix_lot->taste_bars() as $brix_bar ) : ?>
							<div class="brix-taste__row">
								<span><?php echo esc_html( $brix_bar['label'] ); ?></span>
								<span class="brix-taste__track">
									<span class="brix-taste__fill" style="--brix-value:<?php echo esc_attr( (string) $brix_bar['percent'] ); ?>%"></span>
								</span>
								<span class="brix-taste__value"><?php echo esc_html( $brix_bar['score'] . '/5' ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( '' !== $brix_lot->cup_notes ) : ?>
			<div class="brix-passport__cup">
				<h3 class="brix-passport__subtitle"><?php esc_html_e( 'Що це означає в чашці', 'brix' ); ?></h3>
				<p class="brix-lead"><?php echo esc_html( $brix_lot->cup_notes ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</section>
