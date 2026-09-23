<?php
/**
 * Пошта сайту через Brevo.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Mail;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Відправляє всі листи сайту через транзакційний API Brevo.
 *
 * PHP-пошта шаред-хостингу — найкоротший шлях у спам: сервер без
 * репутації, без SPF і DKIM для домену відправника. А лист
 * «замовлення прийнято» чи «задайте пароль», що не дійшов, ламає всю
 * покупку. Brevo відправляє з підписаних серверів із репутацією.
 *
 * Перехоплення — фільтр pre_wp_mail: через wp_mail() ідуть і листи
 * WooCommerce, і листи WordPress, і наші (розсилка, Club), тож одна
 * точка покриває все. SMTP-плагін не потрібен.
 *
 * Налаштування — константи у wp-config.php, а не опції в базі: ключ
 * API не має потрапляти ні в репозиторій, ні в бекап бази.
 *
 *     define( 'BRIX_BREVO_KEY', 'xkeysib-…' );
 *     define( 'BRIX_BREVO_SENDER', 'адреса, підтверджена в Brevo' );
 *
 * Без ключа модуль мовчить, і WordPress шле як завжди.
 */
final class Brevo implements Module {

	/**
	 * Точка API.
	 */
	private const API = 'https://api.brevo.com/v3/smtp/email';

	/**
	 * Домени, що не приймають пошти за стандартом (RFC 2606, 6761).
	 *
	 * Демо-акаунти й тестові замовлення живуть саме на них. Лист на
	 * таку адресу Brevo прийме, спише з ліміту і отримає відмову, а
	 * відмови псують репутацію відправника. Такі листи йдуть звичайним
	 * шляхом — локально їх ловить Mailpit.
	 */
	private const DEAD_DOMAINS = array( 'example.com', 'example.org', 'example.net' );

	/**
	 * Зони, що не приймають пошти.
	 */
	private const DEAD_ZONES = array( '.local', '.test', '.example', '.invalid', '.localhost' );

	/**
	 * Вішає хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! self::configured() ) {
			return;
		}

		add_filter( 'pre_wp_mail', array( $this, 'send' ), 10, 2 );
	}

	/**
	 * Чи задано ключ і відправника.
	 *
	 * @return bool
	 */
	public static function configured(): bool {
		return defined( 'BRIX_BREVO_KEY' ) && '' !== (string) BRIX_BREVO_KEY
			&& defined( 'BRIX_BREVO_SENDER' ) && is_email( (string) BRIX_BREVO_SENDER );
	}

	/**
	 * Відправляє лист замість wp_mail().
	 *
	 * Повертає true або false — і WordPress далі не йде. Повертає null,
	 * коли лист має піти звичайним шляхом: адресат на тестовому домені
	 * або Brevo не відповів. В останньому випадку краще лист через
	 * пошту хостингу, ніж жодного.
	 *
	 * @param null|bool            $short Попередній результат фільтра.
	 * @param array<string, mixed> $atts  to, subject, message, headers, attachments.
	 * @return null|bool
	 */
	public function send( $short, $atts ) {
		if ( null !== $short || ! is_array( $atts ) ) {
			return $short;
		}

		$to = $this->addresses( $atts['to'] ?? array() );

		if ( ! $to ) {
			return null;
		}

		$headers = $this->headers( $atts['headers'] ?? array() );
		$body    = (string) ( $atts['message'] ?? '' );
		$html    = false !== stripos( $headers['content-type'], 'text/html' )
			|| 'text/html' === apply_filters( 'wp_mail_content_type', 'text/plain' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Фільтр ядра.

		$payload = array(
			'sender'  => array(
				'email' => (string) BRIX_BREVO_SENDER,
				'name'  => $this->sender_name( $headers['from'] ),
			),
			'to'      => $to,
			'subject' => wp_specialchars_decode( (string) ( $atts['subject'] ?? '' ), ENT_QUOTES ),
			'tags'    => array( 'brix-site' ),
		);

		$payload[ $html ? 'htmlContent' : 'textContent' ] = '' !== $body ? $body : ' ';

		// Відповідь на лист повинна кудись дійти: або на адресу з
		// Reply-To (у листі про нове замовлення — пошта покупця), або
		// на підтверджену адресу відправника.
		$reply              = $this->addresses( $headers['reply-to'] );
		$payload['replyTo'] = $reply ? $reply[0] : array( 'email' => (string) BRIX_BREVO_SENDER );
		$cc                 = $this->addresses( $headers['cc'] );
		$bcc                = $this->addresses( $headers['bcc'] );
		$files              = $this->attachments( $atts['attachments'] ?? array() );

		if ( $cc ) {
			$payload['cc'] = $cc;
		}

		if ( $bcc ) {
			$payload['bcc'] = $bcc;
		}

		if ( $files ) {
			$payload['attachment'] = $files;
		}

		$response = wp_remote_post(
			self::API,
			array(
				'timeout' => 15,
				'headers' => array(
					'api-key'      => (string) BRIX_BREVO_KEY,
					'accept'       => 'application/json',
					'content-type' => 'application/json',
				),
				'body'    => (string) wp_json_encode( $payload ),
			)
		);

		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		$reason = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response );
		$this->log( sprintf( 'Brevo не прийняв лист «%s» (%d): %s', $payload['subject'], $code, $reason ) );

		return null;
	}

	/**
	 * Адресати у форматі Brevo, без тестових доменів.
	 *
	 * Якщо серед адресатів є тестовий, повертає порожньо — і лист іде
	 * звичайним шляхом цілком: ділити один лист між двома каналами
	 * означало б розсилати його двічі.
	 *
	 * @param mixed $raw Рядок через кому або масив.
	 * @return array<int, array<string, string>>
	 */
	private function addresses( $raw ): array {
		$list = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		$out  = array();

		foreach ( $list as $item ) {
			$item = trim( (string) $item );

			if ( '' === $item ) {
				continue;
			}

			$name  = '';
			$email = $item;

			if ( preg_match( '/^(.*)<(.+)>$/', $item, $m ) ) {
				$name  = trim( $m[1], " \t\"'" );
				$email = trim( $m[2] );
			}

			$email = sanitize_email( $email );

			if ( ! is_email( $email ) ) {
				continue;
			}

			if ( self::dead( $email ) ) {
				return array();
			}

			$out[] = '' !== $name ? array(
				'email' => $email,
				'name'  => $name,
			) : array( 'email' => $email );
		}

		return $out;
	}

	/**
	 * Чи адреса на домені, що не приймає пошти.
	 *
	 * @param string $email Адреса.
	 * @return bool
	 */
	public static function dead( string $email ): bool {
		$domain = strtolower( (string) substr( strrchr( $email, '@' ), 1 ) );

		if ( in_array( $domain, self::DEAD_DOMAINS, true ) ) {
			return true;
		}

		foreach ( self::DEAD_ZONES as $zone ) {
			if ( str_ends_with( $domain, $zone ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Розбирає заголовки листа.
	 *
	 * @param mixed $raw Рядок або масив рядків.
	 * @return array<string, string>
	 */
	private function headers( $raw ): array {
		$lines = is_array( $raw ) ? $raw : explode( "\n", str_replace( "\r\n", "\n", (string) $raw ) );
		$out   = array(
			'content-type' => '',
			'from'         => '',
			'reply-to'     => '',
			'cc'           => '',
			'bcc'          => '',
		);

		foreach ( $lines as $line ) {
			if ( false === strpos( (string) $line, ':' ) ) {
				continue;
			}

			list( $name, $value ) = array_map( 'trim', explode( ':', (string) $line, 2 ) );
			$name                 = strtolower( $name );

			if ( isset( $out[ $name ] ) ) {
				$out[ $name ] = '' === $out[ $name ] ? $value : $out[ $name ] . ',' . $value;
			}
		}

		return $out;
	}

	/**
	 * Ім'я відправника.
	 *
	 * Адреса завжди підтверджена в Brevo, а ім'я беремо те, яким лист
	 * підписав сам WooCommerce чи WordPress, — «BRIX 22°».
	 *
	 * @param string $from Заголовок From, якщо він був.
	 * @return string
	 */
	private function sender_name( string $from ): string {
		if ( preg_match( '/^(.*)<.+>$/', $from, $m ) && '' !== trim( $m[1], " \"'" ) ) {
			return trim( $m[1], " \"'" );
		}

		$name = (string) apply_filters( 'wp_mail_from_name', get_bloginfo( 'name' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Фільтр ядра.

		return '' !== $name && 'WordPress' !== $name ? $name : get_bloginfo( 'name' );
	}

	/**
	 * Вкладення в base64, як їх приймає Brevo.
	 *
	 * @param mixed $raw Шляхи до файлів.
	 * @return array<int, array<string, string>>
	 */
	private function attachments( $raw ): array {
		$out = array();

		foreach ( (array) $raw as $key => $path ) {
			if ( ! is_string( $path ) || ! is_readable( $path ) ) {
				continue;
			}

			$out[] = array(
				'name'    => is_string( $key ) ? $key : basename( $path ),
				'content' => base64_encode( (string) file_get_contents( $path ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Формат вкладень Brevo; локальний файл.
			);
		}

		return $out;
	}

	/**
	 * Пише збій у журнал WooCommerce (WooCommerce → Стан → Журнали).
	 *
	 * @param string $message Повідомлення.
	 * @return void
	 */
	private function log( string $message ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->warning( $message, array( 'source' => 'brix-brevo' ) );
		}
	}
}
