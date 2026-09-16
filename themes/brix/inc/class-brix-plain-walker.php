<?php
/**
 * Walker меню, який друкує голі посилання.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Друкує пункти меню голими посиланнями, без <ul> і <li>.
 *
 * Колонки підвалу в макеті — просто стовпчик посилань; обгортки списку
 * тут лише заважали б розкладці на flex.
 */
class Brix_Plain_Walker extends Walker_Nav_Menu {

	/**
	 * Початок елемента.
	 *
	 * @param string   $output Накопичений HTML.
	 * @param WP_Post  $item   Пункт меню.
	 * @param int      $depth  Рівень вкладеності.
	 * @param stdClass $args   Аргументи wp_nav_menu().
	 * @param int      $id     ID пункту. Не використовується.
	 * @return void
	 */
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		/*
		 * Заголовок пункту вже зберігається як HTML (&amp;, &#8217; і таке
		 * інше), тож esc_html() тут екранував би його вдруге. Ядро в
		 * Walker_Nav_Menu робить так само: фільтр the_title і вивід як є.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Хук ядра, не власний: застосовуємо, а не оголошуємо.
		$title = apply_filters( 'the_title', $item->title, $item->ID );

		$output .= sprintf(
			'<a href="%s">%s</a>',
			esc_url( $item->url ),
			wp_kses_post( $title )
		);
	}

	/**
	 * Кінець елемента. Нічого не друкує.
	 *
	 * @param string   $output Накопичений HTML.
	 * @param WP_Post  $item   Пункт меню.
	 * @param int      $depth  Рівень вкладеності.
	 * @param stdClass $args   Аргументи wp_nav_menu().
	 * @return void
	 */
	public function end_el( &$output, $item, $depth = 0, $args = null ) {}
}
