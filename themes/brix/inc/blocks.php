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
 * Скрипт редактора реєструється вручну з явними залежностями, а не
 * через "editorScript": "file:./editor.js". Останнє чекає на файл
 * *.asset.php з переліком залежностей, який генерує @wordpress/scripts —
 * а в темі бандлера немає навмисно. Один спільний скрипт на чотири
 * блоки й один набір залежностей простіший за чотири збірки.
 *
 * @return void
 */
function brix_register_blocks(): void {
	wp_register_script(
		'brix-blocks-editor',
		brix_asset_uri( 'assets/js/blocks-editor.js' ),
		array(
			'wp-blocks',
			'wp-element',
			'wp-block-editor',
			'wp-components',
			'wp-server-side-render',
			'wp-i18n',
		),
		brix_asset_version( 'assets/js/blocks-editor.js' ),
		true
	);

	// Лінійки для випадного списку в блоці «Сітка лотів».
	wp_localize_script( 'brix-blocks-editor', 'brixBlockData', array( 'categories' => brix_block_category_options() ) );

	foreach ( array( 'hero', 'marquee', 'lot-grid', 'feature', 'farm-teaser', 'brew-guide' ) as $block ) {
		$path = BRIX_DIR . '/blocks/' . $block;

		if ( is_readable( $path . '/block.json' ) ) {
			register_block_type( $path );
		}
	}
}
add_action( 'init', 'brix_register_blocks' );

/**
 * Лінійки товарів для селекта в редакторі.
 *
 * @return array<int, array<string, string>>
 */
function brix_block_category_options(): array {
	$options = array(
		array(
			'label' => __( 'Усі лінійки', 'brix' ),
			'value' => '',
		),
	);

	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		)
	);

	if ( is_wp_error( $terms ) ) {
		return $options;
	}

	foreach ( $terms as $term ) {
		if ( 'uncategorized' === $term->slug ) {
			continue;
		}

		$options[] = array(
			'label' => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
			'value' => $term->slug,
		);
	}

	return $options;
}

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
