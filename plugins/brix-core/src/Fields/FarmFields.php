<?php
/**
 * Група полів «Виробник».
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Fields;

use Brix\Core\PostTypes\Farm;

defined( 'ABSPATH' ) || exit;

/**
 * Поля сторінки ферми. Склад звірений з артбордом Farm.
 */
final class FarmFields {

	public const PREFIX = 'brix_farm_';

	/**
	 * Опис групи.
	 *
	 * @return array<string, mixed>
	 */
	public static function group(): array {
		return array(
			'key'            => 'group_brix_farm',
			'title'          => __( 'Дані виробника', 'brix-core' ),
			'position'       => 'normal',
			'active'         => true,
			'show_in_rest'   => true,
			'location'       => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => Farm::POST_TYPE,
					),
				),
			),
			'hide_on_screen' => array( 'custom_fields', 'discussion', 'comments' ),
			'fields'         => array(
				self::field(
					'headline',
					__( 'Заголовок секції', 'brix-core' ),
					'text',
					array(
						'placeholder' => 'Одна станція, 640 родин',
						'wrapper'     => array( 'width' => '50' ),
					)
				),
				self::field(
					'households',
					__( 'Господарств', 'brix-core' ),
					'number',
					array(
						'min'     => 0,
						'wrapper' => array( 'width' => '25' ),
					)
				),
				self::field(
					'since_year',
					__( 'Працюємо з', 'brix-core' ),
					'number',
					array(
						'min'     => 1990,
						'max'     => 2100,
						'wrapper' => array( 'width' => '25' ),
					)
				),
				self::field(
					'variety',
					__( 'Різновид', 'brix-core' ),
					'text',
					array(
						'placeholder' => 'Heirloom',
						'wrapper'     => array( 'width' => '25' ),
					)
				),
				self::field(
					'fob_price',
					__( 'Ціна FOB, $/кг', 'brix-core' ),
					'number',
					array(
						'min'     => 0,
						'step'    => 0.01,
						'wrapper' => array( 'width' => '25' ),
					)
				),
				self::field(
					'lat',
					__( 'Широта', 'brix-core' ),
					'text',
					array(
						'placeholder'  => '6°10′ пн. ш.',
						'instructions' => __( 'Так, як має бути надруковано.', 'brix-core' ),
						'wrapper'      => array( 'width' => '25' ),
					)
				),
				self::field(
					'lng',
					__( 'Довгота', 'brix-core' ),
					'text',
					array(
						'placeholder' => '38°30′ сх. д.',
						'wrapper'     => array( 'width' => '25' ),
					)
				),
				self::field( 'region_note', __( 'Про місцевість', 'brix-core' ), 'textarea', array( 'rows' => 3 ) ),
				self::field(
					'story',
					__( 'Як ми сюди потрапили', 'brix-core' ),
					'wysiwyg',
					array(
						'media_upload' => 0,
						'toolbar'      => 'basic',
					)
				),
				self::field(
					'quote',
					__( 'Цитата', 'brix-core' ),
					'textarea',
					array(
						'rows'    => 3,
						'wrapper' => array( 'width' => '70' ),
					)
				),
				self::field(
					'quote_author',
					__( 'Хто сказав', 'brix-core' ),
					'text',
					array(
						'placeholder' => 'Абебе, керівник станції',
						'wrapper'     => array( 'width' => '30' ),
					)
				),
				self::field(
					'year_calendar',
					__( 'Рік на станції', 'brix-core' ),
					'repeater',
					array(
						'instructions' => __( 'Від квітки до пачки. Порядок рядків — порядок на сторінці.', 'brix-core' ),
						'layout'       => 'table',
						'button_label' => __( 'Додати етап', 'brix-core' ),
						'sub_fields'   => array(
							self::field( 'calendar_period', __( 'Період', 'brix-core' ), 'text', array( 'placeholder' => 'Жов–Лис' ) ),
							self::field( 'calendar_title', __( 'Що відбувається', 'brix-core' ), 'text', array( 'placeholder' => 'Дозрівання' ) ),
							self::field( 'calendar_note', __( 'Примітка', 'brix-core' ), 'text', array( 'placeholder' => 'Перевіряємо °Bx двічі на тиждень' ) ),
						),
					)
				),
				self::field(
					'gallery',
					__( 'Фото зі станції', 'brix-core' ),
					'gallery',
					array(
						'return_format' => 'id',
						'insert'        => 'append',
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
