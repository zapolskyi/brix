<?php
/**
 * Шапка сайту: смуга оголошень, логотип, навігація, дії.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>

<body <?php body_class( 'brix-page' ); ?>>
<?php wp_body_open(); ?>

<a class="brix-skip-link" href="#brix-content"><?php esc_html_e( 'Перейти до вмісту', 'brix' ); ?></a>

<?php brix_announce(); ?>

<header class="brix-header">
	<?php brix_logo(); ?>

	<nav class="brix-nav" aria-label="<?php esc_attr_e( 'Головне меню', 'brix' ); ?>">
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'brix-nav__list',
				'depth'          => 2,
				'fallback_cb'    => false,
			)
		);
		?>
	</nav>

	<div class="brix-header__actions">
		<span class="brix-lang" aria-label="<?php esc_attr_e( 'Мова сайту', 'brix' ); ?>">
			<b><?php esc_html_e( 'UA', 'brix' ); ?></b> / EN
		</span>

		<?php
		/*
		 * Пошук на <details>: без JavaScript панель усе одно
		 * розкривається, поле приймає запит, Enter надсилає форму —
		 * і сторінка результатів відкривається звичайним переходом.
		 * Скрипт лише додає підказки під полем.
		 */
		?>
		<details class="brix-search" data-brix-search>
			<summary class="brix-iconbtn" aria-label="<?php esc_attr_e( 'Пошук', 'brix' ); ?>">
				<?php echo brix_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</summary>

			<div class="brix-search__panel">
				<form class="brix-search__form" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" role="search">
					<label class="brix-visually-hidden" for="brix-search-field">
						<?php esc_html_e( 'Що шукаємо?', 'brix' ); ?>
					</label>

					<input
						class="brix-input brix-search__field"
						id="brix-search-field"
						type="search"
						name="s"
						value="<?php echo esc_attr( get_search_query() ); ?>"
						placeholder="<?php esc_attr_e( 'Країна, ферма, нота смаку', 'brix' ); ?>"
						autocomplete="off"
						data-brix-search-field
					>

					<button class="brix-btn brix-btn--dark brix-search__submit" type="submit">
						<?php esc_html_e( 'Знайти', 'brix' ); ?>
					</button>
				</form>

				<div class="brix-suggest" data-brix-suggest role="listbox"
					aria-label="<?php esc_attr_e( 'Підказки пошуку', 'brix' ); ?>"></div>
			</div>
		</details>

		<?php if ( brix_has_woocommerce() ) : ?>
			<a class="brix-iconbtn" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
				<?php echo brix_icon( 'account' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="brix-visually-hidden"><?php esc_html_e( 'Мій кабінет', 'brix' ); ?></span>
			</a>

			<?php $brix_bag = brix_bag_count(); ?>
			<a class="brix-iconbtn brix-bag" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
				<?php echo brix_icon( 'bag' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="brix-visually-hidden"><?php esc_html_e( 'Кошик', 'brix' ); ?></span>
				<?php if ( $brix_bag > 0 ) : ?>
					<em class="brix-bag__count" aria-hidden="true"><?php echo esc_html( (string) $brix_bag ); ?></em>
				<?php endif; ?>
			</a>
		<?php endif; ?>

		<button
			class="brix-iconbtn brix-burger"
			type="button"
			data-brix-nav-toggle
			aria-expanded="false"
			aria-controls="brix-mobile-nav"
			aria-label="<?php esc_attr_e( 'Відкрити меню', 'brix' ); ?>"
		>
			<?php echo brix_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</button>
	</div>
</header>

<div class="brix-mobile-nav" id="brix-mobile-nav" hidden>
	<?php
	wp_nav_menu(
		array(
			'theme_location' => 'primary',
			'container'      => false,
			'menu_class'     => 'brix-mobile-nav__list',
			'depth'          => 2,
			'fallback_cb'    => false,
		)
	);
	?>
</div>

<main class="brix-main" id="brix-content">
