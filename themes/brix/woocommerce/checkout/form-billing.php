<?php
/**
 * Дані покупця в checkout.
 *
 * Override шаблону WooCommerce. Одну довгу решітку полів розбито на
 * два блоки: хто отримує і куди везти. Другий зникає при самовивозі —
 * питати адресу в того, хто прийде сам, немає сенсу.
 *
 * Поля лишаються в розмітці навіть прихованими: так скрипт показує їх
 * миттєво, щойно покупець перемикається на доставку, а без скрипта їх
 * повертає кнопка «Оновити».
 *
 * @package BRIX
 * @var WC_Checkout $checkout
 */

defined( 'ABSPATH' ) || exit;

$brix_address_keys = array( 'billing_country', 'billing_city', 'billing_address_1', 'billing_state', 'billing_postcode' );
$brix_fields       = $checkout->get_checkout_fields( 'billing' );
$brix_contact      = array();
$brix_address      = array();

foreach ( $brix_fields as $brix_key => $brix_field ) {
	if ( in_array( $brix_key, $brix_address_keys, true ) ) {
		$brix_address[ $brix_key ] = $brix_field;
		continue;
	}

	$brix_contact[ $brix_key ] = $brix_field;
}
?>

<div class="woocommerce-billing-fields">
	<?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); ?>

	<section class="brix-checkout__block">
		<h3><?php esc_html_e( 'Отримувач', 'brix' ); ?></h3>

		<div class="woocommerce-billing-fields__field-wrapper">
			<?php foreach ( $brix_contact as $brix_key => $brix_field ) : ?>
				<?php woocommerce_form_field( $brix_key, $brix_field, $checkout->get_value( $brix_key ) ); ?>
			<?php endforeach; ?>
		</div>
	</section>

	<?php if ( $brix_address ) : ?>
		<section class="brix-checkout__block brix-checkout__block--address" id="brix-address" <?php echo brix_pickup_chosen() ? 'hidden' : ''; ?>>
			<h3><?php esc_html_e( 'Куди доставити', 'brix' ); ?></h3>

			<div class="woocommerce-billing-fields__field-wrapper">
				<?php foreach ( $brix_address as $brix_key => $brix_field ) : ?>
					<?php woocommerce_form_field( $brix_key, $brix_field, $checkout->get_value( $brix_key ) ); ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php do_action( 'woocommerce_after_checkout_billing_form', $checkout ); ?>
</div>

<?php if ( ! is_user_logged_in() && $checkout->is_registration_enabled() ) : ?>
	<div class="woocommerce-account-fields">
		<?php if ( $checkout->is_registration_required() ) : ?>
			<?php
			/*
			 * Обов'язковим кабінет стає лише з підпискою BRIX Club у
			 * кошику: без нього підписку нема як вести. Мовчки
			 * реєструвати людину не годиться — кажемо, навіщо.
			 */
			?>
			<p class="brix-account-note">
				<?php esc_html_e( 'Для підписки BRIX Club створимо кабінет на вашу пошту — там пауза, пропуск і скасування. Пароль задасте за посиланням із листа.', 'brix' ); ?>
			</p>
		<?php else : ?>
			<p class="form-row form-row-wide create-account">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
					<input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" id="createaccount" <?php checked( ( true === $checkout->get_value( 'createaccount' ) || ( true === apply_filters( 'woocommerce_create_account_default_checked', false ) ) ), true ); ?> type="checkbox" name="createaccount" value="1" />
					<span><?php esc_html_e( 'Створити кабінет — бачитимете статус замовлення й зможете повторити його одним дотиком', 'brix' ); ?></span>
				</label>
			</p>
		<?php endif; ?>

		<?php do_action( 'woocommerce_before_checkout_registration_form', $checkout ); ?>

		<?php if ( $checkout->get_checkout_fields( 'account' ) ) : ?>
			<div class="create-account">
				<?php foreach ( $checkout->get_checkout_fields( 'account' ) as $brix_key => $brix_field ) : ?>
					<?php woocommerce_form_field( $brix_key, $brix_field, $checkout->get_value( $brix_key ) ); ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php do_action( 'woocommerce_after_checkout_registration_form', $checkout ); ?>
	</div>
<?php endif; ?>
