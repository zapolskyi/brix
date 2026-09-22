<?php
/**
 * Вибір «разова покупка / підписка» в кошику.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Club;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Переносить вибір плану з картки товару в кошик і в замовлення.
 *
 * Підписка — не окремий товар, а позначка на звичайному: той самий лот
 * можна купити разово або отримувати раз на два тижні. Окремий товар
 * означав би подвійний каталог і подвійні залишки.
 */
final class Plan implements Module {

	/**
	 * Ключ у даних кошика й поля форми.
	 */
	public const KEY = 'brix_club';

	/**
	 * Знижка учасникам клубу, відсотків.
	 */
	public const DISCOUNT = 10;

	/**
	 * Підписує модуль на хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_data' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'show_in_cart' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_discount' ), 20 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_to_order' ), 10, 4 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'start_subscriptions' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'start_subscriptions' ) );
	}

	/**
	 * Запам'ятовує вибраний інтервал у позиції кошика.
	 *
	 * @param array<string, mixed> $data       Дані позиції.
	 * @param int                  $product_id Товар.
	 * @return array<string, mixed>
	 */
	public function add_data( array $data, int $product_id ): array {
		unset( $product_id );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce перевіряє WooCommerce у формі додавання в кошик.
		$interval = isset( $_POST[ self::KEY ] ) ? absint( wp_unslash( $_POST[ self::KEY ] ) ) : 0;

		if ( in_array( $interval, Subscription::INTERVALS, true ) ) {
			$data[ self::KEY ] = $interval;
		}

		return $data;
	}

	/**
	 * Показує підписку рядком у кошику.
	 *
	 * @param array<int, array<string, string>> $lines Рядки.
	 * @param array<string, mixed>              $item  Позиція кошика.
	 * @return array<int, array<string, string>>
	 */
	public function show_in_cart( array $lines, array $item ): array {
		if ( empty( $item[ self::KEY ] ) ) {
			return $lines;
		}

		$lines[] = array(
			'name'    => __( 'BRIX Club', 'brix-core' ),
			'value'   => sprintf(
				/* translators: 1 — інтервал у тижнях, 2 — знижка. */
				__( 'кожні %1$d тижні, −%2$d%%', 'brix-core' ),
				(int) $item[ self::KEY ] / 7,
				self::DISCOUNT
			),
			'display' => '',
		);

		return $lines;
	}

	/**
	 * Знижує ціну позицій із підпискою.
	 *
	 * @param \WC_Cart $cart Кошик.
	 * @return void
	 */
	public function apply_discount( \WC_Cart $cart ): void {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item[ self::KEY ] ) || ! $item['data'] instanceof \WC_Product ) {
				continue;
			}

			/*
			 * Ціна береться заново з товару, а не з обʼєкта позиції.
			 * Хук спрацьовує не один раз за запит, і рахунок від
			 * поточної ціни накладав знижку двічі: 1 700 → 1 530 → 1 377.
			 * Від ціни в базі результат той самий, скільки б разів
			 * хук не викликали.
			 */
			$source = wc_get_product( $item['variation_id'] ? (int) $item['variation_id'] : (int) $item['product_id'] );

			if ( ! $source instanceof \WC_Product ) {
				continue;
			}

			$item['data']->set_price( round( (float) $source->get_price( 'edit' ) * ( 100 - self::DISCOUNT ) / 100, 2 ) );
		}
	}

	/**
	 * Переносить вибір у позицію замовлення.
	 *
	 * @param \WC_Order_Item_Product $line   Позиція замовлення.
	 * @param string                 $key    Ключ позиції кошика.
	 * @param array<string, mixed>   $values Дані позиції.
	 * @param \WC_Order              $order  Замовлення.
	 * @return void
	 */
	public function save_to_order( \WC_Order_Item_Product $line, string $key, array $values, \WC_Order $order ): void {
		unset( $key, $order );

		if ( ! empty( $values[ self::KEY ] ) ) {
			$line->add_meta_data( '_' . self::KEY, (int) $values[ self::KEY ], true );
		}
	}

	/**
	 * Створює підписки за оплаченим замовленням.
	 *
	 * @param int $order_id Замовлення.
	 * @return void
	 */
	public function start_subscriptions( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order || ! $order->get_customer_id() ) {
			return;
		}

		// Повторний перехід статусу не має плодити других підписок.
		if ( $order->get_meta( '_brix_club_started' ) ) {
			return;
		}

		$created = 0;

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$interval = (int) $item->get_meta( '_' . self::KEY );

			if ( ! in_array( $interval, Subscription::INTERVALS, true ) ) {
				continue;
			}

			if ( Subscription::create( $order, $item, $interval ) ) {
				++$created;
			}
		}

		if ( $created > 0 ) {
			$order->update_meta_data( '_brix_club_started', $created );
			$order->add_order_note(
				sprintf(
					/* translators: %d — кількість підписок. */
					_n( 'Створено %d підписку BRIX Club.', 'Створено %d підписки BRIX Club.', $created, 'brix-core' ),
					$created
				)
			);
			$order->save();
		}
	}
}
