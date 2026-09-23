<?php
/**
 * Checkout: поля, підписи, дрібні правки.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Прибирає й перейменовує поля checkout під український магазин.
 *
 * Менше полів — більше замовлень. Компанія, друга адреса й область
 * для доставки Новою Поштою не потрібні: відділення однозначно
 * визначає і місто, і область.
 *
 * @param array<string, array<string, mixed>> $fields Поля.
 * @return array<string, array<string, mixed>>
 */
function brix_checkout_fields( array $fields ): array {
	/*
	 * Компанію й другу адресу прибираємо всюди: менше полів — більше
	 * оформлених замовлень.
	 *
	 * Область лишається в розмітці, хоч українській адресі вона й не
	 * потрібна. Її ховає локаль країни нижче, і ховає саме в браузері
	 * — тож коли покупець перемикає країну на Італію, поле провінції
	 * з'являється само. Прибрана з PHP, вона не з'явилася б уже
	 * ніколи, і італійська адреса лишилась би без обов'язкової
	 * частини.
	 */
	unset(
		$fields['billing']['billing_company'],
		$fields['billing']['billing_address_2'],
		$fields['shipping']['shipping_company'],
		$fields['shipping']['shipping_address_2']
	);

	/*
	 * Черга полів адреси живе у brix_address_fields(): звідти її бере
	 * і PHP, і скрипт WooCommerce. Але для цих двох полів її треба
	 * повторити тут. Пошта до адресних полів не належить зовсім, а
	 * телефону WooCommerce прописує в checkout власний пріоритет 100
	 * поверх того, що стоїть у наборі адресних полів. Числа мають
	 * збігатися з тими, що у brix_address_fields(), інакше форма без
	 * JavaScript вишикується інакше, ніж із ним.
	 */
	$brix_priority = array(
		'billing_phone' => 30,
		'billing_email' => 40,
	);

	foreach ( $brix_priority as $brix_key => $brix_value ) {
		if ( isset( $fields['billing'][ $brix_key ] ) ) {
			$fields['billing'][ $brix_key ]['priority'] = $brix_value;
		}
	}

	/*
	 * Самовивіз адреси не має. Поля лишаються в розмітці, але
	 * необов'язковими: інакше скрипт не мав би що показати, коли
	 * покупець передумає й обере доставку без перезавантаження.
	 */
	if ( brix_pickup_chosen() ) {
		foreach ( array( 'billing_city', 'billing_address_1', 'billing_postcode' ) as $key ) {
			if ( isset( $fields['billing'][ $key ] ) ) {
				$fields['billing'][ $key ]['required'] = false;
			}
		}
	}

	if ( isset( $fields['billing']['billing_email'] ) ) {
		$fields['billing']['billing_email']['placeholder'] = 'ваш@email.com';
		$fields['billing']['billing_email']['description'] = __( 'Туди надішлемо підтвердження й статус замовлення.', 'brix' );
	}

	if ( isset( $fields['order']['order_comments'] ) ) {
		$fields['order']['order_comments']['label']       = __( 'Коментар до замовлення', 'brix' );
		$fields['order']['order_comments']['placeholder'] = __( 'Помел під іншу техніку, побажання до пакування, дата доставки', 'brix' );
	}

	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'brix_checkout_fields' );

/**
 * Підписи адресних полів під доставку Новою Поштою.
 *
 * Правити їх через `woocommerce_checkout_fields` марно: локаль країни
 * застосовується пізніше й повертає «Назва вулиці» назад. Локаль
 * будується саме з цього фільтра.
 *
 * @param array<string, array<string, mixed>> $fields Поля адреси.
 * @return array<string, array<string, mixed>>
 */
function brix_address_fields( array $fields ): array {
	/*
	 * Порядок полів повторює порядок дій: спершу хто отримує й на
	 * який телефон дзвонити, потім куди везти. Усередині адреси місто
	 * йде перед відділенням, бо відділення шукається саме в місті —
	 * зворотний порядок змушував би повертатись назад.
	 *
	 * Черга задається саме тут, а не у woocommerce_checkout_fields.
	 * Скрипт wc-address-i18n.js перебудовує форму вже в браузері й
	 * бере пріоритети звідси: те, що прописане поруч із полями
	 * checkout, він мовчки перезаписує. Саме тому телефон опинявся
	 * під індексом, хоч у розмітці стояв третім.
	 */
	$priority = array(
		'phone'     => 30,
		'country'   => 50,
		'city'      => 60,
		'address_1' => 70,
		'state'     => 75,
		'postcode'  => 80,
	);

	foreach ( $priority as $brix_key => $brix_value ) {
		if ( isset( $fields[ $brix_key ] ) ) {
			$fields[ $brix_key ]['priority'] = $brix_value;
		}
	}

	if ( isset( $fields['phone'] ) ) {
		$fields['phone']['placeholder'] = '+38 0__ ___ __ __';
		$fields['phone']['required']    = true;
	}

	/*
	 * Підписи тут нейтральні — звичайна поштова адреса. Усе, що
	 * стосується Нової Пошти, переїхало в локаль України нижче:
	 * інакше покупець із Варшави бачив би поле «Адреса або
	 * відділення» й шукав би польське місто в українському довіднику.
	 */
	if ( isset( $fields['address_1'] ) ) {
		$fields['address_1']['label']       = __( 'Адреса', 'brix' );
		$fields['address_1']['placeholder'] = __( 'Вулиця, будинок, квартира', 'brix' );
	}

	if ( isset( $fields['city'] ) ) {
		$fields['city']['label'] = __( 'Місто', 'brix' );
	}

	if ( isset( $fields['postcode'] ) ) {
		$fields['postcode']['label'] = __( 'Поштовий індекс', 'brix' );
	}

	return $fields;
}
add_filter( 'woocommerce_default_address_fields', 'brix_address_fields' );

/**
 * Особливості української адреси.
 *
 * Локаль країни — єдине місце, де WooCommerce дозволяє різні поля для
 * різних адрес. І PHP, і скрипт `wc-address-i18n.js` беруть підписи
 * звідси, тож при зміні країни форма перебудовується прямо на
 * сторінці, без перезавантаження.
 *
 * @param array<string, array<string, mixed>> $locale Локалі країн.
 * @return array<string, array<string, mixed>>
 */
function brix_country_locale( array $locale ): array {
	$locale['UA'] = array_merge(
		$locale['UA'] ?? array(),
		array(
			'address_1' => array(
				'label'       => __( 'Адреса або відділення', 'brix' ),
				'placeholder' => __( 'Відділення №12, вул. Кирилівська, 41', 'brix' ),
				'required'    => true,
			),
			'city'      => array(
				'label'       => __( 'Місто', 'brix' ),
				'placeholder' => __( 'Київ', 'brix' ),
				'required'    => true,
			),
			// Індекс Новій Пошті не потрібен: відділення однозначне
			// саме собою. За кордоном без індексу посилку не вручать.
			'postcode'  => array(
				'label'    => __( 'Поштовий індекс', 'brix' ),
				'required' => false,
			),
			// Область теж зайва: відділення визначає і місто, і область.
			'state'     => array(
				'required' => false,
				'hidden'   => true,
			),
		)
	);

	return $locale;
}
add_filter( 'woocommerce_get_country_locale', 'brix_country_locale' );

/**
 * Чи їде замовлення по Україні.
 *
 * @return bool
 */
function brix_is_home_delivery(): bool {
	return ! class_exists( '\Brix\Core\Shipping\Destination' )
		|| \Brix\Core\Shipping\Destination::is_home();
}

/**
 * Класи теми на полях Woo, щоб не переписувати їх селекторами.
 *
 * @param array<string, mixed> $args Аргументи поля.
 * @return array<string, mixed>
 */
function brix_form_field_args( array $args ): array {
	$args['input_class'][] = 'brix-input';

	if ( 'select' === ( $args['type'] ?? '' ) || 'country' === ( $args['type'] ?? '' ) || 'state' === ( $args['type'] ?? '' ) ) {
		$args['input_class'][] = 'brix-select';
	}

	$args['label_class'][] = 'brix-field__label';

	return $args;
}
add_filter( 'woocommerce_form_field_args', 'brix_form_field_args' );

/**
 * Пачка замість фото в підсумку замовлення.
 *
 * @return void
 */
function brix_checkout_thumbnails(): void {
	add_filter( 'woocommerce_cart_item_name', 'brix_checkout_item_name', 10, 2 );
}
add_action( 'woocommerce_checkout_before_order_review', 'brix_checkout_thumbnails' );

/**
 * Назва позиції в підсумку: лот, під ним вага й помел.
 *
 * @param string               $name      Назва.
 * @param array<string, mixed> $cart_item Позиція.
 * @return string
 */
function brix_checkout_item_name( string $name, array $cart_item ): string {
	if ( ! is_checkout() ) {
		return $name;
	}

	$product = wc_get_product( $cart_item['product_id'] );

	if ( ! $product instanceof WC_Product ) {
		return $name;
	}

	$options = brix_cart_item_options( $cart_item );

	$out = '<span class="brix-order__name">' . esc_html( $product->get_name() ) . '</span>';

	if ( $options ) {
		$out .= '<span class="brix-order__options">' . esc_html( implode( ' · ', $options ) ) . '</span>';
	}

	return $out;
}

/**
 * Прибирає стандартні таблиці із сторінки подяки.
 *
 * WooCommerce друкує там «Подробиці замовлення» й «Платіжна адреса»
 * власною розміткою. Вона дублює те, що сторінка вже показала зверху,
 * і приходить без наших стилів — заголовки таблиці виходять кеглем
 * заголовка сторінки. Замість неї шаблон малює свій компактний список.
 *
 * @return void
 */
function brix_strip_default_thankyou(): void {
	remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );
}
add_action( 'wp', 'brix_strip_default_thankyou' );

/**
 * Чи обрав покупець самовивіз.
 *
 * Обгортка над модулем плагіна: тема має працювати й без brix-core,
 * просто без самовивозу як окремого стану.
 *
 * @return bool
 */
function brix_pickup_chosen(): bool {
	return class_exists( '\Brix\Core\Shipping\Pickup' ) && \Brix\Core\Shipping\Pickup::chosen();
}

/**
 * Чи є ставка самовивозом.
 *
 * @param string $rate_id Ідентифікатор ставки.
 * @return bool
 */
function brix_is_pickup_rate( string $rate_id ): bool {
	return class_exists( '\Brix\Core\Shipping\Pickup' ) && \Brix\Core\Shipping\Pickup::is_pickup( $rate_id );
}

/**
 * Дані точки самовивозу.
 *
 * @return array{address: string, hours: string, ready: string}
 */
function brix_pickup_info(): array {
	if ( ! class_exists( '\Brix\Core\Shipping\Pickup' ) ) {
		return array(
			'address' => '',
			'hours'   => '',
			'ready'   => '',
		);
	}

	return array(
		'address' => \Brix\Core\Shipping\Pickup::address(),
		'hours'   => \Brix\Core\Shipping\Pickup::hours(),
		'ready'   => \Brix\Core\Shipping\Pickup::ready(),
	);
}

/**
 * Ставки доставки для першого пакунка й обрана з них.
 *
 * @return array{rates: array<string, WC_Shipping_Rate>, chosen: string}
 */
function brix_shipping_choices(): array {
	$packages = WC()->shipping() ? WC()->shipping()->get_packages() : array();
	$package  = $packages[0] ?? array();
	$rates    = isset( $package['rates'] ) && is_array( $package['rates'] ) ? $package['rates'] : array();
	$chosen   = WC()->session ? WC()->session->get( 'chosen_shipping_methods' ) : array();

	return array(
		'rates'  => $rates,
		'chosen' => is_array( $chosen ) && isset( $chosen[0] ) ? (string) $chosen[0] : '',
	);
}

/**
 * Блок «Спосіб отримання» на початку checkout.
 *
 * Вибір стоїть перед полями, а не збоку в підсумку, бо саме він
 * вирішує, які поля взагалі потрібні: доставка питає місто й
 * відділення, самовивіз не питає нічого.
 *
 * @return void
 */
function brix_delivery_section(): void {
	$choices = brix_shipping_choices();
	$pickup  = brix_pickup_info();
	$single  = 1 === count( $choices['rates'] );
	?>
	<section class="brix-checkout__block brix-ship" id="brix-delivery">
		<h3 class="brix-ship__title"><?php esc_html_e( 'Спосіб отримання', 'brix' ); ?></h3>

		<?php if ( ! $choices['rates'] ) : ?>
			<p class="brix-small brix-muted">
				<?php esc_html_e( 'Для цієї країни доставки поки немає. Оберіть іншу країну або напишіть нам — порахуємо окремо.', 'brix' ); ?>
			</p>
		<?php else : ?>

		<ul class="brix-ship__options" id="shipping_method">
			<?php foreach ( $choices['rates'] as $brix_rate ) : ?>
				<?php
				$id      = $brix_rate->get_id();
				$cost    = (float) $brix_rate->get_cost();
				$is_self = brix_is_pickup_rate( $id );
				$input   = 'shipping_method_0_' . sanitize_title( $id );
				?>
				<li class="brix-ship__item">
					<label class="brix-ship__option" for="<?php echo esc_attr( $input ); ?>">
						<input
							type="<?php echo $single ? 'hidden' : 'radio'; ?>"
							class="shipping_method"
							name="shipping_method[0]"
							data-index="0"
							data-pickup="<?php echo $is_self ? '1' : '0'; ?>"
							id="<?php echo esc_attr( $input ); ?>"
							value="<?php echo esc_attr( $id ); ?>"
							<?php checked( $id, $choices['chosen'] ); ?>>

						<span class="brix-ship__body">
							<span class="brix-ship__name"><?php echo esc_html( $brix_rate->get_label() ); ?></span>
							<span class="brix-ship__note"><?php echo esc_html( brix_rate_note( $brix_rate, $is_self ) ); ?></span>
						</span>

						<span class="brix-ship__cost brix-num">
							<?php
							echo $cost > 0
								? wp_kses_post( wc_price( $cost ) )
								: esc_html__( 'Безкоштовно', 'brix' );
							?>
						</span>
					</label>
				</li>
			<?php endforeach; ?>
		</ul>

		<div class="brix-pickup" id="brix-pickup" <?php echo brix_pickup_chosen() ? '' : 'hidden'; ?>>
			<p class="brix-pickup__row">
				<?php echo brix_icon( 'bag' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span><?php echo esc_html( $pickup['address'] ); ?></span>
			</p>
			<p class="brix-pickup__row">
				<?php echo brix_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span><?php echo esc_html( $pickup['hours'] ); ?></span>
			</p>
			<p class="brix-pickup__note"><?php echo esc_html( $pickup['ready'] ); ?></p>
		</div>

		<?php endif; ?>

		<?php if ( $choices['rates'] && ! $single ) : ?>
			<noscript>
				<p class="brix-small brix-muted"><?php esc_html_e( 'Змінили спосіб отримання — натисніть «Оновити», щоб форма перебудувалась.', 'brix' ); ?></p>
				<button class="brix-btn brix-btn--outline" type="submit" name="woocommerce_checkout_update_totals" value="<?php esc_attr_e( 'Оновити', 'brix' ); ?>">
					<?php esc_html_e( 'Оновити', 'brix' ); ?>
				</button>
			</noscript>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * Блок способу отримання в відповіді на зміну адреси.
 *
 * Країна вирішує, які способи доставки взагалі існують: Нова Пошта
 * возить Україною, у ЄС їде звичайна посилка. Блок стоїть поза
 * підсумком замовлення, який WooCommerce оновлює сам, тож віддаємо
 * його окремим фрагментом — з тієї самої функції, що малює сторінку.
 * Розмітка лишається в одному місці, а вибір способу не встигає
 * розійтися з адресою.
 *
 * @param array<string, string> $fragments Фрагменти відповіді.
 * @return array<string, string>
 */
function brix_delivery_fragment( array $fragments ): array {
	ob_start();
	brix_delivery_section();
	$html = trim( (string) ob_get_clean() );

	if ( '' !== $html ) {
		$fragments['#brix-delivery'] = $html;
	}

	return $fragments;
}
add_filter( 'woocommerce_update_order_review_fragments', 'brix_delivery_fragment' );

/**
 * Підпис під назвою способу отримання.
 *
 * @param WC_Shipping_Rate $rate   Ставка.
 * @param bool             $pickup Чи це самовивіз.
 * @return string
 */
function brix_rate_note( WC_Shipping_Rate $rate, bool $pickup ): string {
	if ( $pickup ) {
		return brix_pickup_info()['address'];
	}

	if ( ! brix_is_home_delivery() ) {
		return __( 'Нова Пошта Global, 5–9 днів. Трек-номер надішлемо на пошту.', 'brix' );
	}

	$note      = __( 'Відділення або поштомат, 1–3 дні', 'brix' );
	$threshold = function_exists( 'brix_free_shipping_threshold' ) ? brix_free_shipping_threshold() : 0.0;

	if ( (float) $rate->get_cost() > 0 && $threshold > 0 ) {
		$note .= ' · ' . sprintf(
			/* translators: %s — сума порога безкоштовної доставки. */
			__( 'безкоштовно від %s', 'brix' ),
			wp_strip_all_tags( wc_price( $threshold ) )
		);
	}

	return $note;
}

/**
 * Прибирає адресу з даних замовлення, коли обрано самовивіз.
 *
 * Поля лишаються в розмітці — прихованими, але живими, — тож у них
 * може бути текст із попереднього вибору. Записати його в замовлення
 * означало б надіслати посилку туди, куди покупець її не просив.
 *
 * @param array<string, mixed> $data Дані checkout.
 * @return array<string, mixed>
 */
function brix_pickup_posted_data( array $data ): array {
	if ( ! brix_pickup_chosen() ) {
		return $data;
	}

	$keys = array(
		'billing_address_1',
		'billing_city',
		'billing_postcode',
		'shipping_address_1',
		'shipping_city',
		'shipping_postcode',
	);

	foreach ( $keys as $key ) {
		if ( array_key_exists( $key, $data ) ) {
			$data[ $key ] = '';
		}
	}

	return $data;
}
add_filter( 'woocommerce_checkout_posted_data', 'brix_pickup_posted_data' );

/**
 * Мовчить, коли покупець просто оновлює підсумок.
 *
 * Кнопка «Оновити» проходить тим самим шляхом, що й «Підтвердити
 * замовлення»: WooCommerce перевіряє всю форму й тільки потім бачить,
 * що замовлення створювати не треба. Без цього натискання на «Оновити»
 * з половиною заповнених полів відповідало б списком червоних
 * зауважень за ще не зроблене.
 *
 * @param array<string, mixed> $data   Дані checkout.
 * @param WP_Error             $errors Помилки.
 * @return void
 */
function brix_quiet_totals_update( array $data, WP_Error $errors ): void {
	unset( $data );

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce перевірив WooCommerce перед викликом.
	if ( ! isset( $_POST['woocommerce_checkout_update_totals'] ) ) {
		return;
	}

	foreach ( $errors->get_error_codes() as $code ) {
		$errors->remove( $code );
	}

	wc_clear_notices();
}
add_action( 'woocommerce_after_checkout_validation', 'brix_quiet_totals_update', 10, 2 );

/**
 * Точка самовивозу, записана в замовленні.
 *
 * @param WC_Order $order Замовлення.
 * @return string
 */
function brix_order_pickup_point( WC_Order $order ): string {
	return class_exists( '\Brix\Core\Shipping\Pickup' )
		? \Brix\Core\Shipping\Pickup::point( $order )
		: '';
}

/**
 * Прибирає префікс фіксету з зауважень checkout.
 *
 * WooCommerce складає «Оплата Місто — обов'язкове поле», бо розрізняє
 * платіжну й доставкову адресу. У цьому магазині адреса одна, і блок
 * над полем підписаний «Куди доставити» — слово «Оплата» в зауваженні
 * відсилає до блоку, якого на сторінці немає.
 *
 * @param string $translation Переклад.
 * @param string $text        Оригінал.
 * @param string $context     Контекст.
 * @param string $domain      Домен.
 * @return string
 */
function brix_plain_validation_label( string $translation, string $text, string $context, string $domain ): string {
	if ( 'woocommerce' !== $domain || 'checkout-validation' !== $context ) {
		return $translation;
	}

	return in_array( $text, array( 'Billing %s', 'Shipping %s' ), true ) ? '%s' : $translation;
}
add_filter( 'gettext_with_context', 'brix_plain_validation_label', 10, 4 );
