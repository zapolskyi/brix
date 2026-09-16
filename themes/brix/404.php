<?php
/**
 * Сторінка 404.
 *
 * Помилка написана голосом бренду: що сталося і що робити далі,
 * а не «Сторінку не знайдено».
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="brix-section">
	<div class="brix-wrap brix-404">
		<p class="brix-label">404</p>
		<h1><?php esc_html_e( 'Такої сторінки немає', 'brix' ); ?></h1>
		<p class="brix-lead brix-muted">
			<?php esc_html_e( 'Можливо, лот уже розкупили або посилання застаріло. Загляньте в магазин — там завжди є свіже.', 'brix' ); ?>
		</p>

		<p class="brix-404__actions">
			<?php if ( brix_has_woocommerce() ) : ?>
				<a class="brix-btn" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
					<?php esc_html_e( 'У магазин', 'brix' ); ?>
				</a>
			<?php endif; ?>
			<a class="brix-btn brix-btn--outline" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php esc_html_e( 'На головну', 'brix' ); ?>
			</a>
		</p>
	</div>
</div>

<?php
get_footer();
