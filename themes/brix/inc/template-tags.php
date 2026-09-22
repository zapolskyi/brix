<?php
/**
 * Теги шаблонів: дрібні друкувальні функції для розмітки.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Логотип: словесний знак BRIX з надрядковим «22°».
 *
 * Якщо в налаштуваннях завантажено зображення — показуємо його.
 * Інакше друкуємо текстовий знак: він масштабується без втрат
 * і не додає запиту до першого малювання.
 *
 * @return void
 */
function brix_logo(): void {
	if ( has_custom_logo() ) {
		the_custom_logo();
		return;
	}

	printf(
		'<a class="brix-logo" href="%1$s" rel="home"><span>BRIX</span><sup aria-hidden="true">22°</sup><span class="brix-visually-hidden">%2$s</span></a>',
		esc_url( home_url( '/' ) ),
		esc_html__( '22 градуси — на головну', 'brix' )
	);
}

/**
 * Смуга оголошень над шапкою.
 *
 * @return void
 */
function brix_announce(): void {
	$messages = array(
		__( 'Обсмажуємо щопонеділка', 'brix' ),
		__( 'Відправка в день обсмаження', 'brix' ),
	);

	/*
	 * Поріг береться з методу доставки, а не з тексту: інакше смуга
	 * обіцяла б одну суму, а WooCommerce рахував іншу, і розбіжність
	 * виявилась би аж на checkout.
	 */
	$threshold = function_exists( 'brix_free_shipping_threshold' ) ? brix_free_shipping_threshold() : 0.0;

	if ( $threshold > 0 ) {
		array_unshift(
			$messages,
			sprintf(
				/* translators: %s — сума, від якої доставка безкоштовна. */
				__( 'Безкоштовна доставка від %s', 'brix' ),
				wp_strip_all_tags( wc_price( $threshold ) )
			)
		);
	}

	/**
	 * Дозволяє змінити повідомлення в смузі оголошень.
	 *
	 * @param array<int, string> $items Повідомлення.
	 */
	$items = apply_filters( 'brix_announce_items', $messages );

	if ( ! $items ) {
		return;
	}

	echo '<div class="brix-announce">';

	foreach ( $items as $item ) {
		printf( '<span class="brix-announce__item">%s</span>', esc_html( $item ) );
	}

	echo '</div>';
}

/**
 * Кількість позицій у кошику.
 *
 * @return int
 */
function brix_bag_count(): int {
	if ( ! brix_has_woocommerce() || is_null( WC()->cart ) ) {
		return 0;
	}

	return (int) WC()->cart->get_cart_contents_count();
}

/**
 * Лічильник над іконкою кошика.
 *
 * Винесено окремо, бо цей самий шматок віддає REST після додавання
 * товару без перезавантаження — і малюється він тим самим кодом.
 *
 * @return void
 */
function brix_bag_badge(): void {
	$count = brix_bag_count();

	if ( $count < 1 ) {
		return;
	}

	printf(
		'<em class="brix-bag__count" aria-hidden="true">%s</em>',
		esc_html( (string) $count )
	);
}

/**
 * Іконка з набору теми.
 *
 * Іконки інлайняться в розмітку, а не тягнуться спрайтом: їх мало,
 * вони фарбуються currentColor і не дають зайвого запиту.
 *
 * @param string $name Назва іконки.
 * @return string
 */
function brix_icon( string $name ): string {
	$paths = array(
		'search'  => '<circle cx="11" cy="11" r="7"/><path d="M16.3 16.3 21 21"/>',
		'account' => '<circle cx="12" cy="8.5" r="3.8"/><path d="M4.5 20.5c0-3.9 3.4-6 7.5-6s7.5 2.1 7.5 6"/>',
		'bag'     => '<path d="M6.2 8h11.6l1.1 12.5H5.1L6.2 8Z"/><path d="M9.2 8.6V6.4a2.8 2.8 0 0 1 5.6 0v2.2"/>',
		'menu'    => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
		'close'   => '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>',
		'arrow'   => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
		'clock'   => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
		'truck'   => '<path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
		'drop'    => '<path d="M12 3.5s5.5 6 5.5 9.5a5.5 5.5 0 0 1-11 0C6.5 9.5 12 3.5 12 3.5Z"/>',
		'plus'    => '<path d="M12 5v14"/><path d="M5 12h14"/>',
		'minus'   => '<path d="M5 12h14"/>',
		'filter'  => '<path d="M4 6h16"/><path d="M7 12h10"/><path d="M10 18h4"/>',
		'check'   => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
	);

	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
		. ' stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
		. $paths[ $name ] . '</svg>';
}

/**
 * Друкує пункти меню голими посиланнями, без списку.
 *
 * @param string $location Зареєстроване місце меню.
 * @return void
 */
function brix_menu_links( string $location ): void {
	if ( ! has_nav_menu( $location ) ) {
		return;
	}

	wp_nav_menu(
		array(
			'theme_location' => $location,
			'container'      => false,
			'items_wrap'     => '%3$s',
			'depth'          => 1,
			'walker'         => new Brix_Plain_Walker(),
		)
	);
}

/**
 * Колонка посилань у підвалі: заголовок і стовпчик пунктів меню.
 *
 * @param string $location Зареєстроване місце меню.
 * @param string $title    Заголовок колонки.
 * @return void
 */
function brix_footer_column( string $location, string $title ): void {
	if ( ! has_nav_menu( $location ) ) {
		return;
	}

	echo '<div class="brix-footer__col">';
	printf( '<span class="brix-footer__col-title">%s</span>', esc_html( $title ) );
	brix_menu_links( $location );
	echo '</div>';
}

/**
 * Поле кількості з кнопками «−» і «+».
 *
 * Кнопки — надбудова, а не основа: без JavaScript вони не потрібні,
 * бо `type="number"` уже дає і клавіатурні стрілки, і крок. Тому вони
 * виходять з атрибутом `hidden`, а скрипт його знімає. Так сторінка
 * з вимкненим JS лишається робочою, а не показує три кнопки, дві з
 * яких нічого не роблять.
 *
 * @param array<string, mixed> $args Налаштування поля.
 * @return void
 */
function brix_quantity_stepper( array $args = array() ): void {
	$args = wp_parse_args(
		$args,
		array(
			'name'  => 'quantity',
			'value' => 1,
			'min'   => 1,
			'max'   => 0,
			'id'    => 'brix-qty',
			'label' => __( 'Кількість', 'brix' ),
			'size'  => '',
		)
	);

	$classes = 'brix-stepper';

	if ( '' !== $args['size'] ) {
		$classes .= ' brix-stepper--' . sanitize_html_class( (string) $args['size'] );
	}
	?>
	<div class="<?php echo esc_attr( $classes ); ?>" data-brix-stepper>
		<label class="brix-visually-hidden" for="<?php echo esc_attr( (string) $args['id'] ); ?>">
			<?php echo esc_html( (string) $args['label'] ); ?>
		</label>

		<button class="brix-stepper__btn" type="button" data-brix-step="-1"
			aria-controls="<?php echo esc_attr( (string) $args['id'] ); ?>"
			aria-label="<?php esc_attr_e( 'Зменшити кількість', 'brix' ); ?>" hidden>
			<?php echo brix_icon( 'minus' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</button>

		<input
			class="brix-stepper__input"
			id="<?php echo esc_attr( (string) $args['id'] ); ?>"
			type="number"
			name="<?php echo esc_attr( (string) $args['name'] ); ?>"
			value="<?php echo esc_attr( (string) $args['value'] ); ?>"
			min="<?php echo esc_attr( (string) $args['min'] ); ?>"
			<?php echo (int) $args['max'] > 0 ? 'max="' . esc_attr( (string) (int) $args['max'] ) . '"' : ''; ?>
			step="1"
			inputmode="numeric"
			autocomplete="off"
		>

		<button class="brix-stepper__btn" type="button" data-brix-step="1"
			aria-controls="<?php echo esc_attr( (string) $args['id'] ); ?>"
			aria-label="<?php esc_attr_e( 'Збільшити кількість', 'brix' ); ?>" hidden>
			<?php echo brix_icon( 'plus' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</button>
	</div>
	<?php
}
