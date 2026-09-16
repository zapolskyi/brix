<?php
/**
 * Група полів «Гайд заварювання».
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Fields;

use Brix\Core\PostTypes\BrewGuide;

defined( 'ABSPATH' ) || exit;

/**
 * Поля рецепта. Склад звірений з артбордом Guide.
 */
final class GuideFields {

	public const PREFIX = 'brix_guide_';

	/**
	 * Опис групи.
	 *
	 * @return array<string, mixed>
	 */
	public static function group(): array {
		return array(
			'key'            => 'group_brix_guide',
			'title'          => __( 'Рецепт', 'brix-core' ),
			'position'       => 'normal',
			'active'         => true,
			'show_in_rest'   => true,
			'location'       => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => BrewGuide::POST_TYPE,
					),
				),
			),
			'hide_on_screen' => array( 'custom_fields', 'discussion', 'comments' ),
			'fields'         => array(
				self::field(
					'dose',
					__( 'Кава, г', 'brix-core' ),
					'number',
					array(
						'min'     => 0,
						'step'    => 0.5,
						'wrapper' => array( 'width' => '20' ),
					)
				),
				self::field(
					'water',
					__( 'Вода, мл', 'brix-core' ),
					'number',
					array(
						'min'     => 0,
						'wrapper' => array( 'width' => '20' ),
					)
				),
				self::field(
					'temperature',
					__( 'Температура, °C', 'brix-core' ),
					'number',
					array(
						'min'     => 0,
						'max'     => 100,
						'wrapper' => array( 'width' => '20' ),
					)
				),
				self::field(
					'grind',
					__( 'Помел', 'brix-core' ),
					'text',
					array(
						'placeholder' => 'Середньо-дрібний',
						'wrapper'     => array( 'width' => '20' ),
					)
				),
				self::field(
					'total_time',
					__( 'Загальний час, с', 'brix-core' ),
					'number',
					array(
						'min'          => 0,
						'instructions' => __( 'У секундах: 3:00 — це 180.', 'brix-core' ),
						'wrapper'      => array( 'width' => '20' ),
					)
				),
				self::field(
					'steps',
					__( 'Кроки', 'brix-core' ),
					'repeater',
					array(
						'instructions' => __( 'Час кроку — секунди від старту. За ними йде таймер на сторінці.', 'brix-core' ),
						'layout'       => 'block',
						'button_label' => __( 'Додати крок', 'brix-core' ),
						'sub_fields'   => array(
							self::field(
								'step_at',
								__( 'На секунді', 'brix-core' ),
								'number',
								array(
									'min'     => 0,
									'wrapper' => array( 'width' => '20' ),
								)
							),
							self::field(
								'step_title',
								__( 'Назва', 'brix-core' ),
								'text',
								array(
									'placeholder' => 'Блум',
									'wrapper'     => array( 'width' => '30' ),
								)
							),
							self::field(
								'step_target',
								__( 'Ціль', 'brix-core' ),
								'text',
								array(
									'placeholder' => 'до 45 г води',
									'wrapper'     => array( 'width' => '50' ),
								)
							),
							self::field( 'step_text', __( 'Опис', 'brix-core' ), 'textarea', array( 'rows' => 2 ) ),
						),
					)
				),
				self::field(
					'tip_title',
					__( 'Підказка · заголовок', 'brix-core' ),
					'text',
					array(
						'placeholder' => 'Якщо немає термометра',
						'wrapper'     => array( 'width' => '40' ),
					)
				),
				self::field(
					'tip_text',
					__( 'Підказка · текст', 'brix-core' ),
					'textarea',
					array(
						'rows'    => 2,
						'wrapper' => array( 'width' => '60' ),
					)
				),
				self::field(
					'gear',
					__( 'Що знадобиться', 'brix-core' ),
					'relationship',
					array(
						'post_type'     => array( 'product' ),
						'return_format' => 'id',
						'filters'       => array( 'search' ),
						'instructions'  => __( 'Товари з категорії Gear. Показуються блоком під рецептом.', 'brix-core' ),
					)
				),
			),
		);
	}

	/**
	 * Складає опис поля.
	 *
	 * @param string               $name  Коротка назва.
	 * @param string               $label Підпис.
	 * @param string               $type  Тип поля.
	 * @param array<string, mixed> $extra Додаткові налаштування.
	 * @return array<string, mixed>
	 */
	private static function field( string $name, string $label, string $type, array $extra = array() ): array {
		return array_merge(
			array(
				'key'   => 'field_' . self::PREFIX . $name,
				'name'  => self::PREFIX . $name,
				'label' => $label,
				'type'  => $type,
			),
			$extra
		);
	}
}
