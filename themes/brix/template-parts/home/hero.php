<?php
/**
 * Герой головної.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_lot_product = brix_featured_lot();
$brix_lot         = $brix_lot_product ? brix_lot( $brix_lot_product ) : null;
$brix_country     = $brix_lot_product ? get_the_terms( $brix_lot_product->get_id(), 'brix_country' ) : false;
$brix_processing  = $brix_lot ? $brix_lot->processing() : null;
?>

<section class="brix-hero">
	<div class="brix-hero__text">
		<?php if ( $brix_lot_product ) : ?>
			<p class="brix-label">
				<?php
				$brix_bits = array( __( 'Лот тижня', 'brix' ) );

				if ( is_array( $brix_country ) ) {
					$brix_bits[] = $brix_country[0]->name . ( $brix_lot->region ? ', ' . $brix_lot->region : '' );
				}

				if ( $brix_processing ) {
					$brix_bits[] = $brix_processing->name;
				}

				echo esc_html( implode( ' · ', $brix_bits ) );
				?>
			</p>
		<?php endif; ?>

		<h1 class="brix-hero__title brix-display">
			<?php esc_html_e( 'Зібрано', 'brix' ); ?><br>
			<?php esc_html_e( 'при', 'brix' ); ?> <span class="brix-hero__accent">22°Bx</span>
		</h1>

		<p class="brix-lead brix-hero__lead">
			<?php esc_html_e( 'Мікролоти від ферм, з якими працюємо напряму. Обсмажуємо щопонеділка і кладемо в кожну пачку паспорт: висота, обробка, цукристість ягоди при зборі та ціна, яку отримав фермер.', 'brix' ); ?>
		</p>

		<div class="brix-hero__actions">
			<a class="brix-btn brix-btn--xl" href="<?php echo esc_url( (string) wc_get_page_permalink( 'shop' ) ); ?>">
				<?php esc_html_e( 'Обрати каву', 'brix' ); ?>
				<?php echo brix_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</a>
			<a class="brix-btn brix-btn--xl brix-btn--outline" href="<?php echo esc_url( brix_page_url( 'quiz' ) ); ?>">
				<?php esc_html_e( 'Підібрати за 1 хвилину', 'brix' ); ?>
			</a>
		</div>

		<ul class="brix-hero__promises">
			<li><?php echo brix_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Обсмажуємо щопонеділка', 'brix' ); ?></li>
			<li><?php echo brix_icon( 'truck' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Відправка в день обсмаження', 'brix' ); ?></li>
			<li><?php echo brix_icon( 'drop' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Паспорт на кожен лот', 'brix' ); ?></li>
		</ul>
	</div>

	<?php if ( $brix_lot_product ) : ?>
		<div class="brix-hero__visual">
			<span class="brix-hero__plate 
			<?php
				$brix_hero_style = brix_pack_style( $brix_lot_product );
				echo esc_attr( 'brix-card__stage--' . ( '' !== $brix_hero_style ? $brix_hero_style : 'core' ) );
			?>
				"></span>

			<a class="brix-hero__pack" href="<?php echo esc_url( $brix_lot_product->get_permalink() ); ?>">
				<?php brix_the_pack( $brix_lot_product, '100%' ); ?>
				<span class="brix-visually-hidden"><?php echo esc_html( $brix_lot_product->get_name() ); ?></span>
			</a>

			<?php if ( $brix_lot && null !== $brix_lot->brix ) : ?>
				<div class="brix-hero__meter">
					<span class="brix-label"><?php esc_html_e( 'Рефрактометр, станція', 'brix' ); ?></span>
					<b class="brix-mono"><?php echo esc_html( $brix_lot->brix . ' °Bx' ); ?></b>
				</div>
			<?php endif; ?>

			<?php if ( $brix_lot && $brix_lot->roast_date ) : ?>
				<div class="brix-hero__stamp">
					<?php esc_html_e( 'Обсмажено', 'brix' ); ?><br>
					<?php echo esc_html( $brix_lot->roast_date->format( 'd.m' ) ); ?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</section>
