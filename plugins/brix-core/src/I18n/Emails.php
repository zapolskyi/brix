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
		$lang     = ( $customer && Language::SECOND === $this->sending ) ? Language::SECOND : Language::MAIN;

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
