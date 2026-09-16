<?php
/**
 * Структуровані дані товару.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Product;

use Brix\Core\Contracts\Module;
use Brix\Core\Taxonomies\Registrar as Tax;

defined( 'ABSPATH' ) || exit;

/**
 * Доповнює Product-схему WooCommerce даними паспорта лоту.
 *
 * Woo сам віддає назву, ціну, наявність і відгуки — переписувати це
 * марно. А от висота, обробка, °Bx і оцінка SCA — саме те, чим лот
 * відрізняється від «пачки кави», і саме їх варто віддати пошуку
 * як `additionalProperty`.
 */
final class Schema implements Module {

	/**
	 * Підписує модуль на хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_structured_data_product', array( $this, 'extend_product' ), 10, 2 );
		add_action( 'wp_head', array( $this, 'render_open_graph' ), 5 );

		/*
		 * WooCommerce збирає Product-схему на хуку
		 * woocommerce_single_product_summary. Наш шаблон картки його
		 * не викликає — розкладка з макета інша, — тож схема не
		 * зʼявлялась узагалі. Генеруємо самі, до того як Woo друкує
		 * зібране у футері.
		 */
		add_action( 'wp_footer', array( $this, 'generate_product_data' ), 5 );
	}

	/**
	 * Просить WooCommerce зібрати схему поточного товару.
	 *
	 * @return void
	 */
	public function generate_product_data(): void {
		if ( ! is_product() || ! function_exists( 'WC' ) || ! WC()->structured_data ) {
			return;
		}

		$product = wc_get_product( get_queried_object_id() );

		if ( $product instanceof \WC_Product ) {
			WC()->structured_data->generate_product_data( $product );
		}
	}

	/**
	 * Додає дані лоту до схеми товару.
	 *
	 * @param array<string, mixed> $markup  Схема.
	 * @param \WC_Product          $product Товар.
	 * @return array<string, mixed>
	 */
	public function extend_product( array $markup, \WC_Product $product ): array {
		$lot = LotMeta::for_product( $product );

		if ( ! $lot->has_passport() ) {
			return $markup;
		}

		$properties = array();

		foreach ( $this->properties( $lot ) as $name => $value ) {
			$properties[] = array(
				'@type' => 'PropertyValue',
				'name'  => $name,
				'value' => $value,
			);
		}

		if ( $properties ) {
			$markup['additionalProperty'] = $properties;
		}

		$country = get_the_terms( $lot->product_id, Tax::COUNTRY );

		if ( is_array( $country ) ) {
			$markup['countryOfOrigin'] = $country[0]->name;
		}

		$farm = $lot->farm();

		if ( $farm ) {
			$markup['brand'] = array(
				'@type' => 'Brand',
				'name'  => get_bloginfo( 'name' ),
			);

			$markup['manufacturer'] = array(
				'@type' => 'Organization',
				'name'  => get_the_title( $farm ),
				'url'   => get_permalink( $farm ),
			);
		}

		if ( '' !== $lot->code ) {
			$markup['sku'] = $lot->code;
		}

		return $markup;
	}

	/**
	 * Пари «назва → значення» для additionalProperty.
	 *
	 * @param Lot $lot Лот.
	 * @return array<string, string>
	 */
	private function properties( Lot $lot ): array {
		$properties = array();

		if ( null !== $lot->brix ) {
			$properties[ __( 'Цукристість при зборі', 'brix-core' ) ] = $lot->brix . ' °Bx';
		}

		if ( null !== $lot->sca ) {
			$properties[ __( 'Оцінка SCA', 'brix-core' ) ] = (string) $lot->sca;
		}

		if ( '' !== $lot->altitude_label() ) {
			$properties[ __( 'Висота', 'brix-core' ) ] = $lot->altitude_label();
		}

		if ( '' !== $lot->processing_label() ) {
			$properties[ __( 'Обробка', 'brix-core' ) ] = $lot->processing_label();
		}

		if ( '' !== $lot->variety ) {
			$properties[ __( 'Різновид', 'brix-core' ) ] = $lot->variety;
		}

		if ( '' !== $lot->notes_label() ) {
			$properties[ __( 'Смакові ноти', 'brix-core' ) ] = $lot->notes_label();
		}

		if ( $lot->roast_date ) {
			$properties[ __( 'Дата обсмаження', 'brix-core' ) ] = $lot->roast_date->format( 'd.m.Y' );
		}

		return $properties;
	}

	/**
	 * Open Graph для товару, виробника й гайда.
	 *
	 * WordPress своїх og-тегів не друкує, а без них посилання
	 * в месенджері виглядає голим рядком.
	 *
	 * @return void
	 */
	public function render_open_graph(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post_id     = get_queried_object_id();
		$title       = get_the_title( $post_id );
		$description = $this->description( $post_id );
		$image       = get_the_post_thumbnail_url( $post_id, 'full' );

		$tags = array(
			'og:type'        => is_product() ? 'product' : 'article',
			'og:title'       => $title,
			'og:description' => $description,
			'og:url'         => (string) get_permalink( $post_id ),
			'og:site_name'   => get_bloginfo( 'name' ),
			'og:locale'      => 'uk_UA',
		);

		if ( $image ) {
			$tags['og:image'] = $image;
		}

		foreach ( $tags as $property => $content ) {
			if ( '' === $content ) {
				continue;
			}

			printf(
				'<meta property="%s" content="%s">' . "\n",
				esc_attr( $property ),
				esc_attr( $content )
			);
		}

		printf(
			'<meta name="twitter:card" content="%s">' . "\n",
			esc_attr( $image ? 'summary_large_image' : 'summary' )
		);
	}

	/**
	 * Опис для Open Graph.
	 *
	 * Для лоту це смакові ноти з паспорта: вони коротші за анонс
	 * і кажуть більше.
	 *
	 * @param int $post_id Запис.
	 * @return string
	 */
	private function description( int $post_id ): string {
		if ( function_exists( 'is_product' ) && is_product() ) {
			$lot = LotMeta::for_product( $post_id );

			if ( '' !== $lot->notes_label() ) {
				return $lot->notes_label();
			}
		}

		$excerpt = get_the_excerpt( $post_id );

		return $excerpt ? wp_strip_all_tags( $excerpt ) : '';
	}
}
