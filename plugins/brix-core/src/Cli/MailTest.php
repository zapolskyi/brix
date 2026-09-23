<?php
/**
 * WP-CLI: перевірка пошти.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Cli;

use Brix\Core\Mail\Brevo;

defined( 'ABSPATH' ) || exit;

/**
 * Надсилає тестовий лист тим самим шляхом, що й листи магазину.
 */
final class MailTest {

	/**
	 * Надсилає тестовий лист.
	 *
	 * ## OPTIONS
	 *
	 * <to>
	 * : Кому.
	 *
	 * ## EXAMPLES
	 *
	 *     wp brix mail-test you@example.org
	 *
	 * @param array<int, string>    $args       Позиційні аргументи.
	 * @param array<string, string> $assoc_args Іменовані аргументи.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		unset( $assoc_args );

		$to = sanitize_email( (string) ( $args[0] ?? '' ) );

		if ( ! is_email( $to ) ) {
			\WP_CLI::error( 'Вкажіть адресу: wp brix mail-test you@domain.com' );
		}

		if ( ! Brevo::configured() ) {
			\WP_CLI::warning( 'BRIX_BREVO_KEY і BRIX_BREVO_SENDER не задані у wp-config.php — лист піде поштою хостингу.' );
		} elseif ( Brevo::dead( $to ) ) {
			\WP_CLI::warning( 'Це тестовий домен, пошти він не приймає — лист піде поштою хостингу, повз Brevo.' );
		}

		$mailer = WC()->mailer();
		$title  = 'Пошта BRIX 22° працює';
		$body   = '<p>Цей лист пройшов тим самим шляхом, що й листи замовлень, кабінету й розсилки.</p>';

		$ok = $mailer->send( $to, $title, $mailer->wrap_message( $title, $body ), "Content-Type: text/html\r\n" );

		if ( $ok ) {
			\WP_CLI::success( sprintf( 'Лист надіслано на %s.', $to ) );
			return;
		}

		\WP_CLI::error( 'Лист не пішов. Деталі — WooCommerce → Стан → Журнали, джерело brix-brevo.' );
	}
}
