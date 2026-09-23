<?php
/**
 * Перемикач «разова покупка / підписка BRIX Club».
 *
 * Радіокнопки, а не посилання: вибір їде разом із формою покупки, тож
 * підписка оформлюється тим самим натиском «Додати в кошик» і працює
 * без JavaScript. Підписка — не окремий товар, а позначка на
 * звичайному лоті.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_product = $args['product'] ?? null;

if ( ! $brix_product instanceof WC_Product || ! $brix_product->get_price() ) {
	return;
}

/**
 * Знижка учасникам клубу, у відсотках.
 *
 * @param int $discount Відсоток.
 */
$brix_discount = (int) apply_filters( 'brix_club_discount', 10 );
$brix_price    = (float) $brix_product->get_price();
$brix_club     = $brix_price * ( 100 - $brix_discount ) / 100;
?>

<fieldset class="brix-plans">
	<legend class="brix-visually-hidden"><?php esc_html_e( 'Як купуєте', 'brix' ); ?></legend>

	<label class="brix-plans__option">
		<input type="radio" name="brix_club" value="0" checked>
		<span class="brix-plans__label"><?php esc_html_e( 'Разова покупка', 'brix' ); ?></span>
		<b class="brix-num"><?php echo esc_html( wp_strip_all_tags( wc_price( $brix_price ) ) ); ?></b>
	</label>

	<label class="brix-plans__option">
		<input type="radio" name="brix_club" value="14">
		<span class="brix-plans__label">
			<?php esc_html_e( 'BRIX Club · кожні 2 тижні', 'brix' ); ?>
			<small class="brix-muted">
				<?php
				printf(
					/* translators: %d — відсоток знижки. */
					esc_html__( '−%d%%, пауза й пропуск у кабінеті', 'brix' ),
					absint( $brix_discount )
				);
				?>
			</small>
		</span>
		<b class="brix-num"><?php echo esc_html( wp_strip_all_tags( wc_price( round( $brix_club ) ) ) ); ?></b>
	</label>

	<label class="brix-plans__option">
		<input type="radio" name="brix_club" value="28">
		<span class="brix-plans__label">
			<?php esc_html_e( 'BRIX Club · кожні 4 тижні', 'brix' ); ?>
			<small class="brix-muted">
				<?php
				printf(
					/* translators: %d — відсоток знижки. */
					esc_html__( '−%d%%, той самий лот або новий щоразу', 'brix' ),
					absint( $brix_discount )
				);
				?>
			</small>
		</span>
		<b class="brix-num"><?php echo esc_html( wp_strip_all_tags( wc_price( round( $brix_club ) ) ) ); ?></b>
	</label>
</fieldset>
