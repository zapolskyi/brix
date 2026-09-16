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
	/**
	 * Дозволяє змінити повідомлення в смузі оголошень.
	 *
	 * Поки що значення зашиті; на фазі 5 вони переїдуть у налаштування
	 * теми разом з порогом безкоштовної доставки.
	 *
	 * @param array<int, string> $items Повідомлення.
	 */
	$items = apply_filters(
		'brix_announce_items',
		array(
			__( 'Безкоштовна доставка від 1 200 ₴', 'brix' ),
			__( 'Обсмажуємо щопонеділка', 'brix' ),
			__( 'Відправка в день обсмаження', 'brix' ),
		)
	);

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
