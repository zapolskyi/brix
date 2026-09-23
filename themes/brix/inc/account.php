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
		$items['edit-address'] = __( 'Адреса доставки', 'brix' );
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

/**
 * «Повторити» на сторінці замовлення — наше, а не order-again.
 *
 * Стандартна кнопка WooCommerce мовчки пропускає лоти, яких уже немає
 * в продажу, і покупець дізнається про це лише в кошику. Кнопка з
 * brix-core називає кожен пропущений лот. Двох однакових кнопок з
 * різною поведінкою на одній сторінці бути не має.
 *
 * @param WC_Order $order Замовлення.
 * @return void
 */
function brix_order_repeat_button( $order ): void {
	if ( ! brix_has_core() || ! $order instanceof WC_Order || ! is_user_logged_in() ) {
		return;
	}

	if ( (int) $order->get_customer_id() !== get_current_user_id() ) {
		return;
	}

	if ( ! $order->is_paid() && ! $order->has_status( array( 'processing', 'completed' ) ) ) {
		return;
	}

	printf(
		'<p class="brix-order-again"><a class="brix-btn brix-btn--outline" href="%s">%s</a></p>',
		esc_url( \Brix\Core\Orders\Repeat::url( $order ) ),
		esc_html__( 'Повторити замовлення', 'brix' )
	);
}
remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button' );
add_action( 'woocommerce_order_details_after_order_table', 'brix_order_repeat_button' );

/**
 * Назва адреси на сторінці її редагування.
 *
 * WooCommerce називає поля checkout «платіжною адресою», а для покупця
 * це адреса, куди їде кава. Заголовок форми редагування — той самий,
 * що й картка в кабінеті.
 *
 * @param string $title Заголовок сторінки.
 * @param string $endpoint Кінцева точка.
 * @return string
 */
function brix_account_endpoint_title( $title, $endpoint ) {
	return 'edit-address' === $endpoint ? __( 'Адреса доставки', 'brix' ) : $title;
}
add_filter( 'woocommerce_endpoint_edit-address_title', 'brix_account_endpoint_title', 10, 2 );
add_filter( 'woocommerce_my_account_edit_address_title', static fn() => __( 'Адреса доставки', 'brix' ) );
