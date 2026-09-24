<?php
/**
 * Питання про аналітичні cookies.
 *
 * З'являється, лише коли на сайті ввімкнена Google Analytics, і тільки
 * доки відвідувач не відповів. Обидві кнопки однакової ваги: «ні» не
 * ховається в дрібний сірий текст — інакше це вже не вибір.
 *
 * Звичайна форма без JavaScript: відповідь іде на ту саму сторінку,
 * а сервер записує її й повертає назад.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\Brix\Core\Privacy\Consent' ) || ! \Brix\Core\Privacy\Consent::asking() ) {
	return;
}

$brix_privacy = get_privacy_policy_url();
?>
<section class="brix-consent" id="brix-consent" aria-labelledby="brix-consent-title">
	<h2 class="brix-consent__title" id="brix-consent-title"><?php esc_html_e( 'Cookies для статистики', 'brix' ); ?></h2>
	<p class="brix-consent__text">
		<?php esc_html_e( 'Кошик і вхід працюють і без вашої згоди. Дозвольте Google Analytics рахувати відвідування — так ми бачимо, які сторінки корисні. Відповідь можна змінити в підвалі сайту.', 'brix' ); ?>
		<?php if ( $brix_privacy ) : ?>
			<a href="<?php echo esc_url( $brix_privacy ); ?>"><?php esc_html_e( 'Докладніше', 'brix' ); ?></a>
		<?php endif; ?>
	</p>
	<form class="brix-consent__actions" method="post" action="<?php echo esc_url( \Brix\Core\Privacy\Consent::action_url() ); ?>">
		<button class="brix-btn brix-btn--sm brix-btn--light" type="submit" name="brix_consent" value="yes"><?php esc_html_e( 'Дозволити', 'brix' ); ?></button>
		<button class="brix-btn brix-btn--sm brix-btn--light" type="submit" name="brix_consent" value="no"><?php esc_html_e( 'Відмовитись', 'brix' ); ?></button>
	</form>
</section>
