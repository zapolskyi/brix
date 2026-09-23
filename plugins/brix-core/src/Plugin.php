<?php
/**
 * Ядро плагіна: збирає модулі й запускає їх.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Ядро плагіна.
 *
 * Тримає перелік модулів, створює їх у потрібному порядку й дає
 * доступ до вже запущених. Сам нічого не вміє: уся логіка — в модулях.
 */
final class Plugin {

	/**
	 * Єдиний примірник.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Запущені модулі, за іменем класу.
	 *
	 * @var array<class-string<Module>, Module>
	 */
	private array $modules = array();

	/**
	 * Чи вже запускались модулі.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Створюється лише через instance().
	 */
	private function __construct() {}

	/**
	 * Повертає єдиний примірник плагіна.
	 *
	 * @return self
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Створює й реєструє модулі.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		/*
		 * Переклади підключаються на init, а не тут. Мову запиту
		 * визначає модуль I18n\Language, і його фільтр locale має
		 * стояти раніше, ніж WordPress піде шукати файл перекладу —
		 * інакше під /en/ завантажився б український.
		 */
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );

		foreach ( $this->module_classes() as $module_class ) {
			if ( ! class_exists( $module_class ) || ! is_subclass_of( $module_class, Module::class ) ) {
				continue;
			}

			$module = new $module_class();
			$module->register();

			$this->modules[ $module_class ] = $module;
		}

		do_action( 'brix_core_booted', $this );
	}

	/**
	 * Уже запущений модуль за іменем класу.
	 *
	 * @param string $module_class Клас модуля.
	 * @return Module|null
	 */
	public function module( string $module_class ): ?Module {
		return $this->modules[ $module_class ] ?? null;
	}

	/**
	 * Перелік модулів у порядку запуску.
	 *
	 * Порядок має значення: типи записів і таксономії реєструються
	 * раніше за все, що на них спирається, — поля посилаються на них
	 * у правилах розташування.
	 *
	 * @return array<int, class-string<Module>>
	 */
	private function module_classes(): array {
		/**
		 * Дозволяє додати або прибрати модулі плагіна.
		 *
		 * @param array<int, class-string<Module>> $modules Класи модулів.
		 */
		return (array) apply_filters(
			'brix_core_modules',
			array(
				I18n\Language::class,
				I18n\Translations::class,
				I18n\Emails::class,
				I18n\Currency::class,
				PostTypes\Farm::class,
				PostTypes\BrewGuide::class,
				Taxonomies\Registrar::class,
				Fields\Registrar::class,
				Product\LotMeta::class,
				Product\Schema::class,
				Shipping\NovaPoshta::class,
				Shipping\Rates::class,
				Shipping\Pickup::class,
				Payments\Registrar::class,
				Payments\CashOnDelivery::class,
				Payments\BankTransfer::class,
				Club\Subscription::class,
				Club\Plan::class,
				Club\Scheduler::class,
				Club\Actions::class,
				Orders\Repeat::class,
				Emails\RestockReminder::class,
				Wholesale\Application::class,
				Wholesale\Role::class,
				Wholesale\Pricing::class,
			)
		);
	}

	/**
	 * Підключає переклади плагіна.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'brix-core', false, dirname( plugin_basename( BRIX_CORE_FILE ) ) . '/languages' );
	}

	/**
	 * Активація плагіна.
	 *
	 * Реєстрація типів записів і таксономій з'явиться на фазі 2;
	 * поки що достатньо скинути правила перезапису, щоб їхні майбутні
	 * URL не впирались у кеш старих правил.
	 *
	 * @return void
	 */
	public static function on_activate(): void {
		// Таблиця довідника Нової Пошти має існувати до першого
		// звернення: інакше автокомпліт впаде на порожньому запиті.
		Shipping\Directory::install();

		flush_rewrite_rules();
	}

	/**
	 * Деактивація плагіна.
	 *
	 * @return void
	 */
	public static function on_deactivate(): void {
		flush_rewrite_rules();
	}
}
