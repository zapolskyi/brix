<?php
/**
 * Плитка гайда: назва й пропорція.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_guide = $args['guide'] ?? null;

if ( ! $brix_guide instanceof WP_Post ) {
	return;
}

$brix_dose  = (int) get_post_meta( $brix_guide->ID, 'brix_guide_dose', true );
$brix_water = (int) get_post_meta( $brix_guide->ID, 'brix_guide_water', true );
$brix_temp  = (int) get_post_meta( $brix_guide->ID, 'brix_guide_temperature', true );

$brix_bits = array();

if ( $brix_dose ) {
	$brix_bits[] = $brix_dose . __( ' г', 'brix' );
}

if ( $brix_water ) {
	$brix_bits[] = $brix_water . __( ' мл', 'brix' );
}

if ( $brix_temp ) {
	$brix_bits[] = $brix_temp . ' °C';
}
?>

<a class="brix-tile brix-tile--guide" href="<?php echo esc_url( (string) get_permalink( $brix_guide ) ); ?>">
	<span class="brix-tile__title"><?php echo esc_html( get_the_title( $brix_guide ) ); ?></span>
	<span class="brix-tile__meta brix-mono"><?php echo esc_html( implode( ' · ', $brix_bits ) ); ?></span>
</a>
