<?php
/**
 * Команда wp brix demo — наповнення чистої інсталяції.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Cli;

use Brix\Core\PostTypes\BrewGuide;
use Brix\Core\PostTypes\Farm;
use Brix\Core\Product\LotMeta;
use Brix\Core\Support\Slug;
use Brix\Core\Taxonomies\Registrar as Tax;
use Brix\Core\Fields\FarmFields;
use Brix\Core\Fields\GuideFields;

defined( 'ABSPATH' ) || exit;

/**
 * Створює демо-контент магазину.
 *
 * Команда ідемпотентна: повторний запуск оновлює те, що вже є, за slug,
 * а не плодить дублікати. Це важливіше, ніж здається, — демо-контент
 * доводиться перезаливати щоразу, коли міняється модель даних.
 */
final class DemoContent {

	/**
	 * Уміст demo-content.json.
	 *
	 * @var array<string, mixed>
	 */
	private array $data = array();

	/**
	 * Створені записи за slug, щоб зв'язати лоти з фермами й гайдами.
	 *
	 * @var array<string, int>
	 */
	private array $ids = array();

	/**
	 * Наповнює магазин демо-контентом.
	 *
	 * ## OPTIONS
	 *
	 * [--fresh]
	 * : Спершу видалити раніше створений демо-контент.
	 *
	 * ## EXAMPLES
	 *
	 *     wp brix demo
	 *     wp brix demo --fresh
	 *
	 * @param array<int, string>    $args       Позиційні аргументи.
	 * @param array<string, string> $assoc_args Іменовані аргументи.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$this->data = $this->load();

		if ( isset( $assoc_args['fresh'] ) ) {
			$this->purge();
		}

		$this->import_attributes();
		$this->import_terms();
		$this->import_farms();
		$this->import_guides();
		$this->import_products();

		// Нові типи записів і таксономії дають 404, доки правила
		// перезапису не перебудовано.
		flush_rewrite_rules();

		\WP_CLI::success( 'Демо-контент на місці.' );
	}

	/**
	 * Читає файл з даними.
	 *
	 * @return array<string, mixed>
	 */
	private function load(): array {
		$path = BRIX_CORE_DIR . '/data/demo-content.json';

		if ( ! is_readable( $path ) ) {
			\WP_CLI::error( 'Немає файлу data/demo-content.json' );
		}

		$data = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( ! is_array( $data ) ) {
			\WP_CLI::error( 'demo-content.json не читається як JSON' );
		}

		return $data;
	}

	/**
	 * Видаляє раніше створений демо-контент.
	 *
	 * Впізнає його за мета-полем `_brix_demo`: чужі записи не чіпає.
	 *
	 * @return void
	 */
	private function purge(): void {
		$posts = get_posts(
			array(
				'post_type'      => array( 'product', Farm::POST_TYPE, BrewGuide::POST_TYPE ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Разова CLI-операція.
				'meta_query'     => array(
					array(
						'key'     => '_brix_demo',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		foreach ( $posts as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}

		\WP_CLI::log( sprintf( 'Видалено раніше створеного: %d', count( $posts ) ) );
	}

	/**
	 * Створює глобальні атрибути «Вага» і «Помел».
	 *
	 * Локальні атрибути з кириличними назвами дають у посиланні
	 * attribute_%d0%b2%d0%b0%d0%b3%d0%b0 — таке не прочитати й не
	 * надіслати. Глобальні атрибути мають окремо назву («Вага»)
	 * і slug (`weight`), тож адреса лишається читабельною,
	 * а значення ще й можна фільтрувати.
	 *
	 * @return void
	 */
	private function import_attributes(): void {
		$weights = array();

		foreach ( (array) ( $this->data['products'] ?? array() ) as $row ) {
			foreach ( (array) ( $row['weights'] ?? array() ) as $weight ) {
				$weights[ (string) $weight['weight'] ] = true;
			}
		}

		$this->ensure_attribute( 'weight', __( 'Вага', 'brix-core' ), array_keys( $weights ) );
		$this->ensure_attribute( 'grind', __( 'Помел', 'brix-core' ), (array) ( $this->data['grinds'] ?? array() ) );

		// Таксономії атрибутів реєструються на init, а ми вже після
		// нього: без цього wp_set_object_terms не знайде таксономію.
		wc_get_attribute_taxonomies();
		delete_transient( 'wc_attribute_taxonomies' );

		foreach ( wc_get_attribute_taxonomy_names() as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				register_taxonomy(
					$taxonomy,
					array( 'product' ),
					array(
						'hierarchical' => false,
						'public'       => false,
					)
				);
			}
		}

		\WP_CLI::log( 'Атрибути готові.' );
	}

	/**
	 * Створює глобальний атрибут і його значення.
	 *
	 * @param string             $slug   Slug атрибута без префікса pa_.
	 * @param string             $label  Назва для покупця.
	 * @param array<int, string> $values Значення.
	 * @return void
	 */
	private function ensure_attribute( string $slug, string $label, array $values ): void {
		$taxonomy = wc_attribute_taxonomy_name( $slug );

		if ( ! taxonomy_exists( $taxonomy ) && ! wc_attribute_taxonomy_id_by_name( $slug ) ) {
			$created = wc_create_attribute(
				array(
					'name'         => $label,
					'slug'         => $slug,
					'type'         => 'select',
					'order_by'     => 'menu_order',
					'has_archives' => false,
				)
			);

			if ( is_wp_error( $created ) ) {
				\WP_CLI::warning( sprintf( 'Атрибут «%s»: %s', $label, $created->get_error_message() ) );
				return;
			}

			register_taxonomy(
				$taxonomy,
				array( 'product' ),
				array(
					'hierarchical' => false,
					'public'       => false,
				)
			);
		}

		foreach ( $values as $index => $value ) {
			$term = get_term_by( 'name', (string) $value, $taxonomy );

			if ( ! $term instanceof \WP_Term ) {
				$term_id = wp_insert_term(
					(string) $value,
					$taxonomy,
					array( 'slug' => Slug::latin( (string) $value ) )
				);

				if ( is_wp_error( $term_id ) ) {
					continue;
				}

				$term_id = (int) $term_id['term_id'];
			} else {
				$term_id = (int) $term->term_id;
			}

			// Порядок значень — це порядок у макеті: 100 г, 250 г, 1 кг.
			update_term_meta( $term_id, 'order_' . $taxonomy, $index );
		}
	}

	/**
	 * Створює терміни таксономій.
	 *
	 * @return void
	 */
	private function import_terms(): void {
		$terms = (array) ( $this->data['terms'] ?? array() );

		foreach ( (array) ( $terms['brix_country'] ?? array() ) as $name ) {
			$this->ensure_term( (string) $name, Tax::COUNTRY );
		}

		foreach ( (array) ( $terms['brix_processing'] ?? array() ) as $row ) {
			$term_id = $this->ensure_term( (string) $row['name'], Tax::PROCESSING );

			if ( $term_id && ! empty( $row['pack_style'] ) ) {
				update_term_meta( $term_id, 'brix_pack_style', sanitize_key( (string) $row['pack_style'] ) );
			}
		}

		foreach ( (array) ( $terms['brix_brew_method'] ?? array() ) as $name ) {
			$this->ensure_term( (string) $name, Tax::BREW_METHOD );
		}

		/*
		 * Лінійки з власним пакуванням. Origin свого стилю не має —
		 * там колір бере обробка.
		 */
		foreach ( array(
			'Lab'        => 'lab',
			'Core'       => 'core',
			'Drip & Try' => 'drip',
		) as $name => $style ) {
			$term = get_term_by( 'name', $name, 'product_cat' );

			if ( $term instanceof \WP_Term ) {
				update_term_meta( $term->term_id, 'brix_pack_style', $style );
			}
		}

		// Ноти ієрархічні: група — батько, конкретна нота — дитина.
		foreach ( (array) ( $terms['brix_note'] ?? array() ) as $group => $children ) {
			$parent = $this->ensure_term( (string) $group, Tax::NOTE );

			foreach ( (array) $children as $child ) {
				$this->ensure_term( (string) $child, Tax::NOTE, (int) $parent );
			}
		}

		\WP_CLI::log( 'Таксономії заповнені.' );
	}

	/**
	 * Створює або знаходить термін.
	 *
	 * @param string $name     Назва.
	 * @param string $taxonomy Таксономія.
	 * @param int    $parent_id Батьківський термін.
	 * @return int ID терміна або 0.
	 */
	private function ensure_term( string $name, string $taxonomy, int $parent_id = 0 ): int {
		$slug     = Slug::latin( $name );
		$existing = get_term_by( 'name', $name, $taxonomy );

		if ( $existing instanceof \WP_Term ) {
			/*
			 * Термін міг лишитись від старішого запуску з кириличним
			 * slug. Імпортер має приводити дані до потрібного стану,
			 * а не покладатись на те, що базу почистили руками.
			 */
			if ( $existing->slug !== $slug ) {
				wp_update_term( $existing->term_id, $taxonomy, array( 'slug' => $slug ) );
			}

			return (int) $existing->term_id;
		}

		$created = wp_insert_term(
			$name,
			$taxonomy,
			array(
				'parent' => $parent_id,
				// Без цього WordPress лишив би в slug кирилицю, і адреса
				// перетворилася б на /country/%d0%b5%d1%84%d1%96...
				'slug'   => $slug,
			)
		);

		if ( is_wp_error( $created ) ) {
			\WP_CLI::warning( sprintf( 'Термін «%s»: %s', $name, $created->get_error_message() ) );
			return 0;
		}

		return (int) $created['term_id'];
	}

	/**
	 * Створює виробників.
	 *
	 * @return void
	 */
	private function import_farms(): void {
		foreach ( (array) ( $this->data['farms'] ?? array() ) as $farm ) {
			$post_id = $this->ensure_post( $farm, Farm::POST_TYPE );

			if ( ! $post_id ) {
				continue;
			}

			$fields = (array) ( $farm['fields'] ?? array() );

			foreach ( $fields as $name => $value ) {
				if ( 'year_calendar' === $name ) {
					continue;
				}

				update_post_meta( $post_id, FarmFields::PREFIX . $name, $value );
			}

			$this->save_repeater(
				$post_id,
				FarmFields::PREFIX . 'year_calendar',
				(array) ( $fields['year_calendar'] ?? array() ),
				array(
					'period' => FarmFields::PREFIX . 'calendar_period',
					'title'  => FarmFields::PREFIX . 'calendar_title',
					'note'   => FarmFields::PREFIX . 'calendar_note',
				)
			);

			if ( ! empty( $farm['country'] ) ) {
				wp_set_object_terms( $post_id, (string) $farm['country'], Tax::COUNTRY );
			}

			$this->ids[ 'farm:' . $farm['slug'] ] = $post_id;
		}

		\WP_CLI::log( sprintf( 'Виробників: %d', count( (array) ( $this->data['farms'] ?? array() ) ) ) );
	}

	/**
	 * Створює гайди заварювання.
	 *
	 * @return void
	 */
	private function import_guides(): void {
		foreach ( (array) ( $this->data['guides'] ?? array() ) as $guide ) {
			$post_id = $this->ensure_post( $guide, BrewGuide::POST_TYPE );

			if ( ! $post_id ) {
				continue;
			}

			$fields = (array) ( $guide['fields'] ?? array() );

			foreach ( $fields as $name => $value ) {
				if ( 'steps' === $name ) {
					continue;
				}

				update_post_meta( $post_id, GuideFields::PREFIX . $name, $value );
			}

			$this->save_repeater(
				$post_id,
				GuideFields::PREFIX . 'steps',
				(array) ( $fields['steps'] ?? array() ),
				array(
					'at'     => GuideFields::PREFIX . 'step_at',
					'title'  => GuideFields::PREFIX . 'step_title',
					'target' => GuideFields::PREFIX . 'step_target',
					'text'   => GuideFields::PREFIX . 'step_text',
				)
			);

			if ( ! empty( $guide['method'] ) ) {
				wp_set_object_terms( $post_id, (string) $guide['method'], Tax::BREW_METHOD );
			}

			$this->ids[ 'guide:' . $guide['slug'] ] = $post_id;
		}

		\WP_CLI::log( sprintf( 'Гайдів: %d', count( (array) ( $this->data['guides'] ?? array() ) ) ) );
	}

	/**
	 * Створює або оновлює запис за slug.
	 *
	 * @param array<string, mixed> $row       Опис запису.
	 * @param string               $post_type Тип запису.
	 * @return int
	 */
	private function ensure_post( array $row, string $post_type ): int {
		$slug     = (string) ( $row['slug'] ?? '' );
		$existing = get_page_by_path( $slug, OBJECT, $post_type );

		$args = array(
			'post_type'    => $post_type,
			'post_status'  => 'publish',
			'post_title'   => (string) ( $row['title'] ?? $slug ),
			'post_name'    => $slug,
			'post_excerpt' => (string) ( $row['excerpt'] ?? '' ),
			'post_content' => (string) ( $row['content'] ?? '' ),
		);

		if ( $existing instanceof \WP_Post ) {
			$args['ID'] = $existing->ID;
		}

		$post_id = wp_insert_post( $args, true );

		if ( is_wp_error( $post_id ) ) {
			\WP_CLI::warning( sprintf( '«%s»: %s', $slug, $post_id->get_error_message() ) );
			return 0;
		}

		// Позначка, за якою --fresh упізнає своє і не чіпає чуже.
		update_post_meta( (int) $post_id, '_brix_demo', 1 );

		return (int) $post_id;
	}

	/**
	 * Зберігає повторювач у форматі, який розуміє SCF.
	 *
	 * SCF тримає повторювач як лічильник рядків у мета-полі й окремі
	 * записи `поле_0_підполе`. Формат недокументований, але стабільний
	 * від часів ACF 5 — інакше довелося б піднімати весь плагін
	 * у CLI-контексті заради `update_field()`.
	 *
	 * @param int                      $post_id   Запис.
	 * @param string                   $field     Мета-ключ повторювача.
	 * @param array<int, array<mixed>> $rows      Рядки.
	 * @param array<string, string>    $sub_keys  Мапа «ключ у JSON → мета-ключ підполя».
	 * @return void
	 */
	private function save_repeater( int $post_id, string $field, array $rows, array $sub_keys ): void {
		// Прибираємо попередні рядки, інакше при скороченні списку
		// у базі лишаться хвости від довшої версії.
		$previous = (int) get_post_meta( $post_id, $field, true );
		$total    = max( $previous, count( $rows ) );

		for ( $i = 0; $i < $total; $i++ ) {
			foreach ( $sub_keys as $meta_key ) {
				delete_post_meta( $post_id, $field . '_' . $i . '_' . $meta_key );
				delete_post_meta( $post_id, '_' . $field . '_' . $i . '_' . $meta_key );
			}
		}

		update_post_meta( $post_id, $field, count( $rows ) );
		update_post_meta( $post_id, '_' . $field, 'field_' . $field );

		foreach ( array_values( $rows ) as $index => $row ) {
			foreach ( $sub_keys as $json_key => $meta_key ) {
				$value = $row[ $json_key ] ?? '';

				update_post_meta( $post_id, $field . '_' . $index . '_' . $meta_key, $value );
				update_post_meta( $post_id, '_' . $field . '_' . $index . '_' . $meta_key, 'field_' . $meta_key );
			}
		}
	}

	/**
	 * Створює товари.
	 *
	 * @return void
	 */
	private function import_products(): void {
		$grinds = (array) ( $this->data['grinds'] ?? array() );
		$count  = 0;

		foreach ( (array) ( $this->data['products'] ?? array() ) as $row ) {
			$product = $this->build_product( $row, $grinds );

			if ( $product ) {
				++$count;
			}
		}

		\WP_CLI::log( sprintf( 'Товарів: %d', $count ) );
	}

	/**
	 * Створює один товар.
	 *
	 * @param array<string, mixed> $row    Опис товару.
	 * @param array<int, string>   $grinds Варіанти помелу.
	 * @return bool
	 */
	private function build_product( array $row, array $grinds ): bool {
		$slug     = (string) ( $row['slug'] ?? '' );
		$existing = get_page_by_path( $slug, OBJECT, 'product' );
		$is_var   = 'variable' === ( $row['type'] ?? 'simple' );

		$product = $is_var ? new \WC_Product_Variable() : new \WC_Product_Simple();

		if ( $existing instanceof \WP_Post ) {
			$product->set_id( $existing->ID );
		}

		$product->set_name( (string) ( $row['title'] ?? $slug ) );
		$product->set_slug( $slug );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_short_description( (string) ( $row['excerpt'] ?? '' ) );
		$product->set_description( (string) ( $row['fields']['cup_notes'] ?? $row['excerpt'] ?? '' ) );

		if ( ! $is_var ) {
			$product->set_regular_price( (string) ( $row['regular_price'] ?? $row['price'] ) );

			if ( isset( $row['regular_price'] ) ) {
				$product->set_sale_price( (string) $row['price'] );
			}

			$product->set_manage_stock( true );
			$product->set_stock_quantity( (int) ( $row['stock'] ?? 0 ) );
		}

		if ( $is_var ) {
			$product->set_attributes( $this->attributes( $row, $grinds ) );
		}

		$product_id = $product->save();

		if ( ! $product_id ) {
			\WP_CLI::warning( sprintf( 'Товар «%s» не створився', $slug ) );
			return false;
		}

		update_post_meta( $product_id, '_brix_demo', 1 );

		$this->assign_terms( $product_id, $row );

		if ( $is_var ) {
			// Значення атрибутів — це терміни, і вони мають бути
			// прив'язані до товару, інакше Woo не знайде варіацію.
			wp_set_object_terms(
				$product_id,
				array_map(
					fn( $weight ): string => $this->term_slug( 'weight', (string) $weight['weight'] ),
					(array) ( $row['weights'] ?? array() )
				),
				wc_attribute_taxonomy_name( 'weight' )
			);

			wp_set_object_terms(
				$product_id,
				array_map( fn( $grind ): string => $this->term_slug( 'grind', (string) $grind ), $grinds ),
				wc_attribute_taxonomy_name( 'grind' )
			);
		}
		$this->save_lot_fields( $product_id, (array) ( $row['fields'] ?? array() ) );

		if ( $is_var ) {
			$this->build_variations( $product_id, $row, $grinds );
		}

		return true;
	}

	/**
	 * Атрибути варіативного товару: вага × помел.
	 *
	 * @param array<string, mixed> $row    Опис товару.
	 * @param array<int, string>   $grinds Варіанти помелу.
	 * @return array<int, \WC_Product_Attribute>
	 */
	private function attributes( array $row, array $grinds ): array {
		$weights = wp_list_pluck( (array) ( $row['weights'] ?? array() ), 'weight' );

		return array(
			$this->taxonomy_attribute( 'weight', $weights, 0 ),
			$this->taxonomy_attribute( 'grind', $grinds, 1 ),
		);
	}

	/**
	 * Атрибут товару на основі глобальної таксономії.
	 *
	 * @param string             $slug     Slug атрибута без pa_.
	 * @param array<int, string> $values   Значення.
	 * @param int                $position Порядок на сторінці товару.
	 * @return \WC_Product_Attribute
	 */
	private function taxonomy_attribute( string $slug, array $values, int $position ): \WC_Product_Attribute {
		$taxonomy = wc_attribute_taxonomy_name( $slug );
		$term_ids = array();

		foreach ( $values as $value ) {
			$term = get_term_by( 'name', (string) $value, $taxonomy );

			if ( $term instanceof \WP_Term ) {
				$term_ids[] = (int) $term->term_id;
			}
		}

		$attribute = new \WC_Product_Attribute();
		$attribute->set_id( wc_attribute_taxonomy_id_by_name( $slug ) );
		$attribute->set_name( $taxonomy );
		$attribute->set_options( $term_ids );
		$attribute->set_position( $position );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		return $attribute;
	}

	/**
	 * Створює варіації.
	 *
	 * Ціна залежить тільки від ваги: помел на вартість не впливає,
	 * але має бути варіацією, бо змінює те, що кладуть у пачку.
	 *
	 * @param int                  $product_id Товар.
	 * @param array<string, mixed> $row        Опис товару.
	 * @param array<int, string>   $grinds     Варіанти помелу.
	 * @return void
	 */
	private function build_variations( int $product_id, array $row, array $grinds ): void {
		$base  = (float) ( $row['price'] ?? 0 );
		$stock = (int) ( $row['stock'] ?? 0 );

		// Прибираємо старі варіації: інакше при зміні набору ваг
		// у товарі лишаються осиротілі комбінації.
		$product = wc_get_product( $product_id );

		if ( $product instanceof \WC_Product_Variable ) {
			foreach ( $product->get_children() as $child_id ) {
				wp_delete_post( (int) $child_id, true );
			}
		}

		foreach ( (array) ( $row['weights'] ?? array() ) as $weight ) {
			foreach ( $grinds as $grind ) {
				$variation = new \WC_Product_Variation();
				$variation->set_parent_id( $product_id );
				$variation->set_status( 'publish' );
				$variation->set_attributes(
					array(
						wc_attribute_taxonomy_name( 'weight' ) => $this->term_slug( 'weight', (string) $weight['weight'] ),
						wc_attribute_taxonomy_name( 'grind' )  => $this->term_slug( 'grind', (string) $grind ),
					)
				);
				$variation->set_regular_price( (string) round( $base * (float) $weight['factor'] ) );
				$variation->set_manage_stock( true );
				$variation->set_stock_quantity( $stock );
				$variation->save();
			}
		}

		\WC_Product_Variable::sync( $product_id );
	}

	/**
	 * Slug значення атрибута.
	 *
	 * @param string $slug  Атрибут без pa_.
	 * @param string $value Назва значення.
	 * @return string
	 */
	private function term_slug( string $slug, string $value ): string {
		$term = get_term_by( 'name', $value, wc_attribute_taxonomy_name( $slug ) );

		return $term instanceof \WP_Term ? $term->slug : Slug::latin( $value );
	}

	/**
	 * Проставляє терміни товару.
	 *
	 * @param int                  $product_id Товар.
	 * @param array<string, mixed> $row        Опис.
	 * @return void
	 */
	private function assign_terms( int $product_id, array $row ): void {
		if ( ! empty( $row['categories'] ) ) {
			wp_set_object_terms( $product_id, (array) $row['categories'], 'product_cat' );
		}

		if ( ! empty( $row['country'] ) ) {
			wp_set_object_terms( $product_id, (string) $row['country'], Tax::COUNTRY );
		}

		if ( ! empty( $row['processing'] ) ) {
			wp_set_object_terms( $product_id, (string) $row['processing'], Tax::PROCESSING );
		}

		if ( ! empty( $row['notes'] ) ) {
			wp_set_object_terms( $product_id, (array) $row['notes'], Tax::NOTE );

			/*
			 * Таксономія не зберігає порядок — get_the_terms() віддає
			 * терміни за абеткою. А в дегустаційному описі порядок
			 * несе сенс: «Черешня · бергамот · молочний шоколад»,
			 * а не навпаки. Тому окремо кладемо ID у тому порядку,
			 * у якому вони записані в demo-content.json.
			 */
			$ordered = array();

			foreach ( (array) $row['notes'] as $note ) {
				$term = get_term_by( 'name', (string) $note, Tax::NOTE );

				if ( $term instanceof \WP_Term ) {
					$ordered[] = (int) $term->term_id;
				}
			}

			$key = LotMeta::key( 'notes' );
			update_post_meta( $product_id, $key, $ordered );
			update_post_meta( $product_id, '_' . $key, 'field_' . $key );
		}

		if ( ! empty( $row['brew_methods'] ) ) {
			wp_set_object_terms( $product_id, (array) $row['brew_methods'], Tax::BREW_METHOD );
		}
	}

	/**
	 * Зберігає поля паспорта лоту.
	 *
	 * @param int                  $product_id Товар.
	 * @param array<string, mixed> $fields     Поля.
	 * @return void
	 */
	private function save_lot_fields( int $product_id, array $fields ): void {
		foreach ( $fields as $name => $value ) {
			// Зв'язки в JSON записані slug'ами — тут перетворюємо на ID.
			if ( 'farm' === $name ) {
				$value = $this->ids[ 'farm:' . $value ] ?? '';
			}

			if ( 'brew_guide' === $name ) {
				$value = $this->ids[ 'guide:' . $value ] ?? '';
			}

			if ( 'roast_date' === $name ) {
				$value = (string) $value;
			}

			$meta_key = LotMeta::key( (string) $name );

			update_post_meta( $product_id, $meta_key, $value );
			// Прив'язка мета-поля до поля SCF, щоб редактор побачив значення.
			update_post_meta( $product_id, '_' . $meta_key, 'field_' . $meta_key );
		}

		// Дата обсмаження: минулий понеділок, щоб демо завжди було свіжим.
		if ( ! isset( $fields['roast_date'] ) && ! empty( $fields['code'] ) ) {
			$monday = new \DateTimeImmutable( 'last monday', wp_timezone() );
			$key    = LotMeta::key( 'roast_date' );

			update_post_meta( $product_id, $key, $monday->format( 'Ymd' ) );
			update_post_meta( $product_id, '_' . $key, 'field_' . $key );
		}
	}
}
