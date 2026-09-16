<?php
/**
 * Сторінка виробника.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$brix_id       = get_the_ID();
	$brix_headline = (string) get_post_meta( $brix_id, 'brix_farm_headline', true );
	$brix_house    = (int) get_post_meta( $brix_id, 'brix_farm_households', true );
	$brix_since    = (int) get_post_meta( $brix_id, 'brix_farm_since_year', true );
	$brix_fob      = (float) get_post_meta( $brix_id, 'brix_farm_fob_price', true );
	$brix_variety  = (string) get_post_meta( $brix_id, 'brix_farm_variety', true );
	$brix_story    = (string) get_post_meta( $brix_id, 'brix_farm_story', true );
	$brix_quote    = (string) get_post_meta( $brix_id, 'brix_farm_quote', true );
	$brix_author   = (string) get_post_meta( $brix_id, 'brix_farm_quote_author', true );
	$brix_lat      = (string) get_post_meta( $brix_id, 'brix_farm_lat', true );
	$brix_lng      = (string) get_post_meta( $brix_id, 'brix_farm_lng', true );
	$brix_region   = (string) get_post_meta( $brix_id, 'brix_farm_region_note', true );
	$brix_country  = get_the_terms( $brix_id, 'brix_country' );
	$brix_calendar = brix_repeater( $brix_id, 'brix_farm_year_calendar', array( 'brix_farm_calendar_period', 'brix_farm_calendar_title', 'brix_farm_calendar_note' ) );
	$brix_lots     = class_exists( \Brix\Core\PostTypes\Farm::class ) ? \Brix\Core\PostTypes\Farm::lots( $brix_id ) : array();
	?>

	<article <?php post_class( 'brix-farm' ); ?>>
		<section class="brix-section brix-section--tight">
			<div class="brix-wrap">
				<nav class="brix-breadcrumb">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Головна', 'brix' ); ?></a> /
					<a href="<?php echo esc_url( (string) get_post_type_archive_link( 'brix_farm' ) ); ?>"><?php esc_html_e( 'Виробники', 'brix' ); ?></a> /
					<?php the_title(); ?>
				</nav>

				<div class="brix-farm__top">
					<div class="brix-farm__intro">
						<p class="brix-label">
							<?php
							$brix_bits = array( __( 'Виробник', 'brix' ) );

							if ( is_array( $brix_country ) ) {
								$brix_bits[] = $brix_country[0]->name;
							}

							echo esc_html( implode( ' · ', $brix_bits ) );
							?>
						</p>
						<h1><?php the_title(); ?></h1>

						<?php if ( '' !== $brix_headline ) : ?>
							<p class="brix-lead"><?php echo esc_html( $brix_headline ); ?></p>
						<?php endif; ?>

						<?php if ( get_the_excerpt() ) : ?>
							<p class="brix-muted"><?php echo esc_html( get_the_excerpt() ); ?></p>
						<?php endif; ?>
					</div>

					<dl class="brix-farm__facts">
						<?php
						$brix_facts = array();

						if ( $brix_house ) {
							$brix_facts[ __( 'Господарств', 'brix' ) ] = \Brix\Core\Support\Format::number( (float) $brix_house );
						}

						if ( $brix_since ) {
							$brix_facts[ __( 'Працюємо з', 'brix' ) ] = (string) $brix_since;
						}

						if ( '' !== $brix_variety ) {
							$brix_facts[ __( 'Різновид', 'brix' ) ] = $brix_variety;
						}

						if ( $brix_fob ) {
							$brix_facts[ __( 'Ціна FOB', 'brix' ) ] = '$' . number_format( $brix_fob, 2, ',', ' ' ) . __( ' / кг', 'brix' );
						}

						foreach ( $brix_facts as $brix_key => $brix_value ) :
							?>
							<div>
								<dt class="brix-label"><?php echo esc_html( $brix_key ); ?></dt>
								<dd><?php echo esc_html( $brix_value ); ?></dd>
							</div>
						<?php endforeach; ?>
					</dl>
				</div>
			</div>
		</section>

		<?php if ( '' !== $brix_story || '' !== $brix_quote ) : ?>
			<section class="brix-section brix-section--tight">
				<div class="brix-wrap brix-farm__story">
					<?php if ( '' !== $brix_story ) : ?>
						<div class="brix-prose">
							<h2><?php esc_html_e( 'Як ми сюди потрапили', 'brix' ); ?></h2>
							<?php echo wp_kses_post( wpautop( $brix_story ) ); ?>
						</div>
					<?php endif; ?>

					<?php if ( '' !== $brix_quote ) : ?>
						<figure class="brix-quote">
							<blockquote><?php echo esc_html( $brix_quote ); ?></blockquote>
							<?php if ( '' !== $brix_author ) : ?>
								<figcaption class="brix-label"><?php echo esc_html( $brix_author ); ?></figcaption>
							<?php endif; ?>
						</figure>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( '' !== $brix_lat || '' !== $brix_region ) : ?>
			<section class="brix-section brix-section--tight brix-section--dark brix-grain">
				<div class="brix-wrap brix-farm__place">
					<div>
						<p class="brix-label"><?php esc_html_e( 'Де це', 'brix' ); ?></p>
						<p class="brix-farm__coords brix-mono"><?php echo esc_html( trim( $brix_lat . ' ' . $brix_lng ) ); ?></p>
						<?php if ( '' !== $brix_region ) : ?>
							<p class="brix-lead"><?php echo esc_html( $brix_region ); ?></p>
						<?php endif; ?>
					</div>

					<div class="brix-photo brix-photo--green brix-farm__map">
						<span class="brix-photo__caption"><?php the_title(); ?></span>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $brix_calendar ) : ?>
			<section class="brix-section brix-section--tight">
				<div class="brix-wrap">
					<header class="brix-section__head">
						<div>
							<p class="brix-label"><?php esc_html_e( 'Сезон', 'brix' ); ?></p>
							<h2><?php esc_html_e( 'Рік на станції', 'brix' ); ?></h2>
						</div>
						<p class="brix-small brix-muted"><?php esc_html_e( 'Від квітки до вашої пачки проходить близько девʼяти місяців.', 'brix' ); ?></p>
					</header>

					<ol class="brix-timeline">
						<?php foreach ( $brix_calendar as $brix_step ) : ?>
							<li class="brix-timeline__item">
								<span class="brix-timeline__period brix-mono"><?php echo esc_html( $brix_step['brix_farm_calendar_period'] ); ?></span>
								<span class="brix-timeline__title"><?php echo esc_html( $brix_step['brix_farm_calendar_title'] ); ?></span>
								<span class="brix-timeline__note brix-small brix-muted"><?php echo esc_html( $brix_step['brix_farm_calendar_note'] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ol>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $brix_lots ) : ?>
			<section class="brix-section brix-section--tight">
				<div class="brix-wrap">
					<header class="brix-section__head">
						<div>
							<p class="brix-label"><?php esc_html_e( 'У продажу', 'brix' ); ?></p>
							<h2><?php esc_html_e( 'Лоти з цієї станції', 'brix' ); ?></h2>
						</div>
						<a class="brix-section__more" href="<?php echo esc_url( (string) wc_get_page_permalink( 'shop' ) ); ?>">
							<?php esc_html_e( 'Усі лоти', 'brix' ); ?>
							<?php echo brix_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</a>
					</header>

					<div class="brix-card-grid">
						<?php brix_render_product_cards( array_filter( array_map( 'wc_get_product', wp_list_pluck( $brix_lots, 'ID' ) ) ) ); ?>
					</div>
				</div>
			</section>
		<?php endif; ?>
	</article>

	<?php
endwhile;

get_footer();
