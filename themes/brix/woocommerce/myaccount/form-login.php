<?php
/**
 * Вхід і реєстрація.
 *
 * Override шаблону WooCommerce. Дві картки поруч: «Увійти» для тих,
 * хто вже купував, і «Новий покупець». Реєстрація просить лише пошту —
 * ім'я, телефон і адреса однаково прийдуть із першого замовлення, а
 * пароль WooCommerce надішле листом-посиланням.
 *
 * Хуки, імена полів і nonce — ті самі, що в оригіналі: обробляє форми
 * сам WooCommerce.
 *
 * @package BRIX
 * @version 9.9.0
 */

defined( 'ABSPATH' ) || exit;

$brix_register = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' );

do_action( 'woocommerce_before_customer_login_form' );
?>

<div class="brix-auth<?php echo $brix_register ? ' brix-auth--two' : ''; ?>" id="customer_login">

	<section class="brix-auth__card">
		<h2 class="brix-auth__title"><?php esc_html_e( 'Увійти', 'brix' ); ?></h2>

		<form class="woocommerce-form woocommerce-form-login login brix-auth__form" method="post" novalidate>
			<?php do_action( 'woocommerce_login_form_start' ); ?>

			<p class="brix-field">
				<label class="brix-field__label" for="username">
					<?php esc_html_e( 'Пошта або логін', 'brix' ); ?>
				</label>
				<input
					class="brix-input woocommerce-Input input-text"
					type="text"
					name="username"
					id="username"
					autocomplete="username"
					value="<?php echo ( ! empty( $_POST['username'] ) && is_string( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Повертаємо введене назад у поле, екрановано. ?>"
					required
				>
			</p>

			<p class="brix-field">
				<label class="brix-field__label" for="password"><?php esc_html_e( 'Пароль', 'brix' ); ?></label>
				<input class="brix-input woocommerce-Input input-text" type="password" name="password" id="password" autocomplete="current-password" required>
			</p>

			<?php do_action( 'woocommerce_login_form' ); ?>

			<p class="brix-auth__row">
				<label class="woocommerce-form__label-for-checkbox brix-auth__remember">
					<input name="rememberme" type="checkbox" id="rememberme" value="forever">
					<span><?php esc_html_e( 'Запам’ятати мене', 'brix' ); ?></span>
				</label>
				<a class="brix-auth__lost" href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Забули пароль?', 'brix' ); ?></a>
			</p>

			<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
			<button type="submit" class="brix-btn brix-btn--dark brix-auth__submit" name="login" value="<?php esc_attr_e( 'Увійти', 'brix' ); ?>">
				<?php esc_html_e( 'Увійти', 'brix' ); ?>
			</button>

			<?php do_action( 'woocommerce_login_form_end' ); ?>
		</form>
	</section>

	<?php if ( $brix_register ) : ?>
		<section class="brix-auth__card brix-auth__card--new">
			<h2 class="brix-auth__title"><?php esc_html_e( 'Новий покупець', 'brix' ); ?></h2>

			<ul class="brix-auth__perks">
				<li><?php esc_html_e( 'Історія замовлень і повтор одним дотиком', 'brix' ); ?></li>
				<li><?php esc_html_e( 'BRIX Club: пауза, пропуск і скасування підписки', 'brix' ); ?></li>
				<li><?php esc_html_e( 'Профіль смаку з квізу й помел під вашу техніку', 'brix' ); ?></li>
			</ul>

			<form method="post" class="woocommerce-form woocommerce-form-register register brix-auth__form" <?php do_action( 'woocommerce_register_form_tag' ); ?>>
				<?php do_action( 'woocommerce_register_form_start' ); ?>

				<?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
					<p class="brix-field">
						<label class="brix-field__label" for="reg_username"><?php esc_html_e( 'Логін', 'brix' ); ?></label>
						<input class="brix-input woocommerce-Input input-text" type="text" name="username" id="reg_username" autocomplete="username" required>
					</p>
				<?php endif; ?>

				<p class="brix-field">
					<label class="brix-field__label" for="reg_email"><?php esc_html_e( 'Пошта', 'brix' ); ?></label>
					<input
						class="brix-input woocommerce-Input input-text"
						type="email"
						name="email"
						id="reg_email"
						autocomplete="email"
						value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( sanitize_email( wp_unslash( $_POST['email'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Повертаємо введене назад у поле. ?>"
						required
					>
				</p>

				<?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
					<p class="brix-field">
						<label class="brix-field__label" for="reg_password"><?php esc_html_e( 'Пароль', 'brix' ); ?></label>
						<input class="brix-input woocommerce-Input input-text" type="password" name="password" id="reg_password" autocomplete="new-password" required>
					</p>
				<?php else : ?>
					<p class="brix-auth__note">
						<?php esc_html_e( 'Пароль задасте самі за посиланням із листа. Щойно задасте — в кабінеті з’являться й замовлення, які ви вже робили на цю пошту.', 'brix' ); ?>
					</p>
				<?php endif; ?>

				<?php do_action( 'woocommerce_register_form' ); ?>

				<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
				<button type="submit" class="brix-btn brix-auth__submit" name="register" value="<?php esc_attr_e( 'Створити кабінет', 'brix' ); ?>">
					<?php esc_html_e( 'Створити кабінет', 'brix' ); ?>
				</button>

				<?php do_action( 'woocommerce_register_form_end' ); ?>
			</form>
		</section>
	<?php endif; ?>
</div>

<?php do_action( 'woocommerce_after_customer_login_form' ); ?>
