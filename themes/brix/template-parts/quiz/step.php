<?php
/**
 * Один крок квізу.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_answers  = $args['answers'] ?? array();
$brix_step     = brix_quiz_step();
$brix_total    = \Brix\Core\Quiz\Questions::count();
$brix_all      = \Brix\Core\Quiz\Questions::all();
$brix_question = $brix_all[ $brix_step - 1 ];
$brix_chosen   = (array) ( $brix_answers[ $brix_question['key'] ] ?? array() );
$brix_multiple = ! empty( $brix_question['multiple'] );
$brix_max      = (int) ( $brix_question['max'] ?? 2 );
?>

<div class="brix-section brix-section--tight">
	<div class="brix-wrap brix-quiz">
		<header class="brix-quiz__head">
			<p class="brix-label">
				<?php
				printf(
					/* translators: 1: номер кроку, 2: скільки всього. */
					esc_html__( 'Крок %1$d з %2$d', 'brix' ),
					absint( $brix_step ),
					absint( $brix_total )
				);
				?>
			</p>

			<div class="brix-quiz__progress">
				<span class="brix-quiz__fill" style="--brix-value:<?php echo esc_attr( (string) round( $brix_step / $brix_total * 100 ) ); ?>%"></span>
			</div>
		</header>

		<h1 class="brix-quiz__title"><?php echo esc_html( $brix_question['title'] ); ?></h1>

		<?php if ( ! empty( $brix_question['hint'] ) ) : ?>
			<p class="brix-lead brix-muted"><?php echo esc_html( $brix_question['hint'] ); ?></p>
		<?php endif; ?>

		<div class="brix-quiz__options" role="group" aria-label="<?php echo esc_attr( $brix_question['title'] ); ?>">
			<?php foreach ( $brix_question['options'] as $brix_value => $brix_option ) : ?>
				<?php $brix_on = in_array( (string) $brix_value, $brix_chosen, true ); ?>
				<a
					class="brix-quiz__option<?php echo $brix_on ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( brix_quiz_option_url( $brix_question, $brix_answers, (string) $brix_value, $brix_step ) ); ?>"
					<?php echo $brix_on ? 'aria-current="true"' : ''; ?>
					rel="nofollow"
				>
					<span class="brix-quiz__option-label"><?php echo esc_html( $brix_option['label'] ); ?></span>
					<?php if ( $brix_on ) : ?>
						<?php echo brix_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>

		<div class="brix-quiz__nav">
			<?php if ( $brix_step > 1 ) : ?>
				<a class="brix-btn brix-btn--outline" href="<?php echo esc_url( brix_quiz_url( $brix_answers, array( 'step' => (string) ( $brix_step - 1 ) ) ) ); ?>" rel="nofollow">
					<?php esc_html_e( 'Назад', 'brix' ); ?>
				</a>
			<?php endif; ?>

			<?php if ( $brix_multiple ) : ?>
				<p class="brix-quiz__counter brix-label">
					<?php
					printf(
						/* translators: 1: скільки обрано, 2: максимум. */
						esc_html__( 'Обрано %1$d з %2$d', 'brix' ),
						count( $brix_chosen ),
						absint( $brix_max )
					);
					?>
				</p>

				<a
					class="brix-btn<?php echo $brix_chosen ? '' : ' brix-btn--disabled'; ?>"
					href="<?php echo esc_url( brix_quiz_url( $brix_answers, brix_quiz_next_args( $brix_step ) ) ); ?>"
					<?php echo $brix_chosen ? '' : 'aria-disabled="true"'; ?>
					rel="nofollow"
				>
					<?php esc_html_e( 'Далі', 'brix' ); ?>
					<?php echo brix_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			<?php else : ?>
				<p class="brix-quiz__counter brix-small brix-muted"><?php esc_html_e( 'Оберіть варіант, щоб рухатись далі.', 'brix' ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( $brix_answers ) : ?>
			<aside class="brix-quiz__recap">
				<p class="brix-label"><?php esc_html_e( 'Уже знаємо про вас', 'brix' ); ?></p>
				<dl>
					<?php
					foreach ( $brix_all as $brix_item ) :
						$brix_picked = (array) ( $brix_answers[ $brix_item['key'] ] ?? array() );

						if ( ! $brix_picked ) {
							continue;
						}

						$brix_labels = array();

						foreach ( $brix_picked as $brix_pick ) {
							$brix_labels[] = $brix_item['options'][ $brix_pick ]['label'] ?? $brix_pick;
						}
						?>
						<div>
							<dt class="brix-small brix-muted"><?php echo esc_html( $brix_item['title'] ); ?></dt>
							<dd><?php echo esc_html( implode( ', ', $brix_labels ) ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			</aside>
		<?php endif; ?>
	</div>
</div>
