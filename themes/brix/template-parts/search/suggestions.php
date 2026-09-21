<?php
/**
 * Підказки пошуку.
 *
 * Та сама частина малює і випадаючий список під полем, і порожній
 * стан. REST-ендпоінт віддає саме її, тож підказки не можуть
 * розійтися зі сторінкою результатів.
 *
 * @package BRIX
 *
 * @var array<string, mixed> $args Аргументи: term.
 */

defined( 'ABSPATH' ) || exit;

$brix_term = isset( $args['term'] ) ? trim( (string) $args['term'] ) : '';

if ( '' === $brix_term ) {
	return;
}

$brix_groups = array();

foreach ( brix_search_types() as $brix_type => $brix_label ) {
	$brix_found = brix_search_posts( $brix_term, $brix_type, 'product' === $brix_type ? 4 : 2 );

	if ( $brix_found ) {
		$brix_groups[ $brix_label ] = $brix_found;
	}
}

if ( ! $brix_groups ) :
	?>
	<p class="brix-suggest__empty brix-small brix-muted">
		<?php
		printf(
			/* translators: %s — пошуковий запит. */
			esc_html__( 'Нічого не знайшлось на «%s». Спробуйте назву країни або ноту смаку.', 'brix' ),
			esc_html( $brix_term )
		);
		?>
	</p>
	<?php
	return;
endif;

$brix_index = 0;
?>

<?php foreach ( $brix_groups as $brix_label => $brix_posts ) : ?>
	<p class="brix-suggest__label"><?php echo esc_html( $brix_label ); ?></p>

	<ul class="brix-suggest__list" role="none">
		<?php
		foreach ( $brix_posts as $brix_post ) :
			++$brix_index;
			$brix_product = 'product' === $brix_post->post_type && brix_has_woocommerce()
				? wc_get_product( $brix_post )
				: null;
			?>
			<li>
				<a class="brix-suggest__item" id="brix-suggest-<?php echo esc_attr( (string) $brix_index ); ?>"
					href="<?php echo esc_url( (string) get_permalink( $brix_post ) ); ?>" role="option" aria-selected="false">
					<span class="brix-suggest__name"><?php echo esc_html( get_the_title( $brix_post ) ); ?></span>

					<?php if ( $brix_product instanceof WC_Product ) : ?>
						<span class="brix-suggest__meta brix-mono">
							<?php echo wp_kses_post( brix_price_label( $brix_product ) ); ?>
						</span>
					<?php endif; ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endforeach; ?>

<a class="brix-suggest__all" href="<?php echo esc_url( brix_search_url( $brix_term ) ); ?>">
	<?php esc_html_e( 'Показати всі результати', 'brix' ); ?>
	<?php echo brix_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</a>
