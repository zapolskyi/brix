<?php
/**
 * Тип запису «Гайди заварювання».
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\PostTypes;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Рецепт заварювання: кроки, пропорції, таймер.
 */
final class BrewGuide implements Module {

	public const POST_TYPE = 'brix_guide';

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
					'name'               => __( 'Гайди заварювання', 'brix-core' ),
					'singular_name'      => __( 'Гайд', 'brix-core' ),
					'add_new'            => __( 'Додати гайд', 'brix-core' ),
					'add_new_item'       => __( 'Новий гайд', 'brix-core' ),
					'edit_item'          => __( 'Редагувати гайд', 'brix-core' ),
					'new_item'           => __( 'Новий гайд', 'brix-core' ),
					'view_item'          => __( 'Переглянути гайд', 'brix-core' ),
					'search_items'       => __( 'Шукати гайди', 'brix-core' ),
					'not_found'          => __( 'Гайдів немає', 'brix-core' ),
					'not_found_in_trash' => __( 'У кошику порожньо', 'brix-core' ),
					'all_items'          => __( 'Усі гайди', 'brix-core' ),
					'menu_name'          => __( 'Гайди', 'brix-core' ),
				),
				'public'        => true,
				'has_archive'   => true,
				'menu_icon'     => 'dashicons-coffee',
				'menu_position' => 27,
				'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
				'show_in_rest'  => true,
				'rewrite'       => array(
					'slug'       => 'guides',
					'with_front' => false,
				),
			)
		);
	}
}
