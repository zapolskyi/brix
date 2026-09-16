<?php
/**
 * Результат квізу: профіль і три лоти.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_answers = $args['answers'] ?? array();
$brix_profile = \Brix\Core\Quiz\Recommender::profile( $brix_answers );
$brix_grind   = \Brix\Core\Quiz\Recommender::grind( $brix_answers );
$brix_picks   = \Brix\Core\Quiz\Recommender::recommend( $brix_answers, 3 );

$brix_labels = array(
	'acidity'    => __( 'Кислотність', 'brix' ),
	'sweetness'  => __( 'Солодкість', 'brix' ),
	'body'       => __( 'Тіло', 'brix' ),
	'fruitiness' => __( 'Фруктовість', 'brix' ),
	'roast'      => __( 'Обсмаження', 'brix' ),
);
?>

<div class="brix-section brix-section--tight">
	<div class="brix-wrap brix-quiz-result">
		<header class="brix-quiz-result__head">
			<div>
				<p class="brix-label"><?php esc_html_e( 'Ваш профіль', 'brix' ); ?></p>
				<h1><?php esc_html_e( 'Три лоти під ваш смак', 'brix' ); ?></h1>

				<?php if ( '' !== $brix_grind ) : ?>
					<?php $brix_term = get_term_by( 'slug', $brix_grind, 'pa_grind' ); ?>
					<p class="brix-lead brix-muted">
						<?php
						printf(
							/* translators: %s — назва помелу. */
							esc_html__( 'Помел уже виставлено під %s — змінити можна в кошику.', 'brix' ),
							esc_html( $brix_term instanceof WP_Term ? $brix_term->name : $brix_grind )
						);
						?>
					</p>
				<?php endif; ?>
			</div>

			<?php if ( $brix_profile ) : ?>
				<div class="brix-taste brix-quiz-result__profile">
					<?php foreach ( $brix_profile as $brix_key => $brix_score ) : ?>
						<div class="brix-taste__row">
							<span><?php echo esc_html( $brix_labels[ $brix_key ] ?? $brix_key ); ?></span>
							<span class="brix-taste__track">
								<span class="brix-taste__fill" style="--brix-value:<?php echo esc_attr( (string) ( $brix_score * 20 ) ); ?>%"></span>
							</span>
							<span class="brix-taste__value"><?php echo esc_html( $brix_score . '/5' ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</header>

		<?php if ( $brix_picks ) : ?>
			<div class="brix-quiz-result__picks">
				<?php foreach ( $brix_picks as $brix_pick ) : ?>
					<?php
					$brix_product = $brix_pick['product'];
					$brix_lot     = brix_lot( $brix_product );
					$brix_style   = brix_pack_style( $brix_product );

					// Кладемо в кошик одразу з потрібним помелом — саме
					// заради цього квіз і питав про спосіб заварювання.
					$brix_url = $brix_product->get_permalink();

					if ( '' !== $brix_grind ) {
						$brix_url = add_query_arg( 'attribute_pa_grind', $brix_grind, $brix_url );
					}
					?>
					<article class="brix-pick">
						<div class="brix-pick__stage <?php echo $brix_style ? esc_attr( 'brix-card__stage--' . $brix_style ) : ''; ?>">
							<span class="brix-tag brix-tag--soft brix-pick__match">
								<?php
								printf(
									/* translators: %d — відсоток збігу. */
									esc_html__( 'Збіг %d%%', 'brix' ),
									absint( $brix_pick['match'] )
								);
								?>
							</span>
							<?php brix_the_product_visual( $brix_product, '58%' ); ?>
						</div>

						<h2 class="brix-pick__name">
							<a href="<?php echo esc_url( $brix_url ); ?>"><?php echo esc_html( $brix_product->get_name() ); ?></a>
						</h2>

						<?php if ( $brix_lot && '' !== $brix_lot->notes_label() ) : ?>
							<p class="brix-card__notes"><?php echo esc_html( $brix_lot->notes_label() ); ?></p>
						<?php endif; ?>

						<p class="brix-label brix-pick__reason"><?php echo esc_html( $brix_pick['reason'] ); ?></p>

						<div class="brix-pick__foot">
							<b class="brix-mono"><?php echo wp_kses_post( brix_price_label( $brix_product ) ); ?></b>
							<a class="brix-btn brix-btn--sm" href="<?php echo esc_url( $brix_url ); ?>">
								<?php esc_html_e( 'Обрати', 'brix' ); ?>
							</a>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="brix-empty">
				<h2><?php esc_html_e( 'Не змогли підібрати', 'brix' ); ?></h2>
				<p class="brix-muted"><?php esc_html_e( 'Схоже, під такі відповіді зараз немає лотів у наявності. Спробуйте пройти ще раз або подивіться весь каталог.', 'brix' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="brix-quiz-result__actions">
			<a class="brix-btn brix-btn--outline" href="<?php echo esc_url( brix_page_url( 'quiz' ) ); ?>">
				<?php esc_html_e( 'Пройти ще раз', 'brix' ); ?>
			</a>
			<a class="brix-btn brix-btn--outline" href="<?php echo esc_url( (string) wc_get_page_permalink( 'shop' ) ); ?>">
				<?php esc_html_e( 'Весь каталог', 'brix' ); ?>
			</a>
		</div>
	</div>
</div>
