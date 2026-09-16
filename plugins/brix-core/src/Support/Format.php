<?php
/**
 * Форматування чисел і часу.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Перетворює числа на рядки, придатні і для HTML, і для листа.
 */
final class Format {

	/** Нерозривний пробіл. Символ, не сутність. */
	public const NBSP = "\u{00A0}";

	/**
	 * Число з нерозривним пробілом між тисячами.
	 *
	 * `number_format_i18n()` під локаллю uk віддає роздільником
	 * літеральну сутність `&nbsp;`. У шаблоні вона відрендериться
	 * правильно, бо `esc_html()` у WordPress не екранує повторно,
	 * але в листі, JSON чи CSV той самий рядок витече як текст
	 * «2&nbsp;050». Тому шар даних віддає справжній символ,
	 * а рішення про розмітку лишає шаблону.
	 *
	 * @param float $value    Число.
	 * @param int   $decimals Знаків після коми.
	 * @return string
	 */
	public static function number( float $value, int $decimals = 0 ): string {
		return number_format( $value, $decimals, ',', self::NBSP );
	}

	/**
	 * Секунди у вигляді `3:00`.
	 *
	 * @param int $seconds Секунди.
	 * @return string
	 */
	public static function duration( int $seconds ): string {
		return sprintf( '%d:%02d', intdiv( $seconds, 60 ), $seconds % 60 );
	}
}
