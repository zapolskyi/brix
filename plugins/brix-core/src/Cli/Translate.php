<?php
/**
 * Команда wp brix translate — заливка англійських текстів.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Cli;

use Brix\Core\I18n\Translations;

defined( 'ABSPATH' ) || exit;

/**
 * Заливає переклади з data/translations-en.json.
 *
 * Переклад — теж контент, і живе він там само, де решта: у файлі
 * репозиторію, а не тільки в базі. Так розгортання на чистій
 * інсталяції відтворює двомовний магазин цілком, а не наполовину.
 *
 * Редагувати переклад можна і в адмінці — коробка «Англійська версія»
 * пише в ті самі мета-поля. Команда їх перезаписує, тож запускати її
 * після ручних правок означає їх втратити; про це попереджає --force.
 */
final class Translate {

	/**
	 * Заливає англійські тексти.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Свій файл замість data/translations-en.json.
	 *
	 * [--force]
	 * : Перезаписати переклади, які вже є.
	 *
	 * ## EXAMPLES
	 *
	 *     wp brix translate
	 *     wp brix translate --force
	 *
	 * @param array<int, string>    $args       Позиційні аргументи.
	 * @param array<string, string> $assoc_args Іменовані аргументи.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args = array() ): void {
		unset( $args );

		$path  = $assoc_args['file'] ?? dirname( BRIX_CORE_FILE ) . '/data/translations-en.json';
		$force = isset( $assoc_args['force'] );

		if ( ! is_readable( $path ) ) {
			\WP_CLI::error( sprintf( 'Не знайшов файл перекладів: %s', $path ) );
		}

		$data = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( ! is_array( $data ) ) {
			\WP_CLI::error( 'Файл перекладів не читається як JSON.' );
		}

		$this->posts( (array) ( $data['posts'] ?? array() ), $force );
		$this->terms( (array) ( $data['terms'] ?? array() ), $force );
		$this->menu( (array) ( $data['menu'] ?? array() ), $force );
		$this->strings( (array) ( $data['strings'] ?? array() ) );

		\WP_CLI::success( 'Англійська версія на місці.' );
	}

	/**
	 * Записи за типом і slug.
	 *
	 * @param array<string, mixed> $posts Переклади.
	 * @param bool                 $force Перезаписати наявне.
	 * @return void
	 */
	private function posts( array $posts, bool $force ): void {
		$done = 0;
		$miss = array();

		foreach ( $posts as $key => $fields ) {
			[ $type, $slug ] = array_pad( explode( '|', (string) $key, 2 ), 2, '' );
			$found           = get_posts(
				array(
					'post_type'   => $type,
					'name'        => $slug,
					'numberposts' => 1,
					'post_status' => 'any',
				)
			);

			if ( ! $found ) {
				$miss[] = $key;
				continue;
			}

			$id = $found[0]->ID;

			foreach ( array( 'title', 'excerpt', 'content' ) as $field ) {
				$this->post_meta( $id, Translations::META . $field, (string) ( $fields[ $field ] ?? '' ), $force );
			}

			foreach ( (array) ( $fields['meta'] ?? array() ) as $meta_key => $value ) {
				$this->post_meta( $id, Translations::META . '_' . $meta_key, (string) $value, $force );
			}

			++$done;
		}

		\WP_CLI::log( sprintf( 'Записів: %d.', $done ) );

		foreach ( $miss as $key ) {
			\WP_CLI::warning( sprintf( 'Не знайшов запис %s — переклад пропущено.', $key ) );
		}
	}

	/**
	 * Терміни за таксономією і slug.
	 *
	 * @param array<string, mixed> $terms Переклади.
	 * @param bool                 $force Перезаписати наявне.
	 * @return void
	 */
	private function terms( array $terms, bool $force ): void {
		$done = 0;

		foreach ( $terms as $key => $fields ) {
			[ $taxonomy, $slug ] = array_pad( explode( '|', (string) $key, 2 ), 2, '' );
			$term                = get_term_by( 'slug', $slug, $taxonomy );

			if ( ! $term instanceof \WP_Term ) {
				\WP_CLI::warning( sprintf( 'Не знайшов термін %s — переклад пропущено.', $key ) );
				continue;
			}

			foreach ( array( 'name', 'description' ) as $field ) {
				$value = (string) ( $fields[ $field ] ?? '' );
				$meta  = Translations::META . $field;

				if ( '' === trim( $value ) ) {
					continue;
				}

				if ( ! $force && '' !== (string) get_term_meta( $term->term_id, $meta, true ) ) {
					continue;
				}

				update_term_meta( $term->term_id, $meta, $value );
			}

			++$done;
		}

		\WP_CLI::log( sprintf( 'Термінів: %d.', $done ) );
	}

	/**
	 * Пункти меню за їхнім українським підписом.
	 *
	 * Slug у пункта меню немає, а його ідентифікатор змінюється при
	 * кожному перестворенні меню. Лишається підпис — він і так має
	 * бути унікальним у межах сайту, інакше меню читалося б погано.
	 *
	 * @param array<string, string> $labels Переклади.
	 * @param bool                  $force  Перезаписати наявне.
	 * @return void
	 */
	private function menu( array $labels, bool $force ): void {
		$done = 0;

		foreach ( wp_get_nav_menus() as $menu ) {
			$items = wp_get_nav_menu_items( $menu->term_id );

			foreach ( is_array( $items ) ? $items : array() as $item ) {
				$source = html_entity_decode( (string) $item->title, ENT_QUOTES, 'UTF-8' );

				if ( ! isset( $labels[ $source ] ) ) {
					continue;
				}

				$this->post_meta( (int) $item->ID, Translations::META . 'title', (string) $labels[ $source ], $force );
				++$done;
			}
		}

		\WP_CLI::log( sprintf( 'Пунктів меню: %d.', $done ) );
	}

	/**
	 * Рядки з налаштувань.
	 *
	 * @param array<string, string> $table Переклади.
	 * @return void
	 */
	private function strings( array $table ): void {
		$clean = array();

		foreach ( $table as $source => $value ) {
			$source = trim( (string) $source );
			$value  = trim( (string) $value );

			if ( '' === $source || '' === $value ) {
				continue;
			}

			$clean[ $source ] = $value;
		}

		update_option( Translations::STRINGS, $clean, false );

		\WP_CLI::log( sprintf( 'Рядків налаштувань: %d.', count( $clean ) ) );
	}

	/**
	 * Пише мета-поле, якщо є що писати.
	 *
	 * @param int    $id    Запис.
	 * @param string $key   Ключ.
	 * @param string $value Значення.
	 * @param bool   $force Перезаписати наявне.
	 * @return void
	 */
	private function post_meta( int $id, string $key, string $value, bool $force ): void {
		if ( '' === trim( $value ) ) {
			return;
		}

		if ( ! $force && '' !== (string) get_post_meta( $id, $key, true ) ) {
			return;
		}

		update_post_meta( $id, $key, $value );
	}
}
