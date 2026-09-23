<?php
/**
 * Гайд заварювання.
 *
 * Таймер кроків — фаза 6. Поки що кроки читаються як список,
 * і рецепт повністю зрозумілий без жодного скрипта.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$brix_id     = get_the_ID();
	$brix_dose   = (int) get_post_meta( $brix_id, 'brix_guide_dose', true );
	$brix_water  = (int) get_post_meta( $brix_id, 'brix_guide_water', true );
	$brix_temp   = (int) get_post_meta( $brix_id, 'brix_guide_temperature', true );
	$brix_grind  = (string) get_post_meta( $brix_id, 'brix_guide_grind', true );
	$brix_total  = (int) get_post_meta( $brix_id, 'brix_guide_total_time', true );
	$brix_tip_t  = (string) get_post_meta( $brix_id, 'brix_guide_tip_title', true );
	$brix_tip_x  = (string) get_post_meta( $brix_id, 'brix_guide_tip_text', true );
	$brix_steps  = brix_repeater(
		$brix_id,
		'brix_guide_steps',
		array( 'brix_guide_step_at', 'brix_guide_step_title', 'brix_guide_step_target', 'brix_guide_step_text' )
	);
	$brix_others = get_posts(
		array(
			'post_type'      => 'brix_guide',
			'posts_per_page' => 4,
			'post__not_in'   => array( $brix_id ),
			'orderby'        => 'menu_order date',
		)
	);
	?>

	<article <?php post_class( 'brix-guide' ); ?>>
		<section class="brix-section brix-section--tight">
			<div class="brix-wrap">
				<nav class="brix-breadcrumb">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Головна', 'brix' ); ?></a> /
					<a href="<?php echo esc_url( (string) get_post_type_archive_link( 'brix_guide' ) ); ?>"><?php esc_html_e( 'Гайди', 'brix' ); ?></a> /
					<?php the_title(); ?>
				</nav>

				<div class="brix-guide__top">
					<div class="brix-guide__intro">
						<p class="brix-label"><?php esc_html_e( 'Рецепт', 'brix' ); ?></p>
						<h1><?php the_title(); ?></h1>

						<?php if ( get_the_excerpt() ) : ?>
							<p class="brix-lead brix-muted"><?php echo esc_html( get_the_excerpt() ); ?></p>
						<?php endif; ?>
					</div>

					<dl class="brix-guide__specs">
						<?php
						$brix_specs = array();

						if ( $brix_dose ) {
							$brix_specs[ __( 'Кава', 'brix' ) ] = $brix_dose . __( ' г', 'brix' );
						}

						if ( $brix_water ) {
							$brix_specs[ __( 'Вода', 'brix' ) ] = $brix_water . __( ' мл', 'brix' );
						}

						if ( $brix_temp ) {
							$brix_specs[ __( 'Температура', 'brix' ) ] = $brix_temp . ' °C';
						}

						if ( '' !== $brix_grind ) {
							$brix_specs[ __( 'Помел', 'brix' ) ] = $brix_grind;
						}

						if ( $brix_total ) {
							$brix_specs[ __( 'Час', 'brix' ) ] = \Brix\Core\Support\Format::duration( $brix_total );
						}

						foreach ( $brix_specs as $brix_key => $brix_value ) :
							?>
							<div>
								<dt class="brix-label"><?php echo esc_html( $brix_key ); ?></dt>
								<dd class="brix-num"><?php echo esc_html( $brix_value ); ?></dd>
							</div>
						<?php endforeach; ?>
					</dl>
				</div>
			</div>
		</section>

		<?php if ( $brix_steps ) : ?>
			<section class="brix-section brix-section--tight">
				<div class="brix-wrap brix-guide__body">
					<ol class="brix-steps">
						<?php foreach ( $brix_steps as $brix_step ) : ?>
							<li class="brix-steps__item">
								<span class="brix-steps__time brix-num">
									<?php echo esc_html( \Brix\Core\Support\Format::duration( (int) $brix_step['brix_guide_step_at'] ) ); ?>
								</span>
								<span class="brix-steps__body">
									<b class="brix-steps__title"><?php echo esc_html( $brix_step['brix_guide_step_title'] ); ?></b>
									<?php if ( '' !== $brix_step['brix_guide_step_target'] ) : ?>
										<span class="brix-steps__target brix-num"><?php echo esc_html( $brix_step['brix_guide_step_target'] ); ?></span>
									<?php endif; ?>
									<span class="brix-steps__text"><?php echo esc_html( $brix_step['brix_guide_step_text'] ); ?></span>
								</span>
							</li>
						<?php endforeach; ?>
					</ol>

					<?php if ( '' !== $brix_tip_t || '' !== $brix_tip_x ) : ?>
						<aside class="brix-tip">
							<?php if ( '' !== $brix_tip_t ) : ?>
								<h2 class="brix-tip__title"><?php echo esc_html( $brix_tip_t ); ?></h2>
							<?php endif; ?>
							<?php if ( '' !== $brix_tip_x ) : ?>
								<p class="brix-small"><?php echo esc_html( $brix_tip_x ); ?></p>
							<?php endif; ?>
						</aside>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( get_the_content() ) : ?>
			<section class="brix-section brix-section--tight">
				<div class="brix-wrap brix-prose"><?php the_content(); ?></div>
			</section>
		<?php endif; ?>

		<?php if ( $brix_others ) : ?>
			<section class="brix-section brix-section--tight">
				<div class="brix-wrap">
					<header class="brix-section__head">
						<h2><?php esc_html_e( 'Інші рецепти', 'brix' ); ?></h2>
					</header>

					<div class="brix-tiles">
						<?php foreach ( $brix_others as $brix_other ) : ?>
							<?php get_template_part( 'template-parts/guide/tile', null, array( 'guide' => $brix_other ) ); ?>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
		<?php endif; ?>
	</article>

	<?php
endwhile;

get_footer();
