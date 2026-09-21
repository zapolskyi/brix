<?php
/**
 * Результати пошуку.
 *
 * Товари показуються сіткою карток, решта — списком: у лота є пачка
 * й ціна, у гайда чи виробника немає ні того, ні того, і однаковий
 * вигляд для них був би гіршим за різний.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

get_header();

$brix_term   = get_search_query();
$brix_groups = array();

foreach ( brix_search_types() as $brix_type => $brix_label ) {
	$brix_found = brix_search_posts( $brix_term, $brix_type, 24 );

	if ( $brix_found ) {
		$brix_groups[ $brix_type ] = array(
			'label' => $brix_label,
			'posts' => $brix_found,
		);
	}
}

$brix_total = array_sum( array_map( static fn( array $group ): int => count( $group['posts'] ), $brix_groups ) );
?>

<div class="brix-section brix-section--tight">
	<div class="brix-wrap">
		<header class="brix-section__head">
			<h1>
				<?php
				printf(
					/* translators: %s — пошуковий запит. */
					esc_html__( 'Пошук: %s', 'brix' ),
					esc_html( $brix_term )
				);
				?>
			</h1>

			<p class="brix-muted"><?php echo esc_html( brix_catalog_count_text( $brix_total ) ); ?></p>
		</header>

		<?php if ( ! $brix_groups ) : ?>
			<div class="brix-empty">
				<h2><?php esc_html_e( 'Нічого не знайшлось', 'brix' ); ?></h2>
				<p class="brix-muted">
					<?php esc_html_e( 'Спробуйте назву країни, ферми або ноту смаку — наприклад «Ефіопія», «бергамот» або «Natural».', 'brix' ); ?>
				</p>
				<p>
					<a class="brix-btn brix-btn--outline" href="<?php echo esc_url( brix_has_woocommerce() ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>">
						<?php esc_html_e( 'Перейти в магазин', 'brix' ); ?>
					</a>
				</p>
			</div>
		<?php endif; ?>

		<?php foreach ( $brix_groups as $brix_type => $brix_group ) : ?>
			<section class="brix-search-results">
				<h2 class="brix-search-results__title"><?php echo esc_html( $brix_group['label'] ); ?></h2>

				<?php if ( 'product' === $brix_type ) : ?>
					<div class="brix-card-grid">
						<?php
						foreach ( $brix_group['posts'] as $brix_post ) {
							$GLOBALS['post'] = $brix_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
							setup_postdata( $brix_post );
							wc_get_template_part( 'content', 'product' );
						}

						wp_reset_postdata();
						?>
					</div>
				<?php else : ?>
					<ul class="brix-search-results__list">
						<?php foreach ( $brix_group['posts'] as $brix_post ) : ?>
							<li>
								<a href="<?php echo esc_url( (string) get_permalink( $brix_post ) ); ?>">
									<?php echo esc_html( get_the_title( $brix_post ) ); ?>
								</a>

								<?php if ( $brix_post->post_excerpt ) : ?>
									<p class="brix-small brix-muted"><?php echo esc_html( wp_strip_all_tags( $brix_post->post_excerpt ) ); ?></p>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	</div>
</div>

<?php
get_footer();
