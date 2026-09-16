<?php
/**
 * Підвал сайту: підписка на лист, колонки посилань, нижній рядок.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;
?>
</main>

<footer class="brix-footer brix-grain">
	<div class="brix-footer__top">
		<div class="brix-newsletter">
			<h2 class="brix-newsletter__title"><?php esc_html_e( 'Лист у понеділок', 'brix' ); ?></h2>
			<p class="brix-small brix-muted">
				<?php esc_html_e( 'Що обсмажили цього тижня, які лоти закінчуються і один рецепт. Без спаму — 4 листи на місяць.', 'brix' ); ?>
			</p>

			<?php // Форма поки без обробника: розсилку підключаємо на фазі 6. ?>
			<form class="brix-newsletter__form" method="post" action="#">
				<label class="brix-visually-hidden" for="brix-newsletter-email">
					<?php esc_html_e( 'Ваш email', 'brix' ); ?>
				</label>
				<input
					class="brix-newsletter__input"
					id="brix-newsletter-email"
					type="email"
					name="brix_email"
					placeholder="ваш@email.com"
					autocomplete="email"
					required
				>
				<button class="brix-btn" type="submit"><?php esc_html_e( 'Підписатись', 'brix' ); ?></button>
			</form>
		</div>

		<div class="brix-footer__cols">
			<?php
			brix_footer_column( 'shop', __( 'Магазин', 'brix' ) );
			brix_footer_column( 'brand', __( 'Бренд', 'brix' ) );
			brix_footer_column( 'help', __( 'Допомога', 'brix' ) );
			?>
		</div>
	</div>

	<div class="brix-footer__bottom">
		<span>
			<?php
			printf(
				/* translators: %s — поточний рік. */
				esc_html__( '© %s BRIX 22° · Обсмажувальня, Київ', 'brix' ),
				esc_html( wp_date( 'Y' ) )
			);
			?>
		</span>

		<?php if ( has_nav_menu( 'legal' ) ) : ?>
			<span class="brix-footer__legal">
				<?php brix_menu_links( 'legal' ); ?>
			</span>
		<?php endif; ?>

		<span class="brix-footer__pay">
			<span>Apple&nbsp;Pay</span><span>Google&nbsp;Pay</span><span>Visa</span><span>Mastercard</span><span>monobank</span>
		</span>
	</div>

	<div class="brix-footer__mark" aria-hidden="true">BRIX 22°</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
