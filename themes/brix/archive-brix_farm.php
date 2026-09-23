<?php
/**
 * Архів виробників.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="brix-section brix-section--tight">
	<div class="brix-wrap">
		<header class="brix-catalog__head">
			<p class="brix-label"><?php esc_html_e( 'Прямі стосунки', 'brix' ); ?></p>
			<h1><?php esc_html_e( 'Виробники', 'brix' ); ?></h1>
			<p class="brix-lead brix-muted">
				<?php esc_html_e( 'Ферми й станції, з якими працюємо напряму. Для кожної — скільки років співпраці, що вона вирощує і скільки ми за це платимо.', 'brix' ); ?>
			</p>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="brix-tiles">
				<?php
				while ( have_posts() ) :
					the_post();

					$brix_since   = (int) get_post_meta( get_the_ID(), 'brix_farm_since_year', true );
					$brix_house   = (int) get_post_meta( get_the_ID(), 'brix_farm_households', true );
					$brix_country = get_the_terms( get_the_ID(), 'brix_country' );
					?>
					<a class="brix-tile" href="<?php the_permalink(); ?>">
						<?php brix_farm_photo( get_the_ID(), 'brix-photo--warm brix-tile__photo', 'span' ); ?>
						<span class="brix-tile__title"><?php the_title(); ?></span>
						<span class="brix-tile__meta brix-label">
							<?php
							$brix_bits = array();

							if ( is_array( $brix_country ) ) {
								$brix_bits[] = $brix_country[0]->name;
							}

							if ( $brix_since ) {
								/* translators: %d — рік початку співпраці. */
								$brix_bits[] = sprintf( __( 'з %d', 'brix' ), $brix_since );
							}

							if ( $brix_house ) {
								$brix_bits[] = sprintf(
									/* translators: %s — кількість господарств. */
									brix_plural( $brix_house, __( '%s господарство', 'brix' ), __( '%s господарства', 'brix' ), __( '%s господарств', 'brix' ) ),
									\Brix\Core\Support\Format::number( (float) $brix_house )
								);
							}

							echo esc_html( implode( ' · ', $brix_bits ) );
							?>
						</span>
					</a>
					<?php
				endwhile;
				?>
			</div>

			<?php the_posts_pagination( array( 'mid_size' => 1 ) ); ?>
		<?php else : ?>
			<?php get_template_part( 'template-parts/content/content', 'none' ); ?>
		<?php endif; ?>
	</div>
</div>

<?php
get_footer();
