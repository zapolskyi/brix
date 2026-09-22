<?php
/**
 * Локальний довідник міст і відділень Нової Пошти.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Shipping;

defined( 'ABSPATH' ) || exit;

/**
 * Довідник у власній таблиці.
 *
 * Автокомпліт читає звідси, а не з API Нової Пошти. Причин дві.
 *
 * Квота. Маршрут підказок відкритий усім — інакше гість не заповнив би
 * checkout. На публічному стенді будь-хто може слати унікальні запити,
 * які проходять повз кеш, і спалити квоту акаунта. А ключ у Нової
 * Пошти один на бізнес-кабінет, тобто спільний з рештою проєктів.
 *
 * Ключ. Якщо довідник уже в базі, ключ на публічний сервер везти не
 * треба взагалі: ні в конфіг, ні в бекап. Витікати нема чому.
 *
 * Ціна рішення — довідник застигає на даті синхронізації.
 */
final class Directory {

	/**
	 * Назва таблиці без префікса.
	 */
	private const TABLE = 'brix_np_places';

	/**
	 * Види записів.
	 */
	public const CITY      = 'city';
	public const WAREHOUSE = 'warehouse';

	/**
	 * Опція з датою останньої синхронізації.
	 */
	public const SYNCED_AT = 'brix_np_synced_at';

	/**
	 * Повна назва таблиці.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Створює таблицю.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		/*
		 * Окрема колонка `search` з назвою в нижньому регістрі: LIKE по
		 * ній працює однаково для «Київ» і «київ» незалежно від
		 * налаштувань сортування таблиці.
		 */
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				kind varchar(12) NOT NULL,
				ref varchar(40) NOT NULL,
				city_ref varchar(40) NOT NULL DEFAULT '',
				name varchar(255) NOT NULL,
				area varchar(255) NOT NULL DEFAULT '',
				category varchar(20) NOT NULL DEFAULT '',
				search varchar(255) NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY kind_ref (kind, ref),
				KEY lookup (kind, search(60)),
				KEY city (city_ref)
			) {$collate};"
		);
	}

	/**
	 * Чи є в довіднику дані.
	 *
	 * @param string $kind Вид записів.
	 * @return bool
	 */
	public static function has( string $kind ): bool {
		return self::count( $kind ) > 0;
	}

	/**
	 * Скільки записів у довіднику.
	 *
	 * @param string $kind Вид записів.
	 * @return int
	 */
	public static function count( string $kind ): int {
		global $wpdb;

		$table = self::table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Власна таблиця, кешування тут ні до чого.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE kind = %s", $kind ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Записує пачку рядків.
	 *
	 * @param string                            $kind Вид записів.
	 * @param array<int, array<string, string>> $rows Рядки.
	 * @return int
	 */
	public static function put( string $kind, array $rows ): int {
		global $wpdb;

		if ( ! $rows ) {
			return 0;
		}

		$table  = self::table();
		$values = array();
		$args   = array();

		foreach ( $rows as $row ) {
			$values[] = '(%s, %s, %s, %s, %s, %s, %s)';

			$args[] = $kind;
			$args[] = (string) ( $row['ref'] ?? '' );
			$args[] = (string) ( $row['city_ref'] ?? '' );
			$args[] = (string) ( $row['name'] ?? '' );
			$args[] = (string) ( $row['area'] ?? '' );
			$args[] = (string) ( $row['category'] ?? '' );
			$args[] = mb_strtolower( (string) ( $row['name'] ?? '' ) );
		}

		/*
		 * Плейсхолдери будуються з кількості рядків, а не з даних:
		 * у рядку їх шість, і жодне значення в SQL не потрапляє —
		 * усе йде через $wpdb->prepare().
		 */
		$sql = "INSERT INTO {$table} (kind, ref, city_ref, name, area, category, search) VALUES "
			. implode( ', ', $values )
			. ' ON DUPLICATE KEY UPDATE city_ref = VALUES(city_ref), name = VALUES(name), area = VALUES(area), category = VALUES(category), search = VALUES(search)';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$written = (int) $wpdb->query( $wpdb->prepare( $sql, $args ) );
		// phpcs:enable

		return $written;
	}

	/**
	 * Міста за початком назви.
	 *
	 * @param string $query Запит.
	 * @param int    $limit Скільки.
	 * @return array<int, array{ref: string, name: string, area: string}>
	 */
	public static function cities( string $query, int $limit = 12 ): array {
		global $wpdb;

		$table = self::table();
		$like  = $wpdb->esc_like( mb_strtolower( trim( $query ) ) );

		/*
		 * Спершу збіги з початку назви, потім будь-де: «Ніжин» має
		 * стояти вище за «Нижній Ніжин» на запит «ніж».
		 */
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ref, name, area FROM {$table}
				 WHERE kind = %s AND search LIKE %s
				 ORDER BY (search LIKE %s) DESC, CHAR_LENGTH(name), name
				 LIMIT %d",
				self::CITY,
				'%' . $like . '%',
				$like . '%',
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Відділення міста.
	 *
	 * @param string $city  Ідентифікатор міста.
	 * @param int    $limit Скільки.
	 * @return array<int, array{ref: string, name: string}>
	 */
	public static function warehouses( string $city, int $limit = 500 ): array {
		global $wpdb;

		$table = self::table();

		/*
		 * Спершу звичайні відділення, потім поштомати, потім решта:
		 * «Відділення №1» покупець шукає частіше за поштомат, а
		 * сортування лише за назвою ставило б «Поштомат №3559» вище.
		 */

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ref, name FROM {$table}
				 WHERE kind = %s AND city_ref = %s
				 ORDER BY FIELD(category, 'Branch', 'Postomat', 'Store'), CHAR_LENGTH(name), name
				 LIMIT %d",
				self::WAREHOUSE,
				$city,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Очищає довідник.
	 *
	 * @param string $kind Вид записів.
	 * @return void
	 */
	public static function clear( string $kind ): void {
		global $wpdb;

		$table = self::table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE kind = %s", $kind ) );
		// phpcs:enable
	}
}
