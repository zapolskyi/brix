<?php
/**
 * Англійські версії контенту: заголовки, тексти, терміни, меню.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\I18n;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Другий текст поруч із першим.
 *
 * Другої копії записів тут немає — жодного дубля товару, сторінки чи
 * терміна. Переклад лежить у мета-полі поруч з оригіналом і
 * підставляється на читанні. Тому ідентифікатор товару один, кошик
 * один, залишки одні, а посилання між мовами збігаються самі собою:
 * /shop/lot і /en/shop/lot — це той самий запис.
 *
 * Ціна такого рішення — переклад не можна вести як окремий запис зі
 * своїм станом публікації. Для магазину на два десятки сторінок це
 * дешевше, ніж дзеркальне дерево записів, яке доводиться тримати
 * синхронним уручну.
 *
 * Чого перекладу немає — там лишається оригінал. Сторінка ніколи не
 * буває порожньою.
 */
final class Translations implements Module {

	/**
	 * Префікс мета-полів перекладу.
	 *
	 * @var string
	 */
	public const META = '_brix_en_';

	/**
	 * Опція з перекладом рядків, що лежать у налаштуваннях.
	 *
	 * @var string
	 */
	public const STRINGS = 'brix_en_strings';

	/**
	 * Вішає хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
		add_action( 'save_post', array( $this, 'save_box' ), 10, 2 );
		add_action( 'edit_term', array( $this, 'save_term' ), 10, 3 );

		foreach ( self::taxonomies() as $taxonomy ) {
			add_action( $taxonomy . '_edit_form_fields', array( $this, 'term_fields' ), 10, 2 );
		}

		/*
		 * Фільтри вішаються завжди, а не тільки під /en/. Лист про
		 * замовлення малюється поза запитом — тоді мову нав'язує
		 * I18n\Emails, і хуки мають бути вже на місці. Кожен з них
		 * повертається одразу, коли мова не англійська.
		 */
		add_filter( 'the_title', array( $this, 'title' ), 10, 2 );
		add_filter( 'single_post_title', array( $this, 'post_title' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'content' ), 5 );
		add_filter( 'get_the_excerpt', array( $this, 'excerpt' ), 10, 2 );
		add_filter( 'get_post_metadata', array( $this, 'meta' ), 10, 4 );
		add_filter( 'get_term', array( $this, 'term' ) );
		add_filter( 'wp_setup_nav_menu_item', array( $this, 'menu_item' ) );

		add_filter( 'woocommerce_product_get_name', array( $this, 'product_text' ), 10, 2 );
		add_filter( 'woocommerce_product_get_description', array( $this, 'product_description' ), 10, 2 );
		add_filter( 'woocommerce_product_get_short_description', array( $this, 'product_excerpt' ), 10, 2 );
		add_filter( 'woocommerce_attribute_label', array( $this, 'string' ) );
		/*
		 * Позиції замовлення всі мають тип order_item — окремих
		 * префіксів у товарної й доставкової немає, тож фільтр один,
		 * а різницю ловимо за класом позиції.
		 */
		add_filter( 'woocommerce_order_item_get_name', array( $this, 'order_item_name' ), 10, 2 );
		add_filter( 'woocommerce_order_item_get_method_title', array( $this, 'string' ) );
		add_filter( 'woocommerce_order_get_payment_method_title', array( $this, 'string' ) );
		add_filter( 'option_woocommerce_cod_settings', array( $this, 'gateway_settings' ) );
		add_filter( 'option_woocommerce_email_footer_text', array( $this, 'string' ) );
		add_filter( 'woocommerce_shipping_rate_label', array( $this, 'string' ) );
		add_filter( 'woocommerce_gateway_title', array( $this, 'string' ) );
		add_filter( 'woocommerce_gateway_description', array( $this, 'string' ) );
		add_filter( 'brix_translate', array( $this, 'string' ) );
	}

	/**
	 * Таксономії, терміни яких перекладаються.
	 *
	 * @return array<int, string>
	 */
	public static function taxonomies(): array {
		return array(
			'product_cat',
			'product_tag',
			'brix_country',
			'brix_processing',
			'brix_note',
			'brix_brew_method',
			'pa_grind',
			'pa_weight',
		);
	}

	/**
	 * Переклад рядка з таблиці рядків.
	 *
	 * Сюди йде те, що лежить у налаштуваннях, а не в записах: назви
	 * способів доставки, підписи платіжок, години самовивозу. Ключ —
	 * сам український рядок: окремих ідентифікаторів немає навмисно,
	 * бо будь-який з них довелося б тримати синхронним із текстом у
	 * налаштуваннях WooCommerce, до яких у нас немає доступу.
	 *
	 * @param string $source Оригінал.
	 * @return string
	 */
	public static function text( string $source ): string {
		if ( ! Language::is_second() || '' === trim( $source ) ) {
			return $source;
		}

		$table = (array) get_option( self::STRINGS, array() );
		$key   = trim( $source );

		return isset( $table[ $key ] ) && '' !== $table[ $key ] ? (string) $table[ $key ] : $source;
	}

	/**
	 * Те саме як фільтр.
	 *
	 * @param mixed $value Значення.
	 * @return mixed
	 */
	public function string( $value ) {
		return is_string( $value ) ? self::text( $value ) : $value;
	}

	/**
	 * Заголовок запису.
	 *
	 * @param string   $title Заголовок.
	 * @param int|null $id    Запис.
	 * @return string
	 */
	public function title( string $title, $id = null ): string {
		return $id ? $this->swap( (int) $id, 'title', $title ) : $title;
	}

	/**
	 * Заголовок одиночного запису у <title>.
	 *
	 * @param string        $title Заголовок.
	 * @param \WP_Post|null $post  Запис.
	 * @return string
	 */
	public function post_title( string $title, $post = null ): string {
		return $post instanceof \WP_Post ? $this->swap( $post->ID, 'title', $title ) : $title;
	}

	/**
	 * Текст запису.
	 *
	 * Фільтр стоїть раніше за do_blocks: у мета-полі лежить така сама
	 * розмітка блоків, як в оригіналі, тож її ще треба розібрати.
	 *
	 * @param string $content Текст.
	 * @return string
	 */
	public function content( string $content ): string {
		$id = get_the_ID();

		return $id ? $this->swap( (int) $id, 'content', $content ) : $content;
	}

	/**
	 * Короткий опис.
	 *
	 * @param string        $excerpt Опис.
	 * @param \WP_Post|null $post    Запис.
	 * @return string
	 */
	public function excerpt( string $excerpt, $post = null ): string {
		return $post instanceof \WP_Post ? $this->swap( $post->ID, 'excerpt', $excerpt ) : $excerpt;
	}

	/**
	 * Назва товару.
	 *
	 * @param string       $name    Назва.
	 * @param \WC_Product  $product Товар.
	 * @return string
	 */
	public function product_text( string $name, $product ): string {
		return $product instanceof \WC_Product ? $this->swap( $product->get_id(), 'title', $name ) : $name;
	}

	/**
	 * Опис товару.
	 *
	 * @param string      $text    Опис.
	 * @param \WC_Product $product Товар.
	 * @return string
	 */
	public function product_description( string $text, $product ): string {
		return $product instanceof \WC_Product ? $this->swap( $product->get_id(), 'content', $text ) : $text;
	}

	/**
	 * Короткий опис товару.
	 *
	 * @param string      $text    Опис.
	 * @param \WC_Product $product Товар.
	 * @return string
	 */
	public function product_excerpt( string $text, $product ): string {
		return $product instanceof \WC_Product ? $this->swap( $product->get_id(), 'excerpt', $text ) : $text;
	}

	/**
	 * Мета-поля з префіксом brix_.
	 *
	 * @param mixed  $value  Значення (null — читати як завжди).
	 * @param int    $id     Запис.
	 * @param string $key    Ключ.
	 * @param bool   $single Одне значення.
	 * @return mixed
	 */
	public function meta( $value, $id, $key, $single ) {
		if ( ! Language::is_second() || ! is_string( $key ) || 0 !== strpos( $key, 'brix_' ) ) {
			return $value;
		}

		$translation = get_post_meta( (int) $id, self::META . '_' . $key, true );

		if ( ! is_string( $translation ) || '' === $translation ) {
			return $value;
		}

		return $single ? $translation : array( $translation );
	}

	/**
	 * Назва позиції в замовленні.
	 *
	 * У замовленні лежить знімок назви на момент покупки. Під /en/
	 * він показав би українську назву назавжди, тож беремо назву в
	 * товару заново — разом із перекладеними вагою й помелом.
	 *
	 * Знімок від цього не псується: у базі він лишається тим самим,
	 * підміна діє лише на показі й лише англійською.
	 *
	 * @param string $name Назва зі знімка.
	 * @param mixed  $item Позиція.
	 * @return string
	 */
	public function order_item_name( $name, $item ): string {
		if ( ! Language::is_second() || ! $item instanceof \WC_Order_Item_Product ) {
			return (string) $name;
		}

		$product = $item->get_product();

		return $product instanceof \WC_Product ? $product->get_name() : (string) $name;
	}

	/**
	 * Текстові поля в налаштуваннях способу оплати.
	 *
	 * Інструкція накладного платежу друкується на сторінці подяки
	 * і в листі напряму з налаштувань, без власного фільтра. Тож
	 * перекладаємо самі налаштування на читанні.
	 *
	 * @param mixed $settings Налаштування.
	 * @return mixed
	 */
	public function gateway_settings( $settings ) {
		if ( ! Language::is_second() || ! is_array( $settings ) ) {
			return $settings;
		}

		foreach ( array( 'title', 'description', 'instructions' ) as $key ) {
			if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) ) {
				$settings[ $key ] = self::text( $settings[ $key ] );
			}
		}

		return $settings;
	}

	/**
	 * Термін таксономії.
	 *
	 * @param \WP_Term|mixed $term Термін.
	 * @return \WP_Term|mixed
	 */
	public function term( $term ) {
		if ( ! Language::is_second() || ! $term instanceof \WP_Term || ! in_array( $term->taxonomy, self::taxonomies(), true ) ) {
			return $term;
		}

		foreach ( array( 'name', 'description' ) as $field ) {
			$value = get_term_meta( $term->term_id, self::META . $field, true );

			if ( is_string( $value ) && '' !== $value ) {
				$term->{$field} = $value;
			}
		}

		return $term;
	}

	/**
	 * Пункт меню.
	 *
	 * Власний підпис пункту лежить у його заголовку, а пункт меню —
	 * це запис. Тож переклад зберігається там само, де в усіх інших
	 * записів.
	 *
	 * @param \WP_Post|mixed $item Пункт.
	 * @return \WP_Post|mixed
	 */
	public function menu_item( $item ) {
		if ( ! Language::is_second() || ! isset( $item->ID, $item->title ) ) {
			return $item;
		}

		$item->title = $this->swap( (int) $item->ID, 'title', (string) $item->title );

		return $item;
	}

	/**
	 * Підставляє переклад, якщо він є.
	 *
	 * @param int    $id       Запис.
	 * @param string $field    title | content | excerpt.
	 * @param string $original Оригінал.
	 * @return string
	 */
	private function swap( int $id, string $field, string $original ): string {
		if ( $id <= 0 || ! Language::is_second() ) {
			return $original;
		}

		$value = get_post_meta( $id, self::META . $field, true );

		return is_string( $value ) && '' !== trim( $value ) ? $value : $original;
	}

	/**
	 * Коробка «English version» на екрані запису.
	 *
	 * @return void
	 */
	public function add_box(): void {
		add_meta_box(
			'brix-en',
			__( 'Англійська версія', 'brix-core' ),
			array( $this, 'render_box' ),
			array( 'page', 'post', 'product', 'brix_farm', 'brix_guide' ),
			'normal',
			'low'
		);
	}

	/**
	 * Поля коробки.
	 *
	 * @param \WP_Post $post Запис.
	 * @return void
	 */
	public function render_box( \WP_Post $post ): void {
		wp_nonce_field( 'brix_en_save', 'brix_en_nonce' );

		$fields = array(
			'title'   => array( __( 'Заголовок', 'brix-core' ), 'text' ),
			'excerpt' => array( __( 'Короткий опис', 'brix-core' ), 'area' ),
			'content' => array( __( 'Текст', 'brix-core' ), 'area' ),
		);

		echo '<p class="description">' . esc_html__( 'Порожнє поле означає «лишити як українською» — сторінка під /en/ покаже оригінал.', 'brix-core' ) . '</p>';

		foreach ( $fields as $key => $field ) {
			$value = (string) get_post_meta( $post->ID, self::META . $key, true );

			printf( '<p><label for="brix-en-%1$s"><strong>%2$s</strong></label><br>', esc_attr( $key ), esc_html( $field[0] ) );

			if ( 'area' === $field[1] ) {
				printf(
					'<textarea id="brix-en-%1$s" name="brix_en[%1$s]" rows="%2$d" class="large-text code">%3$s</textarea>',
					esc_attr( $key ),
					'content' === $key ? 12 : 3,
					esc_textarea( $value )
				);
			} else {
				printf(
					'<input type="text" id="brix-en-%1$s" name="brix_en[%1$s]" value="%2$s" class="large-text">',
					esc_attr( $key ),
					esc_attr( $value )
				);
			}

			echo '</p>';
		}
	}

	/**
	 * Зберігає коробку.
	 *
	 * @param int      $id   Запис.
	 * @param \WP_Post $post Запис.
	 * @return void
	 */
	public function save_box( $id, $post ): void {
		if ( ! isset( $_POST['brix_en_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['brix_en_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'brix_en_save' ) || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		unset( $post );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce перевірено вище.
		$posted = isset( $_POST['brix_en'] ) ? (array) wp_unslash( $_POST['brix_en'] ) : array();

		foreach ( array( 'title', 'excerpt', 'content' ) as $key ) {
			$value = isset( $posted[ $key ] ) ? (string) $posted[ $key ] : '';
			$value = 'title' === $key ? sanitize_text_field( $value ) : wp_kses_post( $value );

			if ( '' === trim( $value ) ) {
				delete_post_meta( $id, self::META . $key );
				continue;
			}

			update_post_meta( $id, self::META . $key, $value );
		}
	}

	/**
	 * Поля перекладу на екрані терміна.
	 *
	 * @param \WP_Term $term     Термін.
	 * @param string   $taxonomy Таксономія.
	 * @return void
	 */
	public function term_fields( $term, $taxonomy ): void {
		unset( $taxonomy );

		if ( ! $term instanceof \WP_Term ) {
			return;
		}

		wp_nonce_field( 'brix_en_term', 'brix_en_term_nonce' );

		$fields = array(
			'name'        => __( 'Назва англійською', 'brix-core' ),
			'description' => __( 'Опис англійською', 'brix-core' ),
		);

		foreach ( $fields as $key => $label ) {
			$value = (string) get_term_meta( $term->term_id, self::META . $key, true );
			?>
			<tr class="form-field">
				<th scope="row"><label for="brix-en-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
				<td>
					<?php if ( 'description' === $key ) : ?>
						<textarea id="brix-en-<?php echo esc_attr( $key ); ?>" name="brix_en_term[<?php echo esc_attr( $key ); ?>]" rows="4" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
					<?php else : ?>
						<input type="text" id="brix-en-<?php echo esc_attr( $key ); ?>" name="brix_en_term[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="large-text">
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'Порожнє поле означає «лишити як українською».', 'brix-core' ); ?></p>
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Зберігає переклад терміна.
	 *
	 * @param int    $term_id  Термін.
	 * @param int    $tt_id    Зв'язок.
	 * @param string $taxonomy Таксономія.
	 * @return void
	 */
	public function save_term( $term_id, $tt_id, $taxonomy ): void {
		unset( $tt_id );

		if ( ! isset( $_POST['brix_en_term_nonce'] ) || ! in_array( $taxonomy, self::taxonomies(), true ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['brix_en_term_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'brix_en_term' ) || ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce перевірено вище.
		$posted = isset( $_POST['brix_en_term'] ) ? (array) wp_unslash( $_POST['brix_en_term'] ) : array();

		foreach ( array( 'name', 'description' ) as $key ) {
			$value = isset( $posted[ $key ] ) ? sanitize_text_field( (string) $posted[ $key ] ) : '';

			if ( '' === trim( $value ) ) {
				delete_term_meta( $term_id, self::META . $key );
				continue;
			}

			update_term_meta( $term_id, self::META . $key, $value );
		}
	}
}
