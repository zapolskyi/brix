<?php
/**
 * Врізка B2B — темна секція внизу головної.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;
?>

<section class="brix-section brix-section--dark brix-grain">
	<div class="brix-wrap brix-teaser">
		<div class="brix-teaser__text">
			<p class="brix-label"><?php esc_html_e( 'Для кав’ярень', 'brix' ); ?></p>
			<h2><?php esc_html_e( 'Кава для кав’ярень, яку гості впізнають', 'brix' ); ?></h2>
			<p class="brix-lead">
				<?php esc_html_e( 'Оптові ціни від 5 кг, стабільні поставки під графік обсмаження, рахунок і закривні документи. Профіль під вашу машину підберемо разом.', 'brix' ); ?>
			</p>
			<p>
				<a class="brix-btn brix-btn--light" href="<?php echo esc_url( brix_page_url( 'wholesale' ) ); ?>">
					<?php esc_html_e( 'Залишити заявку', 'brix' ); ?>
					<?php echo brix_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			</p>
		</div>

		<div class="brix-photo brix-photo--dark brix-teaser__photo">
			<span class="brix-photo__caption"><?php esc_html_e( 'Фото · бар кав’ярні', 'brix' ); ?></span>
		</div>
	</div>
</section>
