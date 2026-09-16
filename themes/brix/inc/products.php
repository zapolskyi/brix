<?php
/**
 * Помічники для виводу товарів.
 *
 * Тема не читає мета-поля сама: усі дані лоту приходять з плагіна
 * brix-core через LotMeta. Якщо плагіна немає, товар показується
 * без паспорта — без фаталу і без порожніх плашок.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Паспорт лоту для товару.
 *
 * @param int|WC_Product|WP_Post $product Товар.
 * @return \Brix\Core\Product\Lot|null
 */
function brix_lot( $product ) {
	if ( ! brix_has_core() || ! class_exists( \Brix\Core\Product\LotMeta::class ) ) {
		return null;
	}

	return \Brix\Core\Product\LotMeta::for_product( $product );
}

/**
 * Клас стилю пачки для товару: natural, washed, honey, lab, core, drip.
 *
 * @param int|WC_Product|WP_Post $product Товар.
 * @return string
 */
function brix_pack_style( $product ): string {
	$lot = brix_lot( $product );

	return $lot ? $lot->pack_style() : '';
}

/**
 * Чи є товар лотом кави.
 *
 * Обладнання, набори й сертифікати — теж товари, але пачки в них
 * немає: чайник з печаткою «22 °Bx» виглядає як помилка, і це вона
 * і є. Ознака — стиль пачки: його мають лінійки кави й обробки.
 *
 * @param WC_Product $product Товар.
 * @return bool
 */
function brix_is_coffee( WC_Product $product ): bool {
	return '' !== brix_pack_style( $product );
}

/**
 * Друкує зображення товару для картки.
 *
 * Для кави це мальована пачка, для решти — фото або плейсхолдер.
 *
 * @param WC_Product $product Товар.
 * @param string     $size    Ширина пачки, напр. '54%'.
 * @return void
 */
function brix_the_product_visual( WC_Product $product, string $size = '54%' ): void {
	if ( brix_is_coffee( $product ) ) {
		brix_the_pack( $product, $size );
		return;
	}

	if ( $product->get_image_id() ) {
		echo wp_kses_post( $product->get_image( 'brix-card', array( 'class' => 'brix-card__photo' ) ) );
		return;
	}

	?>
	<div class="brix-photo brix-photo--dark brix-card__photo">
		<span class="brix-photo__caption"><?php echo esc_html( $product->get_name() ); ?></span>
	</div>
	<?php
}

/**
 * Друкує пачку кави для товару.
 *
 * Пачка мальована на CSS: колір бере з обробки або лінійки, назву —
 * з товару. Тому вона не потребує ні фото, ні окремого файлу на лот.
 *
 * @param WC_Product $product Товар.
 * @param string     $size    Значення для style="width:…", напр. '54%'.
 * @return void
 */
function brix_the_pack( WC_Product $product, string $size = '' ): void {
	$lot   = brix_lot( $product );
	$style = brix_pack_style( $product );
	$term  = $lot ? $lot->processing() : null;
	$line  = brix_product_line( $product );

	$classes = 'brix-pack' . ( $style ? ' brix-pack--' . $style : '' );
	?>
	<div class="<?php echo esc_attr( $classes ); ?>" <?php echo $size ? 'style="width:' . esc_attr( $size ) . '"' : ''; ?> aria-hidden="true">
		<div class="brix-pack__top">
			<span>BRIX 22°</span>
			<span><?php echo esc_html( $term ? $term->name : $line ); ?></span>
		</div>

		<?php if ( $lot && null !== $lot->brix ) : ?>
			<div class="brix-pack__seal">
				<?php echo esc_html( (string) $lot->brix ); ?><small>°Bx</small>
			</div>
		<?php endif; ?>

		<div class="brix-pack__name"><?php echo esc_html( $product->get_name() ); ?></div>

		<div class="brix-pack__data">
			<?php if ( $lot && '' !== $lot->code ) : ?>
				<span><?php echo esc_html( $lot->code ); ?></span>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Лінійка товару: Core, Origin, Lab, Drip & Try, Gear.
 *
 * @param WC_Product $product Товар.
 * @return string
 */
function brix_product_line( WC_Product $product ): string {
	$terms = get_the_terms( $product->get_id(), 'product_cat' );

	if ( ! is_array( $terms ) ) {
		return '';
	}

	// Назви ліній зберігаються як HTML (Drip &amp; Try), тож декодуємо:
	// далі рядок піде через esc_html і подвоїв би екранування.
	return html_entity_decode( $terms[0]->name, ENT_QUOTES, 'UTF-8' );
}

/**
 * Скільки пачок лишилось. Нуль означає «рахунок не ведеться».
 *
 * @param WC_Product $product Товар.
 * @return int
 */
function brix_stock_left( WC_Product $product ): int {
	if ( $product->is_type( 'variable' ) ) {
		$total = 0;

		foreach ( $product->get_children() as $child_id ) {
			$child = wc_get_product( $child_id );

			if ( $child && $child->managing_stock() ) {
				$total += max( 0, (int) $child->get_stock_quantity() );
			}
		}

		return $total;
	}

	return $product->managing_stock() ? max( 0, (int) $product->get_stock_quantity() ) : 0;
}

/**
 * Ціна товару рядком, як у макеті: «590 ₴» або «від 590 ₴».
 *
 * @param WC_Product $product Товар.
 * @return string
 */
function brix_price_label( WC_Product $product ): string {
	if ( $product->is_type( 'variable' ) ) {
		$min = (float) $product->get_variation_price( 'min' );
		$max = (float) $product->get_variation_price( 'max' );

		if ( $min < $max ) {
			/* translators: %s — найнижча ціна варіації. */
			return sprintf( __( 'від %s', 'brix' ), wc_price( $min ) );
		}

		return wc_price( $min );
	}

	return $product->get_price_html();
}

/**
 * Друкує сітку карток товару поза стандартним циклом WooCommerce.
 *
 * Шаблон картки читає глобальний $product — так влаштований Woo,
 * і переписувати його заради головної не варто. Тут глобальна змінна
 * підміняється на час циклу й повертається назад.
 *
 * @param array<int, WC_Product> $products Товари.
 * @return void
 */
function brix_render_product_cards( array $products ): void {
	if ( ! $products ) {
		return;
	}

	// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $product належить WooCommerce, шаблон картки читає саме його.
	global $product;

	$previous = $product;

	foreach ( $products as $item ) {
		if ( ! $item instanceof WC_Product ) {
			continue;
		}

		$post = get_post( $item->get_id() );

		if ( ! $post ) {
			continue;
		}

		$product = $item;
		setup_postdata( $post );
		wc_get_template_part( 'content', 'product' );
	}

	$product = $previous;
	// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

	wp_reset_postdata();
}
