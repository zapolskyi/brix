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
		<?php
		$brix_subscribe = class_exists( '\Brix\Core\Newsletter\Subscribers' );
		$brix_notice    = $brix_subscribe ? \Brix\Core\Newsletter\Subscribers::notice() : null;
		?>
		<div class="brix-newsletter" id="brix-newsletter">
			<h2 class="brix-newsletter__title"><?php esc_html_e( 'Лист у понеділок', 'brix' ); ?></h2>
			<p class="brix-small brix-muted">
				<?php esc_html_e( 'Що обсмажили цього тижня, які лоти закінчуються і один рецепт. Без спаму — 4 листи на місяць.', 'brix' ); ?>
			</p>

			<?php if ( $brix_notice ) : ?>
				<?php
				/*
				 * Результат підписки стоїть на місці форми: після
				 * відправки сторінка повертається сюди ж, до якоря
				 * #brix-newsletter, і людина одразу бачить, що сталося
				 * і що робити далі.
				 */
				?>
				<p class="brix-newsletter__notice<?php echo $brix_notice['ok'] ? '' : ' is-error'; ?>" role="status" tabindex="-1">
					<?php echo esc_html( $brix_notice['text'] ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $brix_subscribe && ( ! $brix_notice || ! $brix_notice['ok'] ) ) : ?>
				<?php
				/*
				 * Форма відправляється на ту саму сторінку: мова запиту
				 * лишається мовою покупця, і лист-підтвердження приходить
				 * тією ж мовою. Приманка brix_newsletter_site схована від
				 * людей; бот, що її заповнив, отримає «лист надіслано» і
				 * нічого більше.
				 */
				?>
				<form class="brix-newsletter__form" method="post" action="<?php echo esc_url( remove_query_arg( 'newsletter' ) . '#brix-newsletter' ); ?>">
					<label class="brix-visually-hidden" for="brix-newsletter-email">
						<?php esc_html_e( 'Ваш email', 'brix' ); ?>
					</label>
					<input
						class="brix-newsletter__input"
						id="brix-newsletter-email"
						type="email"
						name="brix_newsletter_email"
						placeholder="<?php esc_attr_e( 'ваш@email.com', 'brix' ); ?>"
						autocomplete="email"
						required
					>
					<div class="brix-visually-hidden" aria-hidden="true">
						<label for="brix-newsletter-site">Website</label>
						<input id="brix-newsletter-site" type="text" name="brix_newsletter_site" tabindex="-1" autocomplete="off">
					</div>
					<button class="brix-btn" type="submit"><?php esc_html_e( 'Підписатись', 'brix' ); ?></button>
				</form>
				<p class="brix-newsletter__fine">
					<?php esc_html_e( 'Надішлемо лист для підтвердження. Відписатися можна з будь-якого листа.', 'brix' ); ?>
				</p>
			<?php endif; ?>
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
