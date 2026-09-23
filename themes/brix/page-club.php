<?php
/**
 * BRIX Club — сторінка підписки.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

get_header();

$brix_config    = brix_club_config();
$brix_selection = brix_club_selection();
$brix_price     = brix_club_price( $brix_selection );
$brix_complete  = count( $brix_selection ) === count( $brix_config );
?>

<section class="brix-section brix-section--dark brix-grain">
	<div class="brix-wrap brix-club-hero">
		<div class="brix-club-hero__text">
			<p class="brix-label"><?php esc_html_e( 'Підписка', 'brix' ); ?></p>
			<h1><?php esc_html_e( 'BRIX Club', 'brix' ); ?></h1>
			<p class="brix-lead">
				<?php esc_html_e( 'Свіже обсмаження кожні два або чотири тижні, −10% на все і жодного листування: пауза, пропуск і заміна лоту — в кабінеті у два кліки.', 'brix' ); ?>
			</p>

			<dl class="brix-club-hero__stats">
				<div>
					<dt class="brix-club-hero__value">−<?php echo esc_html( (string) $brix_price['discount'] ); ?>%</dt>
					<dd class="brix-label"><?php esc_html_e( 'на кожне замовлення', 'brix' ); ?></dd>
				</div>
				<div>
					<dt class="brix-club-hero__value"><?php echo wp_kses_post( wc_price( 0 ) ); ?></dt>
					<dd class="brix-label">
						<?php
						printf(
							/* translators: %s — поріг безкоштовної доставки. */
							esc_html__( 'доставка від %s', 'brix' ),
							esc_html( wp_strip_all_tags( wc_price( brix_free_shipping_threshold() ) ) )
						);
						?>
					</dd>
				</div>
				<div>
					<dt class="brix-club-hero__value"><?php esc_html_e( '24 год', 'brix' ); ?></dt>
					<dd class="brix-label"><?php esc_html_e( 'від обсмаження до відправки', 'brix' ); ?></dd>
				</div>
			</dl>

			<p>
				<a class="brix-btn brix-btn--light brix-btn--xl" href="#brix-club-builder">
					<?php esc_html_e( 'Зібрати підписку', 'brix' ); ?>
					<?php echo brix_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			</p>
		</div>

		<div class="brix-club-hero__packs">
			<?php foreach ( array_slice( brix_home_lots( 2 ), 0, 2 ) as $brix_lot_product ) : ?>
				<?php brix_the_pack( $brix_lot_product, '100%' ); ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="brix-section" id="brix-club-builder">
	<div class="brix-wrap">
		<header class="brix-section__head">
			<div>
				<p class="brix-label"><?php esc_html_e( 'Конструктор', 'brix' ); ?></p>
				<h2><?php esc_html_e( 'Зберіть свою підписку', 'brix' ); ?></h2>
			</div>
		</header>

		<div class="brix-club">
			<div class="brix-club__steps">
				<?php
				$brix_index = 0;

				foreach ( $brix_config as $brix_key => $brix_step ) :
					++$brix_index;
					$brix_choice = $brix_selection[ $brix_key ] ?? '';
					?>
					<fieldset class="brix-club__step">
						<legend class="brix-label">
							<?php
							printf(
								/* translators: 1: номер кроку, 2: назва кроку. */
								esc_html__( 'Крок %1$d · %2$s', 'brix' ),
								absint( $brix_index ),
								esc_html( $brix_step['label'] )
							);
							?>
						</legend>

						<div class="brix-club__options">
							<?php foreach ( $brix_step['options'] as $brix_value => $brix_option ) : ?>
								<?php $brix_on = (string) $brix_value === $brix_choice; ?>
								<a
									class="brix-club__option<?php echo $brix_on ? ' is-active' : ''; ?>"
									href="<?php echo esc_url( brix_club_url( $brix_key, (string) $brix_value ) ); ?>"
									aria-pressed="<?php echo $brix_on ? 'true' : 'false'; ?>"
									rel="nofollow"
								>
									<span class="brix-club__option-label"><?php echo esc_html( $brix_option['label'] ); ?></span>
									<?php if ( ! empty( $brix_option['note'] ) ) : ?>
										<span class="brix-club__option-note brix-small brix-muted"><?php echo esc_html( $brix_option['note'] ); ?></span>
									<?php endif; ?>
								</a>
							<?php endforeach; ?>
						</div>
					</fieldset>
				<?php endforeach; ?>
			</div>

			<aside class="brix-club__summary" aria-live="polite">
				<h3 class="brix-club__summary-title"><?php esc_html_e( 'Ваша підписка', 'brix' ); ?></h3>

				<dl class="brix-club__recap">
					<?php foreach ( $brix_config as $brix_key => $brix_step ) : ?>
						<div>
							<dt class="brix-small brix-muted"><?php echo esc_html( $brix_step['label'] ); ?></dt>
							<dd>
								<?php
								$brix_choice = $brix_selection[ $brix_key ] ?? '';
								echo esc_html(
									'' !== $brix_choice
										? $brix_step['options'][ $brix_choice ]['label']
										: __( '—', 'brix' )
								);
								?>
							</dd>
						</div>
					<?php endforeach; ?>
				</dl>

				<?php if ( $brix_price['regular'] > 0 ) : ?>
					<div class="brix-club__price">
						<span class="brix-club__price-was brix-mono"><?php echo esc_html( wp_strip_all_tags( wc_price( $brix_price['regular'] ) ) ); ?></span>
						<b class="brix-mono"><?php echo esc_html( wp_strip_all_tags( wc_price( $brix_price['club'] ) ) ); ?></b>
						<span class="brix-small brix-muted"><?php esc_html_e( 'орієнтовно за одну відправку', 'brix' ); ?></span>
					</div>
				<?php endif; ?>

				<?php
				/*
				 * Повторні списання, пауза й пропуск — фаза 6. Поки що
				 * чесніше вести в каталог, ніж малювати кнопку, яка
				 * нічого не оформлює.
				 */
				?>
				<a class="brix-btn brix-btn--full<?php echo $brix_complete ? '' : ' brix-btn--disabled'; ?>"
					href="<?php echo esc_url( (string) wc_get_page_permalink( 'shop' ) ); ?>"
					<?php echo $brix_complete ? '' : 'aria-disabled="true"'; ?>>
					<?php esc_html_e( 'Обрати перший лот', 'brix' ); ?>
				</a>

				<?php if ( ! $brix_complete ) : ?>
					<p class="brix-small brix-muted"><?php esc_html_e( 'Пройдіть усі чотири кроки, щоб побачити ціну.', 'brix' ); ?></p>
				<?php endif; ?>
			</aside>
		</div>
	</div>
</section>

<section class="brix-section brix-section--tight">
	<div class="brix-wrap">
		<header class="brix-section__head">
			<h2><?php esc_html_e( 'Як це працює', 'brix' ); ?></h2>
		</header>

		<ol class="brix-timeline">
			<?php
			$brix_how = array(
				array( __( 'Крок 1', 'brix' ), __( 'Збираєте підписку', 'brix' ), __( 'Обираєте вагу, частоту й помел. Змінити можна будь-коли.', 'brix' ) ),
				array( __( 'Крок 2', 'brix' ), __( 'Обсмажуємо в понеділок', 'brix' ), __( 'Свіжий лот під ваш профіль, змелений перед відправкою.', 'brix' ) ),
				array( __( 'Крок 3', 'brix' ), __( 'Відправляємо того ж дня', 'brix' ), __( 'Нова Пошта, безкоштовно від порога суми.', 'brix' ) ),
				array( __( 'Крок 4', 'brix' ), __( 'Керуєте з кабінету', 'brix' ), __( 'Пауза, пропуск, заміна лоту або скасування — у два кліки.', 'brix' ) ),
			);

			foreach ( $brix_how as $brix_item ) :
				?>
				<li class="brix-timeline__item">
					<span class="brix-timeline__period brix-mono"><?php echo esc_html( $brix_item[0] ); ?></span>
					<span class="brix-timeline__title"><?php echo esc_html( $brix_item[1] ); ?></span>
					<span class="brix-timeline__note brix-small brix-muted"><?php echo esc_html( $brix_item[2] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</section>

<section class="brix-section brix-section--tight">
	<div class="brix-wrap brix-faq">
		<h2><?php esc_html_e( 'Питання, які ставлять найчастіше', 'brix' ); ?></h2>

		<?php
		$brix_faq = array(
			array( __( 'Чи можна пропустити одну відправку?', 'brix' ), __( 'Так. У кабінеті є кнопка «Пропустити» — наступна відправка просто зсунеться на цикл.', 'brix' ) ),
			array( __( 'А якщо лот не сподобався?', 'brix' ), __( 'Напишіть нам протягом тижня — замінимо на інший безкоштовно.', 'brix' ) ),
			array( __( 'Можна обрати конкретний лот?', 'brix' ), __( 'Так, але тільки поки він є в наявності. Мікролоти закінчуються за 3–6 тижнів.', 'brix' ) ),
			array( __( 'Як скасувати підписку?', 'brix' ), __( 'Одна кнопка в кабінеті, без листування й без утримань.', 'brix' ) ),
		);

		foreach ( $brix_faq as $brix_item ) :
			?>
			<details class="brix-faq__item">
				<summary class="brix-faq__question"><?php echo esc_html( $brix_item[0] ); ?></summary>
				<p class="brix-faq__answer"><?php echo esc_html( $brix_item[1] ); ?></p>
			</details>
		<?php endforeach; ?>
	</div>
</section>

<?php
get_footer();
