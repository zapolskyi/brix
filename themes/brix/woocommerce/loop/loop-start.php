<?php
/**
 * Початок сітки товарів.
 *
 * Override шаблону WooCommerce: <div> замість <ul>. Картка товару в
 * темі — <article>, а не <li>, і список без жодного пункту
 * скрінрідер оголошував як «список, 0 елементів».
 *
 * @package BRIX
 * @version 3.3.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="products columns-<?php echo esc_attr( (string) wc_get_loop_prop( 'columns' ) ); ?>">
