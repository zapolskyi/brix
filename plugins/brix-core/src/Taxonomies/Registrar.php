<?php
/**
 * Таксономії каталогу.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Taxonomies;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Реєструє таксономії, якими описується лот.
 *
 * Чотири штуки, і кожна є у фільтрі каталогу або в паспорті лоту.
 * Склад звірений з артбордами — див. docs/04-model-danykh.md.
 */
final class Registrar implements Module {

	public const COUNTRY     = 'brix_country';
	public const PROCESSING  = 'brix_processing';
	public const NOTE        = 'brix_note';
	public const BREW_METHOD = 'brix_brew_method';

	/**
	 * Допустимі стилі пачки. Термін обробки зберігає один із них
	 * у мета-полі, звідси береться клас .brix-pack--*.
	 *
	 * @var array<int, string>
	 */
	public const PACK_STYLES = array( 'natural', 'washed', 'honey', 'lab', 'core', 'drip' );

	/**
	 * Підписує модуль на хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_taxonomies' ), 5 );
		add_action( self::PROCESSING . '_add_form_fields', array( $this, 'render_pack_style_add_field' ) );
		add_action( self::PROCESSING . '_edit_form_fields', array( $this, 'render_pack_style_edit_field' ) );
		add_action( 'created_' . self::PROCESSING, array( $this, 'save_pack_style' ) );
		add_action( 'edited_' . self::PROCESSING, array( $this, 'save_pack_style' ) );
	}

	/**
	 * Реєструє таксономії.
	 *
	 * @return void
	 */
	public function register_taxonomies(): void {
		register_taxonomy(
			self::COUNTRY,
			array( 'product' ),
			$this->args(
				__( 'Країни', 'brix-core' ),
				__( 'Країна', 'brix-core' ),
				'country',
				false
			)
		);

		register_taxonomy(
			self::PROCESSING,
			array( 'product' ),
			$this->args(
				__( 'Обробки', 'brix-core' ),
				__( 'Обробка', 'brix-core' ),
				'processing',
				false
			)
		);

		/*
		 * Ноти ієрархічні: у фільтрі каталогу вісім широких груп
		 * («ягоди», «цитрус»), а під назвою товару стоять конкретні
		 * ноти («черешня», «бергамот»). Це один запис даних, просто
		 * читається з двох рівнів.
		 */
		register_taxonomy(
			self::NOTE,
			array( 'product' ),
			$this->args(
				__( 'Смакові ноти', 'brix-core' ),
				__( 'Смакова нота', 'brix-core' ),
				'note',
				true
			)
		);

		register_taxonomy(
			self::BREW_METHOD,
			array( 'product', 'brix_guide' ),
			$this->args(
				__( 'Способи заварювання', 'brix-core' ),
				__( 'Спосіб заварювання', 'brix-core' ),
				'brew',
				false
			)
		);
	}

	/**
	 * Спільні аргументи реєстрації.
	 *
	 * @param string $plural       Назва в множині.
	 * @param string $singular     Назва в однині.
	 * @param string $slug         Частина URL.
	 * @param bool   $hierarchical Чи ієрархічна.
	 * @return array<string, mixed>
	 */
	private function args( string $plural, string $singular, string $slug, bool $hierarchical ): array {
		return array(
			'labels'            => array(
				'name'          => $plural,
				'singular_name' => $singular,
				'search_items'  => sprintf( /* translators: %s — назва таксономії в множині. */ __( 'Шукати: %s', 'brix-core' ), $plural ),
				'all_items'     => $plural,
				'edit_item'     => sprintf( /* translators: %s — назва таксономії в однині. */ __( 'Редагувати: %s', 'brix-core' ), $singular ),
				'add_new_item'  => sprintf( /* translators: %s — назва таксономії в однині. */ __( 'Додати: %s', 'brix-core' ), $singular ),
				'not_found'     => __( 'Нічого не знайдено', 'brix-core' ),
				'menu_name'     => $plural,
			),
			'public'            => true,
			'hierarchical'      => $hierarchical,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => array(
				'slug'       => $slug,
				'with_front' => false,
			),
		);
	}

	/**
	 * Поле «стиль пачки» на формі створення терміна.
	 *
	 * @return void
	 */
	public function render_pack_style_add_field(): void {
		?>
		<div class="form-field">
			<label for="brix_pack_style"><?php esc_html_e( 'Стиль пачки', 'brix-core' ); ?></label>
			<?php $this->render_pack_style_select( '' ); ?>
			<p><?php esc_html_e( 'Задає колір пачки й тега для лотів цієї обробки.', 'brix-core' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Поле «стиль пачки» на формі редагування терміна.
	 *
	 * @param \WP_Term $term Термін.
	 * @return void
	 */
	public function render_pack_style_edit_field( \WP_Term $term ): void {
		?>
		<tr class="form-field">
			<th scope="row"><label for="brix_pack_style"><?php esc_html_e( 'Стиль пачки', 'brix-core' ); ?></label></th>
			<td>
				<?php $this->render_pack_style_select( self::pack_style( $term->term_id ) ); ?>
				<p class="description"><?php esc_html_e( 'Задає колір пачки й тега для лотів цієї обробки.', 'brix-core' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Друкує сам випадний список.
	 *
	 * @param string $current Поточне значення.
	 * @return void
	 */
	private function render_pack_style_select( string $current ): void {
		wp_nonce_field( 'brix_pack_style', 'brix_pack_style_nonce' );
		?>
		<select name="brix_pack_style" id="brix_pack_style">
			<option value=""><?php esc_html_e( '— за замовчуванням —', 'brix-core' ); ?></option>
			<?php foreach ( self::PACK_STYLES as $style ) : ?>
				<option value="<?php echo esc_attr( $style ); ?>" <?php selected( $current, $style ); ?>>
					<?php echo esc_html( $style ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Зберігає стиль пачки.
	 *
	 * @param int $term_id Термін.
	 * @return void
	 */
	public function save_pack_style( int $term_id ): void {
		if ( ! current_user_can( 'manage_product_terms' ) && ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		if ( ! isset( $_POST['brix_pack_style_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['brix_pack_style_nonce'] ) ), 'brix_pack_style' ) ) {
			return;
		}

		$style = isset( $_POST['brix_pack_style'] ) ? sanitize_key( wp_unslash( $_POST['brix_pack_style'] ) ) : '';

		if ( ! in_array( $style, self::PACK_STYLES, true ) ) {
			delete_term_meta( $term_id, 'brix_pack_style' );
			return;
		}

		update_term_meta( $term_id, 'brix_pack_style', $style );
	}

	/**
	 * Стиль пачки для терміна обробки.
	 *
	 * @param int $term_id Термін.
	 * @return string Один зі PACK_STYLES або порожній рядок.
	 */
	public static function pack_style( int $term_id ): string {
		$style = (string) get_term_meta( $term_id, 'brix_pack_style', true );

		return in_array( $style, self::PACK_STYLES, true ) ? $style : '';
	}
}
