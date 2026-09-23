<?php
/**
 * Дві мови сайту: українська за замовчуванням, англійська під /en/.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\I18n;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Мова запиту й адреси з префіксом.
 *
 * Уся хитрість тут в одному фільтрі — `home_url`. WordPress будує з
 * нього майже кожну адресу на сайті: посилання на товари, пагінацію,
 * дії форм, канонічні адреси, навіть маршрути REST. Варто додати до
 * нього «/en», і префікс з'являється всюди сам, без окремого фільтра
 * на кожен випадок.
 *
 * Розбір запиту працює з того самого місця. `WP::parse_request()`
 * відрізає від адреси шлях `home_url()`, перш ніж шукати правило
 * перезапису, тож «/en/shop/» перетворюється на «shop/» і далі
 * маршрутизується як завжди. Власних правил перезапису не потрібно
 * жодного.
 *
 * Мова визначається рівно один раз за запит і тільки на фронтенді:
 * в адмінці, у WP-CLI й у крон-завданнях сайт лишається українським,
 * інакше редактор бачив би переклади там, де править оригінал.
 */
final class Language implements Module {

	/**
	 * Мова за замовчуванням.
	 *
	 * @var string
	 */
	public const MAIN = 'uk';

	/**
	 * Друга мова.
	 *
	 * @var string
	 */
	public const SECOND = 'en';

	/**
	 * Локалі WordPress для наших мов.
	 *
	 * @var array<string, string>
	 */
	private const LOCALES = array(
		self::MAIN   => 'uk',
		self::SECOND => 'en_US',
	);

	/**
	 * Мова поточного запиту. Рахується один раз.
	 *
	 * @var string|null
	 */
	private static ?string $current = null;

	/**
	 * Мова, нав'язана поверх запиту.
	 *
	 * Потрібна там, де мову визначає не адреса, а дані: лист про
	 * замовлення надсилає крон, і адреси запиту в нього немає зовсім.
	 *
	 * @var string|null
	 */
	private static ?string $override = null;

	/**
	 * Вішає хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'locale', array( $this, 'locale' ) );
		add_filter( 'determine_locale', array( $this, 'locale' ) );
		add_filter( 'home_url', array( $this, 'home_url' ), 10, 4 );
		add_filter( 'wp_setup_nav_menu_item', array( $this, 'menu_url' ) );
		add_filter( 'the_content', array( $this, 'content_links' ), 20 );
		add_action( 'wp_head', array( $this, 'alternates' ), 1 );
	}

	/**
	 * Мова поточного запиту.
	 *
	 * @return string
	 */
	public static function current(): string {
		return self::$current ??= self::detect();
	}

	/**
	 * Локаль WordPress для мови сайту.
	 *
	 * @param string $lang Код мови.
	 * @return string
	 */
	public static function locale_of( string $lang ): string {
		return self::LOCALES[ $lang ] ?? self::LOCALES[ self::MAIN ];
	}

	/**
	 * Мова, якою зараз малюється сторінка або лист.
	 *
	 * @return string
	 */
	public static function active(): string {
		return self::$override ?? self::current();
	}

	/**
	 * Нав'язує мову поверх запиту або знімає нав'язану.
	 *
	 * @param string|null $lang Код мови або null.
	 * @return void
	 */
	public static function use( ?string $lang ): void {
		self::$override = self::SECOND === $lang ? self::SECOND : ( self::MAIN === $lang ? self::MAIN : null );
	}

	/**
	 * Чи англійська зараз.
	 *
	 * @return bool
	 */
	public static function is_second(): bool {
		return self::SECOND === self::active();
	}

	/**
	 * Усі мови сайту: код => підпис.
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
		return array(
			self::MAIN   => 'UA',
			self::SECOND => 'EN',
		);
	}

	/**
	 * Поточна сторінка іншою мовою.
	 *
	 * Перекладу сторінки може не бути — адреса все одно існує, бо
	 * структура шляхів у двох мов однакова. Так перемикач ніколи не
	 * веде в нікуди.
	 *
	 * @param string $lang Код мови.
	 * @return string
	 */
	public static function switch_url( string $lang ): string {
		$uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '/';

		$path  = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$query = (string) wp_parse_url( $uri, PHP_URL_QUERY );
		$base  = self::base_path();
		$rest  = self::strip_prefix( self::strip_base( $path ) );

		// Кінцевий слеш зберігається: без нього WordPress відповів би
		// перенаправленням на ту саму адресу зі слешем, і перемикач
		// мови коштував би зайвого запиту.
		if ( '' !== $rest && '/' === substr( $path, -1 ) ) {
			$rest .= '/';
		}

		$prefix = self::SECOND === $lang ? '/' . self::SECOND : '';
		$out    = self::origin() . $base . $prefix . ( '' === $rest ? '/' : '/' . $rest );

		return '' === $query ? $out : $out . '?' . $query;
	}

	/**
	 * Локаль під поточну мову.
	 *
	 * @param string $locale Локаль WordPress.
	 * @return string
	 */
	public function locale( string $locale ): string {
		return self::is_second() ? self::LOCALES[ self::SECOND ] : $locale;
	}

	/**
	 * Додає префікс мови до адрес сайту.
	 *
	 * @param string      $url    Готова адреса.
	 * @param string      $path   Шлях, переданий у home_url().
	 * @param string|null $scheme Схема.
	 * @param int|null    $blog   Блог у мережі.
	 * @return string
	 */
	public function home_url( string $url, string $path, $scheme, $blog ): string {
		unset( $path, $blog );

		if ( ! self::is_second() ) {
			return $url;
		}

		if ( 'relative' === $scheme ) {
			return '/' . self::SECOND . '/' . ltrim( $url, '/' );
		}

		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) ) {
			return $url;
		}

		$rest = self::strip_base( (string) ( $parts['path'] ?? '' ) );

		// Префікс уже на місці — WordPress подекуди пропускає адресу
		// через home_url() двічі.
		if ( self::SECOND === $rest || 0 === strpos( $rest, self::SECOND . '/' ) ) {
			return $url;
		}

		// Кінцевий слеш зберігається: без нього WordPress відповідає
		// перенаправленням на ту саму адресу зі слешем, і кожен пункт
		// меню коштував би зайвого запиту.
		$path = (string) ( $parts['path'] ?? '' );

		if ( '' !== $rest && '/' === substr( $path, -1 ) ) {
			$rest .= '/';
		}

		$out = self::origin( $parts ) . self::base_path() . '/' . self::SECOND
			. ( '' === $rest ? '/' : '/' . $rest );

		if ( isset( $parts['query'] ) ) {
			$out .= '?' . $parts['query'];
		}

		if ( isset( $parts['fragment'] ) ) {
			$out .= '#' . $parts['fragment'];
		}

		return $out;
	}

	/**
	 * Адреса пункту меню, якщо вона записана шляхом.
	 *
	 * Пункти-сторінки й пункти-категорії WordPress перебудовує на
	 * кожен показ через get_permalink(), тож префікс мови вони
	 * отримують самі. А «довільне посилання» зберігає адресу в базі
	 * як є — і «/farms/» з англійської сторінки вело назад на
	 * українську. Саме так губилася мова на «Виробниках» і «Гайдах».
	 *
	 * @param \WP_Post|mixed $item Пункт меню.
	 * @return \WP_Post|mixed
	 */
	public function menu_url( $item ) {
		if ( ! self::is_second() || ! isset( $item->url ) || ! is_string( $item->url ) ) {
			return $item;
		}

		$item->url = self::localize( $item->url );

		return $item;
	}

	/**
	 * Посилання всередині тексту сторінки.
	 *
	 * У редакторі посилання на свою ж сторінку пишуть шляхом:
	 * «/about/», «/wholesale/». Під /en/ такий шлях веде на
	 * українську версію — текст англійський, а сторінка за
	 * посиланням ні.
	 *
	 * @param string $content Текст.
	 * @return string
	 */
	public function content_links( $content ): string {
		$content = (string) $content;

		if ( ! self::is_second() || false === strpos( $content, 'href="/' ) ) {
			return $content;
		}

		return (string) preg_replace_callback(
			'~href="(/[^"/][^"]*)"~',
			static fn( array $m ): string => 'href="' . esc_url( self::localize( $m[1] ) ) . '"',
			$content
		);
	}

	/**
	 * Додає префікс мови до шляху всередині сайту.
	 *
	 * Зовнішні адреси, протокольні посилання й те, де префікс уже є,
	 * лишаються як були.
	 *
	 * @param string $url Адреса або шлях.
	 * @return string
	 */
	public static function localize( string $url ): string {
		$url = trim( $url );

		if ( '' === $url || 0 !== strpos( $url, '/' ) || 0 === strpos( $url, '//' ) ) {
			return $url;
		}

		$rest = self::strip_base( (string) wp_parse_url( $url, PHP_URL_PATH ) );

		if ( self::SECOND === $rest || 0 === strpos( $rest, self::SECOND . '/' ) ) {
			return $url;
		}

		return home_url( $url );
	}

	/**
	 * Посилання на другу мовну версію сторінки.
	 *
	 * @return void
	 */
	public function alternates(): void {
		foreach ( array_keys( self::all() ) as $lang ) {
			printf(
				'<link rel="alternate" hreflang="%1$s" href="%2$s">' . "\n",
				esc_attr( self::MAIN === $lang ? 'uk' : 'en' ),
				esc_url( self::switch_url( $lang ) )
			);
		}

		printf(
			'<link rel="alternate" hreflang="x-default" href="%s">' . "\n",
			esc_url( self::switch_url( self::MAIN ) )
		);
	}

	/**
	 * Визначає мову з адреси запиту.
	 *
	 * @return string
	 */
	private static function detect(): string {
		if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return self::MAIN;
		}

		$uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';

		$rest = self::strip_base( (string) wp_parse_url( $uri, PHP_URL_PATH ) );

		return ( self::SECOND === $rest || 0 === strpos( $rest, self::SECOND . '/' ) )
			? self::SECOND
			: self::MAIN;
	}

	/**
	 * Шлях, за яким стоїть WordPress: порожній рядок або «/підтека».
	 *
	 * Читається з опції, а не з home_url(), бо home_url() ми ж і
	 * фільтруємо — виклик звідси зациклив би сам себе.
	 *
	 * @return string
	 */
	private static function base_path(): string {
		$path = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH );

		return rtrim( $path, '/' );
	}

	/**
	 * Початок адреси: схема й хост.
	 *
	 * @param array<string, mixed>|null $parts Розібрана адреса, якщо є.
	 * @return string
	 */
	private static function origin( ?array $parts = null ): string {
		$parts ??= (array) wp_parse_url( (string) get_option( 'home' ) );

		if ( empty( $parts['host'] ) ) {
			return '';
		}

		$out = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '//';
		$out .= $parts['host'];

		return isset( $parts['port'] ) ? $out . ':' . $parts['port'] : $out;
	}

	/**
	 * Прибирає з шляху теку, у якій стоїть WordPress.
	 *
	 * @param string $path Шлях.
	 * @return string
	 */
	private static function strip_base( string $path ): string {
		$base = self::base_path();
		$path = trim( $path, '/' );

		if ( '' === $base ) {
			return $path;
		}

		$base = trim( $base, '/' );

		return 0 === strpos( $path, $base ) ? trim( substr( $path, strlen( $base ) ), '/' ) : $path;
	}

	/**
	 * Прибирає з шляху префікс мови.
	 *
	 * @param string $path Шлях без теки WordPress.
	 * @return string
	 */
	private static function strip_prefix( string $path ): string {
		if ( self::SECOND === $path ) {
			return '';
		}

		return 0 === strpos( $path, self::SECOND . '/' )
			? substr( $path, strlen( self::SECOND ) + 1 )
			: $path;
	}
}
