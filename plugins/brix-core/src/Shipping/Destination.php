<?php
/**
 * Куди їде замовлення: Україна чи закордон.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Shipping;

defined( 'ABSPATH' ) || exit;

/**
 * Країна призначення поточного кошика.
 *
 * Від неї залежить майже все на checkout: чи питати відділення Нової
 * Пошти, чи вимагати індекс, чи пропонувати оплату при отриманні.
 * Доки цієї відповіді не було, форма лишалась новопоштівською для
 * будь-якої країни — покупець із Варшави шукав «Wars» у довіднику
 * українських міст і не знаходив нічого.
 *
 * Класу-модуля тут немає навмисно: це не поведінка, а одне питання,
 * яке ставлять із різних місць.
 */
final class Destination {

	/**
	 * Код домашньої країни.
	 *
	 * @var string
	 */
	public const HOME = 'UA';

	/**
	 * Країна, куди зараз збирається замовлення.
	 *
	 * Спершу POST, потім покупець у сесії, потім база магазину.
	 * Порядок саме такий, бо під час оформлення WooCommerce читає
	 * поля форми раніше, ніж записує їх у сесію: якби ми дивились
	 * лише в сесію, перевірка стосувалася б попередньої країни.
	 *
	 * @return string
	 */
	public static function country(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce перевіряє WooCommerce; тут лише читання вибору.
		$ship_to = ! empty( $_POST['ship_to_different_address'] );
		$key     = $ship_to ? 'shipping_country' : 'billing_country';

		if ( isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ) {
			$posted = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );

			if ( '' !== $posted ) {
				return strtoupper( $posted );
			}
		}
		// phpcs:enable

		$customer = WC()->customer ?? null;

		if ( $customer instanceof \WC_Customer ) {
			$country = (string) $customer->get_shipping_country();

			if ( '' === $country ) {
				$country = (string) $customer->get_billing_country();
			}

			if ( '' !== $country ) {
				return strtoupper( $country );
			}
		}

		$base = (string) WC()->countries->get_base_country();

		return strtoupper( '' === $base ? self::HOME : $base );
	}

	/**
	 * Чи їде посилка по Україні.
	 *
	 * @return bool
	 */
	public static function is_home(): bool {
		return self::HOME === self::country();
	}
}
