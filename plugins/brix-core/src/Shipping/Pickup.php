<?php
/**
 * Самовивіз: звідки забирати й коли буде готове.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Shipping;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Дані точки самовивозу.
 *
 * Адресу модуль не дублює: вона вже є в налаштуваннях магазину, і два
 * місця для однієї адреси рано чи пізно розійдуться. Свої тут лише ті
 * дві речі, яких у WooCommerce немає, — години й строк готовності.
 */
final class Pickup implements Module {

	/**
	 * Мета-поле з точкою самовивозу в замовленні.
	 *
	 * @var string
	 */
	public const META = '_brix_pickup_point';

	/**
	 * Опція з годинами роботи.
	 *
	 * @var string
	 */
	public const HOURS = 'brix_pickup_hours';

	/**
	 * Опція з обіцянкою готовності.
	 *
	 * @var string
	 */
	public const READY = 'brix_pickup_ready';

	/**
	 * Вішає хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_shipping_settings', array( $this, 'settings' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'admin_note' ) );
		add_action( 'woocommerce_email_order_meta', array( $this, 'email_note' ) );
	}

	/**
	 * Запам'ятовує точку в замовленні.
	 *
	 * Адреса магазину колись зміниться, а замовлення має лишитись
	 * правдивим: покупця чекали саме там, де було написано в момент
	 * оформлення.
	 *
	 * @param \WC_Order            $order Замовлення.
	 * @param array<string, mixed> $data  Дані checkout.
	 * @return void
	 */
	public function save( \WC_Order $order, array $data ): void {
		unset( $data );

		if ( ! self::chosen() ) {
			return;
		}

		$order->update_meta_data( self::META, self::address() );
	}

	/**
	 * Точка самовивозу в замовленні, якщо вона там є.
	 *
	 * @param \WC_Order $order Замовлення.
	 * @return string
	 */
	public static function point( \WC_Order $order ): string {
		return (string) $order->get_meta( self::META );
	}

	/**
	 * Рядок про самовивіз в адмінці замовлення.
	 *
	 * @param \WC_Order $order Замовлення.
	 * @return void
	 */
	public function admin_note( \WC_Order $order ): void {
		$point = self::point( $order );

		if ( '' === $point ) {
			return;
		}

		printf(
			'<p><strong>%s</strong><br>%s</p>',
			esc_html__( 'Самовивіз', 'brix-core' ),
			esc_html( $point )
		);
	}

	/**
	 * Те саме в листі покупцю.
	 *
	 * @param \WC_Order $order Замовлення.
	 * @return void
	 */
	public function email_note( \WC_Order $order ): void {
		$point = self::point( $order );

		if ( '' === $point ) {
			return;
		}

		printf(
			'<h2>%s</h2><p>%s<br>%s<br>%s</p>',
			esc_html__( 'Самовивіз', 'brix-core' ),
			esc_html( $point ),
			esc_html( self::hours() ),
			esc_html( self::ready() )
		);
	}

	/**
	 * Чи обрав покупець самовивіз.
	 *
	 * Спершу POST, потім сесія. Порядок саме такий, бо під час
	 * оформлення WooCommerce читає поля форми раніше, ніж записує
	 * вибір у сесію: якби ми дивились у сесію, перевірка адреси
	 * стосувалася б попереднього вибору, а не поточного.
	 *
	 * @return bool
	 */
	public static function chosen(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce перевіряє WooCommerce; тут лише читання вибору.
		$posted = isset( $_POST['shipping_method'] ) ? wp_unslash( $_POST['shipping_method'] ) : null;

		if ( is_array( $posted ) && isset( $posted[0] ) && is_string( $posted[0] ) ) {
			return self::is_pickup( sanitize_text_field( $posted[0] ) );
		}

		$chosen = WC()->session ? WC()->session->get( 'chosen_shipping_methods' ) : null;

		if ( is_array( $chosen ) && isset( $chosen[0] ) && is_string( $chosen[0] ) ) {
			return self::is_pickup( $chosen[0] );
		}

		return false;
	}

	/**
	 * Чи належить ідентифікатор ставки самовивозу.
	 *
	 * @param string $rate_id Ідентифікатор ставки, як-от local_pickup:2.
	 * @return bool
	 */
	public static function is_pickup( string $rate_id ): bool {
		return 0 === strpos( $rate_id, 'local_pickup' );
	}

	/**
	 * Адреса точки одним рядком.
	 *
	 * @return string
	 */
	public static function address(): string {
		$parts = array(
			(string) get_option( 'woocommerce_store_address', '' ),
			(string) get_option( 'woocommerce_store_city', '' ),
		);

		/**
		 * Адреса точки самовивозу.
		 *
		 * Проходить через brix_translate, бо лежить у налаштуваннях
		 * магазину, а не в рядках коду: .po її не бачить.
		 *
		 * @param string $address Адреса одним рядком.
		 */
		return (string) apply_filters( 'brix_translate', implode( ', ', array_filter( array_map( 'trim', $parts ) ) ) );
	}

	/**
	 * Години роботи.
	 *
	 * @return string
	 */
	public static function hours(): string {
		/** This filter is documented in src/Shipping/Pickup.php */
		return (string) apply_filters( 'brix_translate', (string) get_option( self::HOURS, self::default_hours() ) );
	}

	/**
	 * Коли замовлення буде готове.
	 *
	 * @return string
	 */
	public static function ready(): string {
		/** This filter is documented in src/Shipping/Pickup.php */
		return (string) apply_filters( 'brix_translate', (string) get_option( self::READY, self::default_ready() ) );
	}

	/**
	 * Години за замовчуванням.
	 *
	 * @return string
	 */
	private static function default_hours(): string {
		return __( 'Пн–Пт 10:00–19:00, Сб 11:00–17:00', 'brix-core' );
	}

	/**
	 * Обіцянка готовності за замовчуванням.
	 *
	 * @return string
	 */
	private static function default_ready(): string {
		return __( 'Зазвичай замовлення готове за 24 години — напишемо, щойно можна забирати.', 'brix-core' );
	}

	/**
	 * Поля в налаштуваннях доставки WooCommerce.
	 *
	 * @param array<int, array<string, mixed>> $settings Налаштування.
	 * @return array<int, array<string, mixed>>
	 */
	public function settings( array $settings ): array {
		$settings[] = array(
			'title' => __( 'Самовивіз', 'brix-core' ),
			'type'  => 'title',
			'desc'  => sprintf(
				/* translators: %s — адреса магазину з налаштувань WooCommerce. */
				__( 'Адресу точки беремо з налаштувань магазину: %s.', 'brix-core' ),
				self::address()
			),
			'id'    => 'brix_pickup_options',
		);

		$settings[] = array(
			'title'   => __( 'Години роботи', 'brix-core' ),
			'id'      => self::HOURS,
			'type'    => 'text',
			'default' => self::default_hours(),
		);

		$settings[] = array(
			'title'   => __( 'Коли буде готове', 'brix-core' ),
			'desc'    => __( 'Цей рядок покупець бачить у checkout, щойно обере самовивіз.', 'brix-core' ),
			'id'      => self::READY,
			'type'    => 'text',
			'css'     => 'min-width: 400px;',
			'default' => self::default_ready(),
		);

		$settings[] = array(
			'type' => 'sectionend',
			'id'   => 'brix_pickup_options',
		);

		return $settings;
	}
}
