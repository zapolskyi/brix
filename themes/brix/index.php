<?php
/**
 * Загальний шаблон: архіви, блог, результати пошуку.
 *
 * Запасний варіант ієрархії шаблонів. Спеціалізовані екрани магазину
 * живуть у woocommerce/, контентні — у page.php і single.php.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="brix-section brix-section--tight">
	<div class="brix-wrap">
		<?php if ( have_posts() ) : ?>

			<header class="brix-section__head">
				<h1>
					<?php
					if ( is_search() ) {
						printf(
							/* translators: %s — пошуковий запит. */
							esc_html__( 'Результати за запитом «%s»', 'brix' ),
							esc_html( get_search_query() )
						);
					} elseif ( is_archive() ) {
						the_archive_title();
					} else {
						esc_html_e( 'Журнал', 'brix' );
					}
					?>
				</h1>
			</header>

			<div class="brix-posts">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/content/content', 'excerpt' );
				endwhile;
				?>
			</div>

			<?php
			the_posts_pagination(
				array(
					'mid_size'  => 1,
					'prev_text' => esc_html__( 'Назад', 'brix' ),
					'next_text' => esc_html__( 'Далі', 'brix' ),
				)
			);
			?>

		<?php else : ?>
			<?php get_template_part( 'template-parts/content/content', 'none' ); ?>
		<?php endif; ?>
	</div>
</div>

<?php
get_footer();
