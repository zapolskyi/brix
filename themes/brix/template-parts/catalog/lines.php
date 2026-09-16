<?php
/**
 * Перемикач лінійок над каталогом: Усі · Core · Origin · Lab · …
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_lines = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'orderby'    => 'term_id',
	)
);

if ( is_wp_error( $brix_lines ) || ! $brix_lines ) {
	return;
}

$brix_current = is_tax( 'product_cat' ) ? get_queried_object_id() : 0;
?>

<nav class="brix-lines" aria-label="<?php esc_attr_e( 'Лінійки товарів', 'brix' ); ?>">
	<a class="brix-chip<?php echo $brix_current ? '' : ' is-active'; ?>"
		href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"
		<?php echo $brix_current ? '' : 'aria-current="page"'; ?>>
		<?php esc_html_e( 'Усі', 'brix' ); ?>
	</a>

	<?php foreach ( $brix_lines as $brix_line ) : ?>
		<?php
		// Службова категорія WooCommerce для товарів без лінійки.
		if ( 'uncategorized' === $brix_line->slug ) {
			continue;
		}

		$brix_on = $brix_current === $brix_line->term_id;
		?>
		<a class="brix-chip<?php echo $brix_on ? ' is-active' : ''; ?>"
			href="<?php echo esc_url( (string) get_term_link( $brix_line ) ); ?>"
			<?php echo $brix_on ? 'aria-current="page"' : ''; ?>>
			<?php echo esc_html( html_entity_decode( $brix_line->name, ENT_QUOTES, 'UTF-8' ) ); ?>
		</a>
	<?php endforeach; ?>
</nav>
