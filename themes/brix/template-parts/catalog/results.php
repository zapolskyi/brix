<?php
/**
 * Результат каталогу: сітка товарів, пагінація або порожній стан.
 *
 * Винесено окремо від archive-product.php навмисно: рівно цю частину
 * перемальовує REST-ендпоінт фази 4. Поки розмітка одна на обидва
 * шляхи, AJAX-оновлення не може розійтися з тим, що віддає сервер
 * при звичайному переході за посиланням.
 *
 * Працює з глобальним запитом, тож ендпоінт підміняє його своїм.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

?>
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
