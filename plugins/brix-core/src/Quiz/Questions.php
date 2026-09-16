<?php
/**
 * Питання квізу.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Quiz;

defined( 'ABSPATH' ) || exit;

/**
 * Опис питань і варіантів відповіді.
 *
 * Питання живуть у плагіні, а не в шаблоні: за ними рахується підбір,
 * і тема лише малює те, що віддав `all()`.
 */
final class Questions {

	/**
	 * Усі питання по порядку.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all(): array {
		/**
		 * Дозволяє змінити склад питань квізу.
		 *
		 * @param array<int, array<string, mixed>> $questions Питання.
		 */
		return (array) apply_filters( 'brix_quiz_questions', self::definitions() );
	}

	/**
	 * Питання за ключем.
	 *
	 * @param string $key Ключ.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $key ): ?array {
		foreach ( self::all() as $question ) {
			if ( $question['key'] === $key ) {
				return $question;
			}
		}

		return null;
	}

	/**
	 * Скільки всього кроків.
	 *
	 * @return int
	 */
	public static function count(): int {
		return count( self::all() );
	}

	/**
	 * Самі питання.
	 *
	 * Кожен варіант несе ваги — числа, з якими його порівнюють
	 * з полями лоту. `null` означає «на підбір не впливає».
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function definitions(): array {
		return array(
			array(
				'key'      => 'brew',
				'title'    => __( 'Як ви найчастіше заварюєте вдома?', 'brix-core' ),
				'hint'     => __( 'Від цього залежить помел, з яким приїде кава.', 'brix-core' ),
				'multiple' => false,
				'options'  => array(
					'v60'       => array(
						'label' => __( 'V60 або інша лійка', 'brix-core' ),
						'grind' => 'v60-filtr',
					),
					'espresso'  => array(
						'label' => __( 'Еспресо-машина', 'brix-core' ),
						'grind' => 'espreso',
					),
					'aeropress' => array(
						'label' => __( 'Aeropress', 'brix-core' ),
						'grind' => 'espreso',
					),
					'french'    => array(
						'label' => __( 'Френч-прес', 'brix-core' ),
						'grind' => 'french-pres',
					),
					'turka'     => array(
						'label' => __( 'Турка', 'brix-core' ),
						'grind' => 'turka',
					),
				),
			),
			array(
				'key'      => 'milk',
				'title'    => __( 'Молоко додаєте?', 'brix-core' ),
				'hint'     => __( 'Молоко глушить кислотність, тож під нього беруть щільніші лоти.', 'brix-core' ),
				'multiple' => false,
				'options'  => array(
					'never'     => array(
						'label' => __( 'Ніколи', 'brix-core' ),
						'body'  => 2,
						'roast' => 2,
					),
					'sometimes' => array(
						'label' => __( 'Інколи', 'brix-core' ),
						'body'  => 3,
						'roast' => 3,
					),
					'always'    => array(
						'label' => __( 'Завжди', 'brix-core' ),
						'body'  => 5,
						'roast' => 4,
					),
				),
			),
			array(
				'key'      => 'notes',
				'title'    => __( 'Що вам ближче у чашці?', 'brix-core' ),
				'hint'     => __( 'Можна обрати до двох варіантів.', 'brix-core' ),
				'multiple' => true,
				'max'      => 2,
				'options'  => array(
					'berries'   => array(
						'label'      => __( 'Ягоди й квіти', 'brix-core' ),
						'note'       => 'yahody',
						'fruitiness' => 5,
					),
					'citrus'    => array(
						'label'      => __( 'Цитрус і чай', 'brix-core' ),
						'note'       => 'tsytrus',
						'fruitiness' => 4,
					),
					'chocolate' => array(
						'label'      => __( 'Шоколад і горіхи', 'brix-core' ),
						'note'       => 'shokolad',
						'fruitiness' => 1,
					),
					'spices'    => array(
						'label'      => __( 'Спеції й сухофрукти', 'brix-core' ),
						'note'       => 'spetsii',
						'fruitiness' => 2,
					),
				),
			),
			array(
				'key'      => 'acidity',
				'title'    => __( 'Яка кислотність вам смакує?', 'brix-core' ),
				'hint'     => __( 'Яскрава — це відчуття соковитості, а не кислоти.', 'brix-core' ),
				'multiple' => false,
				'options'  => array(
					'soft'   => array(
						'label'   => __( 'Мʼяка', 'brix-core' ),
						'acidity' => 2,
					),
					'medium' => array(
						'label'   => __( 'Середня', 'brix-core' ),
						'acidity' => 3,
					),
					'bright' => array(
						'label'   => __( 'Яскрава', 'brix-core' ),
						'acidity' => 5,
					),
				),
			),
			array(
				'key'      => 'sweetness',
				'title'    => __( 'Наскільки солодкою має бути чашка?', 'brix-core' ),
				'hint'     => __( 'Йдеться про власну солодкість зерна, а не про цукор.', 'brix-core' ),
				'multiple' => false,
				'options'  => array(
					'balanced' => array(
						'label'     => __( 'Збалансовано', 'brix-core' ),
						'sweetness' => 3,
					),
					'sweet'    => array(
						'label'     => __( 'Чим солодше, тим краще', 'brix-core' ),
						'sweetness' => 5,
					),
				),
			),
			array(
				'key'      => 'budget',
				'title'    => __( 'Який бюджет на пачку 250 г?', 'brix-core' ),
				'hint'     => __( 'Це не відсіє лоти, лише вплине на порядок.', 'brix-core' ),
				'multiple' => false,
				'options'  => array(
					'low' => array(
						'label' => __( 'До 600 ₴', 'brix-core' ),
						'price' => 600,
					),
					'mid' => array(
						'label' => __( '600–900 ₴', 'brix-core' ),
						'price' => 900,
					),
					'any' => array(
						'label' => __( 'Без обмежень', 'brix-core' ),
						'price' => null,
					),
				),
			),
		);
	}
}
