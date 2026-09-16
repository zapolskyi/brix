<?php
/**
 * Запасний автозавантажувач PSR-4.
 *
 * Потрібен лише тоді, коли поруч немає vendor/autoload.php — наприклад
 * при заливці архіву плагіна на хостинг без composer install.
 * У розробці працює звичайний автозавантажувач Composer.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Мінімальний PSR-4 автозавантажувач на один простір імен.
 */
final class Autoloader {

	/**
	 * Реєструє автозавантаження для одного простору імен.
	 *
	 * @param string $prefix  Кореневий простір імен, напр. Brix\Core.
	 * @param string $baseDir Тека з класами.
	 * @return void
	 */
	public static function register( string $prefix, string $baseDir ): void {
		$prefix  = trim( $prefix, '\\' ) . '\\';
		$baseDir = rtrim( $baseDir, '/\\' ) . '/';

		spl_autoload_register(
			static function ( string $class_name ) use ( $prefix, $baseDir ): void {
				if ( ! str_starts_with( $class_name, $prefix ) ) {
					return;
				}

				$relative = substr( $class_name, strlen( $prefix ) );
				$path     = $baseDir . str_replace( '\\', '/', $relative ) . '.php';

				if ( is_readable( $path ) ) {
					require_once $path;
				}
			}
		);
	}
}
