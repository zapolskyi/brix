<?php
/**
 * Сторінка подяки.
 *
 * Override шаблону WooCommerce. Замість сухого «Дякуємо, ваше
 * замовлення прийнято» — те, заради чого покупець і прийшов: паспорт
 * купленого лоту, вікно свіжості й рецепт. Це єдина сторінка, яку
 * покупець гарантовано відкриє після оплати.
 *
 * @package BRIX
 *
 * @var WC_Order|false $order Замовлення.
 */

defined( 'ABSPATH' ) || exit;

if ( ! $order instanceof WC_Order ) {
	?>
	<p class="brix-lead"><?php esc_html_e( 'Дякуємо. Замовлення прийняте.', 'brix' ); ?></p>
	<?php
	return;
}

do_action( 'woocommerce_before_thankyou', $order->get_id() );

/*
 * Перечитуємо замовлення: хук вище міг щойно підтвердити оплату в
 * LiqPay, а $order — знімок, зроблений до того. Без цього покупець,
 * що вже заплатив, бачив би «оплата ще не підтверджена».
 */
$order = wc_get_order( $order->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Змінна шаблону, не глобальна.

$brix_failed = $order->has_status( 'failed' );

/*
 * Онлайн-оплата, якої ще немає. Сюди потрапляє покупець, що закрив
 * сторінку LiqPay, не заплативши, — або той, чий банк ще не
 * відповів. Обіцяти такому «смажимо й відправляємо» було б неправдою:
 * без оплати нічого не смажиться.
 */
$brix_unpaid = ! $brix_failed && $order->has_status( 'pending' ) && $order->needs_payment();
?>

<div class="brix-thanks">
	<?php if ( $brix_failed ) : ?>
		<div class="brix-empty">
			<h2><?php esc_html_e( 'Оплата не пройшла', 'brix' ); ?></h2>
			<p class="brix-muted">
				<?php esc_html_e( 'Банк відхилив платіж. Замовлення ми зберегли — спробуйте оплатити ще раз або оберіть інший спосіб.', 'brix' ); ?>
			</p>
			<p>
				<a class="brix-btn brix-btn--dark" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>">
					<?php esc_html_e( 'Спробувати ще раз', 'brix' ); ?>
				</a>
			</p>
		</div>
	<?php elseif ( $brix_unpaid ) : ?>
		<header class="brix-thanks__head">
			<p class="brix-label"><?php esc_html_e( 'Очікуємо оплату', 'brix' ); ?></p>
			<h1><?php esc_html_e( 'Оплата ще не підтверджена', 'brix' ); ?></h1>
			<p class="brix-lead brix-muted">
				<?php
				printf(
					/* translators: %s — номер замовлення. */
					esc_html__( 'Замовлення №%s збережене. Якщо ви вже оплатили, банк підтвердить за хвилину — оновіть сторінку. Якщо ні, оплатити можна зараз.', 'brix' ),
					esc_html( $order->get_order_number() )
				);
				?>
			</p>
			<p class="brix-thanks__actions">
				<a class="brix-btn brix-btn--dark" href="<?php echo esc_url( $order->get_checkout_payment_url( true ) ); ?>">
					<?php esc_html_e( 'Оплатити', 'brix' ); ?>
				</a>
				<a class="brix-btn brix-btn--outline" href="<?php echo esc_url( $order->get_checkout_order_received_url() ); ?>">
					<?php esc_html_e( 'Перевірити оплату', 'brix' ); ?>
				</a>
			</p>
		</header>
	<?php else : ?>
		<header class="brix-thanks__head">
			<p class="brix-label"><?php esc_html_e( 'Замовлення прийняте', 'brix' ); ?></p>
			<h1><?php esc_html_e( 'Смажимо й відправляємо', 'brix' ); ?></h1>
			<p class="brix-lead brix-muted">
				<?php
				printf(
					'' !== brix_order_pickup_point( $order )
						/* translators: 1 — номер замовлення, 2 — пошта покупця. */
						? esc_html__( 'Замовлення №%1$s. Напишемо на %2$s, щойно його можна забирати.', 'brix' )
						/* translators: 1 — номер замовлення, 2 — пошта покупця. */
						: esc_html__( 'Замовлення №%1$s. Номер накладної надішлемо на %2$s у день обсмаження.', 'brix' ),
					esc_html( $order->get_order_number() ),
					esc_html( $order->get_billing_email() )
				);
				?>
			</p>
		</header>

		<dl class="brix-thanks__meta">
			<div>
				<dt><?php esc_html_e( 'Разом', 'brix' ); ?></dt>
				<dd class="brix-num"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></dd>
			</div>
			<div>
				<dt><?php esc_html_e( 'Оплата', 'brix' ); ?></dt>
				<dd><?php echo esc_html( $order->get_payment_method_title() ); ?></dd>
			</div>
			<?php if ( $order->get_shipping_method() ) : ?>
				<div>
					<dt><?php esc_html_e( 'Доставка', 'brix' ); ?></dt>
					<dd><?php echo esc_html( $order->get_shipping_method() ); ?></dd>
				</div>
			<?php endif; ?>
		</dl>
	<?php endif; ?>

	<?php if ( ! $brix_failed ) : ?>
		<section class="brix-thanks__items">
			<h2 class="brix-label"><?php esc_html_e( 'Що їде до вас', 'brix' ); ?></h2>

			<ul>
				<?php foreach ( $order->get_items() as $brix_line ) : ?>
					<li>
						<span class="brix-thanks__name">
							<?php echo esc_html( $brix_line->get_name() ); ?>
							<?php if ( $brix_line->get_quantity() > 1 ) : ?>
								<span class="brix-muted">× <?php echo esc_html( (string) $brix_line->get_quantity() ); ?></span>
							<?php endif; ?>
						</span>
						<span class="brix-num"><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $brix_line ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php
			$brix_point   = brix_order_pickup_point( $order );
			$brix_address = $order->get_formatted_shipping_address();

			if ( ! $brix_address ) {
				$brix_address = $order->get_formatted_billing_address();
			}
			?>
			<?php if ( '' !== $brix_point ) : ?>
				<?php $brix_pickup = brix_pickup_info(); ?>
				<div class="brix-thanks__address">
					<p class="brix-label"><?php esc_html_e( 'Звідки забирати', 'brix' ); ?></p>
					<p class="brix-small brix-muted">
						<?php echo esc_html( $brix_point ); ?><br>
						<?php echo esc_html( $brix_pickup['hours'] ); ?>
					</p>
				</div>
			<?php elseif ( $brix_address ) : ?>
				<div class="brix-thanks__address">
					<p class="brix-label"><?php esc_html_e( 'Куди', 'brix' ); ?></p>
					<p class="brix-small brix-muted"><?php echo wp_kses_post( $brix_address ); ?></p>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php
	do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
	do_action( 'woocommerce_thankyou', $order->get_id() );
	?>
</div>

<?php
if ( $brix_failed ) {
	return;
}

/*
 * Паспорт показуємо для кавових лотів із замовлення. Якщо лотів
 * кілька, беремо кожен: покупець має побачити, що саме він купив,
 * а не абстрактне «дякуємо».
 */
$brix_shown = array();

foreach ( $order->get_items() as $brix_item ) {
	if ( ! $brix_item instanceof WC_Order_Item_Product ) {
		continue;
	}

	$brix_product_id = $brix_item->get_product_id();

	if ( isset( $brix_shown[ $brix_product_id ] ) ) {
		continue;
	}

	$brix_shown[ $brix_product_id ] = true;
	$brix_product                   = wc_get_product( $brix_product_id );

	if ( ! $brix_product instanceof WC_Product || ! brix_is_coffee( $brix_product ) ) {
		continue;
	}

	$brix_lot = brix_lot( $brix_product );

	if ( ! $brix_lot || ! $brix_lot->has_passport() ) {
		continue;
	}

	get_template_part( 'template-parts/product/passport', null, array( 'lot' => $brix_lot ) );

	if ( $brix_lot->brew_guide() ) {
		get_template_part( 'template-parts/product/recipe', null, array( 'lot' => $brix_lot ) );
	}
}
