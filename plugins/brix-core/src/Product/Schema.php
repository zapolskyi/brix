<?php
/**
 * Структуровані дані товару.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Product;

use Brix\Core\Contracts\Module;
use Brix\Core\I18n\Language;
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
	 * Опис сторінки й Open Graph для всього сайту.
	 *
	 * WordPress не друкує ні meta description, ні og-тегів, а без них
	 * пошук показує під заголовком випадковий шматок тексту, а
	 * посилання в месенджері виглядає голим рядком.
	 *
	 * Якщо на сайті з'явиться SEO-плагін, він робить це сам — тоді тут
	 * мовчимо, щоб теги не двоїлись.
	 *
	 * @return void
	 */
	public function render_open_graph(): void {
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || is_404() || is_search() ) {
			return;
		}

		$description = $this->page_description();

		if ( '' !== $description ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
		}

		$english = Language::is_second();
		$image   = is_singular() ? (string) get_the_post_thumbnail_url( get_queried_object_id(), 'full' ) : '';

		/*
		 * Сторінка без власного фото — картинка бренду від теми.
		 * Без og:image посилання в месенджері виходить голим рядком.
		 *
		 * @param string $image Адреса картинки 1200×630.
		 */
		if ( '' === $image ) {
			$image = (string) apply_filters( 'brix_default_og_image', '' );
		}

		$tags = array(
			'og:type'             => function_exists( 'is_product' ) && is_product() ? 'product' : ( is_singular( 'post' ) ? 'article' : 'website' ),
			'og:title'            => wp_get_document_title(),
			'og:description'      => $description,
			'og:url'              => $this->current_url(),
			'og:site_name'        => get_bloginfo( 'name' ),
			'og:locale'           => $english ? 'en_US' : 'uk_UA',
			'og:locale:alternate' => $english ? 'uk_UA' : 'en_US',
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
	 * Опис поточної сторінки, до 160 знаків.
	 *
	 * @return string
	 */
	private function page_description(): string {
		$text = '';

		if ( is_singular() && ! is_front_page() ) {
			$text = $this->description( get_queried_object_id() );
		} elseif ( is_tax() || is_category() || is_tag() ) {
			$text = (string) term_description();
		} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
			$text = __( 'Мікролоти specialty-кави з паспортом: висота, обробка, цукристість ягоди при зборі й ціна фермеру. Обсмажуємо щопонеділка.', 'brix-core' );
		} elseif ( is_post_type_archive() ) {
			$text = (string) get_the_post_type_description();
		}

		// Головна й усе, що лишилось без власного опису, — опис
		// самої обсмажувальні: краще загальне, ніж порожнеча.
		if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
			$text = __( 'BRIX 22° — specialty-обсмажувальня в Києві. Мікролоти від ферм, з якими працюємо напряму, і паспорт у кожній пачці. Обсмажуємо щопонеділка.', 'brix-core' );
		}

		$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) ) );

		if ( mb_strlen( $text ) <= 160 ) {
			return $text;
		}

		// Обрізаємо по слову, а не посеред нього.
		$cut = mb_substr( $text, 0, 157 );

		return rtrim( mb_substr( $cut, 0, (int) mb_strrpos( $cut, ' ' ) ), ' ,.;:—–-' ) . '…';
	}

	/**
	 * Адреса поточної сторінки без службових параметрів.
	 *
	 * @return string
	 */
	private function current_url(): string {
		if ( is_singular() ) {
			return (string) get_permalink( get_queried_object_id() );
		}

		$term = get_queried_object();

		if ( $term instanceof \WP_Term ) {
			$link = get_term_link( $term );

			return is_wp_error( $link ) ? '' : $link;
		}

		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return (string) wc_get_page_permalink( 'shop' );
		}

		if ( is_post_type_archive() ) {
			return (string) get_post_type_archive_link( (string) get_query_var( 'post_type' ) );
		}

		return home_url( '/' );
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
