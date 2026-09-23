<?php
/**
 * Рядок відправлення в підсумках кошика й замовлення.
 *
 * Override шаблону WooCommerce. Він один на дві сторінки, і поводиться
 * на них по-різному.
 *
 * У кошику це вибір: два варіанти — привезти чи забрати. Адреси
 * призначення тут немає навмисно. Стандартний рядок «Доставка до
 * 10025. Зміна адреси» показував індекс, якого покупець не вводив, і
 * пропонував правити адресу там, де її ще ніхто не питав; на ціну ж
 * вона однаково не впливає — тариф однаковий по всій країні.
 *
 * У checkout вибір уже зроблено вище, в блоці «Спосіб отримання». Тут
 * лишається рядок підсумку — назва й ціна. Два набори радіокнопок з
 * однаковим ім'ям в одній формі сперечалися б за те, яку саме обрав
 * покупець.
 *
 * @package BRIX
 * @var array  $package
 * @var int    $index
 * @var array  $available_methods
 * @var string $chosen_method
 */

defined( 'ABSPATH' ) || exit;

$brix_methods = ! empty( $available_methods ) && is_array( $available_methods ) ? $available_methods : array();
$brix_label   = __( 'Відправлення', 'brix' );

/*
 * Одна комірка на всю ширину, а не пара «підпис | значення». Назви
 * способів довші за колонку з числами: у вузькому підсумку кошика
 * «Нова Пошта — відділення» ламалось на три рядки, а ціна з'їжджала
 * під нього. Підпис тут звичайним рядком над варіантами.
 */
?>
<tr class="woocommerce-shipping-totals shipping">
	<td class="brix-ship__cell" colspan="2" data-title="<?php echo esc_attr( $brix_label ); ?>">
		<span class="brix-ship__label"><?php echo esc_html( $brix_label ); ?></span>
		<?php if ( ! $brix_methods ) : ?>

			<p class="brix-small brix-muted"><?php esc_html_e( 'Спосіб доставки з’явиться, щойно в кошику буде хоч одна пачка.', 'brix' ); ?></p>

		<?php elseif ( is_checkout() ) : ?>

			<?php
			$brix_chosen = $brix_methods[ $chosen_method ] ?? reset( $brix_methods );
			$brix_cost   = (float) $brix_chosen->get_cost();
			?>
			<span class="brix-ship__chosen"><?php echo esc_html( $brix_chosen->get_label() ); ?></span>
			<span class="brix-ship__chosen-cost brix-num">
				<?php
				echo $brix_cost > 0
					? wp_kses_post( wc_price( $brix_cost ) )
					: esc_html__( 'Безкоштовно', 'brix' );
				?>
			</span>

		<?php else : ?>

			<ul id="shipping_method" class="woocommerce-shipping-methods brix-ship__list">
				<?php foreach ( $brix_methods as $brix_method ) : ?>
					<?php
					$brix_id    = $brix_method->get_id();
					$brix_input = 'shipping_method_' . (int) $index . '_' . sanitize_title( $brix_id );
					$brix_cost  = (float) $brix_method->get_cost();
					?>
					<li>
						<input
							type="<?php echo 1 < count( $brix_methods ) ? 'radio' : 'hidden'; ?>"
							name="shipping_method[<?php echo esc_attr( (string) $index ); ?>]"
							data-index="<?php echo esc_attr( (string) $index ); ?>"
							id="<?php echo esc_attr( $brix_input ); ?>"
							value="<?php echo esc_attr( $brix_id ); ?>"
							class="shipping_method"
							<?php checked( $brix_id, $chosen_method ); ?>>

						<label for="<?php echo esc_attr( $brix_input ); ?>">
							<span><?php echo esc_html( $brix_method->get_label() ); ?></span>
							<span class="brix-num">
								<?php
								echo $brix_cost > 0
									? wp_kses_post( wc_price( $brix_cost ) )
									: esc_html__( 'Безкоштовно', 'brix' );
								?>
							</span>
						</label>

						<?php do_action( 'woocommerce_after_shipping_rate', $brix_method, $index ); ?>
					</li>
				<?php endforeach; ?>
			</ul>

		<?php endif; ?>
	</td>
</tr>
