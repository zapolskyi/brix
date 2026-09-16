<?php
/**
 * Індикатор свіжості: обсмажено — пік смаку — ще смачно.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_lot = $args['lot'] ?? null;

if ( ! $brix_lot || ! $brix_lot->freshness() ) {
	return;
}

$brix_fresh = $brix_lot->freshness();
$brix_bar   = $brix_fresh->bar();
?>

<div class="brix-buy__freshness">
	<p class="brix-label">
		<?php
		printf(
			/* translators: 1: дата обсмаження, 2: початок піку, 3: кінець піку. */
			esc_html__( 'Обсмажено %1$s · пік смаку %2$s — %3$s', 'brix' ),
			esc_html( $brix_lot->roast_date->format( 'd.m' ) ),
			esc_html( $brix_fresh->peak_starts()->format( 'd.m' ) ),
			esc_html( $brix_fresh->peak_ends()->format( 'd.m' ) )
		);
		?>
	</p>

	<div class="brix-freshness">
		<span class="brix-freshness__track">
			<span class="brix-freshness__peak" style="--brix-left:<?php echo esc_attr( (string) $brix_bar['left'] ); ?>%;--brix-width:<?php echo esc_attr( (string) $brix_bar['width'] ); ?>%"></span>
			<span class="brix-freshness__now" style="--brix-now:<?php echo esc_attr( (string) $brix_bar['now'] ); ?>%"></span>
		</span>
		<span class="brix-freshness__row">
			<span><?php esc_html_e( 'Дегазація', 'brix' ); ?></span>
			<span><?php esc_html_e( 'Найкраще', 'brix' ); ?></span>
			<span><?php esc_html_e( 'Ще смачно', 'brix' ); ?></span>
		</span>
	</div>

	<?php
	// Дублюємо стан словами: сама смужка кольором нічого не каже
	// ні скрінрідеру, ні тому, хто не розрізняє зелений і сірий.
	?>
	<p class="brix-small brix-muted">
		<?php
		printf(
			/* translators: %s — етап свіжості. */
			esc_html__( 'Зараз: %s', 'brix' ),
			esc_html( $brix_fresh->stage_label() )
		);
		?>
	</p>
</div>
