<?php
/**
 * Блок покупки: назва, ціна, вибір ваги й помелу, кнопка.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_product = $args['product'] ?? null;
$brix_lot     = $args['lot'] ?? null;

if ( ! $brix_product instanceof WC_Product ) {
	return;
}

$brix_variable  = $brix_product->is_type( 'variable' );
$brix_selected  = $brix_variable ? brix_selected_attributes( $brix_product ) : array();
$brix_variation = $brix_variable ? brix_matching_variation( $brix_product, $brix_selected ) : null;

// Поки покупець нічого не обрав, показуємо варіант за замовчуванням —
// інакше ціна й наявність на екрані порожні, а купити нічого не можна.
if ( $brix_variable && ! $brix_selected ) {
	$brix_selected  = brix_default_attributes( $brix_product );
	$brix_variation = brix_matching_variation( $brix_product, $brix_selected );
}

$brix_current = $brix_variation ?? $brix_product;
$brix_term    = $brix_lot ? $brix_lot->processing() : null;
$brix_style   = brix_pack_style( $brix_product );
$brix_left    = brix_stock_left( $brix_current );
?>

<div class="brix-buy">
	<div class="brix-buy__tags">
		<?php if ( $brix_term && $brix_style ) : ?>
			<span class="brix-tag <?php echo esc_attr( 'brix-tag--' . $brix_style ); ?>"><?php echo esc_html( $brix_term->name ); ?></span>
		<?php endif; ?>

		<?php if ( brix_product_line( $brix_product ) ) : ?>
			<span class="brix-tag brix-tag--soft"><?php echo esc_html( brix_product_line( $brix_product ) ); ?></span>
		<?php endif; ?>

		<?php if ( $brix_left > 0 && $brix_left <= 30 ) : ?>
			<span class="brix-tag brix-tag--soft">
				<?php
				printf(
					/* translators: %s — кількість пачок. */
					esc_html( brix_plural( $brix_left, __( 'Залишилась %s пачка', 'brix' ), __( 'Залишилось %s пачки', 'brix' ), __( 'Залишилось %s пачок', 'brix' ) ) ),
					esc_html( (string) $brix_left )
				);
				?>
			</span>
		<?php endif; ?>
	</div>

	<h1 class="brix-buy__title"><?php the_title(); ?></h1>

	<?php if ( $brix_lot && '' !== $brix_lot->notes_label() ) : ?>
		<p class="brix-lead brix-muted"><?php echo esc_html( $brix_lot->notes_label() ); ?></p>
	<?php endif; ?>

	<?php if ( $brix_product->get_review_count() > 0 ) : ?>
		<p class="brix-buy__rating">
			<span class="brix-stars" role="img" aria-label="
			<?php
			printf(
				/* translators: %s — середня оцінка. */
				esc_attr__( 'Оцінка %s з 5', 'brix' ),
				esc_attr( (string) $brix_product->get_average_rating() )
			);
			?>
			">
				<?php
				$brix_rating = (float) $brix_product->get_average_rating();

				for ( $brix_star = 1; $brix_star <= 5; $brix_star++ ) {
					printf(
						'<span class="brix-stars__item%s" aria-hidden="true">★</span>',
						$brix_star <= round( $brix_rating ) ? ' is-on' : ''
					);
				}
				?>
			</span>
			<b class="brix-mono"><?php echo esc_html( number_format( (float) $brix_product->get_average_rating(), 1, ',', '' ) ); ?></b>
			<a class="brix-small brix-muted" href="#reviews">
				<?php
				printf(
					/* translators: %s — кількість відгуків. */
					esc_html( brix_plural( $brix_product->get_review_count(), __( '%s відгук', 'brix' ), __( '%s відгуки', 'brix' ), __( '%s відгуків', 'brix' ) ) ),
					esc_html( (string) $brix_product->get_review_count() )
				);
				?>
			</a>
		</p>
	<?php endif; ?>

	<div class="brix-buy__price">
		<b class="brix-mono"><?php echo wp_kses_post( wc_price( (float) $brix_current->get_price() ) ); ?></b>

		<?php if ( $brix_variation ) : ?>
			<?php $brix_per_kilo = brix_price_per_kilo( $brix_variation ); ?>
			<?php if ( '' !== $brix_per_kilo ) : ?>
				<span class="brix-small brix-muted"><?php echo esc_html( $brix_per_kilo ); ?></span>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<?php if ( $brix_variable ) : ?>
		<?php
		foreach ( $brix_product->get_attributes() as $brix_attribute ) :
			if ( ! $brix_attribute->get_variation() ) {
				continue;
			}

			$brix_key           = 'attribute_' . sanitize_title( $brix_attribute->get_name() );
			$brix_current_value = $brix_selected[ $brix_key ] ?? '';
			?>
			<fieldset class="brix-buy__choice">
				<legend class="brix-buy__legend"><?php echo esc_html( wc_attribute_label( $brix_attribute->get_name() ) ); ?></legend>

				<div class="brix-buy__options">
					<?php foreach ( brix_attribute_values( $brix_attribute, $brix_product->get_id() ) as $brix_option ) : ?>
						<?php
						$brix_on = $brix_option === $brix_current_value;

						// Чи існує така комбінація взагалі: у Lab-лотів
						// є тільки 100 г, і показувати 1 кг як доступний
						// було б обманом.
						$brix_probe  = array_merge( $brix_selected, array( $brix_key => $brix_option ) );
						$brix_exists = null !== brix_matching_variation( $brix_product, $brix_probe );
						?>
						<a
							class="brix-chip<?php echo $brix_on ? ' is-active' : ''; ?><?php echo $brix_exists ? '' : ' is-unavailable'; ?>"
							href="<?php echo esc_url( brix_variation_url( $brix_product, $brix_selected, $brix_key, $brix_option ) ); ?>"
							aria-pressed="<?php echo $brix_on ? 'true' : 'false'; ?>"
							<?php echo $brix_exists ? '' : 'aria-disabled="true"'; ?>
						>
							<?php echo esc_html( brix_attribute_label( $brix_attribute, $brix_option ) ); ?>
						</a>
					<?php endforeach; ?>
				</div>
			</fieldset>
		<?php endforeach; ?>

		<?php if ( $brix_lot && $brix_lot->brew_guide() ) : ?>
			<p class="brix-buy__hint brix-small brix-muted">
				<?php
				printf(
					/* translators: %s — посилання на гайд заварювання. */
					esc_html__( 'Змелемо перед самою відправкою. Як заварювати — %s.', 'brix' ),
					'<a href="' . esc_url( (string) get_permalink( $brix_lot->brew_guide() ) ) . '">'
						. esc_html( get_the_title( $brix_lot->brew_guide() ) ) . '</a>'
				);
				?>
			</p>
		<?php endif; ?>
	<?php endif; ?>

	<?php get_template_part( 'template-parts/product/subscription', null, array( 'product' => $brix_current ) ); ?>

	<?php if ( $brix_variable && ! $brix_variation ) : ?>
		<p class="brix-buy__unavailable"><?php esc_html_e( 'Такої комбінації немає. Оберіть іншу вагу або помел.', 'brix' ); ?></p>
	<?php elseif ( ! $brix_current->is_in_stock() ) : ?>
		<p class="brix-buy__unavailable"><?php esc_html_e( 'Лот закінчився. Напишіть нам — підкажемо схожий.', 'brix' ); ?></p>
	<?php else : ?>
		<form class="brix-buy__form" method="post" action="<?php echo esc_url( wc_get_cart_url() ); ?>">
			<?php
			brix_quantity_stepper(
				array(
					'id'   => 'brix-qty',
					'min'  => 1,
					'max'  => $brix_current->get_max_purchase_quantity(),
					'size' => 'xl',
				)
			);
			?>

			<button class="brix-btn brix-btn--xl brix-buy__submit" type="submit">
				<?php esc_html_e( 'Додати в кошик', 'brix' ); ?>
				<span class="brix-mono">· <?php echo esc_html( wp_strip_all_tags( wc_price( (float) $brix_current->get_price() ) ) ); ?></span>
			</button>

			<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( (string) $brix_product->get_id() ); ?>">

			<?php if ( $brix_variation ) : ?>
				<input type="hidden" name="variation_id" value="<?php echo esc_attr( (string) $brix_variation->get_id() ); ?>">
				<?php foreach ( $brix_selected as $brix_name => $brix_value ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $brix_name ); ?>" value="<?php echo esc_attr( $brix_value ); ?>">
				<?php endforeach; ?>
			<?php endif; ?>
		</form>
	<?php endif; ?>

	<?php get_template_part( 'template-parts/product/freshness', null, array( 'lot' => $brix_lot ) ); ?>

	<ul class="brix-buy__promises">
		<li><?php echo brix_icon( 'truck' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Нова Пошта завтра, якщо замовити до 16:00', 'brix' ); ?></li>
		<li><?php echo brix_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Не сподобалось — замінимо лот', 'brix' ); ?></li>
	</ul>
</div>
