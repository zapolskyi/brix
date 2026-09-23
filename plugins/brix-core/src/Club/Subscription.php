<?php
/**
 * Підписка BRIX Club: тип запису й доступ до даних.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Club;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Підписка як окремий запис.
 *
 * Своя спрощена реалізація замість WooCommerce Subscriptions: той
 * платний, а нам потрібні рівно чотири дії — пауза, пропуск, зміна
 * лоту й скасування. Повний цикл білінгу, пробні періоди й проміжні
 * статуси тут були б зайвими.
 */
final class Subscription implements Module {

	/**
	 * Тип запису.
	 */
	public const POST_TYPE = 'brix_subscription';

	/**
	 * Можливі стани.
	 */
	public const ACTIVE    = 'active';
	public const PAUSED    = 'paused';
	public const CANCELLED = 'cancelled';

	/**
	 * Дозволені інтервали, днів.
	 */
	public const INTERVALS = array( 14, 28 );

	/**
	 * Підписує модуль на хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ), 5 );
	}

	/**
	 * Реєструє тип запису.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Підписки', 'brix-core' ),
					'singular_name' => __( 'Підписка', 'brix-core' ),
					'menu_name'     => __( 'Підписки', 'brix-core' ),
					'all_items'     => __( 'Усі підписки', 'brix-core' ),
					'not_found'     => __( 'Підписок немає', 'brix-core' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-update',
				'menu_position'   => 29,
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
				'supports'        => array( 'title' ),
			)
		);
	}

	/**
	 * Створює підписку за позицією замовлення.
	 *
	 * @param \WC_Order              $order    Замовлення.
	 * @param \WC_Order_Item_Product $item     Позиція.
	 * @param int                    $interval Інтервал у днях.
	 * @return int
	 */
	public static function create( \WC_Order $order, \WC_Order_Item_Product $item, int $interval ): int {
		$id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sprintf(
					/* translators: 1 — назва лоту, 2 — номер замовлення. */
					__( '%1$s (замовлення №%2$s)', 'brix-core' ),
					$item->get_name(),
					$order->get_order_number()
				),
			)
		);

		if ( is_wp_error( $id ) ) {
			return 0;
		}

		$id = (int) $id;

		$meta = array(
			'_brix_user'      => $order->get_customer_id(),
			'_brix_order'     => $order->get_id(),
			'_brix_product'   => $item->get_product_id(),
			'_brix_variation' => $item->get_variation_id(),
			'_brix_quantity'  => $item->get_quantity(),
			'_brix_interval'  => $interval,
			'_brix_status'    => self::ACTIVE,
			'_brix_next'      => time() + $interval * DAY_IN_SECONDS,
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}

		return $id;
	}

	/**
	 * Підписки покупця.
	 *
	 * @param int $user_id Користувач.
	 * @return array<int, \WP_Post>
	 */
	public static function for_user( int $user_id ): array {
		if ( ! $user_id ) {
			return array();
		}

		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => 20,
				'post_status'    => 'publish',
				'meta_key'       => '_brix_user', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $user_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
	}

	/**
	 * Чи належить підписка цьому покупцю.
	 *
	 * @param int $id      Підписка.
	 * @param int $user_id Користувач.
	 * @return bool
	 */
	public static function owned_by( int $id, int $user_id ): bool {
		return $user_id > 0 && (int) get_post_meta( $id, '_brix_user', true ) === $user_id;
	}

	/**
	 * Дані підписки у зрозумілому вигляді.
	 *
	 * @param int $id Підписка.
	 * @return array<string, mixed>
	 */
	public static function data( int $id ): array {
		$lot       = wc_get_product( (int) get_post_meta( $id, '_brix_product', true ) );
		$variation = (int) get_post_meta( $id, '_brix_variation', true );
		$product   = $variation ? wc_get_product( $variation ) : $lot;

		/*
		 * Вагу чи помел, на які оформлено підписку, власник може зняти з
		 * продажу — варіація зникне, а лот лишиться. Тоді підписку не
		 * можна ні поновити (без ваги пачку не зібрати), ні мовчки
		 * загубити: кабінет має назвати лот і попросити обрати заново.
		 */
		$broken = $variation && ! ( $product instanceof \WC_Product && $product->is_purchasable() );

		return array(
			'id'       => $id,
			'status'   => (string) get_post_meta( $id, '_brix_status', true ),
			'interval' => (int) get_post_meta( $id, '_brix_interval', true ),
			'next'     => (int) get_post_meta( $id, '_brix_next', true ),
			'quantity' => (int) get_post_meta( $id, '_brix_quantity', true ),
			'product'  => ! $broken && $product instanceof \WC_Product ? $product : null,
			'lot'      => $lot instanceof \WC_Product ? $lot : null,
			'broken'   => $broken,
		);
	}
}
