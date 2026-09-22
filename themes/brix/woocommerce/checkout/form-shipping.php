<?php
/**
 * Додаткові поля checkout.
 *
 * Override шаблону WooCommerce. Блок «Доставити на іншу адресу?»
 * прибрано: адреса в цьому магазині одна — та, що в блоці «Куди
 * доставити». Друга адреса поруч із вибраним відділенням Нової Пошти
 * означала б два різні місця призначення в одному замовленні.
 *
 * Прихованих полів тут теж немає: WooCommerce сам копіює адресу
 * доставки з платіжної, коли «інша адреса» не позначена.
 *
 * @package BRIX
 * @var WC_Checkout $checkout
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="woocommerce-additional-fields">
	<?php do_action( 'woocommerce_before_order_notes', $checkout ); ?>

	<?php if ( apply_filters( 'woocommerce_enable_order_notes_field', 'yes' === get_option( 'woocommerce_enable_order_comments', 'yes' ) ) ) : ?>
		<div class="woocommerce-additional-fields__field-wrapper">
			<?php foreach ( $checkout->get_checkout_fields( 'order' ) as $brix_key => $brix_field ) : ?>
				<?php woocommerce_form_field( $brix_key, $brix_field, $checkout->get_value( $brix_key ) ); ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php do_action( 'woocommerce_after_order_notes', $checkout ); ?>
</div>
