<?php
/**
 * Методи доставки, якими їх бачить покупець.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Shipping;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Зводить методи доставки до двох варіантів: привезти або забрати.
 *
 * WooCommerce тримає «безкоштовну доставку» окремим методом, тож коли
 * кошик переходить поріг, покупець бачить одразу три рядки: «Нова
 * Пошта — 80 ₴», «Самовивіз» і «Безкоштовна доставка». Перший і третій
 * — та сама Нова Пошта, просто з різною ціною, але з назв це не
 * зрозуміло, і дехто справді обирає платний варіант там, де доставка
 * вже безкоштовна.
 *
 * Тому щойно безкоштовний метод доступний, платний зникає, а
 * безкоштовний бере його назву. Вибір лишається тим самим питанням:
 * привезти чи забрати самому.
 */
final class Rates implements Module {

	/**
	 * Вішає хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_package_rates', array( $this, 'collapse' ), 20 );
	}

	/**
	 * Прибирає платну ставку, коли доступна безкоштовна.
	 *
	 * @param array<string, \WC_Shipping_Rate> $rates Ставки пакунка.
	 * @return array<string, \WC_Shipping_Rate>
	 */
	public function collapse( array $rates ): array {
		$free = array();

		foreach ( $rates as $key => $rate ) {
			if ( 'free_shipping' === $rate->get_method_id() ) {
				$free[ $key ] = $rate;
			}
		}

		if ( ! $free ) {
			return $rates;
		}

		$out  = array();
		$done = false;

		foreach ( $rates as $key => $rate ) {
			if ( isset( $free[ $key ] ) ) {
				// Безкоштовна ставка стане на місце платної нижче.
				continue;
			}

			if ( 'flat_rate' !== $rate->get_method_id() || $done ) {
				$out[ $key ] = $rate;
				continue;
			}

			/*
			 * Безкоштовна ставка займає місце платної, а не додається
			 * після неї. Порядок тут не косметика: у класичному
			 * checkout WooCommerce підставляє першу ставку пакунка як
			 * вибрану за замовчуванням. Якби безкоштовна доставка
			 * просто витіснила платну з кінця списку, першим лишався б
			 * самовивіз — і покупець із Львова, не придивившись,
			 * замовив би забрати каву в Києві.
			 */
			foreach ( $free as $free_key => $free_rate ) {
				$free_rate->set_label( $rate->get_label() );
				$out[ $free_key ] = $free_rate;
			}

			$done = true;
		}

		return $done ? $out : $rates;
	}
}
