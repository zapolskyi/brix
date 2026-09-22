<?php
/**
 * Клієнт API Нової Пошти.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Shipping;

defined( 'ABSPATH' ) || exit;

/**
 * Довідники Нової Пошти з кешуванням.
 *
 * Довідник міст і відділень майже не змінюється, а checkout смикав би
 * API на кожен натиск клавіші. Тому кожна відповідь лягає в transient:
 * без цього автокомпліт залежав би від доступності чужого сервера
 * рівно в той момент, коли покупець набирає адресу.
 */
final class Api {

	/**
	 * Адреса API.
	 */
	private const ENDPOINT = 'https://api.novaposhta.ua/v2.0/json/';

	/**
	 * Скільки жити кешу довідників, секунд.
	 */
	private const TTL = DAY_IN_SECONDS;

	/**
	 * Ключ опції з токеном.
	 */
	public const OPTION_KEY = 'brix_nova_poshta_key';

	/**
	 * Константа в wp-config.php, яка має пріоритет над опцією.
	 */
	public const CONSTANT = 'BRIX_NOVA_POSHTA_KEY';

	/**
	 * Ключ доступу до API.
	 *
	 * Спершу константа з wp-config.php, і лише потім налаштування.
	 * Ключ Нової Пошти видається на бізнес-кабінет цілком — з правом
	 * створювати накладні, — тож у базі публічного стенду йому не
	 * місце: базу видно з адмінки, вона лягає в кожен бекап.
	 *
	 * @return string
	 */
	public static function key(): string {
		if ( defined( self::CONSTANT ) && is_string( constant( self::CONSTANT ) ) ) {
			return trim( (string) constant( self::CONSTANT ) );
		}

		return trim( (string) get_option( self::OPTION_KEY, '' ) );
	}

	/**
	 * Чи налаштований доступ до API.
	 *
	 * @return bool
	 */
	public static function configured(): bool {
		return '' !== self::key();
	}

	/**
	 * Міста за початком назви.
	 *
	 * @param string $query Запит.
	 * @return array<int, array{ref: string, name: string, area: string}>
	 */
	public function settlements( string $query ): array {
		$query = trim( $query );

		if ( mb_strlen( $query ) < 2 ) {
			return array();
		}

		$data = $this->call(
			'Address',
			'searchSettlements',
			array(
				'CityName' => $query,
				'Limit'    => '12',
			),
			'np_city_' . md5( mb_strtolower( $query ) )
		);

		$addresses = $data[0]['Addresses'] ?? array();
		$found     = array();

		foreach ( (array) $addresses as $item ) {
			$found[] = array(
				'ref'  => (string) ( $item['DeliveryCity'] ?? '' ),
				'name' => (string) ( $item['MainDescription'] ?? '' ),
				'area' => trim( (string) ( $item['Area'] ?? '' ) . ' ' . (string) ( $item['Region'] ?? '' ) ),
			);
		}

		return $found;
	}

	/**
	 * Відділення й поштомати в місті.
	 *
	 * @param string $city Ідентифікатор міста.
	 * @return array<int, array{ref: string, name: string}>
	 */
	public function warehouses( string $city ): array {
		if ( '' === trim( $city ) ) {
			return array();
		}

		$data = $this->call(
			'AddressGeneral',
			'getWarehouses',
			array(
				'CityRef' => $city,
				'Limit'   => '500',
			),
			'np_wh_' . md5( $city )
		);

		$found = array();

		foreach ( (array) $data as $item ) {
			$found[] = array(
				'ref'  => (string) ( $item['Ref'] ?? '' ),
				'name' => (string) ( $item['Description'] ?? '' ),
			);
		}

		return $found;
	}

	/**
	 * Викликає метод API з кешуванням відповіді.
	 *
	 * @param string               $model      Модель.
	 * @param string               $method     Метод.
	 * @param array<string, mixed> $properties Властивості.
	 * @param string               $cache      Ключ кешу.
	 * @return array<int|string, mixed>
	 */
	private function call( string $model, string $method, array $properties, string $cache ): array {
		$cached = get_transient( $cache );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		if ( ! self::configured() ) {
			return array();
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => (string) wp_json_encode(
					array(
						'apiKey'           => self::key(),
						'modelName'        => $model,
						'calledMethod'     => $method,
						'methodProperties' => $properties,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || empty( $body['success'] ) || ! isset( $body['data'] ) ) {
			return array();
		}

		$data = (array) $body['data'];

		set_transient( $cache, $data, self::TTL );

		return $data;
	}

	/**
	 * Сторінка довідника без кешу — для синхронізації.
	 *
	 * Кеш тут навмисно не задіяний: вивантаження йде один раз і
	 * засмітило б сховище транзієнтів сотнею сторінок.
	 *
	 * Разом із даними повертає `total` — скільки записів у довіднику
	 * загалом. Без цього числа неможливо відрізнити кінець даних від
	 * тимчасової відмови: Нова Пошта в обох випадках віддає порожній
	 * масив.
	 *
	 * @param string $model  Модель.
	 * @param string $method Метод.
	 * @param int    $page   Сторінка, від 1.
	 * @param int    $limit  Розмір сторінки.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public function page( string $model, string $method, int $page, int $limit = 500 ): array {
		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 60,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => (string) wp_json_encode(
					array(
						'apiKey'           => self::key(),
						'modelName'        => $model,
						'calledMethod'     => $method,
						'methodProperties' => array(
							'Page'  => (string) max( 1, $page ),
							'Limit' => (string) $limit,
						),
					)
				),
			)
		);

		$empty = array(
			'rows'  => array(),
			'total' => 0,
		);

		if ( is_wp_error( $response ) ) {
			return $empty;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || empty( $body['success'] ) || ! isset( $body['data'] ) ) {
			return $empty;
		}

		return array(
			'rows'  => (array) $body['data'],
			'total' => (int) ( $body['info']['totalCount'] ?? 0 ),
		);
	}
}
