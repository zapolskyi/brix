<?php
/**
 * Паспорт лоту — типізований об'єкт.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Product;

use Brix\Core\Taxonomies\Registrar;

defined( 'ABSPATH' ) || exit;

/**
 * Дані лоту, вже прочитані й приведені до типів.
 *
 * Шаблони отримують саме цей об'єкт, а не сирі мета-поля: тут число
 * вже число, дата вже дата, а порожнє поле не перетворюється на «0 м».
 * Завдяки цьому заміна SCF на будь-що інше не зачіпає жодного шаблону.
 */
final class Lot {

	/** Межі шкали °Bx на артборді Product. */
	public const SCALE_MIN = 14;
	public const SCALE_MAX = 28;

	/** Діапазон стиглості — зелена смуга на шкалі. */
	public const RIPE_MIN = 20;
	public const RIPE_MAX = 24;

	/**
	 * Складає паспорт з уже прочитаних значень.
	 *
	 * @param int                     $product_id     Товар.
	 * @param string                  $code           Код лоту, напр. ET-GJ-14.
	 * @param float|null              $brix           Цукристість при зборі.
	 * @param float|null              $sca            Оцінка SCA.
	 * @param int|null                $altitude_min   Нижня межа висоти, м.
	 * @param int|null                $altitude_max   Верхня межа висоти, м.
	 * @param string                  $variety        Різновид.
	 * @param int|null                $farm_id        Виробник.
	 * @param string                  $region         Регіон.
	 * @param int|null                $processing_days Днів обробки.
	 * @param float|null              $farmer_price   Ціна фермеру, $/кг FOB.
	 * @param string                  $harvest        Урожай.
	 * @param \DateTimeImmutable|null $roast_date     Дата обсмаження.
	 * @param int                     $roast_level    Ступінь обсмаження 1–5.
	 * @param array<string, int>      $taste          Шкали смаку 1–5.
	 * @param string                  $cup_notes      «Що це означає в чашці».
	 * @param int|null                $brew_guide_id  Рекомендований рецепт.
	 * @param bool                    $lot_of_week    Плашка «Лот тижня».
	 */
	public function __construct(
		public readonly int $product_id,
		public readonly string $code = '',
		public readonly ?float $brix = null,
		public readonly ?float $sca = null,
		public readonly ?int $altitude_min = null,
		public readonly ?int $altitude_max = null,
		public readonly string $variety = '',
		public readonly ?int $farm_id = null,
		public readonly string $region = '',
		public readonly ?int $processing_days = null,
		public readonly ?float $farmer_price = null,
		public readonly string $harvest = '',
		public readonly ?\DateTimeImmutable $roast_date = null,
		public readonly int $roast_level = 0,
		public readonly array $taste = array(),
		public readonly string $cup_notes = '',
		public readonly ?int $brew_guide_id = null,
		public readonly bool $lot_of_week = false,
	) {}

	/**
	 * Чи є в товару паспорт узагалі.
	 *
	 * Обладнання й сертифікати — теж товари, але лотами не є,
	 * і показувати їм порожню таблицю даних не треба.
	 *
	 * @return bool
	 */
	public function has_passport(): bool {
		return null !== $this->brix || '' !== $this->code;
	}

	/**
	 * Висота як у паспорті: «2 050–2 200 м».
	 *
	 * @return string Порожній рядок, якщо висоти немає.
	 */
	public function altitude_label(): string {
		if ( null === $this->altitude_min ) {
			return '';
		}

		$min = number_format_i18n( $this->altitude_min );

		if ( null === $this->altitude_max || $this->altitude_max === $this->altitude_min ) {
			/* translators: %s — висота в метрах. */
			return sprintf( __( '%s м', 'brix-core' ), $min );
		}

		/* translators: 1: нижня межа висоти, 2: верхня межа. */
		return sprintf( __( '%1$s–%2$s м', 'brix-core' ), $min, number_format_i18n( $this->altitude_max ) );
	}

	/**
	 * Висота одним числом — для картки в каталозі.
	 *
	 * Картка показує «2 100 м» там, де в паспорті «2 050–2 200 м»:
	 * це середина діапазону, округлена вниз до 50.
	 *
	 * @return int|null
	 */
	public function altitude_average(): ?int {
		if ( null === $this->altitude_min ) {
			return null;
		}

		$mid = ( $this->altitude_min + ( $this->altitude_max ?? $this->altitude_min ) ) / 2;

		return (int) ( floor( $mid / 50 ) * 50 );
	}

	/**
	 * Термін обробки.
	 *
	 * @return \WP_Term|null
	 */
	public function processing(): ?\WP_Term {
		$terms = get_the_terms( $this->product_id, Registrar::PROCESSING );

		return ( is_array( $terms ) && $terms ) ? $terms[0] : null;
	}

	/**
	 * Стиль пачки: natural, washed, honey, lab, core, drip.
	 *
	 * @return string
	 */
	public function pack_style(): string {
		$term = $this->processing();

		return $term ? Registrar::pack_style( $term->term_id ) : '';
	}

	/**
	 * Конкретні смакові ноти — дочірні терміни таксономії.
	 *
	 * Саме вони стоять під назвою товару: «Черешня · бергамот ·
	 * молочний шоколад». Батьківські групи («ягоди», «цитрус») —
	 * це фільтр каталогу, і в картці вони не показуються.
	 *
	 * @return array<int, \WP_Term>
	 */
	public function notes(): array {
		$terms = get_the_terms( $this->product_id, Registrar::NOTE );

		if ( ! is_array( $terms ) ) {
			return array();
		}

		// Якщо дочірніх немає — товару проставили саму групу; показуємо її.
		$children = array_filter( $terms, static fn( \WP_Term $term ): bool => $term->parent > 0 );

		return array_values( array() === $children ? $terms : $children );
	}

	/**
	 * Ноти одним рядком.
	 *
	 * @return string
	 */
	public function notes_label(): string {
		return implode( ' · ', wp_list_pluck( $this->notes(), 'name' ) );
	}

	/**
	 * Підпис обробки з тривалістю: «Natural, 18 днів».
	 *
	 * @return string
	 */
	public function processing_label(): string {
		$term = $this->processing();

		if ( ! $term ) {
			return '';
		}

		if ( null === $this->processing_days ) {
			return $term->name;
		}

		return $term->name . ', ' . sprintf(
			/* translators: %s — кількість днів обробки. */
			_n( '%s день', '%s днів', $this->processing_days, 'brix-core' ),
			number_format_i18n( $this->processing_days )
		);
	}

	/**
	 * Позиція значення на шкалі °Bx, у відсотках.
	 *
	 * @param float $value Значення в °Bx.
	 * @return float
	 */
	public static function scale_position( float $value ): float {
		$span    = self::SCALE_MAX - self::SCALE_MIN;
		$clamped = min( self::SCALE_MAX, max( self::SCALE_MIN, $value ) );

		return round( ( $clamped - self::SCALE_MIN ) / $span * 100, 2 );
	}

	/**
	 * Координати зеленої смуги стиглості на шкалі °Bx.
	 *
	 * @return array{left: float, width: float}
	 */
	public static function ripe_band(): array {
		$left = self::scale_position( self::RIPE_MIN );

		return array(
			'left'  => $left,
			'width' => round( self::scale_position( self::RIPE_MAX ) - $left, 2 ),
		);
	}

	/**
	 * Свіжість лоту.
	 *
	 * @return Freshness|null Null, якщо дати обсмаження немає.
	 */
	public function freshness(): ?Freshness {
		return $this->roast_date ? new Freshness( $this->roast_date ) : null;
	}

	/**
	 * Виробник.
	 *
	 * @return \WP_Post|null
	 */
	public function farm(): ?\WP_Post {
		if ( ! $this->farm_id ) {
			return null;
		}

		$farm = get_post( $this->farm_id );

		return ( $farm && 'publish' === $farm->post_status ) ? $farm : null;
	}

	/**
	 * Рекомендований рецепт заварювання.
	 *
	 * @return \WP_Post|null
	 */
	public function brew_guide(): ?\WP_Post {
		if ( ! $this->brew_guide_id ) {
			return null;
		}

		$guide = get_post( $this->brew_guide_id );

		return ( $guide && 'publish' === $guide->post_status ) ? $guide : null;
	}

	/**
	 * Шкали смаку, готові до виводу: назва, значення 1–5 і відсоток.
	 *
	 * @return array<int, array{key: string, label: string, score: int, percent: int}>
	 */
	public function taste_bars(): array {
		$labels = array(
			'acidity'    => __( 'Кислотність', 'brix-core' ),
			'sweetness'  => __( 'Солодкість', 'brix-core' ),
			'body'       => __( 'Тіло', 'brix-core' ),
			'intensity'  => __( 'Інтенсивність', 'brix-core' ),
			'fruitiness' => __( 'Фруктовість', 'brix-core' ),
		);

		$bars = array();

		foreach ( $labels as $key => $label ) {
			$score = (int) ( $this->taste[ $key ] ?? 0 );

			if ( $score < 1 ) {
				continue;
			}

			$bars[] = array(
				'key'     => $key,
				'label'   => $label,
				'score'   => $score,
				'percent' => $score * 20,
			);
		}

		return $bars;
	}
}
