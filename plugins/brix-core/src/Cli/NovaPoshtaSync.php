<?php
/**
 * Команда wp brix np-sync — вивантаження довідника Нової Пошти.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Cli;

use Brix\Core\Shipping\Api;
use Brix\Core\Shipping\Directory;

defined( 'ABSPATH' ) || exit;

/**
 * Кладе міста й відділення Нової Пошти у власну таблицю.
 *
 * Запускається там, де є ключ — зазвичай на машині розробника. На
 * публічний сервер їдуть уже дані, а ключ лишається вдома.
 */
final class NovaPoshtaSync {

	/**
	 * Розмір сторінки запиту.
	 */
	private const PAGE_SIZE = 500;

	/**
	 * Стеля сторінок — запобіжник від нескінченного циклу, якщо API
	 * почне віддавати ту саму сторінку.
	 */
	private const MAX_PAGES = 400;

	/**
	 * Вивантажує довідник.
	 *
	 * ## OPTIONS
	 *
	 * [--cities-only]
	 * : Тільки міста, без відділень.
	 *
	 * [--fresh]
	 * : Очистити довідник перед вивантаженням.
	 *
	 * ## EXAMPLES
	 *
	 *     wp brix np-sync
	 *     wp brix np-sync --fresh
	 *
	 * @param array<int, string>    $args       Позиційні аргументи.
	 * @param array<string, string> $assoc_args Іменовані аргументи.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		unset( $args );

		if ( ! Api::configured() ) {
			\WP_CLI::error(
				sprintf(
					'Ключа API немає. Додайте константу %s у wp-config.php або заповніть поле в налаштуваннях WooCommerce.',
					Api::CONSTANT
				)
			);
		}

		Directory::install();

		$api   = new Api();
		$fresh = isset( $assoc_args['fresh'] );

		if ( $fresh ) {
			Directory::clear( Directory::CITY );
			Directory::clear( Directory::WAREHOUSE );
			\WP_CLI::log( 'Довідник очищено.' );
		}

		$cities = $this->sync_cities( $api );

		if ( isset( $assoc_args['cities-only'] ) ) {
			$this->finish( $cities, 0 );

			return;
		}

		$this->finish( $cities, $this->sync_warehouses( $api ) );
	}

	/**
	 * Вивантажує міста.
	 *
	 * @param Api $api Клієнт.
	 * @return int
	 */
	private function sync_cities( Api $api ): int {
		$total = 0;

		for ( $page = 1; $page <= self::MAX_PAGES; $page++ ) {
			$rows = $api->page( 'Address', 'getCities', $page, self::PAGE_SIZE );

			if ( ! $rows ) {
				break;
			}

			$total += Directory::put( Directory::CITY, $this->map_cities( $rows ) );

			\WP_CLI::log( sprintf( 'Міста: сторінка %d, усього %d', $page, Directory::count( Directory::CITY ) ) );
		}

		unset( $total );

		return Directory::count( Directory::CITY );
	}

	/**
	 * Вивантажує відділення.
	 *
	 * @param Api $api Клієнт.
	 * @return int
	 */
	private function sync_warehouses( Api $api ): int {
		for ( $page = 1; $page <= self::MAX_PAGES; $page++ ) {
			$rows = $api->page( 'AddressGeneral', 'getWarehouses', $page, self::PAGE_SIZE );

			if ( ! $rows ) {
				break;
			}

			Directory::put( Directory::WAREHOUSE, $this->map_warehouses( $rows ) );

			\WP_CLI::log( sprintf( 'Відділення: сторінка %d, усього %d', $page, Directory::count( Directory::WAREHOUSE ) ) );
		}

		return Directory::count( Directory::WAREHOUSE );
	}

	/**
	 * Приводить міста до вигляду довідника.
	 *
	 * @param array<int, array<string, mixed>> $rows Дані API.
	 * @return array<int, array<string, string>>
	 */
	private function map_cities( array $rows ): array {
		$mapped = array();

		foreach ( $rows as $row ) {
			$ref = (string) ( $row['Ref'] ?? '' );

			if ( '' === $ref ) {
				continue;
			}

			$mapped[] = array(
				'ref'      => $ref,
				'city_ref' => '',
				'name'     => (string) ( $row['Description'] ?? '' ),
				'area'     => (string) ( $row['AreaDescription'] ?? '' ),
			);
		}

		return $mapped;
	}

	/**
	 * Приводить відділення до вигляду довідника.
	 *
	 * @param array<int, array<string, mixed>> $rows Дані API.
	 * @return array<int, array<string, string>>
	 */
	private function map_warehouses( array $rows ): array {
		$mapped = array();

		foreach ( $rows as $row ) {
			$ref = (string) ( $row['Ref'] ?? '' );

			if ( '' === $ref ) {
				continue;
			}

			$mapped[] = array(
				'ref'      => $ref,
				'city_ref' => (string) ( $row['CityRef'] ?? '' ),
				'name'     => (string) ( $row['Description'] ?? '' ),
				'area'     => (string) ( $row['CityDescription'] ?? '' ),
			);
		}

		return $mapped;
	}

	/**
	 * Підсумок.
	 *
	 * @param int $cities     Міст.
	 * @param int $warehouses Відділень.
	 * @return void
	 */
	private function finish( int $cities, int $warehouses ): void {
		update_option( Directory::SYNCED_AT, time() );

		\WP_CLI::success(
			sprintf( 'Довідник на місці: %d міст, %d відділень.', $cities, $warehouses )
		);

		\WP_CLI::log( 'Тепер ключ на публічному сервері не потрібен — автокомпліт читає цю таблицю.' );
	}
}
