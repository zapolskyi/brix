<?php
/**
 * Дії покупця над підпискою.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Club;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Пауза, відновлення, пропуск і скасування.
 *
 * Усе — звичайні посилання з nonce, без JavaScript: керування
 * підпискою має працювати так само надійно, як решта кабінету.
 */
final class Actions implements Module {

	/**
	 * Дія в admin-post.
	 */
	public const ACTION = 'brix_club_action';

	/**
	 * Дозволені дії.
	 */
	private const ALLOWED = array( 'pause', 'resume', 'skip', 'cancel' );

	/**
	 * Підписує модуль на хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Адреса дії для підписки.
	 *
	 * @param int    $id   Підписка.
	 * @param string $what Дія.
	 * @return string
	 */
	public static function url( int $id, string $what ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ACTION,
					'sub'    => $id,
					'do'     => $what,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . $id
		);
	}

	/**
	 * Виконує дію над підпискою.
	 *
	 * @return void
	 */
	public function handle(): void {
		$id   = isset( $_GET['sub'] ) ? absint( $_GET['sub'] ) : 0;
		$what = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';

		if ( ! $id || ! in_array( $what, self::ALLOWED, true ) ) {
			$this->back();
		}

		check_admin_referer( self::ACTION . '_' . $id );

		// Підписка керується лише власником: інакше перебір номерів
		// дозволяв би скасовувати чужі.
		if ( ! Subscription::owned_by( $id, get_current_user_id() ) ) {
			wc_add_notice( __( 'Ця підписка недоступна.', 'brix-core' ), 'error' );
			$this->back();
		}

		$interval = max( 1, (int) get_post_meta( $id, '_brix_interval', true ) );

		switch ( $what ) {
			case 'pause':
				update_post_meta( $id, '_brix_status', Subscription::PAUSED );
				wc_add_notice( __( 'Підписку поставлено на паузу. Відновити можна будь-коли.', 'brix-core' ), 'success' );
				break;

			case 'resume':
				update_post_meta( $id, '_brix_status', Subscription::ACTIVE );
				update_post_meta( $id, '_brix_next', time() + $interval * DAY_IN_SECONDS );
				wc_add_notice( __( 'Підписку відновлено.', 'brix-core' ), 'success' );
				break;

			case 'skip':
				$next = (int) get_post_meta( $id, '_brix_next', true );
				update_post_meta( $id, '_brix_next', max( time(), $next ) + $interval * DAY_IN_SECONDS );
				wc_add_notice( __( 'Найближчу відправку пропущено.', 'brix-core' ), 'success' );
				break;

			case 'cancel':
				update_post_meta( $id, '_brix_status', Subscription::CANCELLED );
				wc_add_notice( __( 'Підписку скасовано. Лоти лишаються доступними в магазині.', 'brix-core' ), 'notice' );
				break;
		}

		$this->back();
	}

	/**
	 * Повертає в кабінет.
	 *
	 * @return void
	 */
	private function back(): void {
		wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
		exit;
	}
}
