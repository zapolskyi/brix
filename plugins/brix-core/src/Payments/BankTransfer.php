<?php
/**
 * Банківський переказ: спосіб оплати для замовлень за кордон.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Payments;

use Brix\Core\Contracts\Module;
use Brix\Core\Shipping\Destination;

defined( 'ABSPATH' ) || exit;

/**
 * Переказ показується лише там, де решта способів не працює.
 *
 * Накладний платіж — послуга Нової Пошти, і закордон вона не возить.
 * Картка онлайн працює скрізь, але вимагає підключеної платіжки. Доки
 * її немає, покупець із ЄС бачив у себе порожній список способів
 * оплати — і не міг оформити замовлення взагалі.
 *
 * Переказ закриває цю дірку без жодного ключа: рахунок виставляється
 * листом, посилка їде після зарахування. Для українського замовлення
 * він зайвий — там є і накладний платіж, і картка, — тож удома його
 * не показуємо.
 */
final class BankTransfer implements Module {

	/**
	 * Вішає хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		/*
		 * Раніше за накладний платіж. Той знімає себе при великій сумі,
		 * але тільки якщо покупцю лишається чим платити, — і рахує
		 * способи, які бачить. Побачивши переказ, призначений іншій
		 * країні, він зняв би себе даремно, і український checkout
		 * лишився б узагалі без оплати.
		 */
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_gateways' ), 5 );
	}

	/**
	 * Лишає переказ тільки для закордонних замовлень.
	 *
	 * @param array<string, \WC_Payment_Gateway> $gateways Способи оплати.
	 * @return array<string, \WC_Payment_Gateway>
	 */
	public function filter_gateways( $gateways ) {
		if ( ! is_array( $gateways ) || is_admin() || ! isset( $gateways['bacs'] ) ) {
			return $gateways;
		}

		if ( Destination::is_home() ) {
			unset( $gateways['bacs'] );
		}

		return $gateways;
	}
}
