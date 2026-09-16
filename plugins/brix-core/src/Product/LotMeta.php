<?php
/**
 * Читання паспорта лоту.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Product;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Єдине місце, де плагін торкається полів SCF для товару.
 *
 * Шаблони теми викликають `LotMeta::for_product()` і отримують готовий
 * `Lot`. Прямих `get_field()` у темі немає навмисно: поки доступ до
 * даних іде через один клас, шар полів можна замінити, не зачепивши
 * жодного шаблону.
 */
final class LotMeta implements Module {

	/** Префікс мета-ключів. */
	public const PREFIX = 'brix_lot_';

	/**
	 * Уже прочитані лоти, за ID товару.
	 *
	 * @var array<int, Lot>
	 */
	private static array $cache = array();

	/**
	 * Підписує модуль на хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		// Кеш живе в межах запиту, але збереження товару в адмінці
		// відбувається в тому ж запиті, що й наступне читання.
		add_action( 'save_post_product', array( __CLASS__, 'forget' ) );
	}

	/**
	 * Паспорт лоту для товару.
	 *
	 * @param int|\WP_Post|\WC_Product $product Товар у будь-якому вигляді.
	 * @return Lot
	 */
	public static function for_product( $product ): Lot {
		$product_id = self::resolve_id( $product );

		if ( isset( self::$cache[ $product_id ] ) ) {
			return self::$cache[ $product_id ];
		}

		$lot = new Lot(
			product_id: $product_id,
			code: self::text( $product_id, 'code' ),
			brix: self::decimal( $product_id, 'brix' ),
			sca: self::decimal( $product_id, 'sca' ),
			altitude_min: self::integer( $product_id, 'altitude_min' ),
			altitude_max: self::integer( $product_id, 'altitude_max' ),
			variety: self::text( $product_id, 'variety' ),
			farm_id: self::integer( $product_id, 'farm' ),
			region: self::text( $product_id, 'region' ),
			processing_days: self::integer( $product_id, 'processing_days' ),
			farmer_price: self::decimal( $product_id, 'farmer_price' ),
			harvest: self::text( $product_id, 'harvest' ),
			roast_date: self::date( $product_id, 'roast_date' ),
			roast_level: (int) ( self::integer( $product_id, 'roast_level' ) ?? 0 ),
			taste: self::taste( $product_id ),
			cup_notes: self::text( $product_id, 'cup_notes' ),
			brew_guide_id: self::integer( $product_id, 'brew_guide' ),
			lot_of_week: (bool) self::raw( $product_id, 'lot_of_week' ),
			note_ids: self::id_list( $product_id, 'notes' ),
		);

		self::$cache[ $product_id ] = $lot;

		return $lot;
	}

	/**
	 * Скидає кеш — цілком або для одного товару.
	 *
	 * @param int|null $product_id Товар.
	 * @return void
	 */
	public static function forget( ?int $product_id = null ): void {
		if ( null === $product_id ) {
			self::$cache = array();
			return;
		}

		unset( self::$cache[ $product_id ] );
	}

	/**
	 * Мета-ключ поля.
	 *
	 * @param string $field Коротка назва поля.
	 * @return string
	 */
	public static function key( string $field ): string {
		return self::PREFIX . $field;
	}

	/**
	 * Зводить товар до ID.
	 *
	 * @param int|\WP_Post|\WC_Product $product Товар.
	 * @return int
	 */
	private static function resolve_id( $product ): int {
		if ( is_numeric( $product ) ) {
			return (int) $product;
		}

		if ( $product instanceof \WP_Post ) {
			return (int) $product->ID;
		}

		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			return (int) $product->get_id();
		}

		return 0;
	}

	/**
	 * Сире значення поля.
	 *
	 * Через SCF, якщо він активний — тоді спрацьовують його типи
	 * й значення за замовчуванням. Без нього відкочуємось на
	 * `get_post_meta()`: дані лежать у звичайних мета-полях, тож
	 * вимкнений плагін ламає редагування, а не сайт.
	 *
	 * @param int    $product_id Товар.
	 * @param string $field      Поле.
	 * @return mixed
	 */
	private static function raw( int $product_id, string $field ) {
		$key = self::key( $field );

		if ( function_exists( 'get_field' ) ) {
			return get_field( $key, $product_id );
		}

		return get_post_meta( $product_id, $key, true );
	}

	/**
	 * Рядок без зайвих пробілів.
	 *
	 * @param int    $product_id Товар.
	 * @param string $field      Поле.
	 * @return string
	 */
	private static function text( int $product_id, string $field ): string {
		$value = self::raw( $product_id, $field );

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Дробове число або null.
	 *
	 * Порожнє поле має лишитись порожнім, а не стати нулем: «0 °Bx»
	 * у паспорті виглядає як дані, хоч це відсутність даних.
	 *
	 * @param int    $product_id Товар.
	 * @param string $field      Поле.
	 * @return float|null
	 */
	private static function decimal( int $product_id, string $field ): ?float {
		$value = self::raw( $product_id, $field );

		return ( '' === $value || null === $value || ! is_numeric( $value ) ) ? null : (float) $value;
	}

	/**
	 * Ціле число або null.
	 *
	 * @param int    $product_id Товар.
	 * @param string $field      Поле.
	 * @return int|null
	 */
	private static function integer( int $product_id, string $field ): ?int {
		$value = self::raw( $product_id, $field );

		// Поля зв'язку віддають об'єкт або масив, залежно від налаштувань.
		if ( $value instanceof \WP_Post ) {
			return (int) $value->ID;
		}

		if ( is_array( $value ) ) {
			$first = reset( $value );
			$value = $first instanceof \WP_Post ? $first->ID : $first;
		}

		return ( '' === $value || null === $value || ! is_numeric( $value ) ) ? null : (int) $value;
	}

	/**
	 * Дата або null.
	 *
	 * @param int    $product_id Товар.
	 * @param string $field      Поле.
	 * @return \DateTimeImmutable|null
	 */
	private static function date( int $product_id, string $field ): ?\DateTimeImmutable {
		$value = self::text( $product_id, $field );

		if ( '' === $value ) {
			return null;
		}

		// SCF зберігає дату як Ymd; ручний імпорт міг покласти Y-m-d.
		foreach ( array( 'Ymd', 'Y-m-d' ) as $format ) {
			$date = \DateTimeImmutable::createFromFormat( '!' . $format, $value, wp_timezone() );

			if ( $date instanceof \DateTimeImmutable ) {
				return $date;
			}
		}

		return null;
	}

	/**
	 * Список ID із поля-зв'язку, у збереженому порядку.
	 *
	 * @param int    $product_id Товар.
	 * @param string $field      Поле.
	 * @return array<int, int>
	 */
	private static function id_list( int $product_id, string $field ): array {
		$value = self::raw( $product_id, $field );

		if ( ! is_array( $value ) ) {
			return array();
		}

		$ids = array_map(
			static fn( $item ): int => $item instanceof \WP_Term ? (int) $item->term_id : (int) $item,
			$value
		);

		return array_values( array_filter( $ids ) );
	}

	/**
	 * Усі шкали смаку одним масивом.
	 *
	 * @param int $product_id Товар.
	 * @return array<string, int>
	 */
	private static function taste( int $product_id ): array {
		$scales = array();

		foreach ( array( 'acidity', 'sweetness', 'body', 'intensity', 'fruitiness' ) as $scale ) {
			$value = self::integer( $product_id, 'taste_' . $scale );

			if ( null !== $value && $value > 0 ) {
				$scales[ $scale ] = min( 5, $value );
			}
		}

		return $scales;
	}
}
