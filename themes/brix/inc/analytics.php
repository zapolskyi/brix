<?php
/**
 * GA4: підключення й події електронної комерції.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ідентифікатор потоку GA4.
 *
 * Живе в налаштуваннях, а не в коді: на локальному стенді й на
 * продакшені він різний, а міряти локальні кліки як продажі не варто.
 *
 * @return string
 */
function brix_ga4_id(): string {
	return (string) get_option( 'brix_ga4_id', '' );
}

/**
 * Додає поле ідентифікатора в налаштування WooCommerce.
 *
 * @param array<int, array<string, mixed>> $settings Налаштування.
 * @return array<int, array<string, mixed>>
 */
function brix_ga4_setting( array $settings ): array {
	$settings[] = array(
		'title' => __( 'Аналітика', 'brix' ),
		'type'  => 'title',
		'id'    => 'brix_ga4_options',
	);

	$settings[] = array(
		'title'   => __( 'Ідентифікатор потоку GA4', 'brix' ),
		'desc'    => __( 'Порожнє поле вимикає аналітику повністю — жодного запиту до Google.', 'brix' ),
		'id'      => 'brix_ga4_id',
		'type'    => 'text',
		'default' => '',
	);

	$settings[] = array(
		'type' => 'sectionend',
		'id'   => 'brix_ga4_options',
	);

	return $settings;
}
add_filter( 'woocommerce_general_settings', 'brix_ga4_setting', 20 );

/**
 * Підключає gtag.
 *
 * @return void
 */
function brix_ga4_script(): void {
	$id = brix_ga4_id();

	if ( '' === $id || is_admin() ) {
		return;
	}

	wp_enqueue_script(
		'brix-gtag',
		'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $id ),
		array(),
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Версію задає сам Google.
		array( 'strategy' => 'async' )
	);

	wp_add_inline_script(
		'brix-gtag',
		sprintf(
			'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",%s);',
			wp_json_encode( $id )
		),
		'after'
	);
}
add_action( 'wp_enqueue_scripts', 'brix_ga4_script', 5 );

/**
 * Надсилає подію електронної комерції.
 *
 * @param string               $name Назва події.
 * @param array<string, mixed> $data Дані.
 * @return void
 */
function brix_ga4_event( string $name, array $data ): void {
	if ( '' === brix_ga4_id() ) {
		return;
	}

	wp_add_inline_script(
		'brix-gtag',
		sprintf( 'gtag("event",%s,%s);', wp_json_encode( $name ), wp_json_encode( $data ) ),
		'after'
	);
}

/**
 * Подія перегляду товару.
 *
 * @return void
 */
function brix_ga4_view_item(): void {
	if ( ! brix_has_woocommerce() || ! is_product() ) {
		return;
	}

	$product = wc_get_product( get_the_ID() );

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	brix_ga4_event(
		'view_item',
		array(
			'currency' => get_woocommerce_currency(),
			'value'    => (float) $product->get_price(),
			'items'    => array( brix_ga4_item( $product ) ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'brix_ga4_view_item', 20 );

/**
 * Подія покупки на сторінці подяки.
 *
 * @return void
 */
function brix_ga4_purchase(): void {
	if ( ! brix_has_woocommerce() || ! is_order_received_page() ) {
		return;
	}

	$order_id = absint( get_query_var( 'order-received' ) );
	$order    = $order_id ? wc_get_order( $order_id ) : null;

	if ( ! $order instanceof WC_Order || $order->get_meta( '_brix_ga4_sent' ) ) {
		return;
	}

	$items = array();

	foreach ( $order->get_items() as $item ) {
		if ( ! $item instanceof WC_Order_Item_Product ) {
			continue;
		}

		$product = $item->get_product();

		if ( $product instanceof WC_Product ) {
			$items[] = brix_ga4_item( $product, (int) $item->get_quantity() );
		}
	}

	brix_ga4_event(
		'purchase',
		array(
			'transaction_id' => (string) $order->get_order_number(),
			'currency'       => $order->get_currency(),
			'value'          => (float) $order->get_total(),
			'shipping'       => (float) $order->get_shipping_total(),
			'items'          => $items,
		)
	);

	// Перезавантаження сторінки подяки не має рахуватись другою
	// покупкою: у GA4 це зіпсувало б і виторг, і конверсію.
	$order->update_meta_data( '_brix_ga4_sent', 1 );
	$order->save();
}
add_action( 'wp_enqueue_scripts', 'brix_ga4_purchase', 20 );

/**
 * Товар у форматі GA4.
 *
 * @param WC_Product $product  Товар.
 * @param int        $quantity Кількість.
 * @return array<string, mixed>
 */
function brix_ga4_item( WC_Product $product, int $quantity = 1 ): array {
	$categories = get_the_terms( $product->get_id(), 'product_cat' );

	return array(
		'item_id'       => $product->get_sku() ? $product->get_sku() : (string) $product->get_id(),
		'item_name'     => $product->get_name(),
		'item_category' => is_array( $categories ) && $categories ? $categories[0]->name : '',
		'price'         => (float) $product->get_price(),
		'quantity'      => max( 1, $quantity ),
	);
}
