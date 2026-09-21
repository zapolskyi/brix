<?php
/**
 * Вибір варіації без JavaScript.
 *
 * Стандартна форма варіативного товару WooCommerce не працює без
 * скрипта: вагу й помел обирає JS, він же підставляє variation_id.
 * Тут та сама логіка зроблена сервером — вибір їде в адресному рядку
 * як ?attribute_vaha=250+г, сторінка перезавантажується й показує
 * ціну та наявність саме цієї комбінації.
 *
 * На фазі 4 поверх цього ляже AJAX і прибере перезавантаження.
 * Але й без нього товар можна купити, а посиланням на конкретну
 * вагу й помел — поділитись.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Джерело вибору ваги й помелу.
 *
 * Зазвичай це адресний рядок, але той самий вибір приходить і
 * REST-запитом, коли помел міняють без перезавантаження. Щоб підбір
 * варіації лишався одним кодом на обидва шляхи, читачі беруть
 * параметри звідси, а не з $_GET напряму.
 *
 * @param array<string, mixed>|null $params Параметри або null для читання.
 * @return array<string, mixed>
 */
function brix_variation_request( ?array $params = null ): array {
	static $override = null;

	if ( null !== $params ) {
		$override = $params;
	}

	if ( null !== $override ) {
		return $override;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Читання вибору ваги й помелу з адреси, не обробка форми.
	return wp_unslash( $_GET );
}

/**
 * Обрані значення атрибутів з адресного рядка.
 *
 * @param WC_Product $product Товар.
 * @return array<string, string> Ключ — attribute_назва, значення — обране.
 */
function brix_selected_attributes( WC_Product $product ): array {
	$selected = array();

	foreach ( $product->get_attributes() as $attribute ) {
		if ( ! $attribute->get_variation() ) {
			continue;
		}

		$key = 'attribute_' . sanitize_title( $attribute->get_name() );

		$request = brix_variation_request();

		if ( isset( $request[ $key ] ) ) {
			$value = sanitize_text_field( (string) $request[ $key ] );

			if ( in_array( $value, brix_attribute_values( $attribute, $product->get_id() ), true ) ) {
				$selected[ $key ] = $value;
			}
		}
	}

	return $selected;
}

/**
 * Значення атрибута у вигляді, який іде в адресний рядок.
 *
 * Глобальні атрибути зберігають ID термінів, а у варіаціях і в URL
 * стоять slug. Локальні — просто рядки.
 *
 * Порядок береться через `wc_get_product_terms()`, а не через
 * `$attribute->get_terms()`: тільки перша шанує налаштування
 * сортування атрибута. Інакше «1 кг» стає перед «250 г» за абеткою,
 * а помели шикуються не так, як у макеті.
 *
 * @param WC_Product_Attribute $attribute  Атрибут.
 * @param int                  $product_id Товар.
 * @return array<int, string>
 */
function brix_attribute_values( WC_Product_Attribute $attribute, int $product_id = 0 ): array {
	if ( ! $attribute->is_taxonomy() ) {
		return array_map( 'strval', $attribute->get_options() );
	}

	$taxonomy = $attribute->get_name();
	$terms    = $product_id ? get_the_terms( $product_id, $taxonomy ) : $attribute->get_terms();

	if ( ! is_array( $terms ) || ! $terms ) {
		return array();
	}

	/*
	 * Сортуємо самі за мета-полем order_{taxonomy}. WooCommerce
	 * зберігає там порядок значень, але застосовує його лише коли
	 * терміни йдуть через get_terms(); на шляху через кеш об'єктних
	 * термінів — а це і get_the_terms(), і wc_get_product_terms() —
	 * порядок губиться, і «1 кг» стає перед «250 г» за абеткою.
	 */
	usort(
		$terms,
		static function ( WP_Term $a, WP_Term $b ) use ( $taxonomy ): int {
			$order_a = get_term_meta( $a->term_id, 'order_' . $taxonomy, true );
			$order_b = get_term_meta( $b->term_id, 'order_' . $taxonomy, true );

			if ( '' === $order_a && '' === $order_b ) {
				return strnatcasecmp( $a->name, $b->name );
			}

			return (int) $order_a <=> (int) $order_b;
		}
	);

	return wp_list_pluck( $terms, 'slug' );
}

/**
 * Підпис значення атрибута для покупця.
 *
 * @param WC_Product_Attribute $attribute Атрибут.
 * @param string               $value     Значення (slug або рядок).
 * @return string
 */
function brix_attribute_label( WC_Product_Attribute $attribute, string $value ): string {
	if ( ! $attribute->is_taxonomy() ) {
		return $value;
	}

	$term = get_term_by( 'slug', $value, $attribute->get_name() );

	return $term instanceof WP_Term ? $term->name : $value;
}

/**
 * Варіація, що відповідає вибору. Null, якщо вибір неповний.
 *
 * @param WC_Product            $product  Товар.
 * @param array<string, string> $selected Обрані атрибути.
 * @return WC_Product_Variation|null
 */
function brix_matching_variation( WC_Product $product, array $selected ): ?WC_Product_Variation {
	if ( ! $product instanceof WC_Product_Variable ) {
		return null;
	}

	$needed = 0;

	foreach ( $product->get_attributes() as $attribute ) {
		if ( $attribute->get_variation() ) {
			++$needed;
		}
	}

	if ( count( $selected ) < $needed ) {
		return null;
	}

	$data_store   = WC_Data_Store::load( 'product' );
	$variation_id = $data_store->find_matching_product_variation( $product, $selected );

	if ( ! $variation_id ) {
		return null;
	}

	$variation = wc_get_product( $variation_id );

	return $variation instanceof WC_Product_Variation ? $variation : null;
}

/**
 * Варіація за замовчуванням: найдешевша доступна.
 *
 * Показувати «від 590 ₴» без жодного обраного варіанта — зайвий крок
 * для покупця: він однаково має щось обрати. Тому за замовчуванням
 * обираємо найменшу вагу й цільне зерно — так у макеті.
 *
 * @param WC_Product $product Товар.
 * @return array<string, string>
 */
function brix_default_attributes( WC_Product $product ): array {
	$defaults = $product->get_default_attributes();
	$selected = array();

	foreach ( $product->get_attributes() as $attribute ) {
		if ( ! $attribute->get_variation() ) {
			continue;
		}

		$name = sanitize_title( $attribute->get_name() );

		if ( isset( $defaults[ $name ] ) && '' !== $defaults[ $name ] ) {
			$selected[ 'attribute_' . $name ] = (string) $defaults[ $name ];
		}
	}

	// Магазин сам вибору не задав — беремо найдешевшу доступну
	// комбінацію. Порядок термінів для цього не годиться: «1 кг»
	// стоїть перед «250 г» за абеткою, і покупець бачив би
	// кілограмову ціну як стартову.
	if ( count( $selected ) === brix_variation_attribute_count( $product ) ) {
		return $selected;
	}

	return brix_cheapest_attributes( $product );
}

/**
 * Скільки атрибутів бере участь у варіаціях.
 *
 * @param WC_Product $product Товар.
 * @return int
 */
function brix_variation_attribute_count( WC_Product $product ): int {
	$count = 0;

	foreach ( $product->get_attributes() as $attribute ) {
		if ( $attribute->get_variation() ) {
			++$count;
		}
	}

	return $count;
}

/**
 * Атрибути найдешевшої доступної варіації.
 *
 * @param WC_Product $product Товар.
 * @return array<string, string>
 */
function brix_cheapest_attributes( WC_Product $product ): array {
	if ( ! $product instanceof WC_Product_Variable ) {
		return array();
	}

	$cheapest = null;

	foreach ( $product->get_children() as $child_id ) {
		$variation = wc_get_product( $child_id );

		if ( ! $variation instanceof WC_Product_Variation || ! $variation->is_in_stock() ) {
			continue;
		}

		if ( null === $cheapest || (float) $variation->get_price() < (float) $cheapest->get_price() ) {
			$cheapest = $variation;
		}
	}

	if ( ! $cheapest ) {
		return array();
	}

	$selected = array();

	foreach ( $cheapest->get_attributes() as $taxonomy => $value ) {
		if ( '' !== $value ) {
			$selected[ 'attribute_' . $taxonomy ] = (string) $value;
		}
	}

	return $selected;
}

/**
 * Адреса товару з іншим значенням одного атрибута.
 *
 * Саме вона робить селектор робочим без JavaScript: кожен варіант
 * ваги чи помелу — посилання на той самий товар з іншим набором
 * параметрів.
 *
 * @param WC_Product            $product  Товар.
 * @param array<string, string> $selected Поточний вибір.
 * @param string                $key      Атрибут, який міняємо.
 * @param string                $value    Нове значення.
 * @return string
 */
function brix_variation_url( WC_Product $product, array $selected, string $key, string $value ): string {
	$selected[ $key ] = $value;

	return add_query_arg( $selected, $product->get_permalink() );
}

/**
 * Ціна за кілограм — щоб ваги можна було порівняти.
 *
 * @param WC_Product $variation Варіація.
 * @return string Порожній рядок, якщо вагу не вдалося розібрати.
 */
function brix_price_per_kilo( WC_Product $variation ): string {
	$grams = brix_variation_grams( $variation );

	if ( $grams <= 0 ) {
		return '';
	}

	$per_kilo = (float) $variation->get_price() / ( $grams / 1000 );

	/* translators: %s — ціна за кілограм. */
	return sprintf( __( '%s за кг', 'brix' ), wp_strip_all_tags( wc_price( round( $per_kilo ) ) ) );
}

/**
 * Вага варіації в грамах.
 *
 * Вага — текстовий атрибут («250 г», «1 кг»), бо саме так вона
 * показується покупцеві. Для арифметики розбираємо рядок.
 *
 * @param WC_Product $variation Варіація.
 * @return int Грами, або 0, якщо не розібрали.
 */
function brix_variation_grams( WC_Product $variation ): int {
	foreach ( $variation->get_attributes() as $taxonomy => $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			continue;
		}

		// У таксономічних атрибутів тут slug; вага людською мовою —
		// у назві терміна.
		if ( taxonomy_exists( (string) $taxonomy ) ) {
			$term = get_term_by( 'slug', $value, (string) $taxonomy );

			if ( $term instanceof WP_Term ) {
				$value = $term->name;
			}
		}

		if ( preg_match( '/([\d.,]+)\s*(кг|г)\b/u', $value, $match ) ) {
			$number = (float) str_replace( ',', '.', $match[1] );

			return (int) round( 'кг' === $match[2] ? $number * 1000 : $number );
		}
	}

	return 0;
}
