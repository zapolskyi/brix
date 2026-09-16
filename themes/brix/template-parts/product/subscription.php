<?php
/**
 * Перемикач «разова покупка / підписка BRIX Club».
 *
 * Поки що показує різницю в ціні й веде на сторінку клубу: сама
 * підписка — фаза 6. Показувати перемикач уже зараз варто, бо він
 * є в макеті картки товару й формує очікування покупця.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

$brix_product = $args['product'] ?? null;

if ( ! $brix_product instanceof WC_Product || ! $brix_product->get_price() ) {
	return;
}

/**
 * Знижка учасникам клубу, у відсотках.
 *
 * @param int $discount Відсоток.
 */
$brix_discount = (int) apply_filters( 'brix_club_discount', 10 );
$brix_club     = (float) $brix_product->get_price() * ( 100 - $brix_discount ) / 100;
$brix_club_url = get_page_by_path( 'club' );
?>

<div class="brix-plans">
	<div class="brix-plans__option is-active">
		<span class="brix-plans__label"><?php esc_html_e( 'Разова покупка', 'brix' ); ?></span>
		<b class="brix-mono"><?php echo esc_html( wp_strip_all_tags( wc_price( (float) $brix_product->get_price() ) ) ); ?></b>
	</div>

	<a class="brix-plans__option" href="<?php echo esc_url( $brix_club_url ? (string) get_permalink( $brix_club_url ) : home_url( '/club/' ) ); ?>">
		<span class="brix-plans__label">
			<?php esc_html_e( 'BRIX Club · кожні 2 тижні', 'brix' ); ?>
			<small class="brix-muted">
				<?php
				printf(
					/* translators: %d — відсоток знижки. */
					esc_html__( '−%d%%, пауза й пропуск у кабінеті', 'brix' ),
					absint( $brix_discount )
				);
				?>
			</small>
		</span>
		<b class="brix-mono"><?php echo esc_html( wp_strip_all_tags( wc_price( round( $brix_club ) ) ) ); ?></b>
	</a>
</div>
