<?php
/**
 * Адреса в кабінеті.
 *
 * Override шаблону WooCommerce. Checkout збирає одну адресу — поля
 * «платіжної» адреси WooCommerce, яка тут же й адреса доставки: місто
 * й відділення Нової Пошти або вулиця за кордоном. Тож і в кабінеті
 * адреса одна, названа так, як її розуміє покупець. Друга, «адреса
 * доставки» WooCommerce, лишалась би завжди порожньою й питала б
 * «Ви ще не налаштували цей тип адреси».
 *
 * @package BRIX
 * @version 9.3.0
 */

defined( 'ABSPATH' ) || exit;

$brix_address = wc_get_account_formatted_address( 'billing' );
?>

<section class="brix-address-card">
	<header class="brix-address-card__head">
		<h2 class="brix-address-card__title"><?php esc_html_e( 'Адреса доставки', 'brix' ); ?></h2>
		<a class="brix-btn brix-btn--outline brix-btn--sm" href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address', 'billing' ) ); ?>">
			<?php echo $brix_address ? esc_html__( 'Змінити', 'brix' ) : esc_html__( 'Додати', 'brix' ); ?>
		</a>
	</header>

	<?php if ( $brix_address ) : ?>
		<address class="brix-address-card__body"><?php echo wp_kses_post( $brix_address ); ?></address>
	<?php else : ?>
		<p class="brix-muted">
			<?php esc_html_e( 'Адреси ще немає. Вона збережеться сама з першого замовлення — або додайте її зараз.', 'brix' ); ?>
		</p>
	<?php endif; ?>

	<p class="brix-small brix-muted">
		<?php esc_html_e( 'Checkout заповнить її за вас. Змінити адресу для одного замовлення можна просто під час оформлення.', 'brix' ); ?>
	</p>
</section>
