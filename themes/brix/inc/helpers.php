<?php
/**
 * Дрібні помічники, якими користуються решта модулів.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Чи активний WooCommerce.
 *
 * Тема має лишатись робочою і без нього: на чистій інсталяції
 * сторінки контенту мусять віддавати 200, а не фатал.
 *
 * @return bool
 */
function brix_has_woocommerce(): bool {
	return class_exists( 'WooCommerce' );
}

/**
 * Чи активний плагін brix-core з бізнес-логікою магазину.
 *
 * @return bool
 */
function brix_has_core(): bool {
	return defined( 'BRIX_CORE_VERSION' );
}

/**
 * Версія файлу за часом його зміни.
 *
 * Дає браузеру зрозуміти, що CSS перезібрано, без ручного бампу версії
 * теми і без ?ver=timestamp, який ламає кеш на кожному деплої.
 *
 * @param string $relative Шлях відносно кореня теми.
 * @return string
 */
function brix_asset_version( string $relative ): string {
	$path = BRIX_DIR . '/' . ltrim( $relative, '/' );

	return is_readable( $path ) ? (string) filemtime( $path ) : BRIX_VERSION;
}

/**
 * URL файлу в темі.
 *
 * @param string $relative Шлях відносно кореня теми.
 * @return string
 */
function brix_asset_uri( string $relative ): string {
	return BRIX_URI . '/' . ltrim( $relative, '/' );
}

/**
 * Маніфест локальних шрифтів.
 *
 * Самі @font-face лежать у скомпільованому CSS; маніфест потрібен лише
 * щоб знати, які файли варто попередньо завантажити. Повертає тільки
 * записи, для яких файл реально є в темі, — битий маніфест не має
 * перетворюватись на 404.
 *
 * @return array<int, array<string, mixed>>
 */
function brix_font_faces(): array {
	static $faces = null;

	if ( null !== $faces ) {
		return $faces;
	}

	$faces    = array();
	$manifest = BRIX_DIR . '/assets/fonts/fonts.json';

	if ( ! is_readable( $manifest ) ) {
		return $faces;
	}

	$decoded = json_decode( (string) file_get_contents( $manifest ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

	if ( ! is_array( $decoded ) ) {
		return $faces;
	}

	foreach ( $decoded as $face ) {
		if ( empty( $face['file'] ) || ! is_readable( BRIX_DIR . '/assets/fonts/' . $face['file'] ) ) {
			continue;
		}

		$faces[] = $face;
	}

	return $faces;
}

/**
 * Українська множина: три форми замість двох.
 *
 * `_n()` вибирає форму за правилом мови перекладу. Поки в темі немає
 * .po-файлів, він відкочується на англійське правило «один або решта»
 * і дає «24 пачок» там, де треба «24 пачки». Для інтерфейсних рядків
 * із числами це помітно, тож рахуємо форму самі.
 *
 * @param int    $number Число.
 * @param string $one    1 пачка.
 * @param string $few    2 пачки.
 * @param string $many   5 пачок.
 * @return string
 */
function brix_plural( int $number, string $one, string $few, string $many ): string {
	$number = absint( $number );
	$mod10  = $number % 10;
	$mod100 = $number % 100;

	if ( 1 === $mod10 && 11 !== $mod100 ) {
		return $one;
	}

	if ( $mod10 >= 2 && $mod10 <= 4 && ( $mod100 < 12 || $mod100 > 14 ) ) {
		return $few;
	}

	return $many;
}
