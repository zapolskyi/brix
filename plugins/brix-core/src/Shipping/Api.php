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
	 * Чи налаштований доступ до API.
	 *
	 * @return bool
	 */
	public static function configured(): bool {
		return '' !== trim( (string) get_option( self::OPTION_KEY, '' ) );
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
						'apiKey'           => (string) get_option( self::OPTION_KEY, '' ),
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
}
