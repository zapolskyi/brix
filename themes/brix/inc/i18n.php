<?php
/**
 * Мова сайту з боку теми.
 *
 * Саму мову визначає модуль brix-core: це маршрутизація, а не
 * оформлення. Тема лише питає, якою мовою малювати, і будує перемикач.
 * Без плагіна сайт лишається одномовним, і жоден шаблон не падає.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Клас модуля мови, якщо плагін на місці.
 *
 * @return class-string|null
 */
function brix_lang_class(): ?string {
	return class_exists( '\Brix\Core\I18n\Language' ) ? '\Brix\Core\I18n\Language' : null;
}

/**
 * Код поточної мови: uk або en.
 *
 * @return string
 */
function brix_lang(): string {
	$class = brix_lang_class();

	return $class ? $class::current() : 'uk';
}

/**
 * Чи англійська зараз.
 *
 * @return bool
 */
function brix_is_en(): bool {
	return 'en' === brix_lang();
}

/**
 * Мови сайту: код => підпис.
 *
 * @return array<string, string>
 */
function brix_languages(): array {
	$class = brix_lang_class();

	return $class ? $class::all() : array();
}

/**
 * Поточна сторінка іншою мовою.
 *
 * @param string $lang Код мови.
 * @return string
 */
function brix_lang_url( string $lang ): string {
	$class = brix_lang_class();

	return $class ? $class::switch_url( $lang ) : home_url( '/' );
}

/**
 * Перемикач мов у шапці.
 *
 * Це посилання, а не кнопка й не select: перехід між мовами — це
 * перехід між адресами. Тому він працює без JavaScript, відкривається
 * у новій вкладці середньою кнопкою і потрапляє в історію браузера.
 *
 * @return void
 */
function brix_language_switcher(): void {
	$languages = brix_languages();

	if ( count( $languages ) < 2 ) {
		return;
	}

	$current = brix_lang();
	?>
	<nav class="brix-lang" aria-label="<?php esc_attr_e( 'Мова сайту', 'brix' ); ?>">
		<?php foreach ( $languages as $brix_code => $brix_label ) : ?>
			<?php if ( $brix_code === $current ) : ?>
				<b class="brix-lang__item is-current" aria-current="true"><?php echo esc_html( $brix_label ); ?></b>
			<?php else : ?>
				<a class="brix-lang__item" href="<?php echo esc_url( brix_lang_url( $brix_code ) ); ?>"
					hreflang="<?php echo esc_attr( $brix_code ); ?>"
					lang="<?php echo esc_attr( $brix_code ); ?>">
					<?php echo esc_html( $brix_label ); ?>
				</a>
			<?php endif; ?>
		<?php endforeach; ?>
	</nav>
	<?php
}

/**
 * Перемикач валюти: ₴ / €.
 *
 * Валюта окремо від мови: мова — про те, як людина читає, валюта —
 * про те, чим вона платить. Посилання, а не форма: працює без
 * JavaScript, сервер запам'ятовує вибір і повертає на ту саму
 * сторінку вже без параметра.
 *
 * @return void
 */
function brix_currency_switcher(): void {
	if ( ! class_exists( '\Brix\Core\I18n\Currency' ) ) {
		return;
	}

	$currencies = \Brix\Core\I18n\Currency::all();

	if ( count( $currencies ) < 2 ) {
		return;
	}

	$current = \Brix\Core\I18n\Currency::chosen();
	?>
	<nav class="brix-lang brix-lang--currency" aria-label="<?php esc_attr_e( 'Валюта цін', 'brix' ); ?>">
		<?php foreach ( $currencies as $brix_code => $brix_sign ) : ?>
			<?php if ( $brix_code === $current ) : ?>
				<b class="brix-lang__item is-current" aria-current="true">
					<span aria-hidden="true"><?php echo esc_html( $brix_sign ); ?></span>
					<span class="brix-visually-hidden"><?php echo esc_html( $brix_code ); ?></span>
				</b>
			<?php else : ?>
				<a class="brix-lang__item" href="<?php echo esc_url( \Brix\Core\I18n\Currency::switch_url( $brix_code ) ); ?>" rel="nofollow">
					<span aria-hidden="true"><?php echo esc_html( $brix_sign ); ?></span>
					<span class="brix-visually-hidden">
						<?php
						/* translators: %s — код валюти. */
						printf( esc_html__( 'Ціни в %s', 'brix' ), esc_html( $brix_code ) );
						?>
					</span>
				</a>
			<?php endif; ?>
		<?php endforeach; ?>
	</nav>
	<?php
}

/**
 * Мова й валюта поруч — у шапці та в мобільному меню.
 *
 * @return void
 */
function brix_preferences(): void {
	?>
	<div class="brix-prefs">
		<?php brix_language_switcher(); ?>
		<?php brix_currency_switcher(); ?>
	</div>
	<?php
}

/**
 * Адреса всередині сайту з урахуванням мови.
 *
 * Посилання в атрибутах блоків редактор зберігає відносними: «/about/».
 * Під /en/ такий шлях вів би на українську сторінку, бо префікса в
 * ньому немає — його додає home_url(), через який відносний шлях тут
 * і пропускається. Зовнішні адреси лишаються як є.
 *
 * @param string $url Адреса або шлях.
 * @return string
 */
function brix_local_url( string $url ): string {
	$url = trim( $url );

	if ( '' === $url || 0 !== strpos( $url, '/' ) || 0 === strpos( $url, '//' ) ) {
		return $url;
	}

	return home_url( $url );
}

/**
 * Переклад рядка, що лежить у базі, а не в коді.
 *
 * Значення атрибутів блоків, назви способів доставки, підписи
 * платіжок — усе, чого не бачить .po, бо в коді цього тексту немає.
 * Таблиця таких рядків живе в плагіні; без нього повертається
 * оригінал.
 *
 * @param string $text Оригінал.
 * @return string
 */
function brix_t( string $text ): string {
	/** This filter is documented in brix-core/src/Shipping/Pickup.php */
	return (string) apply_filters( 'brix_translate', $text );
}

/**
 * Сума з екрана назад у валюту бази.
 *
 * @param float $amount Сума у валюті показу.
 * @return float
 */
function brix_store_amount( float $amount ): float {
	return class_exists( '\Brix\Core\I18n\Currency' )
		? \Brix\Core\I18n\Currency::to_store( $amount )
		: $amount;
}

/**
 * Символ валюти, якою зараз показуються ціни.
 *
 * @return string
 */
function brix_currency_symbol(): string {
	return function_exists( 'get_woocommerce_currency_symbol' )
		? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' )
		: '';
}
