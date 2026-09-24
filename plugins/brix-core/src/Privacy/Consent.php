<?php
/**
 * Згода на аналітичні cookies.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Privacy;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Питає дозволу, перш ніж вмикати аналітику.
 *
 * Кошик, вхід і обрана валюта тримаються на cookies, без яких сайт не
 * працює, — на них згода не потрібна. Google Analytics — інша справа:
 * вона ставить свої cookies й передає дані в Google, тож без «так»
 * відвідувача вмикати її не можна.
 *
 * Поки аналітика не налаштована, модуль мовчить: питати про те, чого
 * немає, — лише заважати.
 *
 * Відповідь — звичайна форма, що відправляється на ту саму сторінку,
 * тож працює й без JavaScript.
 */
final class Consent implements Module {

	/**
	 * Cookie з відповіддю.
	 *
	 * @var string
	 */
	public const COOKIE = 'brix_consent';

	/**
	 * Поле форми з відповіддю.
	 *
	 * @var string
	 */
	public const FIELD = 'brix_consent';

	/**
	 * Параметр адреси, що знову показує питання.
	 *
	 * @var string
	 */
	public const ASK = 'cookies';

	/**
	 * Відповідь, дана в цьому ж запиті.
	 *
	 * @var string|null
	 */
	private static ?string $answer = null;

	/**
	 * Вішає хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'handle' ), 1 );
		add_filter( 'litespeed_vary_cookies', array( $this, 'vary' ) );
	}

	/**
	 * Чи є на сайті щось, на що треба питати згоди.
	 *
	 * @return bool
	 */
	public static function needed(): bool {
		/**
		 * Чи потрібна згода на cookies.
		 *
		 * @param bool $needed За замовчуванням — коли задано ідентифікатор GA4.
		 */
		return (bool) apply_filters( 'brix_consent_needed', '' !== (string) get_option( 'brix_ga4_id', '' ) );
	}

	/**
	 * Відповідь відвідувача: yes, no або порожньо.
	 *
	 * @return string
	 */
	public static function answer(): string {
		if ( null !== self::$answer ) {
			return self::$answer;
		}

		$value = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';

		return in_array( $value, array( 'yes', 'no' ), true ) ? $value : '';
	}

	/**
	 * Чи можна вмикати аналітику.
	 *
	 * @return bool
	 */
	public static function granted(): bool {
		return self::needed() && 'yes' === self::answer();
	}

	/**
	 * Чи показувати питання.
	 *
	 * Показуємо, поки відповіді немає, і ще раз — за посиланням
	 * «Налаштування cookies» у підвалі: відкликати згоду має бути так
	 * само просто, як дати.
	 *
	 * @return bool
	 */
	public static function asking(): bool {
		if ( ! self::needed() ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Лише показує питання.
		return '' === self::answer() || isset( $_GET[ self::ASK ] );
	}

	/**
	 * Адреса, на яку відправляється форма.
	 *
	 * @return string
	 */
	public static function action_url(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		return remove_query_arg( self::ASK, $uri );
	}

	/**
	 * Адреса, що знову показує питання.
	 *
	 * @return string
	 */
	public static function ask_url(): string {
		return add_query_arg( self::ASK, '1' );
	}

	/**
	 * Записує відповідь і повертає на ту саму сторінку.
	 *
	 * Замість nonce — перевірка, що форму відправили з цього ж сайту.
	 * Nonce у сторінці, яку віддав кеш, за добу застаріває, і людина
	 * натискала б «Так» без жодного результату. А згода, яку чужий
	 * сайт поставив би відвідувачу своєю формою, — вже не згода.
	 *
	 * @return void
	 */
	public function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Джерело форми перевіряє same_origin().
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST[ self::FIELD ] ) ) {
			return;
		}

		$value = sanitize_key( wp_unslash( $_POST[ self::FIELD ] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! in_array( $value, array( 'yes', 'no' ), true ) ) {
			return;
		}

		if ( self::same_origin() ) {
			self::$answer = $value;

			setcookie(
				self::COOKIE,
				$value,
				array(
					'expires'  => time() + 180 * DAY_IN_SECONDS,
					'path'     => COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);

			// Відкликали згоду — прибираємо й те, що GA вже поставила.
			if ( 'no' === $value ) {
				$this->forget_analytics();
			}
		}

		wp_safe_redirect( self::action_url(), 303 );
		exit;
	}

	/**
	 * Чи прийшла форма з цього сайту.
	 *
	 * Браузер сам ставить Origin на кожен POST, підробити його зі
	 * сторінки не можна. Старі браузери, що його не шлють, мають
	 * Referer.
	 *
	 * @return bool
	 */
	private static function same_origin(): bool {
		$source = '';

		if ( ! empty( $_SERVER['HTTP_ORIGIN'] ) ) {
			$source = esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$source = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}

		$host = wp_parse_url( $source, PHP_URL_HOST );

		return is_string( $host ) && wp_parse_url( home_url(), PHP_URL_HOST ) === $host;
	}

	/**
	 * Стирає cookies Google Analytics.
	 *
	 * @return void
	 */
	private function forget_analytics(): void {
		$host   = wp_parse_url( home_url(), PHP_URL_HOST );
		$domain = is_string( $host ) ? '.' . preg_replace( '/^www\./', '', $host ) : '';

		foreach ( array_keys( $_COOKIE ) as $name ) {
			if ( '_ga' !== $name && 0 !== strpos( (string) $name, '_ga_' ) ) {
				continue;
			}

			foreach ( array( $domain, '' ) as $scope ) {
				setcookie( (string) $name, '', time() - YEAR_IN_SECONDS, '/', $scope );
			}
		}
	}

	/**
	 * Різні відповіді — різні копії сторінки в кеші LiteSpeed.
	 *
	 * @param mixed $cookies Cookies, від яких залежить сторінка.
	 * @return array<int, string>
	 */
	public function vary( $cookies ) {
		$cookies   = is_array( $cookies ) ? $cookies : array();
		$cookies[] = self::COOKIE;

		return $cookies;
	}
}
