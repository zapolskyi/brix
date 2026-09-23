<?php
/**
 * Кабінет — головна.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_user   = wp_get_current_user();
$brix_orders = wc_get_orders(
	array(
		'customer' => get_current_user_id(),
		'limit'    => 3,
		'orderby'  => 'date',
		'order'    => 'DESC',
	)
);
?>

<div class="brix-account__dash">
	<h2 class="brix-account__hello">
		<?php
		/*
		 * Лише справжнє ім'я. Без нього WooCommerce підставив би логін,
		 * складений з пошти, — «Привіт, reg-test».
		 */
		if ( $brix_user->first_name ) {
			printf(
				/* translators: %s — імʼя покупця. */
				esc_html__( 'Привіт, %s', 'brix' ),
				esc_html( $brix_user->first_name )
			);
		} else {
			esc_html_e( 'Вітаємо в кабінеті', 'brix' );
		}
		?>
	</h2>

	<p class="brix-muted">
		<?php esc_html_e( 'Тут ваші замовлення, адреси й підписка. Повторити попереднє замовлення — одна кнопка.', 'brix' ); ?>
	</p>

	<?php $brix_subs = brix_has_core() ? \Brix\Core\Club\Subscription::for_user( get_current_user_id() ) : array(); ?>
	<?php if ( $brix_subs ) : ?>
		<section class="brix-account__club">
			<h3 class="brix-label"><?php esc_html_e( 'BRIX Club', 'brix' ); ?></h3>

			<?php
			foreach ( $brix_subs as $brix_sub ) :
				$brix_data = \Brix\Core\Club\Subscription::data( $brix_sub->ID );

				if ( \Brix\Core\Club\Subscription::CANCELLED === $brix_data['status'] ) {
					continue;
				}

				$brix_paused = \Brix\Core\Club\Subscription::PAUSED === $brix_data['status'];
				?>
				<article class="brix-sub-card">
					<h4 class="brix-sub-card__name">
						<?php
						if ( $brix_data['product'] ) {
							echo esc_html( $brix_data['product']->get_name() );
						} elseif ( $brix_data['lot'] ) {
							echo esc_html( $brix_data['lot']->get_name() );
						} else {
							esc_html_e( 'Лот знято з продажу', 'brix' );
						}
						?>
					</h4>

					<?php if ( $brix_data['broken'] && $brix_data['lot'] ) : ?>
						<?php
						/*
						 * Вагу чи помел підписки зняли з продажу. Мовчати
						 * не можна — пачка просто не приїде. Кажемо, що
						 * сталось, і ведемо на лот обрати заново.
						 */
						?>
						<p class="brix-sub-card__alert">
							<?php esc_html_e( 'Цієї ваги чи помелу більше немає в продажу, тож наступну пачку ми відкладаємо. Оберіть варіант заново — підписку буде оформлено з тією ж знижкою.', 'brix' ); ?>
							<a href="<?php echo esc_url( $brix_data['lot']->get_permalink() ); ?>"><?php esc_html_e( 'Обрати заново', 'brix' ); ?></a>
						</p>
					<?php endif; ?>

					<p class="brix-small brix-muted">
						<?php
						if ( $brix_paused ) {
							esc_html_e( 'На паузі', 'brix' );
						} else {
							printf(
								/* translators: 1 — інтервал у тижнях, 2 — дата наступної відправки. */
								esc_html__( 'Кожні %1$d тижні · наступна %2$s', 'brix' ),
								absint( $brix_data['interval'] / 7 ),
								esc_html( date_i18n( 'd.m.Y', $brix_data['next'] ) )
							);
						}
						?>
					</p>

					<p class="brix-sub-card__actions">
						<?php if ( $brix_paused ) : ?>
							<a class="brix-btn brix-btn--outline brix-btn--sm"
								href="<?php echo esc_url( \Brix\Core\Club\Actions::url( $brix_sub->ID, 'resume' ) ); ?>">
								<?php esc_html_e( 'Відновити', 'brix' ); ?>
							</a>
						<?php else : ?>
							<a class="brix-btn brix-btn--outline brix-btn--sm"
								href="<?php echo esc_url( \Brix\Core\Club\Actions::url( $brix_sub->ID, 'skip' ) ); ?>">
								<?php esc_html_e( 'Пропустити', 'brix' ); ?>
							</a>
							<a class="brix-btn brix-btn--outline brix-btn--sm"
								href="<?php echo esc_url( \Brix\Core\Club\Actions::url( $brix_sub->ID, 'pause' ) ); ?>">
								<?php esc_html_e( 'Пауза', 'brix' ); ?>
							</a>
						<?php endif; ?>

						<a class="brix-sub-card__cancel"
							href="<?php echo esc_url( \Brix\Core\Club\Actions::url( $brix_sub->ID, 'cancel' ) ); ?>">
							<?php esc_html_e( 'Скасувати', 'brix' ); ?>
						</a>
					</p>
				</article>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>

	<?php $brix_quiz = function_exists( 'brix_quiz_saved' ) ? brix_quiz_saved() : array(); ?>
	<?php if ( $brix_quiz ) : ?>
		<section class="brix-account__quiz">
			<h3 class="brix-label"><?php esc_html_e( 'Ваш профіль смаку', 'brix' ); ?></h3>

			<p class="brix-lead">
				<?php
				printf(
					/* translators: %s — назва помелу. */
					esc_html__( 'Мелемо під %s', 'brix' ),
					esc_html( (string) ( $brix_quiz['grind'] ?? '' ) )
				);
				?>
			</p>

			<p>
				<a class="brix-btn brix-btn--outline brix-btn--sm" href="<?php echo esc_url( get_permalink( get_page_by_path( 'quiz' ) ) ); ?>">
					<?php esc_html_e( 'Пройти квіз заново', 'brix' ); ?>
				</a>
			</p>
		</section>
	<?php endif; ?>

	<?php if ( $brix_orders ) : ?>
		<section class="brix-account__block">
			<header class="brix-section__head">
				<h3><?php esc_html_e( 'Останні замовлення', 'brix' ); ?></h3>
				<a class="brix-section__more" href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>">
					<?php esc_html_e( 'Усі замовлення', 'brix' ); ?>
					<?php echo brix_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			</header>

			<div class="brix-account__orders">
				<?php foreach ( $brix_orders as $brix_order ) : ?>
					<article class="brix-order-card">
						<p class="brix-label">
							<?php echo esc_html( wc_format_datetime( $brix_order->get_date_created() ) ); ?>
						</p>
						<h4 class="brix-order-card__number">
							<a href="<?php echo esc_url( $brix_order->get_view_order_url() ); ?>">
								<?php
								printf(
									/* translators: %s — номер замовлення. */
									esc_html__( 'Замовлення №%s', 'brix' ),
									esc_html( (string) $brix_order->get_order_number() )
								);
								?>
							</a>
						</h4>

						<p class="brix-order-card__items brix-small brix-muted">
							<?php
							$brix_names = array();

							foreach ( $brix_order->get_items() as $brix_item ) {
								$brix_names[] = $brix_item->get_name();
							}

							echo esc_html( implode( ' · ', array_slice( $brix_names, 0, 3 ) ) );
							?>
						</p>

						<p class="brix-order-card__foot">
							<b class="brix-num"><?php echo wp_kses_post( $brix_order->get_formatted_order_total() ); ?></b>
							<span class="brix-tag brix-tag--soft"><?php echo esc_html( wc_get_order_status_name( $brix_order->get_status() ) ); ?></span>
						</p>

						<?php
						/*
						 * «Повторити» — лише для сплачених замовлень: повтор
						 * неоплаченого — найпростіший спосіб отримати два
						 * однакові. Кнопка власна, а не order-again
						 * WooCommerce: той мовчки пропускає лоти, яких уже
						 * немає, а наш називає їх уголос.
						 */
						if ( brix_has_core() && ( $brix_order->is_paid() || $brix_order->has_status( array( 'processing', 'completed' ) ) ) ) :
							?>
							<p class="brix-order-card__repeat">
								<a class="brix-btn brix-btn--outline brix-btn--sm"
									href="<?php echo esc_url( \Brix\Core\Orders\Repeat::url( $brix_order ) ); ?>">
									<?php esc_html_e( 'Повторити замовлення', 'brix' ); ?>
								</a>
							</p>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
	<?php else : ?>
		<div class="brix-empty">
			<h3><?php esc_html_e( 'Замовлень поки немає', 'brix' ); ?></h3>
			<p class="brix-muted"><?php esc_html_e( 'Щойно замовите — усе зʼявиться тут: статус, накладна й кнопка повтору.', 'brix' ); ?></p>
			<p>
				<a class="brix-btn" href="<?php echo esc_url( (string) wc_get_page_permalink( 'shop' ) ); ?>">
					<?php esc_html_e( 'У магазин', 'brix' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<?php do_action( 'woocommerce_account_dashboard' ); ?>
</div>
