<?php
/**
 * Сортування каталогу.
 *
 * Override шаблону WooCommerce. Рідний шаблон надсилає форму скриптом
 * Woo і не має кнопки взагалі — без JavaScript селект мертвий. Крім
 * того, він переносить лише `orderby` і `paged`, тож вибір сортування
 * скидав усі активні фільтри.
 *
 * Тут кнопка є, а решта фільтрів їде прихованими полями. Скрипт
 * каталогу кнопку ховає й надсилає форму сам — тоді сортування
 * застосовується одразу при виборі.
 *
 * Класи `woocommerce-ordering` і `orderby` прибрані навмисно.
 * `woocommerce.js` вішає на них делегований обробник, який робить
 * jQuery `.trigger('submit')`, а той у підсумку викликає нативний
 * `form.submit()`. Нативний метод не породжує події submit узагалі,
 * тож перехопити таку відправку неможливо: сторінка перезавантажується
 * попри будь-який `preventDefault()`. Інших класів Woo тут не тримає.
 *
 * @package BRIX
 *
 * @var array<string, string> $catalog_orderby_options Варіанти сортування.
 * @var string                $orderby                 Обране сортування.
 */

defined( 'ABSPATH' ) || exit;

?>
<form class="brix-ordering" method="get" action="<?php echo esc_url( brix_catalog_url() ); ?>" data-brix-ordering>
	<label class="brix-visually-hidden" for="brix-orderby"><?php esc_html_e( 'Сортування', 'brix' ); ?></label>

	<select class="brix-select brix-ordering__select" name="orderby" id="brix-orderby">
		<?php foreach ( $catalog_orderby_options as $brix_id => $brix_name ) : ?>
			<option value="<?php echo esc_attr( $brix_id ); ?>" <?php selected( $orderby, $brix_id ); ?>>
				<?php echo esc_html( $brix_name ); ?>
			</option>
		<?php endforeach; ?>
	</select>

	<?php
	/*
	 * Фільтри переносяться прихованими полями: інакше submit цієї
	 * форми лишив би в адресі один orderby, а всі чипи злетіли б.
	 * Пагінація навмисно не переноситься — нове сортування починається
	 * з першої сторінки.
	 */
	foreach ( brix_current_filter_args() as $brix_name => $brix_value ) :
		if ( 'orderby' === $brix_name ) {
			continue;
		}
		?>
		<input type="hidden" name="<?php echo esc_attr( $brix_name ); ?>" value="<?php echo esc_attr( $brix_value ); ?>">
	<?php endforeach; ?>

	<button class="brix-btn brix-btn--outline brix-btn--sm brix-ordering__apply" type="submit" data-brix-ordering-apply>
		<?php esc_html_e( 'Сортувати', 'brix' ); ?>
	</button>
</form>
