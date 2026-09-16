<?php
/**
 * Пояснення шкали Brix — темна секція.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_band = class_exists( \Brix\Core\Product\Lot::class ) ? \Brix\Core\Product\Lot::ripe_band() : array(
	'left'  => 42.86,
	'width' => 28.57,
);
?>

<section class="brix-section brix-section--dark brix-grain brix-explainer">
	<div class="brix-wrap brix-explainer__inner">
		<div class="brix-explainer__text">
			<p class="brix-label"><?php esc_html_e( 'Що ми взагалі міряємо', 'brix' ); ?></p>
			<h2><?php esc_html_e( 'Стиглість вимірюють у градусах Brix', 'brix' ); ?></h2>
			<p class="brix-lead">
				<?php esc_html_e( '°Bx показує вміст цукру в соку ягоди. Фермер перевіряє його рефрактометром просто на дереві: нижче 20 — ягода недостигла і кава вийде порожньою, вище 24 — переспіла. Ми купуємо тільки те, що зібрано в цьому вікні, і пишемо цифру на пачці.', 'brix' ); ?>
			</p>
			<p>
				<a class="brix-btn brix-btn--ghost" href="<?php echo esc_url( brix_page_url( 'about' ) ); ?>">
					<?php esc_html_e( 'Як ми обираємо лоти', 'brix' ); ?>
				</a>
			</p>
		</div>

		<div class="brix-explainer__scale">
			<div class="brix-scale">
				<div class="brix-scale__track">
					<span class="brix-scale__band" style="--brix-left:<?php echo esc_attr( (string) $brix_band['left'] ); ?>%;--brix-width:<?php echo esc_attr( (string) $brix_band['width'] ); ?>%"></span>

					<?php for ( $brix_tick = 14; $brix_tick <= 28; $brix_tick += 2 ) : ?>
						<span class="brix-scale__tick" style="--brix-left:<?php echo esc_attr( (string) round( ( $brix_tick - 14 ) / 14 * 100, 2 ) ); ?>%">
							<span><?php echo esc_html( (string) $brix_tick ); ?></span>
						</span>
					<?php endfor; ?>

					<span class="brix-scale__mark" style="--brix-left:57.14%">
						<b><?php esc_html_e( '22°', 'brix' ); ?></b><i></i>
					</span>
				</div>
			</div>

			<p class="brix-small brix-muted"><?php esc_html_e( 'Зелена смуга — вікно стиглості, 20–24 °Bx. Позначка — середина, від якої бренд і взяв назву.', 'brix' ); ?></p>
		</div>
	</div>
</section>
