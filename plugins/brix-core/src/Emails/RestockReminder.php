<?php
/**
 * Нагадування «Кава закінчується?».
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Emails;

use Brix\Core\Contracts\Module;
use Brix\Core\Orders\Repeat;

defined( 'ABSPATH' ) || exit;

/**
 * Через три тижні після відправки питає, чи не час повторити.
 *
 * Пачка 250 г у щоденному заварюванні закінчується приблизно за три
 * тижні. Лист приходить саме тоді, коли питання доречне, а не «через
 * місяць, бо так у всіх».
 *
 * Черга — Action Scheduler, який приходить із WooCommerce: власний
 * cron у WordPress не переживає видалення замовлення й не показує,
 * що саме стоїть у черзі.
 */
final class RestockReminder implements Module {

	/**
	 * Хук запланованої дії.
	 */
	private const HOOK = 'brix_restock_reminder';

	/**
	 * Група дій у планувальнику.
	 */
	private const GROUP = 'brix';

	/**
	 * Через скільки нагадувати.
	 */
	private const AFTER = 21 * DAY_IN_SECONDS;

	/**
	 * Підписує модуль на хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_order_status_completed', array( $this, 'schedule' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'cancel' ) );
		add_action( self::HOOK, array( $this, 'send' ) );
	}

	/**
	 * Ставить нагадування в чергу.
	 *
	 * @param int $order_id Замовлення.
	 * @return void
	 */
	public function schedule( int $order_id ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order || ! is_email( $order->get_billing_email() ) ) {
			return;
		}

		// Нагадувати про чайник безглуздо — тільки про каву.
		if ( ! $this->has_coffee( $order ) ) {
			return;
		}

		$this->cancel( $order_id );

		as_schedule_single_action(
			time() + self::AFTER,
			self::HOOK,
			array( 'order_id' => $order_id ),
			self::GROUP
		);
	}

	/**
	 * Знімає нагадування з черги.
	 *
	 * @param int $order_id Замовлення.
	 * @return void
	 */
	public function cancel( int $order_id ): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array( 'order_id' => $order_id ), self::GROUP );
		}
	}

	/**
	 * Надсилає нагадування.
	 *
	 * @param int $order_id Замовлення.
	 * @return void
	 */
	public function send( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order || ! is_email( $order->get_billing_email() ) ) {
			return;
		}

		$mailer = WC()->mailer();
		$names  = array();
		$repeat = $order->get_customer_id() ? Repeat::url( $order ) : wc_get_page_permalink( 'shop' );

		foreach ( $order->get_items() as $item ) {
			$names[] = $item->get_name();
		}

		$body = sprintf(
			'<p>%s</p><p>%s</p><p><a href="%s">%s</a></p>',
			esc_html__( 'Три тижні тому ми відправили вам каву. Якщо пачка добігає кінця — свіже обсмаження вже на підході.', 'brix-core' ),
			esc_html( implode( ' · ', array_slice( $names, 0, 3 ) ) ),
			esc_url( $repeat ),
			esc_html__( 'Повторити замовлення', 'brix-core' )
		);

		$mailer->send(
			$order->get_billing_email(),
			__( 'Кава закінчується?', 'brix-core' ),
			$mailer->wrap_message( __( 'Кава закінчується?', 'brix-core' ), $body ),
			"Content-Type: text/html\r\n"
		);

		$order->add_order_note( __( 'Надіслано нагадування «Кава закінчується?».', 'brix-core' ) );
	}

	/**
	 * Чи є в замовленні кава.
	 *
	 * @param \WC_Order $order Замовлення.
	 * @return bool
	 */
	private function has_coffee( \WC_Order $order ): bool {
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$product = wc_get_product( $item->get_product_id() );

			if ( $product instanceof \WC_Product && function_exists( 'brix_is_coffee' ) && brix_is_coffee( $product ) ) {
				return true;
			}
		}

		return false;
	}
}
