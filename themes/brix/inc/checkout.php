<?php
/**
 * Checkout: поля, підписи, дрібні правки.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Прибирає й перейменовує поля checkout під український магазин.
 *
 * Менше полів — більше замовлень. Компанія, друга адреса й область
 * для доставки Новою Поштою не потрібні: відділення однозначно
 * визначає і місто, і область.
 *
 * @param array<string, array<string, mixed>> $fields Поля.
 * @return array<string, array<string, mixed>>
 */
function brix_checkout_fields( array $fields ): array {
	unset(
		$fields['billing']['billing_company'],
		$fields['billing']['billing_address_2'],
		$fields['billing']['billing_state'],
		$fields['shipping']['shipping_company'],
		$fields['shipping']['shipping_address_2'],
		$fields['shipping']['shipping_state']
	);

	if ( isset( $fields['billing']['billing_phone'] ) ) {
		$fields['billing']['billing_phone']['placeholder'] = '+38 0__ ___ __ __';
		$fields['billing']['billing_phone']['priority']    = 25;
		$fields['billing']['billing_phone']['required']    = true;
	}

	if ( isset( $fields['billing']['billing_email'] ) ) {
		$fields['billing']['billing_email']['placeholder'] = 'ваш@email.com';
		$fields['billing']['billing_email']['description'] = __( 'Надішлемо номер накладної й дату обсмаження.', 'brix' );
	}

	if ( isset( $fields['order']['order_comments'] ) ) {
		$fields['order']['order_comments']['label']       = __( 'Коментар до замовлення', 'brix' );
		$fields['order']['order_comments']['placeholder'] = __( 'Помел під іншу техніку, побажання до пакування, дата доставки', 'brix' );
	}

	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'brix_checkout_fields' );

/**
 * Підписи адресних полів під доставку Новою Поштою.
 *
 * Правити їх через `woocommerce_checkout_fields` марно: локаль країни
 * застосовується пізніше й повертає «Назва вулиці» назад. Локаль
 * будується саме з цього фільтра.
 *
 * @param array<string, array<string, mixed>> $fields Поля адреси.
 * @return array<string, array<string, mixed>>
 */
function brix_address_fields( array $fields ): array {
	if ( isset( $fields['address_1'] ) ) {
		$fields['address_1']['label']       = __( 'Адреса або відділення', 'brix' );
		$fields['address_1']['placeholder'] = __( 'Відділення №12, вул. Кирилівська, 41', 'brix' );
	}

	if ( isset( $fields['city'] ) ) {
		$fields['city']['label']       = __( 'Місто', 'brix' );
		$fields['city']['placeholder'] = __( 'Київ', 'brix' );
	}

	// Індекс Новій Пошті не потрібен: відділення однозначне саме собою.
	if ( isset( $fields['postcode'] ) ) {
		$fields['postcode']['required'] = false;
		$fields['postcode']['label']    = __( 'Поштовий індекс', 'brix' );
	}

	return $fields;
}
add_filter( 'woocommerce_default_address_fields', 'brix_address_fields' );

/**
 * Класи теми на полях Woo, щоб не переписувати їх селекторами.
 *
 * @param array<string, mixed> $args Аргументи поля.
 * @return array<string, mixed>
 */
function brix_form_field_args( array $args ): array {
	$args['input_class'][] = 'brix-input';

	if ( 'select' === ( $args['type'] ?? '' ) || 'country' === ( $args['type'] ?? '' ) || 'state' === ( $args['type'] ?? '' ) ) {
		$args['input_class'][] = 'brix-select';
	}

	$args['label_class'][] = 'brix-field__label';

	return $args;
}
add_filter( 'woocommerce_form_field_args', 'brix_form_field_args' );

/**
 * Пачка замість фото в підсумку замовлення.
 *
 * @return void
 */
function brix_checkout_thumbnails(): void {
	add_filter( 'woocommerce_cart_item_name', 'brix_checkout_item_name', 10, 2 );
}
add_action( 'woocommerce_checkout_before_order_review', 'brix_checkout_thumbnails' );

/**
 * Назва позиції в підсумку: лот, під ним вага й помел.
 *
 * @param string               $name      Назва.
 * @param array<string, mixed> $cart_item Позиція.
 * @return string
 */
function brix_checkout_item_name( string $name, array $cart_item ): string {
	if ( ! is_checkout() ) {
		return $name;
	}

	$product = wc_get_product( $cart_item['product_id'] );

	if ( ! $product instanceof WC_Product ) {
		return $name;
	}

	$options = brix_cart_item_options( $cart_item );

	$out = '<span class="brix-order__name">' . esc_html( $product->get_name() ) . '</span>';

	if ( $options ) {
		$out .= '<span class="brix-order__options">' . esc_html( implode( ' · ', $options ) ) . '</span>';
	}

	return $out;
}

/**
 * Прибирає стандартні таблиці із сторінки подяки.
 *
 * WooCommerce друкує там «Подробиці замовлення» й «Платіжна адреса»
 * власною розміткою. Вона дублює те, що сторінка вже показала зверху,
 * і приходить без наших стилів — заголовки таблиці виходять кеглем
 * заголовка сторінки. Замість неї шаблон малює свій компактний список.
 *
 * @return void
 */
function brix_strip_default_thankyou(): void {
	remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );
}
add_action( 'wp', 'brix_strip_default_thankyou' );
