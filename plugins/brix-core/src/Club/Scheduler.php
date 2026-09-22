<?php
/**
 * Черга поновлень підписки.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Club;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Раз на добу перевіряє, кому пора відправляти наступну пачку.
 *
 * Черга на кожну підписку окремо була б крихкою: пропущений запуск
 * губить поновлення назавжди. Щоденний обхід самовідновлюється —
 * підписка, яку не обробили вчора, обробиться сьогодні.
 */
final class Scheduler implements Module {

	/**
	 * Хук щоденної перевірки.
	 */
	private const HOOK = 'brix_club_renewals';

	/**
	 * Група дій.
	 */
	private const GROUP = 'brix';

	/**
	 * Підписує модуль на хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'ensure_schedule' ), 20 );
		add_action( self::HOOK, array( $this, 'run' ) );
	}

	/**
	 * Ставить щоденну перевірку в чергу, якщо її ще немає.
	 *
	 * @return void
	 */
	public function ensure_schedule(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || as_has_scheduled_action( self::HOOK, array(), self::GROUP ) ) {
			return;
		}

		as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, self::HOOK, array(), self::GROUP );
	}

	/**
	 * Створює замовлення для підписок, яким настав час.
	 *
	 * @return void
	 */
	public function run(): void {
		$due = get_posts(
			array(
				'post_type'      => Subscription::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'   => '_brix_status',
						'value' => Subscription::ACTIVE,
					),
					array(
						'key'     => '_brix_next',
						'value'   => time(),
						'compare' => '<=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		foreach ( $due as $id ) {
			$this->renew( (int) $id );
		}
	}

	/**
	 * Створює чергове замовлення за підпискою.
	 *
	 * @param int $id Підписка.
	 * @return void
	 */
	private function renew( int $id ): void {
		$data = Subscription::data( $id );

		if ( ! $data['product'] instanceof \WC_Product ) {
			return;
		}

		$user_id = (int) get_post_meta( $id, '_brix_user', true );
		$source  = wc_get_order( (int) get_post_meta( $id, '_brix_order', true ) );

		if ( ! $user_id || ! $source instanceof \WC_Order ) {
			return;
		}

		// Лот міг закінчитись. Мовчки пропускати не можна: покупець
		// чекає пачку й має дізнатись, що її не буде.
		if ( ! $data['product']->is_in_stock() ) {
			$this->postpone( $id, $data['interval'] );
			return;
		}

		$order = wc_create_order( array( 'customer_id' => $user_id ) );

		if ( is_wp_error( $order ) ) {
			return;
		}

		$order->add_product(
			$data['product'],
			max( 1, $data['quantity'] ),
			array(
				'subtotal' => $this->club_price( $data['product'], $data['quantity'] ),
				'total'    => $this->club_price( $data['product'], $data['quantity'] ),
			)
		);

		$order->set_address( $source->get_address( 'billing' ), 'billing' );
		$order->set_address( $source->get_address( 'shipping' ), 'shipping' );
		$order->update_meta_data( '_brix_subscription', $id );
		$order->calculate_totals();
		$order->update_status( 'pending', __( 'Чергове замовлення BRIX Club.', 'brix-core' ) );

		$this->postpone( $id, $data['interval'] );
	}

	/**
	 * Ціна з клубною знижкою.
	 *
	 * @param \WC_Product $product  Товар.
	 * @param int         $quantity Кількість.
	 * @return float
	 */
	private function club_price( \WC_Product $product, int $quantity ): float {
		return round( (float) $product->get_price( 'edit' ) * max( 1, $quantity ) * ( 100 - Plan::DISCOUNT ) / 100, 2 );
	}

	/**
	 * Переносить наступну відправку.
	 *
	 * @param int $id       Підписка.
	 * @param int $interval Інтервал у днях.
	 * @return void
	 */
	private function postpone( int $id, int $interval ): void {
		update_post_meta( $id, '_brix_next', time() + max( 1, $interval ) * DAY_IN_SECONDS );
	}
}
