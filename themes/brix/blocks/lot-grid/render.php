<?php
/**
 * Рендер блока «Сітка лотів».
 *
 * @package BRIX
 *
 * @var array<string, mixed> $attributes Налаштування блока.
 */

defined( 'ABSPATH' ) || exit;

$brix_limit    = max( 1, (int) ( $attributes['limit'] ?? 4 ) );
$brix_category = (string) ( $attributes['category'] ?? '' );

$brix_args = array(
	'post_type'      => 'product',
	'post_status'    => 'publish',
	'posts_per_page' => $brix_limit,
	'fields'         => 'ids',
	'orderby'        => 'date',
	'order'          => 'DESC',
);

if ( '' !== $brix_category ) {
	$brix_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		array(
			'taxonomy' => 'product_cat',
			'field'    => 'slug',
			'terms'    => array( $brix_category ),
		),
	);
}

$brix_products = array_values( array_filter( array_map( 'wc_get_product', get_posts( $brix_args ) ) ) );

if ( ! $brix_products ) {
	return;
}
?>

<section <?php echo get_block_wrapper_attributes( array( 'class' => 'brix-section' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="brix-wrap">
		<header class="brix-section__head">
			<div>
				<?php if ( ! empty( $attributes['label'] ) ) : ?>
					<p class="brix-label"><?php echo esc_html( $attributes['label'] ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $attributes['heading'] ) ) : ?>
					<h2><?php echo esc_html( $attributes['heading'] ); ?></h2>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $attributes['showLink'] ) ) : ?>
				<a class="brix-section__more" href="<?php echo esc_url( (string) wc_get_page_permalink( 'shop' ) ); ?>">
					<?php esc_html_e( 'Весь каталог', 'brix' ); ?>
					<?php echo brix_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			<?php endif; ?>
		</header>

		<div class="brix-card-grid">
			<?php brix_render_product_cards( $brix_products ); ?>
		</div>
	</div>
</section>
