<?php
/**
 * Сайдбар фільтрів каталогу.
 *
 * Кожен чип — звичайне посилання, кожна група — <fieldset> з легендою.
 * Без JavaScript фільтри працюють повністю; на фазі 4 той самий
 * сайдбар перехопить AJAX.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_filters = brix_catalog_filters();
$brix_price   = brix_price_filter();
$brix_total   = (int) wc_get_loop_prop( 'total' );
?>

<form class="brix-filters" method="get" action="<?php echo esc_url( brix_catalog_url() ); ?>">
	<div class="brix-filters__head">
		<h2 class="brix-filters__title"><?php esc_html_e( 'Фільтри', 'brix' ); ?></h2>

		<?php if ( brix_has_active_filters() ) : ?>
			<a class="brix-filters__reset" href="<?php echo esc_url( brix_catalog_url() ); ?>" data-brix-reset>
				<?php esc_html_e( 'Скинути', 'brix' ); ?>
			</a>
		<?php endif; ?>

		<?php
		/*
		 * Хрестик закриває шторку скриптом. Без JavaScript він схований,
		 * і шторка закривається натиском на затемнення або на сам
		 * <summary> — обидва ведуть до того самого перемикача <details>.
		 */
		?>
		<button class="brix-filters__close" type="button" data-brix-drawer-close
			aria-label="<?php esc_attr_e( 'Закрити фільтри', 'brix' ); ?>">
			<?php echo brix_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</button>
	</div>

	<?php
	/*
	 * Обгортка потрібна лише мобільній шторці: там це область, яка
	 * прокручується між нерухомими шапкою й кнопкою. На десктопі вона
	 * прибрана з розкладки через display:contents.
	 */
	?>
	<div class="brix-filters__body">

	<?php
	foreach ( $brix_filters as $brix_key => $brix_filter ) :
		$brix_active = brix_active_filter( $brix_key );

		if ( ! empty( $brix_filter['taxonomy'] ) ) {
			$brix_terms = get_terms(
				array(
					'taxonomy'   => $brix_filter['taxonomy'],
					'hide_empty' => true,
					'parent'     => ! empty( $brix_filter['parent_only'] ) ? 0 : '',
				)
			);

			if ( is_wp_error( $brix_terms ) || ! $brix_terms ) {
				continue;
			}

			$brix_options = array();

			foreach ( $brix_terms as $brix_term ) {
				$brix_options[ $brix_term->slug ] = array(
					'label' => $brix_term->name,
					'count' => brix_term_count( $brix_term ),
				);
			}
		} else {
			$brix_options = array();

			foreach ( (array) ( $brix_filter['options'] ?? array() ) as $brix_slug => $brix_label ) {
				$brix_options[ $brix_slug ] = array(
					'label' => $brix_label,
					'count' => null,
				);
			}
		}

		if ( ! $brix_options ) {
			continue;
		}
		?>
		<fieldset class="brix-filters__group">
			<legend class="brix-filters__legend"><?php echo esc_html( $brix_filter['label'] ); ?></legend>

			<div class="brix-filters__options">
				<?php foreach ( $brix_options as $brix_slug => $brix_option ) : ?>
					<?php $brix_on = in_array( (string) $brix_slug, $brix_active, true ); ?>
					<a
						class="brix-chip<?php echo $brix_on ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( brix_filter_toggle_url( $brix_key, (string) $brix_slug ) ); ?>"
						aria-pressed="<?php echo $brix_on ? 'true' : 'false'; ?>"
						data-brix-filter="<?php echo esc_attr( (string) $brix_key ); ?>"
						data-brix-value="<?php echo esc_attr( (string) $brix_slug ); ?>"
						rel="nofollow"
					>
						<?php echo esc_html( $brix_option['label'] ); ?>
						<?php if ( null !== $brix_option['count'] ) : ?>
							<span class="brix-chip__count"><?php echo esc_html( (string) $brix_option['count'] ); ?></span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</div>
		</fieldset>
	<?php endforeach; ?>

	<fieldset class="brix-filters__group">
		<legend class="brix-filters__legend"><?php esc_html_e( 'Ціна', 'brix' ); ?></legend>

		<div class="brix-filters__price">
			<label class="brix-field">
				<span class="brix-field__label"><?php esc_html_e( 'від, ₴', 'brix' ); ?></span>
				<input class="brix-input" type="number" name="price_min" min="0" step="10"
					value="<?php echo esc_attr( (string) ( $brix_price['min'] ?? '' ) ); ?>">
			</label>
			<label class="brix-field">
				<span class="brix-field__label"><?php esc_html_e( 'до, ₴', 'brix' ); ?></span>
				<input class="brix-input" type="number" name="price_max" min="0" step="10"
					value="<?php echo esc_attr( (string) ( $brix_price['max'] ?? '' ) ); ?>">
			</label>
		</div>
	</fieldset>

	<label class="brix-filters__stock">
		<input type="checkbox" name="in_stock" value="1" <?php checked( brix_in_stock_only() ); ?>>
		<span><?php esc_html_e( 'Тільки в наявності', 'brix' ); ?></span>
	</label>

	<?php
	/*
	 * Чипи вище — посилання, тож переносять свій стан самі. А поля
	 * ціни й галочка надсилаються формою, і без цих hidden решта
	 * фільтрів злетіла б при submit.
	 */
	foreach ( brix_current_filter_args() as $brix_name => $brix_value ) :
		if ( in_array( $brix_name, array( 'price_min', 'price_max', 'in_stock' ), true ) ) {
			continue;
		}
		?>
		<input type="hidden" name="<?php echo esc_attr( $brix_name ); ?>" value="<?php echo esc_attr( $brix_value ); ?>">
	<?php endforeach; ?>

	</div><!-- .brix-filters__body -->

	<?php
	/*
	 * Кнопка робить дві роботи. Без JavaScript вона надсилає форму —
	 * так застосовуються ціна й галочка наявності, а сторінка
	 * перезавантажується вже з результатом, тобто шторка закривається
	 * сама. Зі скриптом форма йде через AJAX, і шторку закриває він.
	 *
	 * Кількість у підписі — це те, що покупець побачить після
	 * застосування: чипи вже враховані в поточному запиті.
	 */
	?>
	<div class="brix-filters__foot">
		<button class="brix-btn brix-btn--dark brix-btn--full brix-filters__apply" type="submit" data-brix-apply>
			<?php
			printf(
				/* translators: %s — кількість товарів. */
				esc_html__( 'Показати %s', 'brix' ),
				esc_html( brix_catalog_count_text( $brix_total ) )
			);
			?>
		</button>
	</div>
</form>
