<?php
/**
 * UI-кіт: усі компоненти теми на одному аркуші.
 *
 * Службова сторінка для візуальної звірки з макетом
 * design-source/preview/ui-kit.html. З пошуку прихована.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

get_header();

/**
 * Демо-дані пачок: назва, обробка, вага, код лоту.
 *
 * @var array<int, array<string, string>> $brix_packs
 */
$brix_packs = array(
	array( 'Ethiopia Guji', 'natural', 'Natural', '250 г', 'ET-GJ-14' ),
	array( 'Kenya Nyeri', 'washed', 'Washed', '250 г', 'KE-NY-08' ),
	array( 'Colombia Huila', 'honey', 'Honey', '250 г', 'CO-HU-21' ),
	array( 'Panama Geisha', 'lab', 'Lab', '100 г', 'PA-GE-03' ),
	array( 'Everyday', 'core', 'Core', '1 кг', 'BL-EV-01' ),
);

/**
 * Демо-дані шкал смаку.
 *
 * @var array<string, int> $brix_taste
 */
$brix_taste = array(
	'Кислотність'   => 4,
	'Солодкість'    => 5,
	'Тіло'          => 2,
	'Інтенсивність' => 3,
	'Фруктовість'   => 5,
);
?>

<div class="brix-section brix-section--tight">
	<div class="brix-wrap brix-kit">

		<header class="brix-section__head">
			<div>
				<p class="brix-label">Система</p>
				<h1>UI-кіт</h1>
			</div>
		</header>

		<!-- ——— кольори ——— -->
		<section class="brix-kit__block">
			<h2 class="brix-kit__title">Кольори</h2>
			<div class="brix-kit__swatches">
				<?php
				$brix_colors = array(
					'Roast'       => '--brix-roast',
					'Ripe Cherry' => '--brix-cherry',
					'Green Bean'  => '--brix-green',
					'Honey'       => '--brix-honey',
					'Mill White'  => '--brix-mill',
					'Stone'       => '--brix-stone',
					'Line'        => '--brix-line',
					'Muted'       => '--brix-muted',
				);

				foreach ( $brix_colors as $brix_name => $brix_token ) :
					?>
					<div class="brix-kit__swatch">
						<span class="brix-kit__chipcolor" style="background: var(<?php echo esc_attr( $brix_token ); ?>)"></span>
						<b><?php echo esc_html( $brix_name ); ?></b>
						<code class="brix-mono"><?php echo esc_html( $brix_token ); ?></code>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<!-- ——— типографіка ——— -->
		<section class="brix-kit__block">
			<h2 class="brix-kit__title">Типографіка</h2>
			<div class="brix-kit__type">
				<h1>Зібрано при 22 °Bx</h1>
				<h2>Вісім лотів, усі з паспортом</h2>
				<h3>Стиглість вимірюють у градусах Brix</h3>
				<h4>Ethiopia Guji Hambela</h4>
				<p class="brix-lead">Заголовки — Playfair Display, текст набрано Inter, дані лоту — IBM Plex Mono.</p>
				<p>Основний текст: солодкість стиглої черешні, кислотність зеленого яблука. Зібрано на висоті 2 000 м.</p>
				<p class="brix-small brix-muted">Дрібний другорядний текст — підписи, застереження, примітки.</p>
				<p class="brix-label">Моношрифтова мітка капсом</p>
				<p class="brix-mono">22.4 °Bx · 88.25 SCA · $7.80 / кг</p>
			</div>
		</section>

		<!-- ——— кнопки ——— -->
		<section class="brix-kit__block">
			<h2 class="brix-kit__title">Кнопки</h2>
			<div class="brix-kit__row">
				<button class="brix-btn" type="button">Додати в кошик</button>
				<button class="brix-btn brix-btn--dark" type="button">Оформити</button>
				<button class="brix-btn brix-btn--outline" type="button">Детальніше</button>
				<button class="brix-btn brix-btn--sm" type="button">Малий розмір</button>
				<button class="brix-btn brix-btn--xl" type="button">Великий розмір</button>
				<button class="brix-btn" type="button" disabled>Немає в наявності</button>
			</div>
			<div class="brix-kit__row brix-kit__row--dark">
				<button class="brix-btn brix-btn--ghost" type="button">Прозора на темному</button>
				<button class="brix-btn brix-btn--light" type="button">Світла на темному</button>
			</div>
		</section>

		<!-- ——— чипи, сегменти, теги ——— -->
		<section class="brix-kit__block">
			<h2 class="brix-kit__title">Елементи керування</h2>

			<h3 class="brix-kit__sub">Чипи фільтра</h3>
			<div class="brix-kit__row">
				<button class="brix-chip" type="button" aria-pressed="true">Ефіопія <span class="brix-chip__count">4</span></button>
				<button class="brix-chip" type="button" aria-pressed="false">Кенія <span class="brix-chip__count">2</span></button>
				<button class="brix-chip" type="button" aria-pressed="false">Колумбія <span class="brix-chip__count">3</span></button>
				<button class="brix-chip brix-chip--sm" type="button" aria-pressed="false">Малий чип</button>
				<button class="brix-chip" type="button" disabled>Руанда <span class="brix-chip__count">0</span></button>
			</div>

			<h3 class="brix-kit__sub">Сегмент-контрол · помел</h3>
			<div class="brix-segmented" role="group" aria-label="Помел">
				<?php
				$brix_grinds = array( 'Зерно', 'V60 / фільтр', 'Еспресо', 'Френч-прес', 'Турка' );

				foreach ( $brix_grinds as $brix_i => $brix_grind ) :
					$brix_id = 'kit-grind-' . $brix_i;
					?>
					<input
						class="brix-segmented__input"
						type="radio"
						name="kit-grind"
						id="<?php echo esc_attr( $brix_id ); ?>"
						<?php checked( 0, $brix_i ); ?>
					>
					<label class="brix-segmented__label" for="<?php echo esc_attr( $brix_id ); ?>">
						<?php echo esc_html( $brix_grind ); ?>
					</label>
				<?php endforeach; ?>
			</div>

			<h3 class="brix-kit__sub">Теги обробки</h3>
			<div class="brix-kit__row">
				<span class="brix-tag brix-tag--natural">Natural</span>
				<span class="brix-tag brix-tag--washed">Washed</span>
				<span class="brix-tag brix-tag--honey">Honey</span>
				<span class="brix-tag brix-tag--lab">Lab</span>
				<span class="brix-tag brix-tag--soft">Залишилось 24</span>
			</div>

			<h3 class="brix-kit__sub">Поля</h3>
			<div class="brix-kit__fields">
				<label class="brix-field">
					<span class="brix-field__label">Місто</span>
					<input class="brix-input" type="text" placeholder="Київ">
				</label>
				<label class="brix-field">
					<span class="brix-field__label">Відділення</span>
					<select class="brix-select">
						<option>Відділення №12, вул. Кирилівська, 41</option>
						<option>Поштомат №3481, вул. Січових Стрільців, 77</option>
					</select>
				</label>
				<label class="brix-field brix-field--invalid">
					<span class="brix-field__label">Телефон</span>
					<input class="brix-input" type="tel" value="+38 044" aria-invalid="true" aria-describedby="kit-phone-error">
					<span class="brix-field__error" id="kit-phone-error">Введіть повний номер</span>
				</label>
			</div>
		</section>

		<!-- ——— пачки ——— -->
		<section class="brix-kit__block">
			<h2 class="brix-kit__title">Пачка кави</h2>
			<p class="brix-small brix-muted">Колір кодує обробку. Мальована на CSS, розміри в <code class="brix-mono">cqw</code> — однаково виглядає в сітці й у галереї.</p>
			<div class="brix-kit__packs">
				<?php foreach ( $brix_packs as $brix_pack ) : ?>
					<div class="brix-pack brix-pack--<?php echo esc_attr( $brix_pack[1] ); ?>">
						<div class="brix-pack__top"><span>BRIX 22°</span><span><?php echo esc_html( $brix_pack[2] ); ?></span></div>
						<div class="brix-pack__seal">22<small>°Bx</small></div>
						<div class="brix-pack__name"><?php echo esc_html( $brix_pack[0] ); ?></div>
						<div class="brix-pack__data">
							<span><?php echo esc_html( $brix_pack[3] ); ?></span>
							<span><?php echo esc_html( $brix_pack[4] ); ?></span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<h3 class="brix-kit__sub">Та сама пачка в трьох розмірах</h3>
			<div class="brix-kit__scale">
				<div class="brix-kit__scale-item brix-kit__scale-item--sm"><div class="brix-pack brix-pack--natural"><div class="brix-pack__top"><span>BRIX 22°</span><span>Natural</span></div><div class="brix-pack__seal">22<small>°Bx</small></div><div class="brix-pack__name">Ethiopia Guji</div><div class="brix-pack__data"><span>250 г</span><span>ET-GJ-14</span></div></div></div>
				<div class="brix-kit__scale-item brix-kit__scale-item--md"><div class="brix-pack brix-pack--natural"><div class="brix-pack__top"><span>BRIX 22°</span><span>Natural</span></div><div class="brix-pack__seal">22<small>°Bx</small></div><div class="brix-pack__name">Ethiopia Guji</div><div class="brix-pack__data"><span>250 г</span><span>ET-GJ-14</span></div></div></div>
				<div class="brix-kit__scale-item brix-kit__scale-item--lg"><div class="brix-pack brix-pack--natural"><div class="brix-pack__top"><span>BRIX 22°</span><span>Natural</span></div><div class="brix-pack__seal">22<small>°Bx</small></div><div class="brix-pack__name">Ethiopia Guji</div><div class="brix-pack__data"><span>250 г</span><span>ET-GJ-14</span></div></div></div>
			</div>
		</section>

		<!-- ——— картка товару ——— -->
		<section class="brix-kit__block">
			<h2 class="brix-kit__title">Картка товару</h2>
			<div class="brix-card-grid">
				<?php
				$brix_cards = array(
					array( 'Ethiopia Guji Hambela', 'natural', 'Natural', 'Черешня · бергамот · какао', '590 ₴', '22.4 °Bx', '88.25 SCA', false ),
					array( 'Kenya Nyeri Gathaithi', 'washed', 'Washed', 'Смородина · томат · цукор', '620 ₴', '21.8 °Bx', '87.75 SCA', false ),
					array( 'Panama Geisha Lab', 'lab', 'Lab', 'Жасмин · персик · мед', '1 240 ₴', '23.1 °Bx', '91.00 SCA', true ),
				);

				foreach ( $brix_cards as $brix_card ) :
					?>
					<article class="brix-card <?php echo $brix_card[7] ? 'brix-card--sold-out' : ''; ?>">
						<div class="brix-card__stage brix-card__stage--<?php echo esc_attr( $brix_card[1] ); ?>">
							<span class="brix-tag brix-tag--<?php echo esc_attr( $brix_card[1] ); ?> brix-card__tag"><?php echo esc_html( $brix_card[2] ); ?></span>
							<div class="brix-pack brix-pack--<?php echo esc_attr( $brix_card[1] ); ?>">
								<div class="brix-pack__top"><span>BRIX 22°</span><span><?php echo esc_html( $brix_card[2] ); ?></span></div>
								<div class="brix-pack__seal">22<small>°Bx</small></div>
								<div class="brix-pack__name"><?php echo esc_html( $brix_card[0] ); ?></div>
								<div class="brix-pack__data"><span>250 г</span></div>
							</div>
							<?php if ( ! $brix_card[7] ) : ?>
								<a class="brix-card__add" href="#" aria-label="Додати в кошик">
									<?php echo brix_icon( 'bag' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</a>
							<?php endif; ?>
						</div>

						<div class="brix-card__head">
							<span class="brix-card__name"><a href="#"><?php echo esc_html( $brix_card[0] ); ?></a></span>
							<span class="brix-card__price"><?php echo esc_html( $brix_card[4] ); ?></span>
						</div>
						<p class="brix-card__notes"><?php echo esc_html( $brix_card[3] ); ?></p>
						<p class="brix-card__data">
							<span><b><?php echo esc_html( $brix_card[5] ); ?></b></span>
							<span><b><?php echo esc_html( $brix_card[6] ); ?></b></span>
						</p>
					</article>
				<?php endforeach; ?>
			</div>
		</section>

		<!-- ——— дані лоту ——— -->
		<section class="brix-kit__block">
			<h2 class="brix-kit__title">Паспорт лоту</h2>

			<div class="brix-data-grid">
				<?php
				$brix_lot = array(
					'Країна'     => 'Ефіопія',
					'Регіон'     => 'Гуджі, Хамбела',
					'Висота'     => '2 050–2 200 м',
					'Різновид'   => 'Heirloom 74158',
					'Обробка'    => 'Natural, 18 днів',
					'Оцінка SCA' => '88.25',
				);

				foreach ( $brix_lot as $brix_key => $brix_val ) :
					?>
					<div class="brix-data-grid__cell">
						<span class="brix-data-grid__label"><?php echo esc_html( $brix_key ); ?></span>
						<b class="brix-data-grid__value"><?php echo esc_html( $brix_val ); ?></b>
					</div>
				<?php endforeach; ?>
			</div>

			<h3 class="brix-kit__sub">Шкала °Bx</h3>
			<div class="brix-scale">
				<div class="brix-scale__track">
					<span class="brix-scale__band" style="--brix-left:42.8%;--brix-width:28.6%"></span>
					<?php
					// Шкала від 14 до 28 °Bx, поділка кожні 2 градуси.
					for ( $brix_v = 14; $brix_v <= 28; $brix_v += 2 ) :
						$brix_pos = ( $brix_v - 14 ) / 14 * 100;
						?>
						<span class="brix-scale__tick" style="--brix-left:<?php echo esc_attr( (string) round( $brix_pos, 2 ) ); ?>%">
							<span><?php echo esc_html( (string) $brix_v ); ?></span>
						</span>
					<?php endfor; ?>
					<span class="brix-scale__mark" style="--brix-left:60%">
						<b>22.4</b><i></i>
					</span>
				</div>
			</div>

			<h3 class="brix-kit__sub">Профіль смаку</h3>
			<div class="brix-taste">
				<?php foreach ( $brix_taste as $brix_key => $brix_score ) : ?>
					<div class="brix-taste__row">
						<span><?php echo esc_html( $brix_key ); ?></span>
						<span class="brix-taste__track">
							<span class="brix-taste__fill" style="--brix-value:<?php echo esc_attr( (string) ( $brix_score * 20 ) ); ?>%"></span>
						</span>
						<span class="brix-taste__value"><?php echo esc_html( $brix_score . '/5' ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<h3 class="brix-kit__sub">Свіжість</h3>
			<div class="brix-freshness">
				<span class="brix-freshness__track">
					<span class="brix-freshness__peak" style="--brix-left:20%;--brix-width:55%"></span>
					<span class="brix-freshness__now" style="--brix-now:34%"></span>
				</span>
				<span class="brix-freshness__row">
					<span>Дегазація</span><span>Найкраще</span><span>Ще смачно</span>
				</span>
			</div>
		</section>

		<!-- ——— плейсхолдери фото ——— -->
		<section class="brix-kit__block">
			<h2 class="brix-kit__title">Плейсхолдери фото</h2>
			<div class="brix-kit__photos">
				<?php
				$brix_photos = array(
					''         => 'Макро зерна',
					'--warm'   => 'Процес обсмаження',
					'--cherry' => 'Ягода на гілці',
					'--green'  => 'Сушильні ліжка',
					'--dark'   => 'Цех уночі',
				);

				foreach ( $brix_photos as $brix_mod => $brix_caption ) :
					?>
					<div class="<?php echo esc_attr( trim( 'brix-photo ' . ( $brix_mod ? 'brix-photo' . $brix_mod : '' ) ) ); ?>">
						<span class="brix-photo__caption"><?php echo esc_html( $brix_caption ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

	</div>
</div>

<?php
get_footer();
