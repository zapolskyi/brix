<?php
/**
 * Підбір лотів за відповідями квізу.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Quiz;

use Brix\Core\Product\LotMeta;
use Brix\Core\Taxonomies\Registrar as Tax;

defined( 'ABSPATH' ) || exit;

/**
 * Рахує, наскільки кожен лот підходить під відповіді.
 *
 * Логіка навмисно проста й прозора: профіль користувача — це ті самі
 * шкали, що й у лоту, а збіг — відстань між ними. Так результат можна
 * пояснити покупцеві («ви просили яскраву кислотність — ось лот на 5
 * з 5»), а не ховати за непроникним алгоритмом.
 */
final class Recommender {

	/** Шкали, за якими рахується збіг. */
	private const SCALES = array( 'acidity', 'sweetness', 'body', 'fruitiness' );

	/**
	 * Профіль з відповідей: шкала → бажане значення 1–5.
	 *
	 * @param array<string, array<int, string>> $answers Відповіді.
	 * @return array<string, int>
	 */
	public static function profile( array $answers ): array {
		$profile = array();

		foreach ( Questions::all() as $question ) {
			$chosen = (array) ( $answers[ $question['key'] ] ?? array() );

			foreach ( $chosen as $value ) {
				$option = $question['options'][ $value ] ?? null;

				if ( ! is_array( $option ) ) {
					continue;
				}

				foreach ( array_merge( self::SCALES, array( 'roast' ) ) as $scale ) {
					if ( isset( $option[ $scale ] ) ) {
						// Кілька відповідей на одну шкалу усереднюються:
						// «ягоди й цитрус» дає фруктовість між 4 і 5.
						$profile[ $scale ] = isset( $profile[ $scale ] )
							? (int) round( ( $profile[ $scale ] + (int) $option[ $scale ] ) / 2 )
							: (int) $option[ $scale ];
					}
				}
			}
		}

		return $profile;
	}

	/**
	 * Помел, який треба виставити за замовчуванням.
	 *
	 * @param array<string, array<int, string>> $answers Відповіді.
	 * @return string Slug терміна помелу або порожній рядок.
	 */
	public static function grind( array $answers ): string {
		$chosen = (array) ( $answers['brew'] ?? array() );
		$option = Questions::get( 'brew' )['options'][ reset( $chosen ) ] ?? null;

		return is_array( $option ) ? (string) ( $option['grind'] ?? '' ) : '';
	}

	/**
	 * Рекомендовані лоти з відсотком збігу.
	 *
	 * @param array<string, array<int, string>> $answers Відповіді.
	 * @param int                               $limit   Скільки повернути.
	 * @return array<int, array{product: \WC_Product, match: int, reason: string}>
	 */
	public static function recommend( array $answers, int $limit = 3 ): array {
		$profile = self::profile( $answers );
		$notes   = self::wanted_notes( $answers );
		$budget  = self::budget( $answers );

		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'slug',
						'terms'    => array( 'origin', 'lab', 'core' ),
					),
				),
			)
		);

		$scored = array();

		foreach ( $ids as $id ) {
			$product = wc_get_product( (int) $id );

			if ( ! $product instanceof \WC_Product || ! $product->is_in_stock() ) {
				continue;
			}

			$lot = LotMeta::for_product( (int) $id );

			if ( ! $lot->taste ) {
				continue;
			}

			$scored[] = array(
				'product' => $product,
				'match'   => self::score( $lot, $profile, $notes, $budget, (int) $id ),
				'reason'  => self::reason( $lot, $profile ),
			);
		}

		usort( $scored, static fn( array $a, array $b ): int => $b['match'] <=> $a['match'] );

		return array_slice( $scored, 0, $limit );
	}

	/**
	 * Відсоток збігу лоту з профілем.
	 *
	 * @param \Brix\Core\Product\Lot $lot     Лот.
	 * @param array<string, int>     $profile Профіль.
	 * @param array<int, string>     $notes   Бажані групи нот.
	 * @param int|null               $budget  Стеля ціни.
	 * @param int                    $id      Товар.
	 * @return int 0–100.
	 */
	private static function score( $lot, array $profile, array $notes, ?int $budget, int $id ): int {
		$distance = 0;
		$counted  = 0;

		foreach ( self::SCALES as $scale ) {
			if ( ! isset( $profile[ $scale ], $lot->taste[ $scale ] ) ) {
				continue;
			}

			$distance += abs( $profile[ $scale ] - $lot->taste[ $scale ] );
			++$counted;
		}

		if ( isset( $profile['roast'] ) && $lot->roast_level > 0 ) {
			$distance += abs( $profile['roast'] - $lot->roast_level );
			++$counted;
		}

		// Максимальна відстань по шкалі 1–5 — чотири пункти.
		$match = $counted > 0 ? 100 - (int) round( $distance / ( $counted * 4 ) * 100 ) : 50;

		// Збіг за нотами важить помітно: саме про смак питали.
		if ( $notes && has_term( $notes, Tax::NOTE, $id ) ) {
			$match += 12;
		}

		if ( null !== $budget ) {
			$price = (float) ( $lot->product_id ? self::cheapest_price( $id ) : 0 );

			if ( $price > 0 && $price > $budget ) {
				$match -= 10;
			}
		}

		return max( 0, min( 99, $match ) );
	}

	/**
	 * Найдешевша ціна товару.
	 *
	 * @param int $id Товар.
	 * @return float
	 */
	private static function cheapest_price( int $id ): float {
		$product = wc_get_product( $id );

		if ( ! $product instanceof \WC_Product ) {
			return 0.0;
		}

		return $product->is_type( 'variable' )
			? (float) $product->get_variation_price( 'min' )
			: (float) $product->get_price();
	}

	/**
	 * Пояснення, чому саме цей лот.
	 *
	 * @param \Brix\Core\Product\Lot $lot     Лот.
	 * @param array<string, int>     $profile Профіль.
	 * @return string
	 */
	private static function reason( $lot, array $profile ): string {
		if ( isset( $profile['acidity'] ) && $profile['acidity'] >= 4 && ( $lot->taste['acidity'] ?? 0 ) >= 4 ) {
			return __( 'Найяскравіша кислотність', 'brix-core' );
		}

		if ( isset( $profile['body'] ) && $profile['body'] >= 4 && ( $lot->taste['body'] ?? 0 ) >= 4 ) {
			return __( 'Тримає молоко', 'brix-core' );
		}

		if ( ( $lot->taste['sweetness'] ?? 0 ) >= 5 ) {
			return __( 'Найсолодший у добірці', 'brix-core' );
		}

		return __( 'Наймʼякший варіант', 'brix-core' );
	}

	/**
	 * Групи нот, обрані у квізі.
	 *
	 * @param array<string, array<int, string>> $answers Відповіді.
	 * @return array<int, string>
	 */
	private static function wanted_notes( array $answers ): array {
		$question = Questions::get( 'notes' );
		$slugs    = array();

		foreach ( (array) ( $answers['notes'] ?? array() ) as $value ) {
			$option = $question['options'][ $value ] ?? null;

			if ( is_array( $option ) && ! empty( $option['note'] ) ) {
				$slugs[] = (string) $option['note'];
			}
		}

		return $slugs;
	}

	/**
	 * Стеля бюджету.
	 *
	 * @param array<string, array<int, string>> $answers Відповіді.
	 * @return int|null
	 */
	private static function budget( array $answers ): ?int {
		$chosen = (array) ( $answers['budget'] ?? array() );
		$option = Questions::get( 'budget' )['options'][ reset( $chosen ) ] ?? null;

		return is_array( $option ) && isset( $option['price'] ) ? (int) $option['price'] : null;
	}
}
