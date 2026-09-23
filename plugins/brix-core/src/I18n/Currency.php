<?php
/**
 * Валюта другої мови: євро замість гривні.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\I18n;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Друга валюта: євро поруч із гривнею.
 *
 * Валюту покупець обирає сам, перемикачем «₴ / €» у шапці. Якщо не
 * обирав — вона випливає з мови: українська версія рахує в гривні,
 * англійська в євро. Вибір пам'ятає кука, тож він переживає зміну
 * мови й повернення на сайт.
 *
 * Спершу валюта була жорстко прив'язана до мови, і це ламалось на
 * живих людях. Англомовний експат у Києві отримував ціни в євро для
 * посилки Новою Поштою — і накладний платіж у євро, якого Нова Пошта
 * не приймає. Українка у Варшаві читала сайт українською й не могла
 * побачити ціну у валюті, якою платить. Мова — про те, як людина
 * читає; валюта — про те, чим вона платить. Це різні питання.
 *
 * Друга ціна ніде не зберігається. У базі лежить гривня, а євро
 * рахується на читанні за курсом з налаштувань — тож зміна курсу не
 * вимагає переписувати чотирнадцять товарів і дев'яносто варіацій.
 * Плата за це — копійки після ділення: 620 ₴ за курсом 48 дають
 * 12,92 €, і сума трьох таких пачок на цент розходиться з ціною,
 * перерахованою від 1 860 ₴. Для магазину, де євро — друга валюта,
 * це дешевше за окреме поле ціни в кожній варіації.
 *
 * Замовлення зберігає ту валюту, в якій його зробили: у полі
 * замовлення лежить EUR, і лист, адмінка й повернення показують євро
 * навіть тоді, коли решта сайту рахує в гривні.
 */
final class Currency implements Module {

	/**
	 * Опція з курсом: скільки гривень коштує євро.
	 *
	 * @var string
	 */
	public const OPTION = 'brix_eur_rate';

	/**
	 * Код другої валюти.
	 *
	 * @var string
	 */
	public const CODE = 'EUR';

	/**
	 * Основна валюта магазину.
	 *
	 * @var string
	 */
	public const MAIN = 'UAH';

	/**
	 * Кука з вибором покупця.
	 *
	 * @var string
	 */
	public const COOKIE = 'brix_currency';

	/**
	 * Параметр адреси, яким перемикач міняє валюту.
	 *
	 * @var string
	 */
	public const PARAM = 'currency';

	/**
	 * Курс за замовчуванням.
	 *
	 * @var float
	 */
	private const DEFAULT_RATE = 48.0;

	/**
	 * Пороги безкоштовної доставки в гривні, за екземплярами способу.
	 *
	 * Спосіб доставки створюється наново на кожне звернення до зони,
	 * тож прапорець «уже перерахував» на ньому не протримається.
	 * Натомість пам'ятаємо вихідне число: скільки б разів фільтр не
	 * спрацював, ділимо завжди гривні, а не вже поділене.
	 *
	 * @var array<int, float>
	 */
	private static array $thresholds = array();

	/**
	 * Валюта цього запиту, коли вже визначена.
	 *
	 * @var string|null
	 */
	private static ?string $chosen = null;

	/**
	 * Вішає хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_general_settings', array( $this, 'settings' ) );
		add_filter( 'wc_price_args', array( $this, 'price_args' ) );
		add_filter( 'litespeed_vary_cookies', array( $this, 'vary' ) );
		add_action( 'template_redirect', array( $this, 'clean_url' ), 1 );

		// Вибір із перемикача записуємо до того, як вирішувати, чи
		// вмикати перерахунок: інакше перша сторінка після кліку
		// показала б ще стару валюту.
		self::remember();

		if ( ! self::active() ) {
			return;
		}

		add_filter( 'woocommerce_currency', array( $this, 'code' ) );
		add_filter( 'option_woocommerce_currency_pos', array( $this, 'position' ) );

		foreach ( array( 'price', 'regular_price', 'sale_price' ) as $field ) {
			add_filter( 'woocommerce_product_get_' . $field, array( $this, 'convert' ), 5 );
			add_filter( 'woocommerce_product_variation_get_' . $field, array( $this, 'convert' ), 5 );
			add_filter( 'woocommerce_variation_prices_' . $field, array( $this, 'convert' ), 5 );
		}

		add_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'hash' ) );
		add_filter( 'woocommerce_package_rates', array( $this, 'rates' ), 5 );
		add_filter( 'woocommerce_shipping_zone_shipping_methods', array( $this, 'methods' ) );
	}

	/**
	 * Чи рахуємо зараз у другій валюті.
	 *
	 * @return bool
	 */
	public static function active(): bool {
		return self::CODE === self::chosen() && self::rate() > 0;
	}

	/**
	 * Валюта, в якій покупець зараз бачить ціни.
	 *
	 * Адмінка, крон і WP-CLI завжди в гривні — як і мова там завжди
	 * українська: власник рахує гроші в тій валюті, в якій їх отримує.
	 *
	 * @return string
	 */
	public static function chosen(): string {
		if ( null !== self::$chosen ) {
			return self::$chosen;
		}

		self::$chosen = self::detect();

		return self::$chosen;
	}

	/**
	 * Визначає валюту запиту: адмінка, кука, мова — саме в такому порядку.
	 *
	 * @return string
	 */
	private static function detect(): string {
		if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return self::MAIN;
		}

		$cookie = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		$cookie = strtoupper( $cookie );

		if ( in_array( $cookie, array( self::MAIN, self::CODE ), true ) ) {
			return $cookie;
		}

		return Language::SECOND === Language::current() ? self::CODE : self::MAIN;
	}

	/**
	 * Адреса поточної сторінки з іншою валютою.
	 *
	 * Перемикач — звичайне посилання, а не форма чи скрипт: так він
	 * працює без JavaScript, а адреса з параметром одразу
	 * перенаправляється на чисту.
	 *
	 * @param string $code Код валюти.
	 * @return string
	 */
	public static function switch_url( string $code ): string {
		return add_query_arg( self::PARAM, strtolower( $code ) );
	}

	/**
	 * Валюти для перемикача: код => знак.
	 *
	 * Порожньо, коли курсу немає: тоді й перемикати нема на що.
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
		if ( self::rate() <= 0 ) {
			return array();
		}

		return array(
			self::MAIN => '₴',
			self::CODE => '€',
		);
	}

	/**
	 * Запам'ятовує вибір із перемикача.
	 *
	 * @return void
	 */
	private static function remember(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Перемикання показу, нічого не змінює на сервері.
		$wanted = isset( $_GET[ self::PARAM ] ) ? strtoupper( sanitize_key( wp_unslash( $_GET[ self::PARAM ] ) ) ) : '';

		if ( ! in_array( $wanted, array( self::MAIN, self::CODE ), true ) || is_admin() ) {
			return;
		}

		self::$chosen = $wanted;

		if ( headers_sent() ) {
			return;
		}

		setcookie(
			self::COOKIE,
			$wanted,
			array(
				'expires'  => time() + YEAR_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Прибирає параметр валюти з адреси.
	 *
	 * Вибір уже в куці, а адреса з параметром потрапила б у закладки
	 * й перемикала б валюту кожному, хто нею скористається.
	 *
	 * @return void
	 */
	public function clean_url(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Лише читання параметра перемикача.
		if ( ! isset( $_GET[ self::PARAM ] ) || is_admin() ) {
			return;
		}

		wp_safe_redirect( remove_query_arg( self::PARAM ), 302 );
		exit;
	}

	/**
	 * Просить кеш сторінок LiteSpeed зберігати копію на кожну валюту.
	 *
	 * Мова живе в адресі, тож кеш розрізняє її сам. Валюта живе в
	 * куці — і без цього перший відвідувач, що обрав євро, лишив би
	 * євро в кеші для всіх наступних.
	 *
	 * @param array<int, string> $cookies Куки, від яких залежить сторінка.
	 * @return array<int, string>
	 */
	public function vary( $cookies ) {
		$cookies   = is_array( $cookies ) ? $cookies : array();
		$cookies[] = self::COOKIE;

		return $cookies;
	}

	/**
	 * Курс: гривень за євро.
	 *
	 * @return float
	 */
	public static function rate(): float {
		$rate = (float) get_option( self::OPTION, self::DEFAULT_RATE );

		return $rate > 0 ? $rate : 0.0;
	}

	/**
	 * Сума, яку бачить покупець, назад у валюту бази.
	 *
	 * Потрібна там, де число вводить сам покупець: фільтр ціни в
	 * каталозі питає «від» і «до» в тій валюті, що на екрані, а
	 * порівнювати їх доводиться з гривневим `_price` у базі.
	 *
	 * @param float $amount Сума у валюті показу.
	 * @return float
	 */
	public static function to_store( float $amount ): float {
		$rate = self::active() ? self::rate() : 0.0;

		return $rate > 0 ? $amount * $rate : $amount;
	}

	/**
	 * Гривнева сума у валюті показу.
	 *
	 * @param float $amount Сума в гривні.
	 * @return float
	 */
	public static function amount( float $amount ): float {
		$rate = self::active() ? self::rate() : 0.0;

		return $rate > 0 ? round( $amount / $rate, 2 ) : $amount;
	}

	/**
	 * Гривнева сума, надрукована валютою показу.
	 *
	 * @param float $amount Сума в гривні.
	 * @return string
	 */
	public static function money( float $amount ): string {
		return wp_strip_all_tags( wc_price( self::amount( $amount ) ) );
	}

	/**
	 * Переводить гривню в євро.
	 *
	 * @param mixed $amount Сума в гривні.
	 * @return mixed
	 */
	public function convert( $amount ) {
		if ( '' === $amount || null === $amount || ! is_numeric( $amount ) ) {
			return $amount;
		}

		$rate = self::rate();

		return $rate > 0 ? round( (float) $amount / $rate, 2 ) : $amount;
	}

	/**
	 * Код валюти.
	 *
	 * @param string $currency Код.
	 * @return string
	 */
	public function code( $currency ): string {
		unset( $currency );

		return self::CODE;
	}

	/**
	 * Символ євро стоїть перед сумою, а гривня — після.
	 *
	 * @param mixed $position Положення символу.
	 * @return string
	 */
	public function position( $position ): string {
		unset( $position );

		return 'left';
	}

	/**
	 * Формат суми під валюту, в якій її друкують.
	 *
	 * Фільтр стоїть завжди, а не тільки під /en/: замовлення в євро
	 * відкривають і з української адмінки, і формат має бути євровий
	 * там теж. Валюту беремо з самої суми, а не з мови сторінки —
	 * інакше євро в українському списку замовлень малювалося б
	 * гривневими правилами й губило центи.
	 *
	 * @param array<string, mixed> $args Аргументи wc_price().
	 * @return array<string, mixed>
	 */
	public function price_args( $args ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}

		$currency = '' !== (string) ( $args['currency'] ?? '' )
			? (string) $args['currency']
			: get_woocommerce_currency();

		if ( self::CODE === $currency ) {
			$args['decimals']           = 2;
			$args['decimal_separator']  = '.';
			$args['thousand_separator'] = ',';
			$args['price_format']       = '%1$s%2$s';

			return $args;
		}

		/*
		 * Гривня друкується без копійок. Самі ціни рахуються з двома
		 * знаками — інакше євро округлювалось би до цілого ще до
		 * показу, — але «620,00 ₴» у каталозі ніхто не пише.
		 */
		if ( 'UAH' === $currency ) {
			$args['decimals'] = 0;
		}

		return $args;
	}

	/**
	 * Додає валюту до ключа кешу цін варіацій.
	 *
	 * Без цього діапазон «від 580 ₴» лишився б у кеші й показувався
	 * на англійській сторінці — з символом євро, але гривневим числом.
	 *
	 * @param array<int, mixed> $hash Складові ключа.
	 * @return array<int, mixed>
	 */
	public function hash( array $hash ): array {
		$hash[] = self::CODE;
		$hash[] = self::rate();

		return $hash;
	}

	/**
	 * Переводить вартість доставки.
	 *
	 * @param array<string, \WC_Shipping_Rate> $rates Ставки пакунка.
	 * @return array<string, \WC_Shipping_Rate>
	 */
	public function rates( array $rates ): array {
		foreach ( $rates as $rate ) {
			$cost = $rate->get_cost();

			if ( '' === $cost || ! is_numeric( $cost ) ) {
				continue;
			}

			$rate->set_cost( (string) $this->convert( $cost ) );
		}

		return $rates;
	}

	/**
	 * Переводить поріг безкоштовної доставки.
	 *
	 * Поріг лежить у налаштуваннях способу доставки, і читають його
	 * двоє: сам WooCommerce, коли вирішує, чи показувати безкоштовну
	 * ставку, і тема — для смуги «до безкоштовної доставки». Правимо
	 * його в самому об'єкті способу, щоб обидва бачили те саме число.
	 *
	 * @param array<int, \WC_Shipping_Method> $methods Способи доставки зони.
	 * @return array<int, \WC_Shipping_Method>
	 */
	public function methods( $methods ) {
		if ( ! is_array( $methods ) ) {
			return $methods;
		}

		foreach ( $methods as $method ) {
			if ( ! $method instanceof \WC_Shipping_Method || 'free_shipping' !== $method->id ) {
				continue;
			}

			$instance = $method->get_instance_id();

			// get_option() заразом підвантажує налаштування: до першого
			// звернення масиви порожні, і писати в них рано.
			$amount = $method->get_option( 'min_amount' );

			if ( ! isset( self::$thresholds[ $instance ] ) ) {
				if ( '' === $amount || ! is_numeric( $amount ) ) {
					continue;
				}

				self::$thresholds[ $instance ] = (float) $amount;
			}

			$converted = $this->convert( self::$thresholds[ $instance ] );

			/*
			 * Записуємо у два масиви. WC_Shipping_Method::get_option()
			 * віддає перевагу instance_settings — налаштуванням саме
			 * цього екземпляра способу в зоні, — і якщо поправити лише
			 * settings, поріг для теми лишиться гривневим.
			 */
			$method->min_amount                      = $converted;
			$method->settings['min_amount']          = $converted;
			$method->instance_settings['min_amount'] = $converted;
		}

		return $methods;
	}

	/**
	 * Поле курсу в налаштуваннях WooCommerce.
	 *
	 * @param array<int, array<string, mixed>> $settings Налаштування.
	 * @return array<int, array<string, mixed>>
	 */
	public function settings( array $settings ): array {
		$settings[] = array(
			'title' => __( 'Друга валюта: євро', 'brix-core' ),
			'type'  => 'title',
			'desc'  => __( 'Покупець перемикає валюту знаком ₴ / € у шапці; англійська версія без вибору показує євро. У базі лишається гривня — євро рахується за цим курсом на показі.', 'brix-core' ),
			'id'    => 'brix_currency_options',
		);

		$settings[] = array(
			'title'             => __( 'Гривень за євро', 'brix-core' ),
			'desc'              => __( 'Нуль вимикає євро зовсім: перемикач зникне, і весь сайт рахуватиме в гривні.', 'brix-core' ),
			'id'                => self::OPTION,
			'type'              => 'number',
			'default'           => (string) self::DEFAULT_RATE,
			'custom_attributes' => array(
				'min'  => '0',
				'step' => '0.01',
			),
		);

		$settings[] = array(
			'type' => 'sectionend',
			'id'   => 'brix_currency_options',
		);

		return $settings;
	}
}
