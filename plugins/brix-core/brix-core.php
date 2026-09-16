<?php
/**
 * Plugin Name:       BRIX Core
 * Plugin URI:        https://github.com/nzapolskyi/brix22
 * Description:       Бізнес-логіка магазину BRIX 22°: паспорт лоту, таксономії обробки, виробники, гайди, AJAX-фільтри каталогу, квіз підбору, Нова Пошта, оптовий кабінет і транзакційні листи. Презентація — в темі brix.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.2
 * Author:            Nazar Zapolskyi
 * Author URI:        https://github.com/nzapolskyi
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       brix-core
 * Domain Path:       /languages
 *
 * WC requires at least: 9.0
 * WC tested up to:      11.1
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core;

defined( 'ABSPATH' ) || exit;

const VERSION = '0.1.0';

define( 'BRIX_CORE_VERSION', VERSION );
define( 'BRIX_CORE_FILE', __FILE__ );
define( 'BRIX_CORE_DIR', __DIR__ );
define( 'BRIX_CORE_URL', plugin_dir_url( __FILE__ ) );

/*
 * Автозавантаження. Composer — основний шлях (PSR-4, composer.json);
 * простий фолбек нижче потрібен, щоб плагін не падав білим екраном
 * на сервері, куди залили архів без vendor/.
 */
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	require_once __DIR__ . '/src/Support/Autoloader.php';
	Support\Autoloader::register( __NAMESPACE__, __DIR__ . '/src' );
}

/**
 * Оголошує сумісність з HPOS і блочним checkout.
 *
 * Без цього WooCommerce вважає плагін несумісним і показує
 * попередження в налаштуваннях, навіть якщо він нічого не ламає.
 */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
);

/**
 * Запускає плагін після того, як WordPress завантажив решту.
 *
 * Плагін безглуздий без WooCommerce, тож за його відсутності
 * показуємо зрозуміле повідомлення замість фаталу.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', __NAMESPACE__ . '\\render_missing_woocommerce_notice' );
			return;
		}

		Plugin::instance()->boot();
	}
);

/**
 * Повідомлення в адмінці, коли WooCommerce не активний.
 *
 * @return void
 */
function render_missing_woocommerce_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'BRIX Core потребує активного WooCommerce. Плагін завантажено, але його модулі вимкнені.', 'brix-core' )
	);
}

register_activation_hook( __FILE__, array( Plugin::class, 'on_activate' ) );
register_deactivation_hook( __FILE__, array( Plugin::class, 'on_deactivate' ) );
