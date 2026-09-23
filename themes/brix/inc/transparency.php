<?php
/**
 * Дані для сторінки «Прозорість».
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Рядки таблиці закупівельних цін.
 *
 * @return array<int, array<string, string>>
 */
function brix_transparency_rows(): array {
	if ( ! brix_has_core() ) {
		return array();
	}

	$ids = get_posts(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Одна сторінка, результат кешується об'єктним кешем.
			'meta_query'     => array(
				array(
					'key'     => \Brix\Core\Product\LotMeta::key( 'farmer_price' ),
					'value'   => 0,
					'type'    => 'NUMERIC',
					'compare' => '>',
				),
			),
		)
	);

	$rows = array();

	foreach ( $ids as $id ) {
		$product = wc_get_product( (int) $id );

		if ( ! $product instanceof WC_Product ) {
			continue;
		}

		$lot     = brix_lot( $product );
		$country = get_the_terms( (int) $id, 'brix_country' );
		$farm    = $lot ? $lot->farm() : null;

		$rows[] = array(
			'name'     => $product->get_name(),
			'url'      => $product->get_permalink(),
			'code'     => $lot ? $lot->code : '',
			'country'  => is_array( $country ) ? $country[0]->name : '—',
			'farm'     => $farm ? get_the_title( $farm ) : '—',
			'farm_url' => $farm ? (string) get_permalink( $farm ) : '',
			'fob'      => $lot && null !== $lot->farmer_price ? brix_dollars( $lot->farmer_price ) : '—',
			'retail'   => brix_price_per_kilo_label( $product ),
			'brix'     => $lot && null !== $lot->brix ? (string) $lot->brix : '—',
		);
	}

	// Найдорожчі для фермера лоти вгорі: сторінка про те, скільки
	// отримує ферма, а не про те, що дешевше нам.
	usort(
		$rows,
		static function ( array $a, array $b ): int {
			return (float) str_replace( array( '$', ' ', ',' ), array( '', '', '.' ), $b['fob'] )
				<=> (float) str_replace( array( '$', ' ', ',' ), array( '', '', '.' ), $a['fob'] );
		}
	);

	return $rows;
}

/**
 * Роздрібна ціна за кілограм.
 *
 * @param WC_Product $product Товар.
 * @return string
 */
function brix_price_per_kilo_label( WC_Product $product ): string {
	if ( ! $product->is_type( 'variable' ) ) {
		return '—';
	}

	foreach ( $product->get_children() as $child_id ) {
		$variation = wc_get_product( $child_id );

		if ( ! $variation instanceof WC_Product_Variation ) {
			continue;
		}

		$grams = brix_variation_grams( $variation );

		if ( $grams <= 0 ) {
			continue;
		}

		$per_kilo = (float) $variation->get_price() / ( $grams / 1000 );

		// Округлення до точності валюти: у гривні копійок немає, в
		// євро без центів 12,92 перетворилось би на 13.
		return wp_strip_all_tags( wc_price( round( $per_kilo, wc_get_price_decimals() ) ) );
	}

	return '—';
}

/**
 * Ціна фермеру в доларах, розділювачі — за мовою сторінки.
 *
 * FOB завжди в доларах, тож wc_price() тут не годиться: він малює
 * валюту магазину. А розділювачі числа мовно залежні — «24,50»
 * українською й «24.50» англійською.
 *
 * @param float $amount Сума.
 * @return string
 */
function brix_dollars( float $amount ): string {
	$english = function_exists( 'brix_is_en' ) && brix_is_en();

	return '$' . number_format( $amount, 2, $english ? '.' : ',', $english ? ',' : ' ' );
}
