<?php
/**
 * Каталог: сітка товарів із фільтрами.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

get_header();

$brix_total = (int) wc_get_loop_prop( 'total' );
?>

<div class="brix-section brix-section--tight">
	<div class="brix-wrap">
		<?php
		woocommerce_breadcrumb(
			array(
				'wrap_before' => '<nav class="brix-breadcrumb">',
				'wrap_after'  => '</nav>',
				'delimiter'   => ' / ',
			)
		);
		?>

		<header class="brix-catalog__head">
			<h1><?php woocommerce_page_title(); ?></h1>

			<?php if ( is_product_taxonomy() && term_description() ) : ?>
				<div class="brix-lead brix-muted"><?php echo wp_kses_post( term_description() ); ?></div>
			<?php endif; ?>
		</header>

		<?php get_template_part( 'template-parts/catalog/lines' ); ?>

		<div class="brix-catalog">
			<aside class="brix-catalog__aside">
				<?php
				/*
				 * На телефоні сайдбар стає шторкою. Деталі — в details,
				 * бо цей елемент відкривається без JavaScript і вже вміє
				 * все, що треба: клавіатуру, фокус і aria-expanded.
				 */
				?>
				<?php
				/*
				 * open за замовчуванням: закритий <details> ховає вміст
				 * силами браузера, і display:flex на дитині цього не
				 * перебиває. На десктопі підсумок узагалі схований,
				 * на телефоні ним можна згорнути фільтри.
				 * Справжня шторка з підрахунком збігів — фаза 4.
				 */
				?>
				<details class="brix-filters__drawer" open>
					<summary class="brix-btn brix-btn--outline brix-btn--full">
						<?php echo brix_icon( 'filter' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php esc_html_e( 'Фільтри', 'brix' ); ?>
					</summary>
					<?php get_template_part( 'template-parts/catalog/filters' ); ?>
				</details>
			</aside>

			<div class="brix-catalog__main">
				<div class="brix-catalog__bar">
					<p class="brix-catalog__count" aria-live="polite">
						<?php
						printf(
							/* translators: %s — кількість товарів. */
							esc_html( _n( '%s товар', '%s товарів', $brix_total, 'brix' ) ),
							esc_html( number_format_i18n( $brix_total ) )
						);
						?>
					</p>

					<?php get_template_part( 'template-parts/catalog/active-filters' ); ?>

					<?php woocommerce_catalog_ordering(); ?>
				</div>

				<?php if ( woocommerce_product_loop() ) : ?>
					<div class="brix-card-grid">
						<?php
						woocommerce_product_loop_start( false );

						while ( have_posts() ) {
							the_post();
							wc_get_template_part( 'content', 'product' );
						}

						woocommerce_product_loop_end( false );
						?>
					</div>

					<?php woocommerce_pagination(); ?>
				<?php else : ?>
					<div class="brix-empty">
						<h2><?php esc_html_e( 'Під ці фільтри нічого немає', 'brix' ); ?></h2>
						<p class="brix-muted">
							<?php esc_html_e( 'Спробуйте прибрати частину умов — лотів у нас небагато, і вони швидко закінчуються.', 'brix' ); ?>
						</p>
						<p>
							<a class="brix-btn brix-btn--outline" href="<?php echo esc_url( brix_catalog_url() ); ?>">
								<?php esc_html_e( 'Скинути фільтри', 'brix' ); ?>
							</a>
						</p>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<?php
get_footer();
