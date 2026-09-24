<?php
/**
 * Заявки від кавʼярень.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Wholesale;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Приймає й зберігає заявку на оптову співпрацю.
 *
 * Модерація, роль Wholesale і оптові ціни — фаза 6. Зараз заявка
 * просто зберігається й видно її в адмінці: форма, яка нікуди не
 * веде, гірша за її відсутність.
 */
final class Application implements Module {

	public const POST_TYPE = 'brix_lead';
	public const ACTION    = 'brix_wholesale_apply';
	public const NONCE     = 'brix_wholesale_nonce';

	/**
	 * Поля форми: ключ → чи обовʼязкове.
	 *
	 * @var array<string, bool>
	 */
	private const FIELDS = array(
		'place'   => true,
		'city'    => true,
		'name'    => true,
		'phone'   => true,
		'email'   => true,
		'volume'  => false,
		'machine' => false,
		'comment' => false,
	);

	/**
	 * Підписує модуль на хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ), 5 );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'template_redirect', array( $this, 'no_cache' ) );
	}

	/**
	 * Не кешує сторінку з формою.
	 *
	 * У формі nonce, а він живе добу. Сторінка, яку кеш LiteSpeed
	 * віддавав би тиждень, приймала б заявки лише першого дня — далі
	 * кожна поверталась би з помилкою «форма застаріла».
	 *
	 * @return void
	 */
	public function no_cache(): void {
		if ( ! is_page( 'wholesale' ) ) {
			return;
		}

		do_action( 'litespeed_control_set_nocache', 'nonce у формі заявки' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Хук LiteSpeed Cache.
		nocache_headers();
	}

	/**
	 * Реєструє тип запису для заявок.
	 *
	 * Не публічний: заявки бачить тільки адміністратор.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Заявки B2B', 'brix-core' ),
					'singular_name' => __( 'Заявка', 'brix-core' ),
					'menu_name'     => __( 'Заявки B2B', 'brix-core' ),
					'all_items'     => __( 'Усі заявки', 'brix-core' ),
					'edit_item'     => __( 'Заявка', 'brix-core' ),
					'not_found'     => __( 'Заявок немає', 'brix-core' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-email',
				'menu_position'   => 28,
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
				'supports'        => array( 'title' ),
			)
		);
	}

	/**
	 * Обробляє надіслану форму.
	 *
	 * @return void
	 */
	public function handle(): void {
		$referer = wp_get_referer() ? (string) wp_get_referer() : home_url( '/' );

		if ( ! isset( $_POST[ self::NONCE ] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), self::ACTION ) ) {
			$this->redirect( $referer, 'nonce' );
		}

		// Приманка для ботів: людина цього поля не бачить і не заповнює.
		if ( ! empty( $_POST['brix_website'] ) ) {
			$this->redirect( $referer, 'ok' );
		}

		$data    = array();
		$missing = false;

		foreach ( self::FIELDS as $field => $required ) {
			$value = isset( $_POST[ 'brix_' . $field ] )
				? sanitize_textarea_field( wp_unslash( $_POST[ 'brix_' . $field ] ) )
				: '';

			if ( 'email' === $field ) {
				$value = sanitize_email( $value );
			}

			if ( $required && '' === $value ) {
				$missing = true;
			}

			$data[ $field ] = $value;
		}

		if ( $missing || ! is_email( $data['email'] ) ) {
			$this->redirect( $referer, 'invalid' );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sprintf( '%s — %s', $data['place'], $data['city'] ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$this->redirect( $referer, 'error' );
		}

		foreach ( $data as $field => $value ) {
			update_post_meta( (int) $post_id, 'brix_lead_' . $field, $value );
		}

		/**
		 * Спрацьовує після збереження заявки.
		 *
		 * @param int                   $post_id Заявка.
		 * @param array<string, string> $data    Дані форми.
		 */
		do_action( 'brix_wholesale_application_saved', (int) $post_id, $data );

		$this->redirect( $referer, 'ok' );
	}

	/**
	 * Повертає користувача назад із позначкою результату.
	 *
	 * @param string $url    Куди.
	 * @param string $status Стан.
	 * @return void
	 */
	private function redirect( string $url, string $status ): void {
		wp_safe_redirect( add_query_arg( 'b2b', $status, remove_query_arg( 'b2b', $url ) ) . '#brix-b2b-form' );
		exit;
	}
}
