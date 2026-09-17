<?php
/**
 * Обрані фільтри над сіткою — кожен знімається кліком.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

if ( ! brix_has_active_filters() ) {
	return;
}
?>

<div class="brix-active-filters">
	<?php
	foreach ( brix_catalog_filters() as $brix_key => $brix_filter ) :
		foreach ( brix_active_filter( $brix_key ) as $brix_value ) :
			if ( ! empty( $brix_filter['taxonomy'] ) ) {
				$brix_term  = get_term_by( 'slug', $brix_value, $brix_filter['taxonomy'] );
				$brix_label = $brix_term instanceof WP_Term ? $brix_term->name : $brix_value;
			} else {
				$brix_label = (string) ( $brix_filter['options'][ $brix_value ] ?? $brix_value );
			}
			?>
			<a class="brix-chip brix-chip--sm is-active"
				href="<?php echo esc_url( brix_filter_toggle_url( $brix_key, $brix_value ) ); ?>"
				data-brix-filter="<?php echo esc_attr( (string) $brix_key ); ?>"
				data-brix-value="<?php echo esc_attr( (string) $brix_value ); ?>"
				rel="nofollow">
				<?php echo esc_html( $brix_label ); ?>
				<?php echo brix_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="brix-visually-hidden"><?php esc_html_e( 'прибрати фільтр', 'brix' ); ?></span>
			</a>
		<?php endforeach; ?>
	<?php endforeach; ?>
</div>
