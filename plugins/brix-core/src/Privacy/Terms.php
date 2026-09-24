<?php
/**
 * Згода з умовами продажу на checkout.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Privacy;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Галочка «Погоджуюсь з умовами продажу».
 *
 * Саму галочку й перевірку дає WooCommerce, щойно в налаштуваннях
 * вказано сторінку умов. Тут — лише текст. Стандартний підставляє
 * назву «правила та умови» в чужу фразу: «Я прочитав (а) і
 * погоджуюся з правилами сайту правила та умови». Англійською так
 * само криво.
 *
 * Текст сторінки WooCommerce ще й вбудовує прихованим блоком у
 * checkout, щоб розгортати скриптом. Це кілька екранів юридичного
 * тексту в кожній сторінці оформлення — натомість звичайне посилання,
 * що відкривається в новій вкладці й не губить заповнену форму.
 */
final class Terms implements Module {

	/**
	 * Вішає хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_get_terms_and_conditions_checkbox_text', array( $this, 'text' ) );
		add_action( 'init', array( $this, 'drop_inline_page' ) );
		add_filter( 'gettext_woocommerce', array( $this, 'error_text' ), 10, 2 );
	}

	/**
	 * Помилка, коли галочку не поставили.
	 *
	 * Український переклад WooCommerce каже «прийміть умови та умови».
	 *
	 * @param string $translation Переклад.
	 * @param string $text        Оригінал.
	 * @return string
	 */
	public function error_text( $translation, $text ): string {
		if ( 'Please read and accept the terms and conditions to proceed with your order.' !== $text ) {
			return (string) $translation;
		}

		return __( 'Поставте галочку «Я погоджуюсь з умовами продажу» — без неї замовлення не оформити.', 'brix-core' );
	}

	/**
	 * Текст біля галочки.
	 *
	 * @param string $text Текст WooCommerce.
	 * @return string
	 */
	public function text( $text ): string {
		$page = function_exists( 'wc_terms_and_conditions_page_id' ) ? (int) wc_terms_and_conditions_page_id() : 0;

		if ( ! $page ) {
			return (string) $text;
		}

		return sprintf(
			/* translators: %s — посилання на сторінку умов продажу. */
			__( 'Я погоджуюсь з %s', 'brix-core' ),
			sprintf(
				'<a href="%1$s" target="_blank" rel="noopener">%2$s</a>',
				esc_url( get_permalink( $page ) ),
				esc_html__( 'умовами продажу', 'brix-core' )
			)
		);
	}

	/**
	 * Прибирає вбудований текст сторінки умов.
	 *
	 * @return void
	 */
	public function drop_inline_page(): void {
		remove_action( 'woocommerce_checkout_terms_and_conditions', 'wc_terms_and_conditions_page_content', 30 );
	}
}
