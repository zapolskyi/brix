<?php
/**
 * Повторення замовлення в один клік.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Orders;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Кладе весь склад минулого замовлення назад у кошик.
 *
 * Кава — покупка, яку роблять щомісяця тим самим складом, тож
 * «повторити» економить п'ять кроків. WooCommerce має власний
 * `order_again`, але він мовчки пропускає те, чого вже немає в
 * продажу, і покупець дізнається про це аж у кошику. Тут кожен
 * пропущений лот називається вголос.
 */
final class Repeat implements Module {

	/**
	 * Дія повторення.
	 */
	public const ACTION = 'brix_repeat_order';

	/**
	 * Підписує модуль на хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Адреса кнопки «Повторити» для замовлення.
	 *
	 * @param \WC_Order $order Замовлення.
	 * @return string
	 */
	public static function url( \WC_Order $order ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ACTION,
					'order'  => $order->get_id(),
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . $order->get_id()
		);
	}

	/**
	 * Переносить позиції замовлення в кошик.
	 *
	 * @return void
	 */
	public function handle(): void {
		$id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;

		if ( ! $id ) {
			$this->back();
		}

		check_admin_referer( self::ACTION . '_' . $id );

		$order = wc_get_order( $id );

		// Повторити можна лише власне замовлення: інакше перебір
		// номерів показував би чужі покупки через вміст кошика.
		if ( ! $order instanceof \WC_Order || (int) $order->get_customer_id() !== get_current_user_id() || ! is_user_logged_in() ) {
			wc_add_notice( __( 'Це замовлення недоступне.', 'brix-core' ), 'error' );
			$this->back();
		}

		if ( ! WC()->cart ) {
			$this->back();
		}

		$added   = 0;
		$skipped = array();

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();

			if ( ! $product instanceof \WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				$skipped[] = $item->get_name();

				continue;
			}

			$ok = WC()->cart->add_to_cart(
				$item->get_product_id(),
				(int) $item->get_quantity(),
				$item->get_variation_id(),
				(array) $item->get_variation_attributes()
			);

			if ( $ok ) {
				++$added;
			} else {
				$skipped[] = $item->get_name();
			}
		}

		if ( $added > 0 ) {
			wc_add_notice(
				sprintf(
					/* translators: %s — номер замовлення. */
					__( 'Склад замовлення №%s у кошику.', 'brix-core' ),
					$order->get_order_number()
				),
				'success'
			);
		}

		if ( $skipped ) {
			wc_add_notice(
				sprintf(
					/* translators: %s — перелік лотів. */
					__( 'Цих лотів уже немає: %s. Свіжі партії — у магазині.', 'brix-core' ),
					implode( ', ', $skipped )
				),
				'notice'
			);
		}

		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}

	/**
	 * Повертає в кабінет.
	 *
	 * @return void
	 */
	private function back(): void {
		wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
		exit;
	}
}
