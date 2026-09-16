<?php
/**
 * Транслітерація кирилиці в URL.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Робить із «Ефіопія» slug `efiopiia`, а не `%d0%b5%d1%84...`.
 *
 * WordPress лишає кирилицю в slug як є, і в адресному рядку вона
 * перетворюється на відсоткову кашу: `/country/%d0%b5%d1%84%d1%96...`.
 * Таке посилання не можна ні прочитати, ні надіслати в месенджері,
 * а ТЗ вимагає чисті URL фільтрів.
 *
 * Таблиця — офіційна українська транслітерація (постанова КМУ 55:2010),
 * та сама, що в закордонних паспортах.
 */
final class Slug {

	/**
	 * Літери, що читаються по-різному на початку слова й усередині.
	 *
	 * @var array<string, array{0: string, 1: string}>
	 */
	private const POSITIONAL = array(
		'є' => array( 'ye', 'ie' ),
		'ї' => array( 'yi', 'i' ),
		'й' => array( 'y', 'i' ),
		'ю' => array( 'yu', 'iu' ),
		'я' => array( 'ya', 'ia' ),
	);

	/**
	 * Решта літер.
	 *
	 * @var array<string, string>
	 */
	private const PLAIN = array(
		'а'  => 'a',
		'б'  => 'b',
		'в'  => 'v',
		'г'  => 'h',
		'ґ'  => 'g',
		'д'  => 'd',
		'е'  => 'e',
		'ж'  => 'zh',
		'з'  => 'z',
		'и'  => 'y',
		'і'  => 'i',
		'к'  => 'k',
		'л'  => 'l',
		'м'  => 'm',
		'н'  => 'n',
		'о'  => 'o',
		'п'  => 'p',
		'р'  => 'r',
		'с'  => 's',
		'т'  => 't',
		'у'  => 'u',
		'ф'  => 'f',
		'х'  => 'kh',
		'ц'  => 'ts',
		'ч'  => 'ch',
		'ш'  => 'sh',
		'щ'  => 'shch',
		'ь'  => '',
		'ъ'  => '',
		'ы'  => 'y',
		'э'  => 'e',
		'ё'  => 'e',
		'’'  => '',
		'\'' => '',
	);

	/**
	 * Транслітерує рядок і робить із нього slug.
	 *
	 * @param string $text Вихідний текст.
	 * @return string
	 */
	public static function latin( string $text ): string {
		$text  = mb_strtolower( $text, 'UTF-8' );
		$out   = '';
		$start = true;

		$chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );

		foreach ( is_array( $chars ) ? $chars : array() as $char ) {
			if ( isset( self::POSITIONAL[ $char ] ) ) {
				$out  .= self::POSITIONAL[ $char ][ $start ? 0 : 1 ];
				$start = false;
				continue;
			}

			if ( isset( self::PLAIN[ $char ] ) ) {
				$out  .= self::PLAIN[ $char ];
				$start = false;
				continue;
			}

			// Межа слова: наступна літера знову вважається початковою.
			$start = ! preg_match( '/[a-z0-9]/u', $char );
			$out  .= $char;
		}

		// «zh» і «shch» — уже латиниця, тож sanitize_title її не чіпає.
		return sanitize_title( $out );
	}
}
