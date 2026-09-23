<?php
/**
 * Мова транзакційних листів.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\I18n;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Лист приходить тією мовою, якою зроблено замовлення.
 *
 * Мову запиту тут не спитаєш: лист про зміну статусу надсилає крон або
 * адміністратор з української адмінки. Тому мова записується в
 * замовлення в момент оформлення й читається з нього ж.
 *
 * WooCommerce перед відправкою сам перемикається на мову сайту —
 * саме для того, щоб лист не пішов мовою адмінки. Нам це заважає,
 * тож для англійських замовлень це перемикання вимикається, а своє
 * стоїть раніше.
 */
final class Emails implements Module {

	/**
	 * Мета-поле з мовою замовлення.
	 *
	 * @var string
	 */
	public const META = '_brix_lang';

	/**
	 * Локаль другої мови.
	 *
	 * @var string
	 */
	private const LOCALE = 'en_US';

	/**
	 * Мова замовлення, листи про яке зараз надсилаються.
	 *
	 * @var string|null
	 */
	private ?string $sending = null;

	/**
	 * Чи нав'язали мову поточному листу.
	 *
	 * @var bool
	 */
	private bool $imposed = false;

	/**
	 * Чи перемкнули локаль WordPress.
	 *
	 * Функція switch_to_locale() повертає false, коли локаль уже та
	 * сама, — тоді повертати назад нема чого, але зняти нав'язану
	 * мову все одно треба. Тому прапорці два.
	 *
	 * @var bool
	 */
	private bool $switched = false;

	/**
	 * Вішає хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'woocommerce_checkout_create_order', array( $this, 'remember' ) );
		add_action( 'woocommerce_created_customer', array( $this, 'remember_user' ), 1 );
		add_filter( 'woocommerce_email_actions', array( $this, 'wrap_actions' ) );
		add_filter( 'woocommerce_allow_switching_email_locale', array( $this, 'setup_locale' ), 10, 2 );
		add_filter( 'woocommerce_allow_restoring_email_locale', array( $this, 'restore_locale' ), 10, 2 );
	}

	/**
	 * Запам'ятовує мову замовлення.
	 *
	 * @param \WC_Order $order Замовлення.
	 * @return void
	 */
	public function remember( \WC_Order $order ): void {
		$order->update_meta_data( self::META, Language::current() );
	}

	/**
	 * Запам'ятовує мову покупця, що створив кабінет.
	 *
	 * Листи про кабінет — «кабінет створено», «скидання пароля» — не
	 * мають замовлення, з якого взяти мову. Тож мову бере сам покупець:
	 * та, якою він реєструвався.
	 *
	 * @param int $user_id Покупець.
	 * @return void
	 */
	public function remember_user( $user_id ): void {
		update_user_meta( (int) $user_id, self::META, Language::current() );
	}

	/**
	 * Мова листа за його об'єктом.
	 *
	 * @param \WC_Email $email Лист.
	 * @return string|null Код мови або null, якщо об'єкт мови не знає.
	 */
	private static function language_of_email( \WC_Email $email ): ?string {
		if ( $email->object instanceof \WC_Order ) {
			return self::language( $email->object );
		}

		if ( $email->object instanceof \WP_User ) {
			$lang = (string) get_user_meta( $email->object->ID, self::META, true );

			return Language::SECOND === $lang ? Language::SECOND : Language::MAIN;
		}

		return null;
	}

	/**
	 * Мова замовлення.
	 *
	 * @param \WC_Order $order Замовлення.
	 * @return string
	 */
	public static function language( \WC_Order $order ): string {
		$lang = (string) $order->get_meta( self::META );

		return Language::SECOND === $lang ? Language::SECOND : Language::MAIN;
	}

	/**
	 * Обгортає кожну подію листа перемиканням локалі.
	 *
	 * WooCommerce вішає на кожну з цих подій свій send_transactional_email
	 * з пріоритетом 10, а вже той усередині смикає листи. Тож
	 * перемикання стоїть на 5, а повернення — на 15: разом вони
	 * охоплюють усю відправку.
	 *
	 * @param array<int, string> $actions Події листів.
	 * @return array<int, string>
	 */
	public function wrap_actions( array $actions ): array {
		foreach ( $actions as $action ) {
			add_action( $action, array( $this, 'before' ), 5 );
			add_action( $action, array( $this, 'after' ), 15 );
		}

		return $actions;
	}

	/**
	 * Запам'ятовує мову замовлення на час відправки.
	 *
	 * @param mixed $order_id Замовлення.
	 * @return void
	 */
	public function before( $order_id ): void {
		$order = wc_get_order( $order_id );

		$this->sending = $order instanceof \WC_Order ? self::language( $order ) : null;
	}

	/**
	 * Забуває мову й повертає локаль, якщо щось лишилось перемкненим.
	 *
	 * @return void
	 */
	public function after(): void {
		$this->sending = null;

		$this->restore_locale();
	}

	/**
	 * Ставить кожному листу свою мову.
	 *
	 * Листів на одну подію буває два — покупцю й власнику магазину, —
	 * і мова в них різна. Покупець читає тією, якою оформив
	 * замовлення; власник завжди українською, бо адмінка українська.
	 *
	 * Без цього обидва листи йшли б мовою запиту: замовлення,
	 * оформлене на /en/, відправляло б власнику англійський лист.
	 *
	 * Повертаємо false, щоб WooCommerce не перемкнувся слідом і не
	 * перебив те, що ми щойно поставили.
	 *
	 * @param bool  $allow Чи перемикати локаль силами WooCommerce.
	 * @param mixed $email Лист.
	 * @return bool
	 */
	public function setup_locale( $allow, $email = null ): bool {
		$customer = $email instanceof \WC_Email && $email->is_customer_email();

		/*
		 * Мову беремо з об'єкта листа: із замовлення або з покупця.
		 * Колись вона бралась лише з аргументу події через
		 * wc_get_order() — а в листах про кабінет перший аргумент —
		 * ID користувача: лист «кабінет створено» йшов українською
		 * навіть з /en/, а збіг ID з номером чужого замовлення дав
		 * би мову того замовлення.
		 */
		$own = $customer ? self::language_of_email( $email ) : null;

		/*
		 * WooCommerce перемикає мову на самому початку trigger(), іноді
		 * ще до того, як лист знає свого адресата. Лист без замовлення —
		 * «кабінет створено», «скидання пароля» — відправляється під
		 * час запиту самого покупця, тож тоді правду каже мова запиту.
		 * В адмінці й кроні вона завжди українська.
		 */
		$want = $own ?? $this->sending ?? ( $customer ? Language::current() : null );
		$lang = $customer && Language::SECOND === $want ? Language::SECOND : Language::MAIN;

		Language::use( $lang );

		$this->imposed  = true;
		$this->switched = switch_to_locale( Language::locale_of( $lang ) );

		return false;
	}

	/**
	 * Повертає локаль після листа.
	 *
	 * @param bool  $allow Чи повертати локаль силами WooCommerce.
	 * @param mixed $email Лист.
	 * @return bool
	 */
	public function restore_locale( $allow = true, $email = null ): bool {
		unset( $email );

		if ( ! $this->imposed ) {
			return (bool) $allow;
		}

		if ( $this->switched ) {
			restore_previous_locale();
		}

		Language::use( null );

		$this->imposed  = false;
		$this->switched = false;

		return false;
	}
}
