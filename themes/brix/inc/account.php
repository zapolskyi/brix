<?php
/**
 * Кабінет покупця: розділи й дрібні правки.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Прибирає й перейменовує розділи кабінету.
 *
 * «Завантаження» WooCommerce показує завжди, хоч цифрових товарів у
 * обсмажувальні не буває — порожній розділ виглядає як недоробка.
 *
 * @param array<string, string> $items Розділи.
 * @return array<string, string>
 */
function brix_account_menu( array $items ): array {
	unset( $items['downloads'] );

	if ( isset( $items['dashboard'] ) ) {
		$items['dashboard'] = __( 'Моя кава', 'brix' );
	}

	if ( isset( $items['edit-address'] ) ) {
		$items['edit-address'] = __( 'Адреси доставки', 'brix' );
	}

	return $items;
}
add_filter( 'woocommerce_account_menu_items', 'brix_account_menu' );

/**
 * Прибирає посилання на завантаження й з інших місць.
 *
 * Фільтр меню ховає розділ, але сама адреса лишається робочою, а
 * WooCommerce подекуди веде на неї сам.
 *
 * @param array<int, string> $endpoints Кінцеві точки.
 * @return array<int, string>
 */
function brix_account_endpoints( array $endpoints ): array {
	return array_diff( $endpoints, array( 'downloads' ) );
}
add_filter( 'woocommerce_get_query_vars', 'brix_account_endpoints' );
