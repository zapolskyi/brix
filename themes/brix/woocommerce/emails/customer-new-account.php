<?php
/**
 * Лист «Кабінет створено».
 *
 * Override шаблону WooCommerce. Стандартний лист вітався логіном —
 * «Привіт, reg-test!» — і показував його як «ім'я користувача», хоча
 * логін покупець не обирав і не знає: WooCommerce склав його з пошти.
 * Увійти тут можна поштою, тож про логін лист мовчить, а головна дія
 * — задати пароль — стоїть кнопкою.
 *
 * @package BRIX
 * @version 10.9.0
 *
 * @var string   $email_heading      Заголовок.
 * @var string   $user_login         Логін.
 * @var bool     $password_generated Чи пароль згенеровано.
 * @var string   $set_password_url   Посилання, щоб задати пароль.
 * @var string   $additional_content Додатковий текст із налаштувань.
 * @var WC_Email $email              Лист.
 */

defined( 'ABSPATH' ) || exit;

$brix_user = get_user_by( 'login', $user_login );
$brix_name = $brix_user ? trim( (string) $brix_user->first_name ) : '';

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p>
	<?php
	echo '' !== $brix_name
		/* translators: %s — ім'я покупця. */
		? esc_html( sprintf( __( 'Привіт, %s!', 'brix' ), $brix_name ) )
		: esc_html__( 'Вітаємо!', 'brix' );
	?>
</p>

<p><?php esc_html_e( 'Кабінет у BRIX 22° створено. Там історія замовлень і повтор одним дотиком, підписка BRIX Club і ваш профіль смаку.', 'brix' ); ?></p>

<?php if ( $password_generated && $set_password_url ) : ?>
	<p><?php esc_html_e( 'Залишилось задати пароль:', 'brix' ); ?></p>
	<p>
		<a href="<?php echo esc_url( $set_password_url ); ?>" style="display:inline-block;padding:12px 24px;border-radius:999px;background:#B61F3A;color:#ffffff;text-decoration:none;font-weight:600">
			<?php esc_html_e( 'Задати пароль', 'brix' ); ?>
		</a>
	</p>
<?php endif; ?>

<p>
	<?php
	printf(
		/* translators: %s — пошта покупця. */
		esc_html__( 'Входити можна поштою %s — логін запам’ятовувати не треба.', 'brix' ),
		'<strong>' . esc_html( $brix_user ? $brix_user->user_email : $user_login ) . '</strong>'
	);
	?>
</p>

<p><a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><?php esc_html_e( 'Відкрити кабінет', 'brix' ); ?></a></p>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
