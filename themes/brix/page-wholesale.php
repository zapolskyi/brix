<?php
/**
 * Для кавʼярень — B2B.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

get_header();

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Читання результату редіректу, не обробка форми.
$brix_status = isset( $_GET['b2b'] ) ? sanitize_key( wp_unslash( $_GET['b2b'] ) ) : '';
?>

<section class="brix-section brix-section--dark brix-grain">
	<div class="brix-wrap brix-teaser">
		<div class="brix-teaser__text">
			<p class="brix-label"><?php esc_html_e( 'Для кавʼярень', 'brix' ); ?></p>
			<h1><?php esc_html_e( 'Кава, яку гості впізнають', 'brix' ); ?></h1>
			<p class="brix-lead">
				<?php esc_html_e( 'Оптові ціни від 5 кг, обсмаження під ваш графік, профіль під вашу машину. Рахунок і закривні документи — як годиться.', 'brix' ); ?>
			</p>
			<p>
				<a class="brix-btn brix-btn--light brix-btn--xl" href="#brix-b2b-form">
					<?php esc_html_e( 'Залишити заявку', 'brix' ); ?>
					<?php echo brix_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			</p>
		</div>

		<?php
		brix_photo(
			array(
				'class' => 'brix-photo--dark brix-teaser__photo',
				'asset' => 'section-wholesale',
				'alt'   => __( 'Бариста готує еспресо за баром кав’ярні', 'brix' ),
			)
		);
		?>
	</div>
</section>

<section class="brix-section brix-section--tight">
	<div class="brix-wrap">
		<header class="brix-section__head">
			<div>
				<p class="brix-label"><?php esc_html_e( 'Чесно', 'brix' ); ?></p>
				<h2><?php esc_html_e( 'Три речі, на яких зазвичай спотикаються', 'brix' ); ?></h2>
			</div>
		</header>

		<div class="brix-tiles">
			<?php
			$brix_objections = array(
				array(
					__( '«Мікролоти надто дорогі для меню»', 'brix' ),
					__( 'Для щоденного напою беріть Core: він дешевший за більшість спешелті й стабільний від партії до партії. Мікролоти лишіть на гостьову позицію — вона й продає, і виправдовує ціну.', 'brix' ),
				),
				array(
					__( '«Профіль пливе від партії до партії»', 'brix' ),
					__( 'Тому ми фіксуємо профіль обсмаження й перевіряємо кожну партію на каппінгу. Якщо чашка поїхала — міняємо за свій рахунок.', 'brix' ),
				),
				array(
					__( '«Бариста доведеться перенавчати»', 'brix' ),
					__( 'Приїжджаємо на калібрування при першій поставці й лишаємо рецепт під вашу машину. Далі — на звʼязку в месенджері.', 'brix' ),
				),
			);

			foreach ( $brix_objections as $brix_item ) :
				?>
				<div class="brix-tile brix-tile--guide">
					<span class="brix-tile__title"><?php echo esc_html( $brix_item[0] ); ?></span>
					<span class="brix-small brix-muted"><?php echo esc_html( $brix_item[1] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="brix-section brix-section--tight" id="brix-b2b-form">
	<div class="brix-wrap brix-b2b">
		<div class="brix-b2b__intro">
			<p class="brix-label"><?php esc_html_e( 'Заявка', 'brix' ); ?></p>
			<h2><?php esc_html_e( 'Розкажіть про заклад', 'brix' ); ?></h2>
			<p class="brix-muted">
				<?php esc_html_e( 'Відповімо протягом робочого дня: надішлемо прайс, зразки на пробу й домовимось про калібрування.', 'brix' ); ?>
			</p>

			<ul class="brix-b2b__facts">
				<li><?php echo brix_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Мінімальне замовлення — 5 кг', 'brix' ); ?></li>
				<li><?php echo brix_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Обсмаження під графік поставок', 'brix' ); ?></li>
				<li><?php echo brix_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Рахунок і закривні документи', 'brix' ); ?></li>
			</ul>
		</div>

		<div class="brix-b2b__form-wrap">
			<?php if ( 'ok' === $brix_status ) : ?>
				<div class="woocommerce-message" role="status">
					<?php esc_html_e( 'Заявку отримали. Відповімо протягом робочого дня.', 'brix' ); ?>
				</div>
			<?php elseif ( 'invalid' === $brix_status ) : ?>
				<div class="woocommerce-error" role="alert">
					<?php esc_html_e( 'Не всі обовʼязкові поля заповнені або email виглядає неправильно. Перевірте, будь ласка.', 'brix' ); ?>
				</div>
			<?php elseif ( '' !== $brix_status ) : ?>
				<div class="woocommerce-error" role="alert">
					<?php esc_html_e( 'Не вдалося надіслати заявку. Спробуйте ще раз або напишіть нам на пошту.', 'brix' ); ?>
				</div>
			<?php endif; ?>

			<form class="brix-b2b__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="brix_wholesale_apply">
				<?php wp_nonce_field( 'brix_wholesale_apply', 'brix_wholesale_nonce' ); ?>

				<?php
				// Приманка для ботів: людина цього поля не бачить.
				?>
				<p class="brix-visually-hidden" aria-hidden="true">
					<label for="brix_website"><?php esc_html_e( 'Не заповнюйте це поле', 'brix' ); ?></label>
					<input type="text" id="brix_website" name="brix_website" tabindex="-1" autocomplete="off">
				</p>

				<?php
				$brix_fields = array(
					'place'   => array( __( 'Назва закладу', 'brix' ), 'text', true, 'Кав’ярня «Пʼятниця»' ),
					'city'    => array( __( 'Місто', 'brix' ), 'text', true, 'Київ' ),
					'name'    => array( __( 'Як до вас звертатись', 'brix' ), 'text', true, 'Олена' ),
					'phone'   => array( __( 'Телефон', 'brix' ), 'tel', true, '+38 0__ ___ __ __' ),
					'email'   => array( __( 'Email', 'brix' ), 'email', true, 'ваш@email.com' ),
					'volume'  => array( __( 'Скільки кави на місяць', 'brix' ), 'text', false, '8 кг' ),
					'machine' => array( __( 'Яка машина й кавомолка', 'brix' ), 'text', false, 'La Marzocco Linea + Mythos' ),
				);

				foreach ( $brix_fields as $brix_key => $brix_field ) :
					$brix_id = 'brix_' . $brix_key;
					?>
					<p class="brix-field<?php echo in_array( $brix_key, array( 'volume', 'machine' ), true ) ? '' : ' brix-field--half'; ?>">
						<label class="brix-field__label" for="<?php echo esc_attr( $brix_id ); ?>">
							<?php echo esc_html( $brix_field[0] ); ?>
							<?php if ( $brix_field[2] ) : ?>
								<span class="brix-field__required" aria-hidden="true">*</span>
							<?php endif; ?>
						</label>
						<input
							class="brix-input"
							type="<?php echo esc_attr( $brix_field[1] ); ?>"
							id="<?php echo esc_attr( $brix_id ); ?>"
							name="<?php echo esc_attr( $brix_id ); ?>"
							placeholder="<?php echo esc_attr( $brix_field[3] ); ?>"
							<?php echo $brix_field[2] ? 'required' : ''; ?>
						>
					</p>
				<?php endforeach; ?>

				<p class="brix-field">
					<label class="brix-field__label" for="brix_comment"><?php esc_html_e( 'Коментар', 'brix' ); ?></label>
					<textarea class="brix-input brix-input--textarea" id="brix_comment" name="brix_comment" rows="3"
						placeholder="<?php esc_attr_e( 'Що вже пробували, який профіль шукаєте, коли потрібна перша поставка', 'brix' ); ?>"></textarea>
				</p>

				<p class="brix-b2b__submit">
					<button class="brix-btn brix-btn--xl" type="submit"><?php esc_html_e( 'Надіслати заявку', 'brix' ); ?></button>
					<span class="brix-small brix-muted">
						<?php esc_html_e( 'Поля із зірочкою обовʼязкові. Контакти з заявки потрібні лише, щоб відповісти вам.', 'brix' ); ?>
						<?php if ( get_privacy_policy_url() ) : ?>
							<a href="<?php echo esc_url( get_privacy_policy_url() ); ?>"><?php esc_html_e( 'Політика конфіденційності', 'brix' ); ?></a>
						<?php endif; ?>
					</span>
				</p>
			</form>
		</div>
	</div>
</section>

<?php
get_footer();
