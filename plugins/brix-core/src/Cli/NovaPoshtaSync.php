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
	 * Модель, яка віддає міста, — решта віддає відділення.
	 */
	private const CITY_MODEL = 'Address';

	/**
	 * Категорії, у яких покупець може забрати посилку.
	 *
	 * DropOff приймає відправлення, але не видає їх, а Fulfillment —
	 * внутрішні склади Нової Пошти. Обидві в списку для покупця це
	 * помилка: у Житомирі «Склад №1 (Фулфілмент)» стояв першим.
	 */
	private const PICKUP = array( 'Branch', 'Postomat', 'Store' );

	/**
	 * Стеля сторінок — запобіжник від нескінченного циклу, якщо API
	 * почне віддавати ту саму сторінку.
	 */
	private const MAX_PAGES = 400;

	/**
	 * Пауза між сторінками, мікросекунд.
	 */
	private const PAUSE = 400000;

	/**
	 * Скільки разів перепитати сторінку, яка не прийшла.
	 */
	private const RETRIES = 6;

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
		return $this->sync( $api, 'Address', 'getCities', Directory::CITY, 'Міста' );
	}

	/**
	 * Загальний цикл вивантаження.
	 *
	 * Орієнтир — `totalCount` з відповіді, а не порожня сторінка:
	 * Нова Пошта притримує часті запити й відповідає тим самим
	 * порожнім масивом, яким позначає кінець даних. У першому прогоні
	 * через це набралось 500 відділень із 54 678.
	 *
	 * @param Api    $api    Клієнт.
	 * @param string $model  Модель.
	 * @param string $method Метод.
	 * @param string $kind   Вид записів у довіднику.
	 * @param string $label  Підпис для журналу.
	 * @return int
	 */
	private function sync( Api $api, string $model, string $method, string $kind, string $label ): int {
		$total = 0;

		for ( $page = 1; $page <= self::MAX_PAGES; $page++ ) {
			$answer = $this->fetch( $api, $model, $method, $page );
			$rows   = $answer['rows'];

			if ( ! $rows ) {
				break;
			}

			if ( $answer['total'] > 0 ) {
				$total = $answer['total'];
			}

			Directory::put( $kind, self::CITY_MODEL === $model ? $this->map_cities( $rows ) : $this->map_warehouses( $rows ) );

			$have = Directory::count( $kind );

			\WP_CLI::log( sprintf( '%s: сторінка %d, %d із %d', $label, $page, $have, $total ) );

			// Кінець даних визначає лічильник, а не порожня відповідь.
			if ( $total > 0 && $page * self::PAGE_SIZE >= $total ) {
				break;
			}
		}

		return Directory::count( $kind );
	}

	/**
	 * Читає сторінку, перепитуючи порожню відповідь.
	 *
	 * Нова Пошта притримує занадто часті запити й відповідає порожнім
	 * масивом — тим самим, яким позначає кінець даних. Без повторів
	 * вивантаження зупинялось на першому ж тротлінгу: у першому
	 * прогоні так набралось 500 відділень замість сорока тисяч.
	 *
	 * @param Api    $api    Клієнт.
	 * @param string $model  Модель.
	 * @param string $method Метод.
	 * @param int    $page   Сторінка.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	private function fetch( Api $api, string $model, string $method, int $page ): array {
		$answer = array(
			'rows'  => array(),
			'total' => 0,
		);

		for ( $try = 1; $try <= self::RETRIES; $try++ ) {
			$answer = $api->page( $model, $method, $page, self::PAGE_SIZE );

			if ( $answer['rows'] ) {
				usleep( self::PAUSE );

				return $answer;
			}

			// Пауза росте з кожною спробою: якщо це справді кінець
			// даних, ми втратимо кілька секунд; якщо тротлінг —
			// дочекаємось.
			usleep( self::PAUSE * $try * 4 );
		}

		return $answer;
	}

	/**
	 * Вивантажує відділення.
	 *
	 * @param Api $api Клієнт.
	 * @return int
	 */
	private function sync_warehouses( Api $api ): int {
		return $this->sync( $api, 'AddressGeneral', 'getWarehouses', Directory::WAREHOUSE, 'Відділення' );
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
			$ref      = (string) ( $row['Ref'] ?? '' );
			$category = (string) ( $row['CategoryOfWarehouse'] ?? '' );

			if ( '' === $ref || ! in_array( $category, self::PICKUP, true ) ) {
				continue;
			}

			$mapped[] = array(
				'ref'      => $ref,
				'city_ref' => (string) ( $row['CityRef'] ?? '' ),
				'name'     => (string) ( $row['Description'] ?? '' ),
				'area'     => (string) ( $row['CityDescription'] ?? '' ),
				'category' => $category,
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
