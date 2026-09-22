<?php
/**
 * Оптові ціни й мінімальне замовлення.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Wholesale;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Знижка й мінімум ваги для оптових клієнтів.
 *
 * Ціна рахується від роздрібної, а не задається окремим полем на кожній
 * варіації: варіацій дев'яносто, і другий набір цін розійшовся б із
 * першим за перший же тиждень.
 */
final class Pricing implements Module {

	/**
	 * Знижка для оптових клієнтів, відсотків.
	 */
	private const DISCOUNT = 25;

	/**
	 * Мінімальна вага замовлення, кілограмів.
	 */
	private const MIN_KG = 5;

	/**
	 * Підписує модуль на хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_product_get_price', array( $this, 'price' ), 20, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'price' ), 20, 2 );
		add_filter( 'woocommerce_variation_prices_price', array( $this, 'price' ), 20, 2 );
		add_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'price_hash' ) );
		add_action( 'woocommerce_check_cart_items', array( $this, 'check_minimum' ) );
	}

	/**
	 * Чи оптовий зараз покупець.
	 *
	 * @return bool
	 */
	public static function is_wholesale(): bool {
		$user = wp_get_current_user();

		return $user->exists() && in_array( Role::ROLE, (array) $user->roles, true );
	}

	/**
	 * Оптова ціна замість роздрібної.
	 *
	 * @param mixed $price   Ціна.
	 * @param mixed $product Товар.
	 * @return mixed
	 */
	public function price( $price, $product ) {
		if ( '' === $price || null === $price || ! self::is_wholesale() ) {
			return $price;
		}

		unset( $product );

		return round( (float) $price * ( 100 - self::DISCOUNT ) / 100, 2 );
	}

	/**
	 * Розділяє кеш цін варіацій для опту й роздробу.
	 *
	 * Без цього перший відкритий варіативний товар клав у кеш свої
	 * ціни, і решта покупців бачила б їх — опт показувався б усім
	 * або не показувався нікому.
	 *
	 * @param array<int, mixed> $hash Складові ключа кешу.
	 * @return array<int, mixed>
	 */
	public function price_hash( array $hash ): array {
		$hash[] = self::is_wholesale() ? 'wholesale' : 'retail';

		return $hash;
	}

	/**
	 * Не пускає оптове замовлення менше мінімуму.
	 *
	 * @return void
	 */
	public function check_minimum(): void {
		if ( ! self::is_wholesale() || ! WC()->cart ) {
			return;
		}

		$weight = (float) WC()->cart->get_cart_contents_weight();

		if ( $weight >= self::MIN_KG ) {
			return;
		}

		wc_add_notice(
			sprintf(
				/* translators: 1 — мінімум, 2 — поточна вага. */
				__( 'Оптове замовлення — від %1$s кг. Зараз у кошику %2$s кг.', 'brix-core' ),
				self::MIN_KG,
				number_format_i18n( $weight, 2 )
			),
			'error'
		);
	}
}
