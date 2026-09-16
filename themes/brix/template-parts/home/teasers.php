<?php
/**
 * Врізки розділів: квіз, клуб, B2B.
 *
 * Розмітка й посилання готові вже зараз, самі розділи приходять
 * у фазі 3Б і фазі 6.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_teasers = array(
	array(
		'label'  => __( 'Підбір за хвилину', 'brix' ),
		'title'  => __( 'Не знаєте, з чого почати?', 'brix' ),
		'text'   => __( 'Шість питань про те, як ви заварюєте і що любите в чашці. На виході — три лоти з уже виставленим помелом.', 'brix' ),
		'button' => __( 'Пройти квіз', 'brix' ),
		'url'    => brix_page_url( 'quiz' ),
		'photo'  => 'brix-photo--green',
		'dark'   => false,
	),
	array(
		'label'  => __( 'BRIX Club', 'brix' ),
		'title'  => __( 'Свіжа кава приїжджає сама', 'brix' ),
		'text'   => __( 'Підписка кожні 2 або 4 тижні зі знижкою 10%. Паузу, пропуск і зміну лоту вмикаєте в кабінеті.', 'brix' ),
		'button' => __( 'Як це працює', 'brix' ),
		'url'    => brix_page_url( 'club' ),
		'photo'  => 'brix-photo--cherry',
		'dark'   => false,
	),
);
?>

<?php foreach ( $brix_teasers as $brix_index => $brix_teaser ) : ?>
	<section class="brix-section brix-section--tight">
		<div class="brix-wrap brix-teaser <?php echo 1 === $brix_index % 2 ? 'brix-teaser--reverse' : ''; ?>">
			<div class="brix-teaser__text">
				<p class="brix-label"><?php echo esc_html( $brix_teaser['label'] ); ?></p>
				<h2><?php echo esc_html( $brix_teaser['title'] ); ?></h2>
				<p class="brix-lead brix-muted"><?php echo esc_html( $brix_teaser['text'] ); ?></p>
				<p>
					<a class="brix-btn brix-btn--outline" href="<?php echo esc_url( $brix_teaser['url'] ); ?>">
						<?php echo esc_html( $brix_teaser['button'] ); ?>
						<?php echo brix_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				</p>
			</div>

			<div class="brix-photo <?php echo esc_attr( $brix_teaser['photo'] ); ?> brix-teaser__photo">
				<span class="brix-photo__caption"><?php echo esc_html( $brix_teaser['title'] ); ?></span>
			</div>
		</div>
	</section>
<?php endforeach; ?>
