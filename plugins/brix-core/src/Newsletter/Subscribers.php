<?php
/**
 * Підписка на «Лист у понеділок».
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Newsletter;

use Brix\Core\Contracts\Module;
use Brix\Core\I18n\Language;

defined( 'ABSPATH' ) || exit;

/**
 * Список підписників із подвійним підтвердженням.
 *
 * Довго форма в підвалі відправлялась на «#»: адреса зникала, сторінка
 * перезавантажувалась, і людина не бачила жодного відгуку. Тепер шлях
 * такий: адреса → лист «Підтвердіть підписку» → клік у листі → підписку
 * підтверджено. Кожен крок закінчується повідомленням на місці форми.
 *
 * Подвійне підтвердження — не формальність. Без нього будь-хто міг би
 * підписати на лист чужу адресу, а політика конфіденційності обіцяє,
 * що лист приходить тільки тим, хто на нього підписався.
 *
 * Nonce у формі немає навмисно: підвал стоїть на кожній сторінці, а
 * сторінки кешуються, і nonce у кеші протухав би за добу. Від ботів
 * захищають приманка й ліміт спроб, від підписання чужих адрес —
 * лист-підтвердження.
 *
 * Самі понеділкові листи сайт не розсилає: для цього є сервіси
 * розсилок. Власник вивантажує підтверджених підписників у CSV.
 */
final class Subscribers implements Module {

	public const POST_TYPE = 'brix_subscriber';

	/**
	 * Поле з адресою у формі.
	 */
	public const FIELD = 'brix_newsletter_email';

	/**
	 * Приманка для ботів.
	 */
	public const TRAP = 'brix_newsletter_site';

	/**
	 * Параметр адреси з результатом.
	 */
	public const RESULT = 'newsletter';

	/**
	 * Параметри посилань із листа.
	 */
	private const CONFIRM     = 'newsletter_confirm';
	private const UNSUBSCRIBE = 'newsletter_unsubscribe';

	/**
	 * Дія вивантаження в адмінці.
	 */
	private const EXPORT = 'brix_newsletter_export';

	/**
	 * Скільки спроб з однієї адреси IP за годину.
	 */
	private const LIMIT = 5;

	/**
	 * Підписує модуль на хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ), 5 );
		add_action( 'template_redirect', array( $this, 'handle' ), 5 );
		add_action( 'admin_post_' . self::EXPORT, array( $this, 'export' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'views_edit-' . self::POST_TYPE, array( $this, 'export_link' ) );
	}

	/**
	 * Тип запису для підписників. Бачить лише адміністратор.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Підписники', 'brix-core' ),
					'singular_name' => __( 'Підписник', 'brix-core' ),
					'menu_name'     => __( 'Розсилка', 'brix-core' ),
					'all_items'     => __( 'Підписники', 'brix-core' ),
					'not_found'     => __( 'Підписників поки немає', 'brix-core' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-email-alt',
				'menu_position'   => 29,
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
				'supports'        => array( 'title' ),
			)
		);
	}

	/**
	 * Приймає форму й посилання з листа.
	 *
	 * Форма відправляється на ту саму сторінку, де стоїть, — тож мова
	 * запиту та сама, що й у покупця, і повертаємо його туди ж.
	 *
	 * @return void
	 */
	public function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification -- Див. опис класу: захист — приманка, ліміт і лист-підтвердження.
		if ( isset( $_GET[ self::CONFIRM ] ) ) {
			$this->confirm( self::token( sanitize_text_field( wp_unslash( $_GET[ self::CONFIRM ] ) ) ) );
		}

		if ( isset( $_GET[ self::UNSUBSCRIBE ] ) ) {
			$this->unsubscribe( self::token( sanitize_text_field( wp_unslash( $_GET[ self::UNSUBSCRIBE ] ) ) ) );
		}

		if ( ! isset( $_POST[ self::FIELD ] ) || 'POST' !== strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
			return;
		}

		$email = strtolower( sanitize_email( wp_unslash( $_POST[ self::FIELD ] ) ) );
		$trap  = ! empty( $_POST[ self::TRAP ] );
		// phpcs:enable WordPress.Security.NonceVerification

		// Бот отримує той самий відгук, що й людина, — щоб не вчився.
		if ( $trap ) {
			$this->back( 'sent' );
		}

		if ( ! is_email( $email ) ) {
			$this->back( 'invalid' );
		}

		if ( $this->limited() ) {
			$this->back( 'limit' );
		}

		$this->back( $this->subscribe( $email ) );
	}

	/**
	 * Записує адресу й надсилає лист-підтвердження.
	 *
	 * @param string $email Адреса.
	 * @return string Результат для повідомлення.
	 */
	private function subscribe( string $email ): string {
		$existing = $this->find( '_brix_email', $email );

		if ( $existing && 'publish' === $existing->post_status ) {
			return 'already';
		}

		if ( $existing ) {
			// Лист уже йшов нещодавно — вдруге не шлемо, але людині
			// кажемо те саме: перевірити пошту.
			$sent = (int) get_post_meta( $existing->ID, '_brix_sent_at', true );

			if ( time() - $sent < 10 * MINUTE_IN_SECONDS ) {
				return 'sent';
			}

			$id = $existing->ID;
		} else {
			$id = wp_insert_post(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'pending',
					'post_title'  => $email,
				),
				true
			);

			if ( is_wp_error( $id ) ) {
				return 'error';
			}

			update_post_meta( $id, '_brix_email', $email );
			// Шістнадцятковий, у нижньому регістрі: переживе будь-яку
			// поштову програму й не залежить від порівняння рядків у базі.
			update_post_meta( $id, '_brix_token', bin2hex( random_bytes( 16 ) ) );
		}

		update_post_meta( $id, '_brix_lang', Language::current() );
		update_post_meta( $id, '_brix_sent_at', time() );

		return $this->send_confirmation( (int) $id, $email ) ? 'sent' : 'error';
	}

	/**
	 * Лист «Підтвердіть підписку».
	 *
	 * @param int    $id    Підписник.
	 * @param string $email Адреса.
	 * @return bool
	 */
	private function send_confirmation( int $id, string $email ): bool {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}

		$token  = (string) get_post_meta( $id, '_brix_token', true );
		$mailer = WC()->mailer();
		$title  = __( 'Підтвердіть підписку', 'brix-core' );

		$body = sprintf(
			'<p>%1$s</p><p><a href="%2$s" style="display:inline-block;padding:12px 24px;border-radius:999px;background:#B61F3A;color:#ffffff;text-decoration:none;font-weight:600">%3$s</a></p><p>%4$s</p><p style="font-size:13px;color:#6C6763">%5$s <a href="%6$s">%7$s</a></p>',
			esc_html__( 'Щопонеділка — що обсмажили цього тижня, які лоти закінчуються і один рецепт. Щоб лист почав приходити, підтвердіть адресу:', 'brix-core' ),
			esc_url( add_query_arg( self::CONFIRM, $token, home_url( '/' ) ) ),
			esc_html__( 'Так, надсилайте', 'brix-core' ),
			esc_html__( 'Якщо ви не підписувались — просто проігноруйте цей лист, більше ми не напишемо.', 'brix-core' ),
			esc_html__( 'Передумали?', 'brix-core' ),
			esc_url( add_query_arg( self::UNSUBSCRIBE, $token, home_url( '/' ) ) ),
			esc_html__( 'Відписатися', 'brix-core' )
		);

		return (bool) $mailer->send(
			$email,
			$title,
			$mailer->wrap_message( $title, $body ),
			"Content-Type: text/html\r\n"
		);
	}

	/**
	 * Підтверджує адресу за посиланням із листа.
	 *
	 * @param string $token Токен.
	 * @return void
	 */
	private function confirm( string $token ): void {
		$subscriber = '' !== $token ? $this->find( '_brix_token', $token ) : null;

		if ( ! $subscriber ) {
			$this->back( 'expired', home_url( '/' ) );
		}

		if ( 'publish' !== $subscriber->post_status ) {
			wp_update_post(
				array(
					'ID'          => $subscriber->ID,
					'post_status' => 'publish',
				)
			);
			update_post_meta( $subscriber->ID, '_brix_confirmed_at', time() );
		}

		$this->back( 'confirmed', home_url( '/' ) );
	}

	/**
	 * Відписка за посиланням із листа.
	 *
	 * Запис видаляється зовсім, а не позначається: відписаний не має
	 * лишатись у нашій базі.
	 *
	 * @param string $token Токен.
	 * @return void
	 */
	private function unsubscribe( string $token ): void {
		$subscriber = '' !== $token ? $this->find( '_brix_token', $token ) : null;

		if ( $subscriber ) {
			wp_delete_post( $subscriber->ID, true );
		}

		// Повторний клік по тому ж посиланню — теж «ви відписались»:
		// для людини результат той самий.
		$this->back( 'unsubscribed', home_url( '/' ) );
	}

	/**
	 * Токен з адреси: лише 32 шістнадцяткові символи, інакше порожньо.
	 *
	 * @param mixed $raw Значення параметра.
	 * @return string
	 */
	private static function token( $raw ): string {
		$token = is_string( $raw ) ? strtolower( $raw ) : '';

		return 1 === preg_match( '/^[a-f0-9]{32}$/', $token ) ? $token : '';
	}

	/**
	 * Чи вичерпано ліміт спроб з цієї адреси IP.
	 *
	 * @return bool
	 */
	private function limited(): bool {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'brix_nl_' . md5( $ip );
		$hit = (int) get_transient( $key );

		if ( $hit >= self::LIMIT ) {
			return true;
		}

		set_transient( $key, $hit + 1, HOUR_IN_SECONDS );

		return false;
	}

	/**
	 * Підписник за мета-полем.
	 *
	 * @param string $key   Мета-ключ.
	 * @param string $value Значення.
	 * @return \WP_Post|null
	 */
	private function find( string $key, string $value ): ?\WP_Post {
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => array( 'pending', 'publish' ),
				'posts_per_page'   => 1,
				'meta_key'         => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Кілька сотень записів, запит рідкісний.
				'meta_value'       => $value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Див. вище.
				'suppress_filters' => true,
			)
		);

		return $posts ? $posts[0] : null;
	}

	/**
	 * Повертає на сторінку з результатом біля форми.
	 *
	 * @param string      $result Результат.
	 * @param string|null $url    Куди, якщо не на поточну сторінку.
	 * @return never
	 */
	private function back( string $result, ?string $url = null ): void {
		$url = $url ?? remove_query_arg( array( self::RESULT, self::CONFIRM, self::UNSUBSCRIBE ) );

		// 303: після POST браузер іде GET-ом, і оновлення сторінки не
		// надсилає форму вдруге.
		wp_safe_redirect( add_query_arg( self::RESULT, $result, $url ) . '#brix-newsletter', 303 );
		exit;
	}

	/**
	 * Повідомлення для поточної сторінки, якщо вона — результат підписки.
	 *
	 * @return array{text: string, ok: bool}|null
	 */
	public static function notice(): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Лише вибір тексту повідомлення.
		$result = isset( $_GET[ self::RESULT ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT ] ) ) : '';

		$messages = array(
			'sent'         => array( __( 'Майже готово. Ми надіслали вам лист — натисніть у ньому «Так, надсилайте», і підписку буде підтверджено.', 'brix-core' ), true ),
			'already'      => array( __( 'Ця адреса вже підписана. Наступний лист прийде в понеділок.', 'brix-core' ), true ),
			'confirmed'    => array( __( 'Підписку підтверджено. Перший лист — у найближчий понеділок.', 'brix-core' ), true ),
			'unsubscribed' => array( __( 'Ви відписались. Більше листів не буде.', 'brix-core' ), true ),
			'invalid'      => array( __( 'Схоже, в адресі помилка. Перевірте й спробуйте ще раз.', 'brix-core' ), false ),
			'limit'        => array( __( 'Забагато спроб. Спробуйте за годину.', 'brix-core' ), false ),
			'expired'      => array( __( 'Посилання застаріло. Підпишіться ще раз — надішлемо нове.', 'brix-core' ), false ),
			'error'        => array( __( 'Не вдалося надіслати лист. Спробуйте трохи пізніше.', 'brix-core' ), false ),
		);

		if ( ! isset( $messages[ $result ] ) ) {
			return null;
		}

		return array(
			'text' => $messages[ $result ][0],
			'ok'   => $messages[ $result ][1],
		);
	}

	/**
	 * Колонки списку в адмінці.
	 *
	 * @param array<string, string> $columns Колонки.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		return array(
			'cb'          => $columns['cb'] ?? '',
			'title'       => __( 'Пошта', 'brix-core' ),
			'brix_status' => __( 'Стан', 'brix-core' ),
			'brix_lang'   => __( 'Мова', 'brix-core' ),
			'date'        => $columns['date'] ?? __( 'Дата', 'brix-core' ),
		);
	}

	/**
	 * Значення колонки.
	 *
	 * @param string $column  Колонка.
	 * @param int    $post_id Підписник.
	 * @return void
	 */
	public function column( string $column, int $post_id ): void {
		if ( 'brix_status' === $column ) {
			echo 'publish' === get_post_status( $post_id )
				? esc_html__( 'Підтверджено', 'brix-core' )
				: esc_html__( 'Чекає підтвердження', 'brix-core' );
		}

		if ( 'brix_lang' === $column ) {
			echo esc_html( strtoupper( (string) get_post_meta( $post_id, '_brix_lang', true ) ) );
		}
	}

	/**
	 * Посилання «Вивантажити CSV» над списком.
	 *
	 * @param array<string, string> $views Перемикачі списку.
	 * @return array<string, string>
	 */
	public function export_link( array $views ): array {
		$views['brix_export'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::EXPORT ), self::EXPORT ) ),
			esc_html__( 'Вивантажити підтверджених у CSV', 'brix-core' )
		);

		return $views;
	}

	/**
	 * Віддає CSV з підтвердженими підписниками.
	 *
	 * @return void
	 */
	public function export(): void {
		if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( self::EXPORT ) ) {
			wp_die( esc_html__( 'Немає доступу.', 'brix-core' ), 403 );
		}

		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=brix-subscribers-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Потік відповіді, не файл.

		fputcsv( $out, array( 'email', 'language', 'confirmed_at' ) );

		foreach ( $ids as $id ) {
			$confirmed = (int) get_post_meta( $id, '_brix_confirmed_at', true );

			fputcsv(
				$out,
				array(
					(string) get_post_meta( $id, '_brix_email', true ),
					(string) get_post_meta( $id, '_brix_lang', true ),
					$confirmed ? gmdate( 'c', $confirmed ) : '',
				)
			);
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Див. вище.
		exit;
	}
}
