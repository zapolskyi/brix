<?php
/**
 * Власні блоки Gutenberg.
 *
 * Блоки динамічні: розмітку малює PHP при кожному рендері, у базі
 * лежать тільки налаштування. Тому сітка лотів на головній ніколи
 * не протухає — вона робить запит, а не зберігає назви.
 *
 * ACF Blocks свідомо не використані: з block.json фронтенд не залежить
 * від SCF узагалі. Рішення 11 у docs/DECISIONS.md.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Реєструє блоки з тек у blocks/.
 *
 * @return void
 */
function brix_register_blocks(): void {
	foreach ( array( 'hero', 'lot-grid', 'farm-teaser', 'brew-guide' ) as $block ) {
		$path = BRIX_DIR . '/blocks/' . $block;

		if ( is_readable( $path . '/block.json' ) ) {
			register_block_type( $path );
		}
	}
}
add_action( 'init', 'brix_register_blocks' );

/**
 * Категорія блоків теми.
 *
 * @param array<int, array<string, mixed>> $categories Категорії.
 * @return array<int, array<string, mixed>>
 */
function brix_block_category( array $categories ): array {
	array_unshift(
		$categories,
		array(
			'slug'  => 'brix',
			'title' => __( 'BRIX 22°', 'brix' ),
			'icon'  => null,
		)
	);

	return $categories;
}
add_filter( 'block_categories_all', 'brix_block_category' );
