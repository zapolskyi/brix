<?php
/**
 * Кошик: прогрес до безкоштовної доставки й дрібні правки.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Поріг безкоштовної доставки.
 *
 * Береться з методу «Безкоштовна доставка» в зоні поточного покупця,
 * тож смуга прогресу в кошику й реальне правило WooCommerce — це одне
 * й те саме число. Нуль означає, що в зоні такого методу немає, і
 * смугу показувати нема сенсу.
 *
 * @return float
 */
function brix_free_shipping_threshold(): float {
	$threshold = 0.0;

	/*
	 * Поріг живе в методі доставки, а не в коді теми. Доки він був
	 * константою, смуга в кошику могла обіцяти безкоштовну доставку
	 * раніше або пізніше, ніж її реально давав WooCommerce, — і
	 * помітити це можна було б тільки на checkout.
	 */
	if ( function_exists( 'wc_get_chosen_shipping_method_ids' ) && WC()->cart ) {
		foreach ( brix_free_shipping_methods() as $method ) {
			$amount = (float) $method->get_option( 'min_amount' );

			if ( $amount > 0 && ( 0.0 === $threshold || $amount < $threshold ) ) {
				$threshold = $amount;
			}
		}
	}

	/**
	 * Сума, від якої доставка безкоштовна.
	 *
	 * @param float $threshold Поріг у гривнях.
	 */
	return (float) apply_filters( 'brix_free_shipping_threshold', $threshold );
}

/**
 * Методи безкоштовної доставки в зоні поточного покупця.
 *
 * @return array<int, WC_Shipping_Method>
 */
function brix_free_shipping_methods(): array {
	$packages = WC()->cart ? WC()->cart->get_shipping_packages() : array();

	if ( ! $packages ) {
		return array();
	}

	$zone  = wc_get_shipping_zone( reset( $packages ) );
	$found = array();

	foreach ( $zone->get_shipping_methods( true ) as $method ) {
		if ( 'free_shipping' === $method->id ) {
			$found[] = $method;
		}
	}

	return $found;
}

/**
 * Скільки лишилось до безкоштовної доставки.
 *
 * @return array{left: float, percent: float, reached: bool}
 */
function brix_free_shipping_progress(): array {
	$threshold = brix_free_shipping_threshold();
	$subtotal  = (float) ( WC()->cart ? WC()->cart->get_displayed_subtotal() : 0 );

	if ( $threshold <= 0 ) {
		return array(
			'left'    => 0.0,
			'percent' => 100.0,
			'reached' => true,
		);
	}

	return array(
		'left'    => max( 0, $threshold - $subtotal ),
		'percent' => min( 100, round( $subtotal / $threshold * 100, 2 ) ),
		'reached' => $subtotal >= $threshold,
	);
}

/**
 * Пачка замість фото в рядку кошика.
 *
 * Товар у кошику — це варіація, а пачку малюємо з даних батьківського
 * товару: обробка й код лоту живуть саме там.
 *
 * @param string               $thumbnail Стандартна мініатюра Woo.
 * @param array<string, mixed> $cart_item Позиція кошика.
 * @return string
 */
function brix_cart_thumbnail( string $thumbnail, array $cart_item ): string {
	$product = wc_get_product( $cart_item['product_id'] );

	if ( ! $product instanceof WC_Product ) {
		return $thumbnail;
	}

	// Фото є — лишаємо його: у обладнання пачки немає.
	if ( $product->get_image_id() || ! brix_is_coffee( $product ) ) {
		return $thumbnail;
	}

	ob_start();
	$style = brix_pack_style( $product );
	printf( '<span class="brix-cart__pack %s">', esc_attr( $style ? 'brix-card__stage--' . $style : '' ) );
	brix_the_pack( $product, '72%' );
	echo '</span>';

	return (string) ob_get_clean();
}
add_filter( 'woocommerce_cart_item_thumbnail', 'brix_cart_thumbnail', 10, 2 );

/**
 * Вага й помел позиції кошика, людською мовою.
 *
 * У `$cart_item['variation']` лежать slug термінів; покупцеві потрібні
 * назви. Порядок беремо з самого масиву — він відповідає порядку
 * атрибутів товару.
 *
 * @param array<string, mixed> $cart_item Позиція кошика.
 * @return array<int, string>
 */
function brix_cart_item_options( array $cart_item ): array {
	$options = array();

	foreach ( (array) ( $cart_item['variation'] ?? array() ) as $key => $value ) {
		if ( '' === $value ) {
			continue;
		}

		$taxonomy = str_replace( 'attribute_', '', (string) $key );

		if ( taxonomy_exists( $taxonomy ) ) {
			$term = get_term_by( 'slug', (string) $value, $taxonomy );

			if ( $term instanceof WP_Term ) {
				$options[] = $term->name;
				continue;
			}
		}

		$options[] = (string) $value;
	}

	return $options;
}

/**
 * Прибирає посилання «Купон» у кошику, якщо купонів немає.
 *
 * Порожнє поле промокоду на checkout — класичний спосіб відправити
 * покупця шукати знижку деінде замість того, щоб купити.
 *
 * @param bool $enabled Чи ввімкнені купони.
 * @return bool
 */
function brix_coupons_enabled( bool $enabled ): bool {
	if ( ! $enabled ) {
		return false;
	}

	$coupons = get_posts(
		array(
			'post_type'      => 'shop_coupon',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);

	return (bool) $coupons;
}
add_filter( 'woocommerce_coupons_enabled', 'brix_coupons_enabled' );
