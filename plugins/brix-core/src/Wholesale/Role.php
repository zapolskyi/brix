<?php
/**
 * Роль «Оптовий клієнт» і схвалення заявок.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Wholesale;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Перетворює заявку B2B на користувача з оптовими цінами.
 *
 * Схвалення — дія в адмінці, а не автоматика: оптова ціна це подарунок,
 * і роздавати його за фактом заповнення форми не можна. Кнопка стоїть
 * у списку заявок, поруч із самою заявкою.
 */
final class Role implements Module {

	/**
	 * Ідентифікатор ролі.
	 */
	public const ROLE = 'brix_wholesale';

	/**
	 * Дія схвалення.
	 */
	private const ACTION = 'brix_approve_lead';

	/**
	 * Мета-ключ із ідентифікатором створеного користувача.
	 */
	public const USER_META = '_brix_lead_user';

	/**
	 * Підписує модуль на хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'ensure_role' ) );
		add_filter( 'post_row_actions', array( $this, 'row_action' ), 10, 2 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'approve' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Створює роль, якщо її ще немає.
	 *
	 * @return void
	 */
	public function ensure_role(): void {
		if ( get_role( self::ROLE ) ) {
			return;
		}

		$customer = get_role( 'customer' );

		add_role(
			self::ROLE,
			__( 'Оптовий клієнт', 'brix-core' ),
			$customer ? $customer->capabilities : array( 'read' => true )
		);
	}

	/**
	 * Додає дію «Схвалити» в рядок заявки.
	 *
	 * @param array<string, string> $actions Дії.
	 * @param \WP_Post              $post    Запис.
	 * @return array<string, string>
	 */
	public function row_action( array $actions, \WP_Post $post ): array {
		if ( Application::POST_TYPE !== $post->post_type ) {
			return $actions;
		}

		if ( $post->{'_brix_lead_user'} ?? get_post_meta( $post->ID, self::USER_META, true ) ) {
			$actions['brix_done'] = esc_html__( 'Уже схвалено', 'brix-core' );

			return $actions;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ACTION,
					'lead'   => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . $post->ID
		);

		$actions['brix_approve'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Схвалити й дати оптові ціни', 'brix-core' )
		);

		return $actions;
	}

	/**
	 * Створює користувача з оптовою роллю.
	 *
	 * @return void
	 */
	public function approve(): void {
		$lead = isset( $_GET['lead'] ) ? absint( $_GET['lead'] ) : 0;

		if ( ! $lead || ! current_user_can( 'edit_post', $lead ) ) {
			wp_die( esc_html__( 'Недостатньо прав.', 'brix-core' ) );
		}

		check_admin_referer( self::ACTION . '_' . $lead );

		$email = (string) get_post_meta( $lead, '_brix_email', true );

		if ( ! is_email( $email ) ) {
			$this->back( $lead, 'bad-email' );
		}

		$user = get_user_by( 'email', $email );

		if ( $user instanceof \WP_User ) {
			// Клієнт уже є — просто додаємо роль, не чіпаючи решти.
			$user->add_role( self::ROLE );
			$id = $user->ID;
		} else {
			$id = wp_insert_user(
				array(
					'user_login'   => $email,
					'user_email'   => $email,
					'user_pass'    => wp_generate_password( 20 ),
					'display_name' => (string) get_post_meta( $lead, '_brix_name', true ),
					'role'         => self::ROLE,
				)
			);

			if ( is_wp_error( $id ) ) {
				$this->back( $lead, 'failed' );
			}

			/*
			 * Пароль не вигадуємо за клієнта й не шлемо відкритим
			 * текстом: WordPress надішле лист із посиланням на
			 * встановлення власного.
			 */
			wp_new_user_notification( (int) $id, null, 'user' );
		}

		update_post_meta( $lead, self::USER_META, (int) $id );

		$this->back( $lead, 'approved' );
	}

	/**
	 * Повідомлення про результат схвалення.
	 *
	 * @return void
	 */
	public function notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Читання результату редіректу, не дія.
		$status = isset( $_GET['brix_lead'] ) ? sanitize_key( wp_unslash( $_GET['brix_lead'] ) ) : '';

		$messages = array(
			'approved'  => array( 'success', __( 'Заявку схвалено. Клієнт отримав лист із посиланням на пароль і бачить оптові ціни.', 'brix-core' ) ),
			'bad-email' => array( 'error', __( 'У заявці немає коректної пошти — акаунт створити нема на що.', 'brix-core' ) ),
			'failed'    => array( 'error', __( 'Не вдалося створити користувача.', 'brix-core' ) ),
		);

		if ( ! isset( $messages[ $status ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $messages[ $status ][0] ),
			esc_html( $messages[ $status ][1] )
		);
	}

	/**
	 * Повертає до списку заявок із результатом.
	 *
	 * @param int    $lead   Заявка.
	 * @param string $status Результат.
	 * @return void
	 */
	private function back( int $lead, string $status ): void {
		unset( $lead );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => Application::POST_TYPE,
					'brix_lead' => $status,
				),
				admin_url( 'edit.php' )
			)
		);

		exit;
	}
}
