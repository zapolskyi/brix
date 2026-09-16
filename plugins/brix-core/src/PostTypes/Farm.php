<?php
/**
 * Тип запису «Виробники».
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\PostTypes;

use Brix\Core\Contracts\Module;
use Brix\Core\Product\LotMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Ферма або станція обробки, з якою працює обсмажувальня.
 *
 * Зв'язок «ферма ↔ лоти» зберігається в одному місці — у полі `farm`
 * на товарі. Зворотний бік не дублюється полем на фермі, а робиться
 * запитом: два поля, які треба тримати синхронними, рано чи пізно
 * розходяться.
 */
final class Farm implements Module {

	public const POST_TYPE = 'brix_farm';

	/**
	 * Підписує модуль на хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ), 5 );
	}

	/**
	 * Реєструє тип запису.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'        => array(
					'name'               => __( 'Виробники', 'brix-core' ),
					'singular_name'      => __( 'Виробник', 'brix-core' ),
					'add_new'            => __( 'Додати виробника', 'brix-core' ),
					'add_new_item'       => __( 'Новий виробник', 'brix-core' ),
					'edit_item'          => __( 'Редагувати виробника', 'brix-core' ),
					'new_item'           => __( 'Новий виробник', 'brix-core' ),
					'view_item'          => __( 'Переглянути виробника', 'brix-core' ),
					'search_items'       => __( 'Шукати виробників', 'brix-core' ),
					'not_found'          => __( 'Виробників немає', 'brix-core' ),
					'not_found_in_trash' => __( 'У кошику порожньо', 'brix-core' ),
					'all_items'          => __( 'Усі виробники', 'brix-core' ),
					'menu_name'          => __( 'Виробники', 'brix-core' ),
				),
				'public'        => true,
				'has_archive'   => true,
				'menu_icon'     => 'dashicons-palmtree',
				'menu_position' => 26,
				'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
				'show_in_rest'  => true,
				'rewrite'       => array(
					'slug'       => 'farms',
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Лоти цієї ферми.
	 *
	 * @param int  $farm_id      ID ферми.
	 * @param int  $limit        Скільки повернути, -1 — усі.
	 * @param bool $in_stock_only Тільки те, що є в наявності.
	 * @return array<int, \WP_Post>
	 */
	public static function lots( int $farm_id, int $limit = -1, bool $in_stock_only = false ): array {
		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => $limit,
			'post_status'    => 'publish',
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Зв'язок «ферма → лоти» інакше не вибрати; результат кешується об'єктним кешем.
			'meta_query'     => array(
				array(
					'key'   => LotMeta::key( 'farm' ),
					'value' => $farm_id,
				),
			),
		);

		if ( $in_stock_only ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => 'outofstock',
					'operator' => 'NOT IN',
				),
			);
		}

		return get_posts( $args );
	}
}
