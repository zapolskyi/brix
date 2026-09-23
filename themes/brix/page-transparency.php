<?php
/**
 * Прозорість: скільки отримав фермер за кожен лот.
 *
 * Таблиця збирається з полів товарів, а не ведеться руками. Інакше
 * вона розійдеться з каталогом наступного ж сезону — а сторінка, яка
 * обіцяє прозорість і показує застарілі цифри, гірша за її відсутність.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

get_header();

$brix_rows = brix_transparency_rows();
?>

<div class="brix-section brix-section--tight">
	<div class="brix-wrap">
		<header class="brix-catalog__head">
			<p class="brix-label"><?php esc_html_e( 'Прозорість', 'brix' ); ?></p>
			<h1><?php the_title(); ?></h1>
			<p class="brix-lead brix-muted">
				<?php esc_html_e( 'Скільки коштує кілограм зеленої кави на фермі й скільки ви платите за пачку. Цифри беруться з паспорта кожного лоту й оновлюються разом із каталогом.', 'brix' ); ?>
			</p>
		</header>

		<?php if ( get_the_content() ) : ?>
			<div class="brix-prose"><?php the_content(); ?></div>
		<?php endif; ?>

		<?php if ( $brix_rows ) : ?>
			<div class="brix-table-wrap">
				<table class="brix-table">
					<caption class="brix-visually-hidden"><?php esc_html_e( 'Закупівельні ціни за лотами', 'brix' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Лот', 'brix' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Країна', 'brix' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Виробник', 'brix' ); ?></th>
							<th scope="col" class="brix-table__num"><?php esc_html_e( 'Ціна фермеру, $/кг', 'brix' ); ?></th>
							<th scope="col" class="brix-table__num">
								<?php
								printf(
									/* translators: %s — символ валюти магазину. */
									esc_html__( 'Наша ціна, %s/кг', 'brix' ),
									esc_html( html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) )
								);
								?>
							</th>
							<th scope="col" class="brix-table__num"><?php esc_html_e( '°Bx', 'brix' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $brix_rows as $brix_row ) : ?>
							<tr>
								<th scope="row">
									<a href="<?php echo esc_url( $brix_row['url'] ); ?>"><?php echo esc_html( $brix_row['name'] ); ?></a>
									<?php if ( '' !== $brix_row['code'] ) : ?>
										<span class="brix-label"><?php echo esc_html( $brix_row['code'] ); ?></span>
									<?php endif; ?>
								</th>
								<td><?php echo esc_html( $brix_row['country'] ); ?></td>
								<td>
									<?php if ( '' !== $brix_row['farm_url'] ) : ?>
										<a href="<?php echo esc_url( $brix_row['farm_url'] ); ?>"><?php echo esc_html( $brix_row['farm'] ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $brix_row['farm'] ); ?>
									<?php endif; ?>
								</td>
								<td class="brix-table__num brix-num"><?php echo esc_html( $brix_row['fob'] ); ?></td>
								<td class="brix-table__num brix-num"><?php echo esc_html( $brix_row['retail'] ); ?></td>
								<td class="brix-table__num brix-num"><?php echo esc_html( $brix_row['brix'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<p class="brix-small brix-muted brix-transparency__note">
				<?php esc_html_e( 'Ціна фермеру — FOB, тобто вартість зеленої кави з доставкою в порт відправлення. У нашу ціну поверх неї входять логістика, мито, втрата ваги при обсмаженні (близько 16%), пакування, податки й робота.', 'brix' ); ?>
			</p>
		<?php else : ?>
			<div class="brix-empty">
				<h2><?php esc_html_e( 'Даних поки немає', 'brix' ); ?></h2>
				<p class="brix-muted"><?php esc_html_e( 'Таблиця заповнюється з паспортів лотів. Щойно в каталозі зʼявляться лоти з ціною фермеру — вони будуть тут.', 'brix' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php
get_footer();
