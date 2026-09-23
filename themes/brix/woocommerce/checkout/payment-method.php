<?php
/**
 * Один спосіб оплати.
 *
 * Override шаблону WooCommerce: картка з макета — відмітка, назва,
 * опис під нею і платіжна система праворуч. Уся картка — це <label>,
 * тож натискається будь-де, а не тільки в кружок.
 *
 * Опис живе в самій картці й видно його завжди: покупець порівнює
 * способи, а порівнювати те, що сховане до вибору, не вийде. Окрема
 * панель під карткою лишається тільки для платіжок із власними
 * полями — WooCommerce розгортає її при виборі.
 *
 * @package BRIX
 * @version 3.5.0
 *
 * @var WC_Payment_Gateway $gateway
 */

defined( 'ABSPATH' ) || exit;

$brix_id = 'payment_method_' . $gateway->id;

// Назву платіжної системи показуємо лише для власних шлюзів: у
// накладного платежу «системи» немає, і там був би дубль назви.
$brix_via = 0 === strpos( $gateway->id, 'brix_' ) ? $gateway->get_method_title() : '';
?>
<li class="wc_payment_method payment_method_<?php echo esc_attr( $gateway->id ); ?> brix-pay">
	<input
		id="<?php echo esc_attr( $brix_id ); ?>"
		type="radio"
		class="input-radio brix-pay__input"
		name="payment_method"
		value="<?php echo esc_attr( $gateway->id ); ?>"
		<?php checked( $gateway->chosen, true ); ?>
		data-order_button_text="<?php echo esc_attr( $gateway->order_button_text ); ?>">

	<label class="brix-pay__card" for="<?php echo esc_attr( $brix_id ); ?>">
		<span class="brix-pay__body">
			<span class="brix-pay__name"><?php echo wp_kses_post( $gateway->get_title() ); ?></span>
			<?php if ( $gateway->get_description() ) : ?>
				<span class="brix-pay__desc"><?php echo wp_kses_post( wp_strip_all_tags( $gateway->get_description() ) ); ?></span>
			<?php endif; ?>
		</span>

		<?php if ( $brix_via ) : ?>
			<span class="brix-pay__via"><?php echo esc_html( $brix_via ); ?></span>
		<?php endif; ?>
	</label>

	<?php if ( $gateway->has_fields() ) : ?>
		<div class="payment_box payment_method_<?php echo esc_attr( $gateway->id ); ?>" <?php echo $gateway->chosen ? '' : 'style="display:none;"'; ?>>
			<?php $gateway->payment_fields(); ?>
		</div>
	<?php endif; ?>
</li>
