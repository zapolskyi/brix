<?php
/**
 * Кабінет — головна.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_user   = wp_get_current_user();
$brix_orders = wc_get_orders(
	array(
		'customer' => get_current_user_id(),
		'limit'    => 3,
		'orderby'  => 'date',
		'order'    => 'DESC',
	)
);
?>

<div class="brix-account__dash">
	<h2 class="brix-account__hello">
		<?php
		printf(
			/* translators: %s — імʼя покупця. */
			esc_html__( 'Привіт, %s', 'brix' ),
			esc_html( $brix_user->first_name ? $brix_user->first_name : $brix_user->display_name )
		);
		?>
	</h2>

	<p class="brix-muted">
		<?php esc_html_e( 'Тут ваші замовлення, адреси й підписка. Повторити попереднє замовлення — одна кнопка.', 'brix' ); ?>
	</p>

	<?php $brix_quiz = function_exists( 'brix_quiz_saved' ) ? brix_quiz_saved() : array(); ?>
	<?php if ( $brix_quiz ) : ?>
		<section class="brix-account__quiz">
			<h3 class="brix-label"><?php esc_html_e( 'Ваш профіль смаку', 'brix' ); ?></h3>

			<p class="brix-lead">
				<?php
				printf(
					/* translators: %s — назва помелу. */
					esc_html__( 'Мелемо під %s', 'brix' ),
					esc_html( (string) ( $brix_quiz['grind'] ?? '' ) )
				);
				?>
			</p>

			<p>
				<a class="brix-btn brix-btn--outline brix-btn--sm" href="<?php echo esc_url( get_permalink( get_page_by_path( 'quiz' ) ) ); ?>">
					<?php esc_html_e( 'Пройти квіз заново', 'brix' ); ?>
				</a>
			</p>
		</section>
	<?php endif; ?>

	<?php if ( $brix_orders ) : ?>
		<section class="brix-account__block">
			<header class="brix-section__head">
				<h3><?php esc_html_e( 'Останні замовлення', 'brix' ); ?></h3>
				<a class="brix-section__more" href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>">
					<?php esc_html_e( 'Усі замовлення', 'brix' ); ?>
					<?php echo brix_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			</header>

			<div class="brix-account__orders">
				<?php foreach ( $brix_orders as $brix_order ) : ?>
					<article class="brix-order-card">
						<p class="brix-label">
							<?php echo esc_html( wc_format_datetime( $brix_order->get_date_created() ) ); ?>
						</p>
						<h4 class="brix-order-card__number">
							<a href="<?php echo esc_url( $brix_order->get_view_order_url() ); ?>">
								<?php
								printf(
									/* translators: %s — номер замовлення. */
									esc_html__( 'Замовлення №%s', 'brix' ),
									esc_html( (string) $brix_order->get_order_number() )
								);
								?>
							</a>
						</h4>

						<p class="brix-order-card__items brix-small brix-muted">
							<?php
							$brix_names = array();

							foreach ( $brix_order->get_items() as $brix_item ) {
								$brix_names[] = $brix_item->get_name();
							}

							echo esc_html( implode( ' · ', array_slice( $brix_names, 0, 3 ) ) );
							?>
						</p>

						<p class="brix-order-card__foot">
							<b class="brix-mono"><?php echo wp_kses_post( $brix_order->get_formatted_order_total() ); ?></b>
							<span class="brix-tag brix-tag--soft"><?php echo esc_html( wc_get_order_status_name( $brix_order->get_status() ) ); ?></span>
						</p>

						<?php if ( brix_has_core() ) : ?>
							<p class="brix-order-card__repeat">
								<a class="brix-btn brix-btn--outline brix-btn--sm"
									href="<?php echo esc_url( \Brix\Core\Orders\Repeat::url( $brix_order ) ); ?>">
									<?php esc_html_e( 'Повторити замовлення', 'brix' ); ?>
								</a>
							</p>
						<?php endif; ?>

						<?php
						/*
						 * «Повторити» доступне лише для сплачених замовлень:
						 * пропонувати повтор того, що ще не оплатили, —
						 * найпростіший спосіб отримати два однакові.
						 */
						if ( $brix_order->is_paid() || $brix_order->has_status( array( 'processing', 'completed' ) ) ) :
							?>
							<a class="brix-btn brix-btn--sm brix-btn--outline" href="<?php echo esc_url( wc_get_endpoint_url( 'order-again', (string) $brix_order->get_id(), wc_get_cart_url() ) ); ?>">
								<?php esc_html_e( 'Повторити замовлення', 'brix' ); ?>
							</a>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
	<?php else : ?>
		<div class="brix-empty">
			<h3><?php esc_html_e( 'Замовлень поки немає', 'brix' ); ?></h3>
			<p class="brix-muted"><?php esc_html_e( 'Щойно замовите — усе зʼявиться тут: статус, накладна й кнопка повтору.', 'brix' ); ?></p>
			<p>
				<a class="brix-btn" href="<?php echo esc_url( (string) wc_get_page_permalink( 'shop' ) ); ?>">
					<?php esc_html_e( 'У магазин', 'brix' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<?php
	// Підписка зʼявиться тут на фазі 6 — разом з паузою й пропуском.
	do_action( 'woocommerce_account_dashboard' );
	?>
</div>
