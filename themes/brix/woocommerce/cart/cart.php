<?php
/**
 * Кошик.
 *
 * Override шаблону WooCommerce. Таблиця замінена на список: у макеті
 * рядок кошика — це картка з пачкою, назвою, помелом і ціною, а не
 * комірки з колонками. Кількість і видалення працюють звичайними
 * формами, без JavaScript.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' );

$brix_progress = brix_free_shipping_progress();
?>

<div class="brix-cart">
	<form class="brix-cart__items woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
		<?php do_action( 'woocommerce_before_cart_table' ); ?>
		<?php do_action( 'woocommerce_before_cart_contents' ); ?>

		<?php
		foreach ( WC()->cart->get_cart() as $brix_key => $brix_item ) :
			$brix_product = apply_filters( 'woocommerce_cart_item_product', $brix_item['data'], $brix_item, $brix_key );

			if ( ! $brix_product || ! $brix_product->exists() || $brix_item['quantity'] <= 0 ) {
				continue;
			}

			if ( ! apply_filters( 'woocommerce_cart_item_visible', true, $brix_item, $brix_key ) ) {
				continue;
			}

			$brix_permalink = apply_filters( 'woocommerce_cart_item_permalink', $brix_product->is_visible() ? $brix_product->get_permalink( $brix_item ) : '', $brix_item, $brix_key );
			?>
			<div class="brix-cart__row <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $brix_item, $brix_key ) ); ?>">
				<div class="brix-cart__visual">
					<?php
					$brix_thumb = apply_filters( 'woocommerce_cart_item_thumbnail', $brix_product->get_image(), $brix_item, $brix_key );

					if ( $brix_permalink ) {
						printf( '<a href="%s">%s</a>', esc_url( $brix_permalink ), wp_kses_post( $brix_thumb ) );
					} else {
						echo wp_kses_post( $brix_thumb );
					}
					?>
				</div>

				<div class="brix-cart__info">
					<?php
					/*
					 * У варіації get_name() повертає «Лот - 250 г, Еспресо».
					 * У макеті це два рядки: назва лоту, під нею вага
					 * й помел приглушеним. Тож беремо назву батька,
					 * а параметри друкуємо окремо.
					 */
					$brix_parent = $brix_product->is_type( 'variation' )
						? wc_get_product( $brix_product->get_parent_id() )
						: $brix_product;
					$brix_title  = $brix_parent instanceof WC_Product ? $brix_parent->get_name() : $brix_product->get_name();
					?>
					<h2 class="brix-cart__name">
						<?php
						if ( $brix_permalink ) {
							printf( '<a href="%s">%s</a>', esc_url( $brix_permalink ), esc_html( $brix_title ) );
						} else {
							echo esc_html( $brix_title );
						}
						?>
					</h2>

					<?php $brix_options = brix_cart_item_options( $brix_item ); ?>
					<?php if ( $brix_options ) : ?>
						<p class="brix-cart__options"><?php echo esc_html( implode( ' · ', $brix_options ) ); ?></p>
					<?php endif; ?>

					<?php do_action( 'woocommerce_after_cart_item_name', $brix_item, $brix_key ); ?>

					<?php if ( $brix_product->backorders_require_notification() && $brix_product->is_on_backorder( $brix_item['quantity'] ) ) : ?>
						<p class="brix-small brix-muted"><?php esc_html_e( 'Немає в наявності — доставимо окремо.', 'brix' ); ?></p>
					<?php endif; ?>
				</div>

				<div class="brix-cart__qty">
					<?php
					if ( $brix_product->is_sold_individually() ) {
						printf( '<span class="brix-num">1</span><input type="hidden" name="cart[%s][qty]" value="1">', esc_attr( $brix_key ) );
					} else {
						brix_quantity_stepper(
							array(
								'name'  => "cart[{$brix_key}][qty]",
								'value' => $brix_item['quantity'],
								// Нуль дозволений навмисно: він прибирає
								// позицію з кошика при оновленні.
								'min'   => 0,
								'max'   => $brix_product->get_max_purchase_quantity(),
								'id'    => 'brix-qty-' . $brix_key,
								/* translators: %s — назва товару. */
								'label' => sprintf( __( 'Кількість: %s', 'brix' ), $brix_product->get_name() ),
								'size'  => 'sm',
							)
						);
					}
					?>
				</div>

				<div class="brix-cart__price">
					<b class="brix-num"><?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $brix_product, $brix_item['quantity'] ), $brix_item, $brix_key ) ); ?></b>

					<a class="brix-cart__remove" href="<?php echo esc_url( wc_get_cart_remove_url( $brix_key ) ); ?>"
						aria-label="
						<?php
						/* translators: %s — назва товару. */
						echo esc_attr( sprintf( __( 'Прибрати з кошика: %s', 'brix' ), $brix_product->get_name() ) );
						?>
						">
						<?php echo brix_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				</div>
			</div>
		<?php endforeach; ?>

		<?php do_action( 'woocommerce_cart_contents' ); ?>

		<div class="brix-cart__actions">
			<?php if ( wc_coupons_enabled() ) : ?>
				<div class="brix-cart__coupon">
					<label class="brix-visually-hidden" for="coupon_code"><?php esc_html_e( 'Промокод', 'brix' ); ?></label>
					<input class="brix-input" type="text" name="coupon_code" id="coupon_code" placeholder="<?php esc_attr_e( 'Промокод', 'brix' ); ?>">
					<button class="brix-btn brix-btn--outline" type="submit" name="apply_coupon" value="<?php esc_attr_e( 'Застосувати', 'brix' ); ?>">
						<?php esc_html_e( 'Застосувати', 'brix' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<?php
			/*
			 * Кнопка оновлення потрібна саме без JavaScript: змінив
			 * кількість — натиснув — сторінка перерахувала. Зі скриптом
			 * (фаза 4) вона зникне, бо оновлення стане миттєвим.
			 */
			?>
			<button class="brix-btn brix-btn--outline" type="submit" name="update_cart" value="<?php esc_attr_e( 'Оновити кошик', 'brix' ); ?>">
				<?php esc_html_e( 'Оновити кошик', 'brix' ); ?>
			</button>

			<?php do_action( 'woocommerce_cart_actions' ); ?>
			<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
		</div>

		<?php do_action( 'woocommerce_after_cart_contents' ); ?>
		<?php do_action( 'woocommerce_after_cart_table' ); ?>
	</form>

	<aside class="brix-cart__side">
		<?php /* Самовивозу поріг безкоштовної доставки не стосується — підганяти покупця добирати до нього було б нечесно. */ ?>
		<?php if ( brix_pickup_chosen() ) : ?>
			<?php /* Нічого: спосіб отримання вже обрано, смуга тут не про нього. */ ?>
		<?php elseif ( ! $brix_progress['reached'] ) : ?>
			<div class="brix-shipbar">
				<p class="brix-small">
					<?php
					printf(
						/* translators: %s — сума, якої бракує. */
						esc_html__( 'До безкоштовної доставки лишилось %s', 'brix' ),
						wp_kses_post( wc_price( $brix_progress['left'] ) )
					);
					?>
				</p>
				<span class="brix-shipbar__track">
					<span class="brix-shipbar__fill" style="--brix-value:<?php echo esc_attr( (string) $brix_progress['percent'] ); ?>%"></span>
				</span>
			</div>
		<?php else : ?>
			<p class="brix-shipbar brix-shipbar--done">
				<?php echo brix_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php esc_html_e( 'Доставка безкоштовна', 'brix' ); ?>
			</p>
		<?php endif; ?>

		<?php do_action( 'woocommerce_before_cart_collaterals' ); ?>
		<?php woocommerce_cart_totals(); ?>
		<?php do_action( 'woocommerce_after_cart_totals' ); ?>
	</aside>
</div>

<?php do_action( 'woocommerce_after_cart' ); ?>
