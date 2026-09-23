<?php
/**
 * Команда wp brix setup — налаштування магазину з нуля.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Cli;

defined( 'ABSPATH' ) || exit;

/**
 * Приводить чисту інсталяцію до стану, з якого працює тема.
 *
 * Усе, що тут робиться, раніше застосовувалось руками через wp-cli, і
 * повторити це на хостингу можна було тільки за списком у журналі. Тепер
 * розгортання — одна команда, і саме вона є документацією налаштувань.
 *
 * Команда ідемпотентна: повторний запуск нічого не дублює, бо сторінки,
 * категорії й меню шукаються за slug, а зони доставки — за назвою.
 */
final class Setup {

	/**
	 * Країни продажу: Україна й 27 країн ЄС.
	 */
	private const COUNTRIES = array(
		'UA',
		'PL',
		'DE',
		'FR',
		'IT',
		'ES',
		'NL',
		'AT',
		'CZ',
		'SK',
		'RO',
		'HU',
		'LT',
		'LV',
		'EE',
		'BE',
		'PT',
		'SE',
		'FI',
		'DK',
		'IE',
		'GR',
		'BG',
		'HR',
		'SI',
		'LU',
		'CY',
		'MT',
	);

	/**
	 * Поріг безкоштовної доставки, гривень.
	 */
	public const FREE_SHIPPING_FROM = 1200;

	/**
	 * Контентні сторінки: slug => заголовок.
	 */
	private const PAGES = array(
		'about'        => 'Про нас',
		'contacts'     => 'Контакти',
		'delivery'     => 'Доставка й оплата',
		'faq'          => 'Питання',
		'transparency' => 'Прозорість',
		'wholesale'    => 'Для кав’ярень',
		'club'         => 'BRIX Club',
		'quiz'         => 'Підібрати каву',
		'privacy'      => 'Політика конфіденційності',
		'ui-kit'       => 'UI-кіт',
	);

	/**
	 * Сторінки WooCommerce: опція з ідентифікатором => [slug, заголовок].
	 */
	private const SHOP_PAGES = array(
		'woocommerce_shop_page_id'      => array( 'shop', 'Магазин', '' ),
		'woocommerce_cart_page_id'      => array( 'cart', 'Кошик', 'woocommerce_cart' ),
		'woocommerce_checkout_page_id'  => array( 'checkout', 'Оформлення замовлення', 'woocommerce_checkout' ),
		'woocommerce_myaccount_page_id' => array( 'account', 'Мій кабінет', 'woocommerce_my_account' ),
	);

	/**
	 * Лінійки товарів: slug => назва.
	 */
	private const CATEGORIES = array(
		'core'     => 'Core',
		'origin'   => 'Origin',
		'lab'      => 'Lab',
		'drip-try' => 'Drip & Try',
		'gear'     => 'Gear',
	);

	/**
	 * Налаштовує магазин.
	 *
	 * ## EXAMPLES
	 *
	 *     wp brix setup
	 *
	 * @param array<int, string>    $args       Позиційні аргументи.
	 * @param array<string, string> $assoc_args Іменовані аргументи.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );

		if ( ! class_exists( 'WooCommerce' ) ) {
			\WP_CLI::error( 'WooCommerce не активовано.' );
		}

		// Мовні пакети — першими: WordPress мовчки відмовляється
		// записати WPLANG=uk, поки українського пакета немає, і сайт
		// лишався б англійським — з англійськими перекладами теми на
		// українській версії.
		$this->languages();
		$this->core();
		$this->store();
		$this->pages();
		$this->categories();
		$this->menus();
		$this->shipping();
		$this->payments();

		flush_rewrite_rules();

		\WP_CLI::success( 'Магазин налаштовано.' );
	}

	/**
	 * Українські переклади WordPress і WooCommerce.
	 *
	 * Тема й плагін несуть свої переклади з собою, а WooCommerce — ні:
	 * його переклад приходить окремим мовним пакетом. Локально пакет
	 * стояв давно, тож дірку видно не було. Чиста інсталяція без нього
	 * показувала посеред українського магазину «Your cart is currently
	 * empty!» і «Add to cart».
	 *
	 * Потрібен доступ в інтернет. Без нього setup не падає, а каже,
	 * що поставити вручну.
	 *
	 * @return void
	 */
	private function languages(): void {
		$commands = array(
			'language core install uk',
			'language plugin install woocommerce uk',
		);

		foreach ( $commands as $command ) {
			$result = \WP_CLI::runcommand(
				$command,
				array(
					'return'     => 'all',
					'launch'     => false,
					'exit_error' => false,
				)
			);

			if ( 0 !== (int) $result->return_code ) {
				\WP_CLI::warning( sprintf( 'Не вдалося: wp %s. Запустіть вручну, коли буде мережа.', $command ) );
			}
		}

		\WP_CLI::log( 'Українські мовні пакети на місці.' );
	}

	/**
	 * Налаштування самого WordPress.
	 *
	 * @return void
	 */
	private function core(): void {
		$options = array(
			'blogname'               => 'BRIX 22°',
			'blogdescription'        => 'Specialty-обсмажувальня · зібрано на піку',
			'WPLANG'                 => 'uk',
			'timezone_string'        => 'Europe/Kyiv',
			'date_format'            => 'd.m.Y',
			'time_format'            => 'H:i',
			'start_of_week'          => 1,
			'permalink_structure'    => '/%postname%/',
			// Демо-стенд не має потрапляти в пошук.
			'blog_public'            => 0,
			'default_comment_status' => 'closed',
			'default_ping_status'    => 'closed',
		);

		foreach ( $options as $key => $value ) {
			update_option( $key, $value );
		}

		\WP_CLI::log( 'Налаштування WordPress застосовано.' );
	}

	/**
	 * Налаштування магазину під Україну.
	 *
	 * @return void
	 */
	private function store(): void {
		$options = array(
			'woocommerce_store_address'                => 'вул. Кирилівська, 41',
			'woocommerce_store_city'                   => 'Київ',
			'woocommerce_store_postcode'               => '04080',
			'woocommerce_default_country'              => 'UA:UA-30',
			'woocommerce_currency'                     => 'UAH',
			'woocommerce_currency_pos'                 => 'right_space',
			// Роздільник тисяч — нерозривний пробіл: «1 200 ₴» не має
			// розриватись на кінці рядка.
			'woocommerce_price_thousand_sep'           => "\u{00A0}",
			'woocommerce_price_decimal_sep'            => ',',

			/*
			 * Дві цифри після коми, хоч гривневі ціни цілі. Це не про
			 * показ, а про точність: англійська версія рахує в євро,
			 * і з нулем WooCommerce округлював би 14,17 € до 14 —
			 * не на екрані, а в самому замовленні. Гривню без копійок
			 * малює вже форматування.
			 */
			'woocommerce_price_num_decimals'           => 2,
			'woocommerce_weight_unit'                  => 'kg',
			'woocommerce_dimension_unit'               => 'cm',
			'woocommerce_allowed_countries'            => 'specific',
			'woocommerce_specific_allowed_countries'   => self::COUNTRIES,
			'woocommerce_ship_to_countries'            => 'shipping',
			'woocommerce_enable_guest_checkout'        => 'yes',
			'woocommerce_manage_stock'                 => 'yes',
			'woocommerce_notify_low_stock_amount'      => 5,
			'woocommerce_enable_reviews'               => 'yes',
			'woocommerce_review_rating_required'       => 'yes',
			// Магазин має бути видимим. WooCommerce 11 вмикає режим
			// «Coming soon» за замовчуванням, і кожна сторінка віддає
			// заглушку — зі статусом 200, тож перевірка маршрутів
			// рапортує успіх на сторінках, яких ніхто не бачить.
			'woocommerce_coming_soon'                  => 'no',
			'woocommerce_store_pages_only'             => 'no',
			// Підказки, майстер онбордингу й телеметрія в адмінці
			// демо-стенду тільки заважають.
			'woocommerce_show_marketplace_suggestions' => 'no',
			'woocommerce_allow_tracking'               => 'no',
			'woocommerce_task_list_hidden'             => 'yes',
			'woocommerce_onboarding_profile'           => array( 'skipped' => true ),
		);

		foreach ( $options as $key => $value ) {
			update_option( $key, $value );
		}

		/*
		 * Тексти, які WooCommerce пише при активації мовою, що на той
		 * момент є, — на свіжому сайті це англійська.
		 */
		$texts = array(
			'woocommerce_checkout_privacy_policy_text'     => 'Ваші дані потрібні, щоб оформити й доставити замовлення. Деталі — у [privacy_policy].',
			'woocommerce_registration_privacy_policy_text' => 'Ваші дані потрібні, щоб створити кабінет. Деталі — у [privacy_policy].',
			'woocommerce_email_footer_text'                => 'BRIX 22° · Обсмажувальня, Київ · {site_url}',
		);

		foreach ( $texts as $key => $value ) {
			update_option( $key, $value );
		}

		/*
		 * Кабінет. Купувати можна й гостем — змушувати реєструватись
		 * заради пачки кави зайве. Але кабінет має бути доступний: на
		 * сторінці входу й галочкою на checkout, а з підпискою BRIX
		 * Club — обов'язково (це вмикає сам модуль Club\Plan).
		 * Логін і пароль не питаємо: логін WooCommerce складе з пошти,
		 * пароль покупець задасть за посиланням із листа.
		 */
		$accounts = array(
			'woocommerce_enable_myaccount_registration'  => 'yes',
			'woocommerce_registration_generate_username' => 'yes',
			'woocommerce_registration_generate_password' => 'yes',
		);

		// Галочка «Створити кабінет» на checkout.
		$accounts['woocommerce_enable_signup_and_login_from_checkout'] = 'yes';

		foreach ( $accounts as $key => $value ) {
			update_option( $key, $value );
		}

		\WP_CLI::log( 'Налаштування магазину застосовано.' );
	}

	/**
	 * Контентні сторінки й прив'язка сторінок WooCommerce.
	 *
	 * @return void
	 */
	private function pages(): void {
		$home = $this->page( 'home', 'Головна' );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home );

		foreach ( self::PAGES as $slug => $title ) {
			$this->page( $slug, $title );
		}

		$this->legal_pages();

		// Сторінки магазину створює сам WooCommerce, але на чистій
		// інсталяції їх може ще не бути.
		\WC_Install::create_pages();
		$this->shop_pages();
		$this->drop_samples();

		\WP_CLI::log( 'Сторінки на місці.' );
	}

	/**
	 * Називає сторінки WooCommerce так, як їх називає решта сайту.
	 *
	 * WooCommerce створює їх мовою власного перекладу, а на свіжому
	 * хостингу перекладу може ще не бути: у меню з'являлись «Shop» і
	 * «My account». Кабінет до того ж отримував slug my-account, і
	 * англійський переклад, записаний для account, його не знаходив.
	 *
	 * Головне тут — вміст. Свіжий WooCommerce ставить у кошик і
	 * checkout блоки, а тема перевизначає класичні шаблони: Нова
	 * Пошта, спосіб отримання, картки оплати й робота без JavaScript
	 * живуть саме там. З блоками покупець на хостингу побачив би
	 * стандартний checkout WooCommerce, а без JavaScript — порожню
	 * сторінку. Тож кладемо класичні шорткоди.
	 *
	 * @return void
	 */
	private function shop_pages(): void {
		foreach ( self::SHOP_PAGES as $option => $page ) {
			$id = (int) get_option( $option );

			if ( ! $id || ! get_post( $id ) ) {
				continue;
			}

			$args = array(
				'ID'         => $id,
				'post_name'  => $page[0],
				'post_title' => $page[1],
			);

			if ( '' !== $page[2] ) {
				$args['post_content'] = '<!-- wp:shortcode -->[' . $page[2] . ']<!-- /wp:shortcode -->';
			}

			wp_update_post( wp_slash( $args ) );
		}
	}

	/**
	 * Прибирає зразки WordPress: «Sample Page» і «Hello world!».
	 *
	 * Лише в кошик і лише поки вони неторкані — сторінку, яку хтось
	 * уже переписав, не чіпаємо.
	 *
	 * @return void
	 */
	private function drop_samples(): void {
		$samples = array(
			array( 'sample-page', 'page' ),
			array( 'hello-world', 'post' ),
		);

		foreach ( $samples as $sample ) {
			$post = get_page_by_path( $sample[0], OBJECT, $sample[1] );

			if ( $post instanceof \WP_Post && $post->post_modified_gmt === $post->post_date_gmt ) {
				wp_trash_post( $post->ID );
			}
		}
	}

	/**
	 * Політика конфіденційності й заглушка повернень.
	 *
	 * На політику посилається згода під кнопкою «Підтвердити
	 * замовлення». WordPress при встановленні створює її чернеткою з
	 * англійським шаблоном — і покупець, що хотів її прочитати,
	 * отримував 404. Тож сторінку публікуємо й прописуємо в
	 * налаштуваннях; текст для неї кладе `wp brix demo`.
	 *
	 * WooCommerce своєю чергою публікує англійську «Refund and Returns
	 * Policy» на 30 днів — вона суперечить нашим умовам на сторінці
	 * «Доставка й оплата». Її ховаємо в чернетки, але лише доки там
	 * лежить заглушка: сторінку, яку хтось переписав, не чіпаємо.
	 *
	 * @return void
	 */
	private function legal_pages(): void {
		$privacy = $this->page( 'privacy', self::PAGES['privacy'] );

		if ( $privacy ) {
			if ( 'publish' !== get_post_status( $privacy ) ) {
				wp_update_post(
					array(
						'ID'          => $privacy,
						'post_status' => 'publish',
					)
				);
			}

			update_option( 'wp_page_for_privacy_policy', $privacy );
		}

		$refund = get_page_by_path( 'refund_returns' );

		if (
			$refund instanceof \WP_Post
			&& 'publish' === $refund->post_status
			&& false !== strpos( $refund->post_content, 'Our refund and returns policy lasts 30 days' )
		) {
			wp_update_post(
				array(
					'ID'          => $refund->ID,
					'post_status' => 'draft',
				)
			);
		}
	}

	/**
	 * Створює сторінку або повертає наявну.
	 *
	 * @param string $slug  Slug.
	 * @param string $title Заголовок.
	 * @return int
	 */
	private function page( string $slug, string $title ): int {
		$existing = get_page_by_path( $slug );

		if ( $existing instanceof \WP_Post ) {
			return $existing->ID;
		}

		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_name'    => $slug,
				'post_title'   => $title,
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);

		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/**
	 * Лінійки товарів.
	 *
	 * @return void
	 */
	private function categories(): void {
		foreach ( self::CATEGORIES as $slug => $name ) {
			if ( term_exists( $slug, 'product_cat' ) ) {
				continue;
			}

			wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) );
		}

		\WP_CLI::log( 'Лінійки товарів на місці.' );
	}

	/**
	 * Меню: головне й чотири колонки підвалу.
	 *
	 * @return void
	 */
	private function menus(): void {
		$menus = array(
			'primary' => array(
				'name'  => 'Головне меню',
				'items' => array(
					array( 'page', 'shop', 'Магазин' ),
					array( 'page', 'quiz', 'Підбір кави' ),
					array( 'page', 'club', 'BRIX Club' ),
					array( 'url', '/farms/', 'Виробники' ),
					array( 'url', '/guides/', 'Гайди' ),
					array( 'page', 'wholesale', 'Для кав’ярень' ),
				),
			),
			'brand'   => array(
				'name'  => 'Підвал · Бренд',
				'items' => array(
					array( 'page', 'about', 'Про нас' ),
					array( 'url', '/farms/', 'Виробники' ),
					array( 'page', 'transparency', 'Прозорість' ),
					array( 'url', '/guides/', 'Гайди' ),
					array( 'page', 'wholesale', 'Для кав’ярень' ),
				),
			),
			'help'    => array(
				'name'  => 'Підвал · Допомога',
				'items' => array(
					array( 'page', 'delivery', 'Доставка й оплата' ),
					array( 'page', 'faq', 'Питання' ),
					array( 'page', 'contacts', 'Контакти' ),
				),
			),
			'shop'    => array(
				'name'  => 'Підвал · Магазин',
				'items' => array(
					array( 'cat', 'core', 'Core' ),
					array( 'cat', 'origin', 'Origin' ),
					array( 'cat', 'lab', 'Lab' ),
					array( 'cat', 'drip-try', 'Drip & Try' ),
					array( 'cat', 'gear', 'Gear' ),
				),
			),
			'legal'   => array(
				'name'  => 'Підвал · Правова',
				'items' => array(
					array( 'url', 'https://instagram.com', 'Instagram' ),
					array( 'url', 'https://t.me', 'Telegram' ),
					array( 'page', 'privacy', 'Політика конфіденційності' ),
				),
			),
		);

		$locations = array();

		foreach ( $menus as $location => $menu ) {
			$object = wp_get_nav_menu_object( $menu['name'] );
			$id     = $object ? (int) $object->term_id : (int) wp_create_nav_menu( $menu['name'] );

			if ( ! $id ) {
				continue;
			}

			$locations[ $location ] = $id;

			// Пункти додаються лише в порожнє меню: інакше повторний
			// запуск подвоїв би їх.
			if ( wp_get_nav_menu_items( $id ) ) {
				continue;
			}

			foreach ( $menu['items'] as $item ) {
				$this->menu_item( $id, $item[0], $item[1], $item[2] );
			}
		}

		set_theme_mod( 'nav_menu_locations', $locations );

		\WP_CLI::log( 'Меню на місці.' );
	}

	/**
	 * Додає пункт меню.
	 *
	 * @param int    $menu  Ідентифікатор меню.
	 * @param string $kind  page, cat або url.
	 * @param string $value Slug або адреса.
	 * @param string $title Підпис.
	 * @return void
	 */
	private function menu_item( int $menu, string $kind, string $value, string $title ): void {
		$data = array(
			'menu-item-title'  => $title,
			'menu-item-status' => 'publish',
		);

		if ( 'page' === $kind ) {
			$page = get_page_by_path( $value );

			if ( ! $page instanceof \WP_Post ) {
				return;
			}

			$data['menu-item-type']      = 'post_type';
			$data['menu-item-object']    = 'page';
			$data['menu-item-object-id'] = $page->ID;
		} elseif ( 'cat' === $kind ) {
			$term = get_term_by( 'slug', $value, 'product_cat' );

			if ( ! $term instanceof \WP_Term ) {
				return;
			}

			$data['menu-item-type']      = 'taxonomy';
			$data['menu-item-object']    = 'product_cat';
			$data['menu-item-object-id'] = $term->term_id;
		} else {
			$data['menu-item-type'] = 'custom';
			$data['menu-item-url']  = $value;
		}

		wp_update_nav_menu_item( $menu, 0, $data );
	}

	/**
	 * Зони доставки.
	 *
	 * Їх не було взагалі: замовлення проходили з підсумком, що
	 * дорівнює ціні товару, а смуга «до безкоштовної доставки» в
	 * кошику показувала прогрес до порога, за яким нічого не стояло.
	 *
	 * @return void
	 */
	private function shipping(): void {
		$zones = array(
			array(
				'name'    => 'Україна',
				'regions' => array( array( 'UA', 'country' ) ),
				'methods' => array(
					array(
						'id'       => 'flat_rate',
						'settings' => array(
							'title'      => 'Нова Пошта — відділення',
							'cost'       => '80',
							'tax_status' => 'none',
						),
					),
					array(
						'id'       => 'local_pickup',
						'settings' => array(
							'title'      => 'Самовивіз з обжарювальні',
							'cost'       => '0',
							'tax_status' => 'none',
						),
					),
					array(
						'id'       => 'free_shipping',
						'settings' => array(
							'title'      => 'Безкоштовна доставка',
							'requires'   => 'min_amount',
							'min_amount' => (string) self::FREE_SHIPPING_FROM,
						),
					),
				),
			),
			array(
				'name'    => 'Європейський Союз',
				'regions' => array_map(
					static fn( string $code ): array => array( $code, 'country' ),
					array_values( array_diff( self::COUNTRIES, array( 'UA' ) ) )
				),
				'methods' => array(
					array(
						'id'       => 'flat_rate',
						'settings' => array(
							'title'      => 'Доставка в ЄС',
							'cost'       => '450',
							'tax_status' => 'none',
						),
					),
				),
			),
		);

		foreach ( $zones as $config ) {
			$zone = $this->zone( $config['name'] );

			if ( ! $zone ) {
				continue;
			}

			foreach ( $config['regions'] as $region ) {
				$zone->add_location( $region[0], $region[1] );
			}

			$existing = wp_list_pluck( $zone->get_shipping_methods(), 'id' );

			foreach ( $config['methods'] as $method ) {
				if ( in_array( $method['id'], $existing, true ) ) {
					continue;
				}

				$instance = $zone->add_shipping_method( $method['id'] );

				foreach ( $method['settings'] as $key => $value ) {
					update_option(
						'woocommerce_' . $method['id'] . '_' . $instance . '_settings',
						array_merge(
							(array) get_option( 'woocommerce_' . $method['id'] . '_' . $instance . '_settings', array() ),
							array(
								$key      => $value,
								'enabled' => 'yes',
							)
						)
					);
				}
			}

			$zone->save();
		}

		\WP_CLI::log( 'Зони доставки на місці.' );
	}

	/**
	 * Зона доставки за назвою — наявна або нова.
	 *
	 * @param string $name Назва.
	 * @return \WC_Shipping_Zone|null
	 */
	private function zone( string $name ): ?\WC_Shipping_Zone {
		foreach ( \WC_Shipping_Zones::get_zones() as $data ) {
			if ( $data['zone_name'] === $name ) {
				return new \WC_Shipping_Zone( (int) $data['zone_id'] );
			}
		}

		$zone = new \WC_Shipping_Zone();
		$zone->set_zone_name( $name );
		$zone->save();

		return $zone;
	}

	/**
	 * Способи оплати.
	 *
	 * @return void
	 */
	private function payments(): void {
		$cod = (array) get_option( 'woocommerce_cod_settings', array() );

		update_option(
			'woocommerce_cod_settings',
			array_merge(
				$cod,
				array(
					'enabled'     => 'yes',
					'title'       => 'Оплата при отриманні',
					'description' => 'Оплатите у відділенні Нової Пошти, коли забиратимете посилку.',
				)
			)
		);

		/*
		 * Переказ вимкнено навмисно. Закордонні замовлення оплачуються
		 * карткою — те саме, що й удома, тільки без накладного платежу,
		 * якого Нова Пошта за кордон не возить.
		 */
		$bacs = (array) get_option( 'woocommerce_bacs_settings', array() );

		if ( $bacs ) {
			update_option( 'woocommerce_bacs_settings', array_merge( $bacs, array( 'enabled' => 'no' ) ) );
		}

		/*
		 * Картка першою: вона працює і вдома, і за кордоном, і саме її
		 * checkout обирає за замовчуванням. Накладний платіж — другим.
		 */
		update_option(
			'woocommerce_gateway_order',
			array(
				'brix_liqpay'   => 0,
				'brix_monobank' => 1,
				'cod'           => 2,
				'bacs'          => 3,
			)
		);

		\WP_CLI::log( 'Способи оплати на місці.' );
	}
}
