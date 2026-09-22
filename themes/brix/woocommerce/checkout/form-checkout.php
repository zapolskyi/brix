<?php
/**
 * Оформлення замовлення.
 *
 * Override шаблону WooCommerce: двоколонкова розкладка з макета —
 * поля зліва, підсумок замовлення липким блоком справа.
 *
 * Першим у лівій колонці стоїть спосіб отримання, бо він вирішує, що
 * буде нижче: доставка питає місто й відділення Нової Пошти,
 * самовивіз — нічого, крім контактів. Форма проходиться без жодного
 * JavaScript; без нього спосіб отримання перемикає кнопка «Оновити».
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_checkout_form', $checkout );

// Гість не може оформити, а увійти йому не запропонували — тупик.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'Щоб оформити замовлення, увійдіть у кабінет.', 'brix' ) ) );
	return;
}
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout brix-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

	<div class="brix-checkout__fields">
		<?php if ( $checkout->get_checkout_fields() ) : ?>
			<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

			<?php brix_delivery_section(); ?>

			<div id="customer_details">
				<?php do_action( 'woocommerce_checkout_billing' ); ?>
				<?php do_action( 'woocommerce_checkout_shipping' ); ?>
			</div>

			<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
		<?php endif; ?>
	</div>

	<aside class="brix-checkout__summary">
		<h2 class="brix-checkout__title" id="order_review_heading"><?php esc_html_e( 'Ваше замовлення', 'brix' ); ?></h2>

		<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>
		<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

		<div class="woocommerce-checkout-review-order" id="order_review">
			<?php do_action( 'woocommerce_checkout_order_review' ); ?>
		</div>

		<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
	</aside>
</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
