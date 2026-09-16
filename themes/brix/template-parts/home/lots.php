<?php
/**
 * Сітка лотів на головній.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_lots = brix_home_lots( 4 );

if ( ! $brix_lots ) {
	return;
}

$brix_count = (int) wp_count_posts( 'product' )->publish;
?>

<section class="brix-section">
	<div class="brix-wrap">
		<header class="brix-section__head">
			<div>
				<p class="brix-label"><?php esc_html_e( 'Зараз в обсмаженні', 'brix' ); ?></p>
				<h2>
					<?php
					printf(
						/* translators: %s — кількість лотів. */
						esc_html( brix_plural( $brix_count, __( '%s лот,', 'brix' ), __( '%s лоти,', 'brix' ), __( '%s лотів,', 'brix' ) ) ),
						esc_html( (string) $brix_count )
					);
					?>
					<br><?php esc_html_e( 'усі з паспортом', 'brix' ); ?>
				</h2>
			</div>

			<a class="brix-section__more" href="<?php echo esc_url( (string) wc_get_page_permalink( 'shop' ) ); ?>">
				<?php esc_html_e( 'Весь каталог', 'brix' ); ?>
				<?php echo brix_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</a>
		</header>

		<div class="brix-card-grid">
			<?php brix_render_product_cards( $brix_lots ); ?>
		</div>
	</div>
</section>
