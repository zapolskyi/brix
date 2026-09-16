<?php
/**
 * Вікно свіжості лоту.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Product;

defined( 'ABSPATH' ) || exit;

/**
 * Рахує, на якому етапі життя перебуває обсмажена кава.
 *
 * Свіжість ніде не зберігається: вона однозначно виводиться з дати
 * обсмаження й сьогоднішнього дня. Зберігати її означало б щодня
 * перераховувати мета-поля в усіх товарів — і мати неправильні дані
 * рівно доти, доки перерахунок не відпрацює.
 *
 * Межі вікна взяті з артборда Product: «Обсмажено 14.09 · пік смаку
 * 21.09 — 12.10», тобто пік починається через тиждень після обсмаження
 * і триває три тижні.
 */
final class Freshness {

	/** Днів на дегазацію після обсмаження. */
	public const DEGAS_DAYS = 7;

	/** День, коли пік смаку закінчується. */
	public const PEAK_END_DAYS = 28;

	/** Кінець шкали: далі кава ще питна, але вже не про неї кейс. */
	public const WINDOW_DAYS = 45;

	public const STAGE_DEGASSING = 'degassing';
	public const STAGE_PEAK      = 'peak';
	public const STAGE_GOOD      = 'good';
	public const STAGE_PAST      = 'past';

	/**
	 * Дата обсмаження.
	 *
	 * @var \DateTimeImmutable
	 */
	private \DateTimeImmutable $roasted;

	/**
	 * Сьогодні.
	 *
	 * @var \DateTimeImmutable
	 */
	private \DateTimeImmutable $today;

	/**
	 * Створює розрахунок для конкретної дати обсмаження.
	 *
	 * @param \DateTimeImmutable      $roasted Дата обсмаження.
	 * @param \DateTimeImmutable|null $today   Сьогодні; параметр потрібен тестам.
	 */
	public function __construct( \DateTimeImmutable $roasted, ?\DateTimeImmutable $today = null ) {
		$this->roasted = $roasted->setTime( 0, 0 );
		$this->today   = ( $today ?? new \DateTimeImmutable( 'today', wp_timezone() ) )->setTime( 0, 0 );
	}

	/**
	 * Скільки днів минуло від обсмаження. Може бути від'ємним:
	 * обсмаження планують наперед.
	 *
	 * @return int
	 */
	public function days_passed(): int {
		return (int) $this->roasted->diff( $this->today )->format( '%r%a' );
	}

	/**
	 * Початок піку смаку.
	 *
	 * @return \DateTimeImmutable
	 */
	public function peak_starts(): \DateTimeImmutable {
		return $this->roasted->modify( '+' . self::DEGAS_DAYS . ' days' );
	}

	/**
	 * Кінець піку смаку.
	 *
	 * @return \DateTimeImmutable
	 */
	public function peak_ends(): \DateTimeImmutable {
		return $this->roasted->modify( '+' . self::PEAK_END_DAYS . ' days' );
	}

	/**
	 * Поточний етап.
	 *
	 * @return string Одна зі STAGE_*.
	 */
	public function stage(): string {
		$days = $this->days_passed();

		if ( $days < self::DEGAS_DAYS ) {
			return self::STAGE_DEGASSING;
		}

		if ( $days <= self::PEAK_END_DAYS ) {
			return self::STAGE_PEAK;
		}

		return $days <= self::WINDOW_DAYS ? self::STAGE_GOOD : self::STAGE_PAST;
	}

	/**
	 * Підпис етапу людською мовою.
	 *
	 * @return string
	 */
	public function stage_label(): string {
		switch ( $this->stage() ) {
			case self::STAGE_DEGASSING:
				return __( 'Дегазація', 'brix-core' );
			case self::STAGE_PEAK:
				return __( 'Пік смаку', 'brix-core' );
			case self::STAGE_GOOD:
				return __( 'Ще смачно', 'brix-core' );
			default:
				return __( 'Краще взяти свіжіше', 'brix-core' );
		}
	}

	/**
	 * Координати для індикатора `.brix-freshness`, у відсотках.
	 *
	 * Повертає готові значення кастомних властивостей, які шаблон
	 * ставить у `style`: межі зеленої смуги і позначку «сьогодні».
	 *
	 * @return array{left: float, width: float, now: float}
	 */
	public function bar(): array {
		$left  = self::DEGAS_DAYS / self::WINDOW_DAYS * 100;
		$right = self::PEAK_END_DAYS / self::WINDOW_DAYS * 100;
		$now   = $this->days_passed() / self::WINDOW_DAYS * 100;

		return array(
			'left'  => round( $left, 2 ),
			'width' => round( $right - $left, 2 ),
			// Позначка не вилазить за шкалу, навіть якщо лот давно лежить.
			'now'   => round( min( 100, max( 0, $now ) ), 2 ),
		);
	}

	/**
	 * Чи варто показувати індикатор.
	 *
	 * Для обладнання й сертифікатів свіжість безглузда, а лот, який
	 * пролежав понад вікно, краще не рекламувати шкалою.
	 *
	 * @return bool
	 */
	public function is_relevant(): bool {
		return self::STAGE_PAST !== $this->stage();
	}
}
