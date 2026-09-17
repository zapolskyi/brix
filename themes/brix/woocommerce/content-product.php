<?php
/**
 * Картка товару в сітці.
 *
 * Override шаблону WooCommerce. Використовується і в каталозі,
 * і на головній, і на сторінці виробника — одна розмітка на всі сітки.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product || ! $product->is_visible() ) {
	return;
}

$brix_lot   = brix_lot( $product );
$brix_style = brix_pack_style( $product );
$brix_term  = $brix_lot ? $brix_lot->processing() : null;
$brix_left  = brix_stock_left( $product );
$brix_sold  = ! $product->is_in_stock();

$brix_classes = array( 'brix-card' );

if ( $brix_sold ) {
	$brix_classes[] = 'brix-card--sold-out';
}
?>

<article <?php wc_product_class( implode( ' ', $brix_classes ), $product ); ?>>
	<div class="brix-card__stage <?php echo $brix_style ? esc_attr( 'brix-card__stage--' . $brix_style ) : ''; ?>">
		<?php if ( $brix_lot && $brix_lot->lot_of_week ) : ?>
			<span class="brix-tag brix-tag--lab brix-card__tag"><?php esc_html_e( 'Лот тижня', 'brix' ); ?></span>
		<?php elseif ( $brix_term && $brix_style ) : ?>
			<span class="brix-tag <?php echo esc_attr( 'brix-tag--' . ( '' !== $brix_style ? $brix_style : 'soft' ) ); ?> brix-card__tag">
				<?php echo esc_html( $brix_term->name ); ?>
			</span>
		<?php endif; ?>

		<?php brix_the_product_visual( $product, '54%' ); ?>

		<?php if ( ! $brix_sold ) : ?>
			<?php
			/*
			 * Звичайне посилання, а не AJAX: без JavaScript кнопка теж
			 * має класти товар у кошик. Прискорення — фаза 4.
			 * Варіативний товар веде на сторінку: вагу й помел
			 * не можна обрати за покупця.
			 */
			$brix_add_url = $product->is_type( 'variable' )
				? $product->get_permalink()
				: add_query_arg( 'add-to-cart', $product->get_id(), wc_get_cart_url() );
			?>
			<a
				class="brix-card__add"
				href="<?php echo esc_url( $brix_add_url ); ?>"
				<?php echo $product->is_type( 'variable' ) ? '' : 'data-brix-add="' . esc_attr( (string) $product->get_id() ) . '"'; ?>
			>
				<?php echo brix_icon( $product->is_type( 'variable' ) ? 'arrow' : 'bag' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="brix-visually-hidden">
					<?php
					echo esc_html(
						$product->is_type( 'variable' )
							/* translators: %s — назва товару. */
							? sprintf( __( 'Обрати вагу й помел: %s', 'brix' ), $product->get_name() )
							/* translators: %s — назва товару. */
							: sprintf( __( 'Додати в кошик: %s', 'brix' ), $product->get_name() )
					);
					?>
				</span>
			</a>
		<?php endif; ?>
	</div>

	<div class="brix-card__head">
		<span class="brix-card__name">
			<a href="<?php the_permalink(); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
		</span>
		<span class="brix-card__price"><?php echo wp_kses_post( brix_price_label( $product ) ); ?></span>
	</div>

	<?php if ( $brix_lot && '' !== $brix_lot->notes_label() ) : ?>
		<p class="brix-card__notes"><?php echo esc_html( $brix_lot->notes_label() ); ?></p>
	<?php elseif ( $product->get_short_description() ) : ?>
		<p class="brix-card__notes"><?php echo esc_html( wp_strip_all_tags( $product->get_short_description() ) ); ?></p>
	<?php endif; ?>

	<?php if ( $brix_lot && $brix_lot->has_passport() ) : ?>
		<p class="brix-card__data">
			<?php if ( $brix_term ) : ?>
				<span><b><?php echo esc_html( $brix_term->name ); ?></b></span>
			<?php endif; ?>

			<?php if ( $brix_lot->altitude_average() ) : ?>
				<span><b><?php echo esc_html( \Brix\Core\Support\Format::number( (float) $brix_lot->altitude_average() ) . ' м' ); ?></b></span>
			<?php endif; ?>

			<?php if ( null !== $brix_lot->brix ) : ?>
				<span><b><?php echo esc_html( $brix_lot->brix . ' °Bx' ); ?></b></span>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<?php if ( $brix_sold ) : ?>
		<p class="brix-card__data"><span><b><?php esc_html_e( 'Розпродано', 'brix' ); ?></b></span></p>
	<?php elseif ( $brix_left > 0 && $brix_left <= 30 ) : ?>
		<p class="brix-card__data">
			<span><b>
			<?php
			// «Пачок» — тільки про каву: у чайника пачок немає.
			if ( brix_is_coffee( $product ) ) {
				$brix_left_label = brix_plural(
					$brix_left,
					/* translators: %s — кількість пачок. */
					__( 'Залишилась %s пачка', 'brix' ),
					/* translators: %s — кількість пачок. */
					__( 'Залишилось %s пачки', 'brix' ),
					/* translators: %s — кількість пачок. */
					__( 'Залишилось %s пачок', 'brix' )
				);
			} else {
				$brix_left_label = brix_plural(
					$brix_left,
					/* translators: %s — кількість штук. */
					__( 'Залишилась %s штука', 'brix' ),
					/* translators: %s — кількість штук. */
					__( 'Залишилось %s штуки', 'brix' ),
					/* translators: %s — кількість штук. */
					__( 'Залишилось %s штук', 'brix' )
				);
			}

			printf(
				/* translators: %s — кількість товару. */
				esc_html( $brix_left_label ),
				esc_html( (string) $brix_left )
			);
			?>
			</b></span>
		</p>
	<?php endif; ?>
</article>
