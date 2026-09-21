<?php
/**
 * Оплата при отриманні: поріг суми.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Payments;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Обмежує накладний платіж сумою замовлення.
 *
 * Рішення 18: дрібне замовлення можна взяти накладним платежем, велике
 * йде передоплатою. Причина не в недовірі до покупця, а в тому, що
 * кава мелеться під замовлення — неотриману посилку зі змеленим під
 * V60 лотом не перепродаси.
 *
 * Поріг живе в налаштуваннях самого способу оплати, а не в константі:
 * це бізнес-правило, і міняти його мають без деплою.
 */
final class CashOnDelivery implements Module {

	/**
	 * Ключ поля в налаштуваннях способу оплати.
	 */
	private const FIELD = 'brix_max_total';

	/**
	 * Поріг за замовчуванням, гривень.
	 */
	private const DEFAULT_MAX = 2000;

	/**
	 * Вішає хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_settings_api_form_fields_cod', array( $this, 'add_setting' ) );
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_gateways' ) );
	}

	/**
	 * Додає поле порога в налаштування способу оплати.
	 *
	 * @param array<string, array<string, mixed>> $fields Поля.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_setting( array $fields ): array {
		$fields[ self::FIELD ] = array(
			'title'             => __( 'Максимальна сума замовлення', 'brix-core' ),
			'type'              => 'number',
			'description'       => __( 'Понад цю суму оплата при отриманні недоступна. Нуль вимикає обмеження.', 'brix-core' ),
			'default'           => (string) self::DEFAULT_MAX,
			'desc_tip'          => false,
			'custom_attributes' => array(
				'min'  => '0',
				'step' => '50',
			),
		);

		return $fields;
	}

	/**
	 * Прибирає накладний платіж із дорогих замовлень.
	 *
	 * @param array<string, \WC_Payment_Gateway> $gateways Доступні способи.
	 * @return array<string, \WC_Payment_Gateway>
	 */
	public function filter_gateways( array $gateways ): array {
		if ( is_admin() || ! isset( $gateways['cod'] ) || ! WC()->cart ) {
			return $gateways;
		}

		$max = (float) $gateways['cod']->get_option( self::FIELD, (string) self::DEFAULT_MAX );

		if ( $max <= 0 ) {
			return $gateways;
		}

		/*
		 * Беремо підсумок кошика, а не разом із доставкою: покупець
		 * читає поріг як «до 2 000 ₴ можна накладним», і додавати
		 * туди вартість доставки було б несподіванкою.
		 */
		$total = (float) WC()->cart->get_displayed_subtotal();

		if ( $total <= $max ) {
			return $gateways;
		}

		/*
		 * Прибирати накладний платіж можна лише тоді, коли покупцю
		 * лишається чим заплатити. Доки онлайн-оплати немає, поріг
		 * зробив би дороге замовлення неоформлюваним — способів
		 * оплати не лишалось би взагалі.
		 */
		if ( count( $gateways ) < 2 ) {
			return $gateways;
		}

		unset( $gateways['cod'] );

		/*
		 * Мовчки прибраний спосіб оплати виглядає як помилка сайту,
		 * тож пояснюємо просто на checkout.
		 */
		if ( is_checkout() && ! wc_has_notice( $this->notice( $max ), 'notice' ) ) {
			wc_add_notice( $this->notice( $max ), 'notice' );
		}

		return $gateways;
	}

	/**
	 * Пояснення, чому накладного платежу немає.
	 *
	 * @param float $max Поріг.
	 * @return string
	 */
	private function notice( float $max ): string {
		return sprintf(
			/* translators: %s — сума порога. */
			__( 'Оплата при отриманні доступна для замовлень до %s. Для більших сум лишається оплата карткою — так ми впевнені, що змелений під ваш рецепт лот дочекається саме вас.', 'brix-core' ),
			wp_strip_all_tags( wc_price( $max ) )
		);
	}
}
