<?php
/**
 * Транзакційні листи.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Брендові доповнення до стилів листа.
 *
 * Правимо поверх стилів WooCommerce, а не замість них: свій
 * email-styles.php довелося б підтримувати при кожному оновленні Woo,
 * а виграш — кілька селекторів.
 *
 * @param string $css Стилі.
 * @return string
 */
function brix_email_styles( string $css ): string {
	return $css . '
		body, #wrapper {
			background-color: #F2F3EE;
		}
		#template_container {
			border: 1px solid #D4D6CB;
			border-radius: 7px;
			box-shadow: none;
		}
		#template_header {
			background-color: #1D1A19;
			border-radius: 6px 6px 0 0;
			border-bottom: 0;
		}
		#header_wrapper {
			padding: 32px 36px 28px;
		}
		.brix-mark {
			margin: 0 0 14px;
			font-family: Helvetica, Arial, sans-serif;
			font-size: 22px;
			font-weight: 800;
			letter-spacing: -0.04em;
			color: #F2F3EE;
			line-height: 1;
		}
		.brix-mark sup {
			font-size: 10px;
			color: #B61F3A;
			vertical-align: super;
		}
		#template_header h1 {
			margin: 0;
			font-family: Helvetica, Arial, sans-serif;
			font-size: 28px;
			font-weight: 700;
			letter-spacing: -0.02em;
			line-height: 1.15;
			color: #F2F3EE;
			text-align: left;
			text-shadow: none;
		}
		#body_content_inner {
			font-family: Helvetica, Arial, sans-serif;
			font-size: 15px;
			line-height: 1.6;
			color: #1D1A19;
			text-align: left;
		}
		#body_content h2 {
			font-family: Helvetica, Arial, sans-serif;
			font-size: 18px;
			font-weight: 700;
			color: #1D1A19;
		}
		#body_content table.td,
		#body_content td.td {
			border-color: #D4D6CB;
			color: #1D1A19;
		}
		#body_content th.td {
			border-color: #D4D6CB;
			color: #6C6763;
			font-size: 11px;
			letter-spacing: 0.12em;
			text-transform: uppercase;
		}
		#template_footer #credit {
			font-family: Helvetica, Arial, sans-serif;
			font-size: 11px;
			letter-spacing: 0.1em;
			text-transform: uppercase;
			color: #6C6763;
		}
		a { color: #B61F3A; }
	';
}
add_filter( 'woocommerce_email_styles', 'brix_email_styles' );

/**
 * Додає дані лоту в рядок товару в листі.
 *
 * ТЗ обіцяє паспорт лоту в листі після покупки — але лист має
 * лишатись листом, а не сторінкою товару. Тому тут коротко:
 * код лоту, °Bx і коли обсмажено. Решта — за посиланням.
 *
 * @param string        $html     Поточна розмітка.
 * @param object        $item     Позиція замовлення.
 * @param array<string> $args     Аргументи.
 * @return string
 */
function brix_email_item_meta( string $html, $item, array $args ): string {
	if ( empty( $args['plain_text'] ) === false ) {
		return $html;
	}

	$product = is_object( $item ) && method_exists( $item, 'get_product' ) ? $item->get_product() : null;

	if ( ! $product instanceof WC_Product ) {
		return $html;
	}

	$lot = brix_lot( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id() );

	if ( ! $lot || ! $lot->has_passport() ) {
		return $html;
	}

	$bits = array();

	if ( '' !== $lot->code ) {
		$bits[] = $lot->code;
	}

	if ( null !== $lot->brix ) {
		$bits[] = $lot->brix . ' °Bx';
	}

	if ( $lot->roast_date ) {
		/* translators: %s — дата обсмаження. */
		$bits[] = sprintf( __( 'обсмажено %s', 'brix' ), $lot->roast_date->format( 'd.m' ) );
	}

	if ( ! $bits ) {
		return $html;
	}

	/*
	 * Без власних стилів: WooCommerce обгортає мета позиції у власний
	 * блок, уже дрібний і приглушений, а атрибут style на вкладеному
	 * тегу все одно зрізає санітизація листа.
	 */
	return $html . '<span>' . esc_html( implode( ' · ', $bits ) ) . '</span>';
}
add_filter( 'woocommerce_display_item_meta', 'brix_email_item_meta', 20, 3 );
