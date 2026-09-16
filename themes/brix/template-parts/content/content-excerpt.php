<?php
/**
 * Запис у списку: дата, заголовок, короткий опис.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;
?>

<article <?php post_class( 'brix-post-card' ); ?>>
	<p class="brix-label"><?php echo esc_html( get_the_date() ); ?></p>

	<h2 class="brix-post-card__title">
		<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
	</h2>

	<?php if ( has_excerpt() ) : ?>
		<p class="brix-small brix-muted"><?php echo esc_html( get_the_excerpt() ); ?></p>
	<?php endif; ?>
</article>
