<?php
/**
 * Базовий захист сайту.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Security;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Те, що WordPress з коробки лишає відчиненим.
 *
 * Жодне з цього не замінює оновлень і сильного пароля — це лише
 * прибирає дрібні щілини: логін адміністратора, який видно за
 * ?author=1, XML-RPC для перебору паролів, сторінку в чужому
 * iframe, версію PHP у заголовках.
 */
final class Hardening implements Module {

	/**
	 * Вішає хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		/*
		 * Редактор файлів в адмінці — готовий бекдор для того, хто
		 * підібрав пароль адміністратора. Код тут змінюється тільки
		 * через git і архів. Константа, задана в wp-config.php, має
		 * перевагу.
		 */
		if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Константа ядра WordPress.
		}

		add_action( 'send_headers', array( $this, 'headers' ) );

		/*
		 * XML-RPC сайтові не потрібен: ні мобільного застосунку, ні
		 * пінгбеків. А перебирати через нього паролі зручно — сотні
		 * спроб в одному запиті. Фільтр xmlrpc_enabled вимикає лише
		 * методи з паролем, а system.multicall лишається, тож xmlrpc.php
		 * просто відповідає 403.
		 */
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', '__return_empty_array' );
		add_action( 'init', array( $this, 'refuse_xmlrpc' ), 0 );
		add_filter( 'wp_headers', array( $this, 'drop_pingback' ) );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wp_generator' );

		add_action( 'template_redirect', array( $this, 'hide_authors' ), 0 );
		add_filter( 'rest_endpoints', array( $this, 'hide_users_endpoint' ) );
		add_filter( 'oembed_response_data', array( $this, 'hide_oembed_author' ) );
		add_filter( 'wp_sitemaps_add_provider', array( $this, 'drop_users_sitemap' ), 10, 2 );
	}

	/**
	 * Заголовки безпеки для публічних сторінок.
	 *
	 * @return void
	 */
	public function headers(): void {
		if ( headers_sent() ) {
			return;
		}

		header_remove( 'X-Powered-By' );

		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()' );

		/*
		 * HSTS — лише через HTTPS: заголовок, надісланий по HTTP,
		 * браузер ігнорує, а локальний стенд без сертифіката ним би
		 * зламався. Без includeSubDomains: сайт живе на субдомені, і
		 * рішення за сусідні домени приймати не нам.
		 */
		if ( is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=31536000' );
		}
	}

	/**
	 * Відмовляє будь-якому запиту до xmlrpc.php.
	 *
	 * @return void
	 */
	public function refuse_xmlrpc(): void {
		if ( ! defined( 'XMLRPC_REQUEST' ) || ! XMLRPC_REQUEST ) {
			return;
		}

		status_header( 403 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo 'XML-RPC disabled.';
		exit;
	}

	/**
	 * Прибирає X-Pingback: він рекламує вимкнений XML-RPC.
	 *
	 * @param array<string, string> $headers Заголовки.
	 * @return array<string, string>
	 */
	public function drop_pingback( array $headers ): array {
		unset( $headers['X-Pingback'] );

		return $headers;
	}

	/**
	 * Закриває архіви авторів.
	 *
	 * /?author=1 перенаправляє на /author/<логін>/ — так будь-хто
	 * дізнається логін адміністратора, половину пари для входу.
	 * Авторських сторінок у магазині немає, тож це просто 404.
	 *
	 * @return void
	 */
	public function hide_authors(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Лише читаємо, чи є параметр.
		if ( ! is_author() && ! isset( $_GET['author'] ) ) {
			return;
		}

		if ( is_admin() || current_user_can( 'list_users' ) ) {
			return;
		}

		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Ховає список користувачів у REST від гостей.
	 *
	 * /wp-json/wp/v2/users віддає логіни всіх, хто щось публікував.
	 * Редакторові блоків він потрібен, тож для тих, хто увійшов,
	 * лишається.
	 *
	 * @param array<string, mixed> $endpoints Маршрути.
	 * @return array<string, mixed>
	 */
	public function hide_users_endpoint( array $endpoints ): array {
		if ( is_user_logged_in() ) {
			return $endpoints;
		}

		foreach ( array_keys( $endpoints ) as $route ) {
			if ( 0 === strpos( (string) $route, '/wp/v2/users' ) ) {
				unset( $endpoints[ $route ] );
			}
		}

		return $endpoints;
	}

	/**
	 * Прибирає автора з oEmbed — там теж його логін.
	 *
	 * @param array<string, mixed> $data Дані.
	 * @return array<string, mixed>
	 */
	public function hide_oembed_author( array $data ): array {
		unset( $data['author_name'], $data['author_url'] );

		return $data;
	}

	/**
	 * Прибирає користувачів із карти сайту.
	 *
	 * @param mixed  $provider Постачальник.
	 * @param string $name     Назва.
	 * @return mixed
	 */
	public function drop_users_sitemap( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	}
}
