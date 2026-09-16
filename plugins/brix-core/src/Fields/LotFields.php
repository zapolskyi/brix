<?php
/**
 * Група полів «Паспорт лоту».
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Fields;

use Brix\Core\PostTypes\BrewGuide;
use Brix\Core\PostTypes\Farm;
use Brix\Core\Product\LotMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Поля товару-лоту.
 *
 * Група описана кодом, а не клікана в адмінці: тоді вона лежить у git,
 * їде на staging разом з релізом і видно в дифі, коли поле додали.
 */
final class LotFields {

	/**
	 * Опис групи для acf_add_local_field_group().
	 *
	 * @return array<string, mixed>
	 */
	public static function group(): array {
		return array(
			'key'             => 'group_brix_lot',
			'title'           => __( 'Паспорт лоту', 'brix-core' ),
			'menu_order'      => 0,
			'position'        => 'normal',
			'style'           => 'default',
			'label_placement' => 'top',
			'active'          => true,
			'show_in_rest'    => true,
			'description'     => __( 'Дані, які показуються в картці товару, каталозі, квізі й на сторінці «Прозорість».', 'brix-core' ),
			'location'        => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'product',
					),
				),
			),
			// Ховаємо те, що тема не використовує, щоб екран товару не рябів.
			'hide_on_screen'  => array( 'excerpt', 'custom_fields', 'discussion', 'comments' ),
			'fields'          => array_merge(
				array( self::tab( 'origin', __( 'Походження', 'brix-core' ) ) ),
				self::origin_fields(),
				array( self::tab( 'taste', __( 'Смак', 'brix-core' ) ) ),
				self::taste_fields(),
				array( self::tab( 'trade', __( 'Закупівля і свіжість', 'brix-core' ) ) ),
				self::trade_fields()
			),
		);
	}

	/**
	 * Вкладка групи.
	 *
	 * @param string $slug  Ключ.
	 * @param string $label Підпис.
	 * @return array<string, mixed>
	 */
	private static function tab( string $slug, string $label ): array {
		return array(
			'key'       => 'field_brix_lot_tab_' . $slug,
			'label'     => $label,
			'type'      => 'tab',
			'placement' => 'top',
		);
	}

	/**
	 * Поля походження.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function origin_fields(): array {
		return array(
			self::field(
				'code',
				__( 'Код лоту', 'brix-core' ),
				'text',
				array(
					'placeholder'  => 'ET-GJ-14',
					'instructions' => __( 'Стоїть у заголовку паспорта й на пачці. Порожній код означає, що товар не є лотом.', 'brix-core' ),
					'wrapper'      => array( 'width' => '25' ),
				)
			),
			self::field(
				'farm',
				__( 'Виробник', 'brix-core' ),
				'post_object',
				array(
					'post_type'     => array( Farm::POST_TYPE ),
					'return_format' => 'id',
					'allow_null'    => 1,
					'instructions'  => __( 'Звідси ж будується зворотний список «лоти цієї ферми».', 'brix-core' ),
					'wrapper'       => array( 'width' => '35' ),
				)
			),
			self::field(
				'region',
				__( 'Регіон', 'brix-core' ),
				'text',
				array(
					'placeholder' => 'Гуджі, Хамбела',
					'wrapper'     => array( 'width' => '40' ),
				)
			),
			self::field(
				'altitude_min',
				__( 'Висота від, м', 'brix-core' ),
				'number',
				array(
					'min'     => 0,
					'max'     => 4000,
					'wrapper' => array( 'width' => '25' ),
				)
			),
			self::field(
				'altitude_max',
				__( 'Висота до, м', 'brix-core' ),
				'number',
				array(
					'min'          => 0,
					'max'          => 4000,
					'instructions' => __( 'У каталозі показується середина діапазону.', 'brix-core' ),
					'wrapper'      => array( 'width' => '25' ),
				)
			),
			self::field(
				'variety',
				__( 'Різновид', 'brix-core' ),
				'text',
				array(
					'placeholder' => 'Heirloom 74158',
					'wrapper'     => array( 'width' => '25' ),
				)
			),
			self::field(
				'harvest',
				__( 'Урожай', 'brix-core' ),
				'text',
				array(
					'placeholder' => 'Груд. 2025',
					'wrapper'     => array( 'width' => '25' ),
				)
			),
			self::field(
				'processing_days',
				__( 'Днів обробки', 'brix-core' ),
				'number',
				array(
					'min'          => 0,
					'max'          => 120,
					'instructions' => __( 'Показується поруч з обробкою: «Natural, 18 днів».', 'brix-core' ),
					'wrapper'      => array( 'width' => '25' ),
				)
			),
		);
	}

	/**
	 * Поля смаку.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function taste_fields(): array {
		$scales = array(
			'taste_acidity'    => __( 'Кислотність', 'brix-core' ),
			'taste_sweetness'  => __( 'Солодкість', 'brix-core' ),
			'taste_body'       => __( 'Тіло', 'brix-core' ),
			'taste_intensity'  => __( 'Інтенсивність', 'brix-core' ),
			'taste_fruitiness' => __( 'Фруктовість', 'brix-core' ),
		);

		$fields = array();

		foreach ( $scales as $name => $label ) {
			$fields[] = self::field(
				$name,
				$label,
				'range',
				array(
					'min'           => 0,
					'max'           => 5,
					'step'          => 1,
					'default_value' => 0,
					'wrapper'       => array( 'width' => '20' ),
				)
			);
		}

		$fields[] = self::field(
			'roast_level',
			__( 'Ступінь обсмаження', 'brix-core' ),
			'range',
			array(
				'min'           => 0,
				'max'           => 5,
				'step'          => 1,
				'default_value' => 0,
				'instructions'  => __( '1 — світле, 5 — темне. Фільтра за ним немає, але за ним підбирає квіз.', 'brix-core' ),
				'wrapper'       => array( 'width' => '30' ),
			)
		);

		$fields[] = self::field(
			'cup_notes',
			__( 'Що це означає в чашці', 'brix-core' ),
			'textarea',
			array(
				'rows'         => 4,
				'instructions' => __( 'Пояснення людською мовою під шкалами смаку. Цифри замість епітетів.', 'brix-core' ),
			)
		);

		$fields[] = self::field(
			'brew_guide',
			__( 'Рецепт під цей лот', 'brix-core' ),
			'post_object',
			array(
				'post_type'     => array( BrewGuide::POST_TYPE ),
				'return_format' => 'id',
				'allow_null'    => 1,
				'wrapper'       => array( 'width' => '50' ),
			)
		);

		return $fields;
	}

	/**
	 * Поля закупівлі й свіжості.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function trade_fields(): array {
		return array(
			self::field(
				'brix',
				__( 'Цукристість при зборі, °Bx', 'brix-core' ),
				'number',
				array(
					'min'          => 0,
					'max'          => 40,
					'step'         => 0.1,
					'instructions' => __( 'Головне число бренду. Стоїть на печатці пачки й на шкалі стиглості.', 'brix-core' ),
					'wrapper'      => array( 'width' => '25' ),
				)
			),
			self::field(
				'sca',
				__( 'Оцінка SCA', 'brix-core' ),
				'number',
				array(
					'min'     => 0,
					'max'     => 100,
					'step'    => 0.25,
					'wrapper' => array( 'width' => '25' ),
				)
			),
			self::field(
				'farmer_price',
				__( 'Ціна фермеру, $/кг FOB', 'brix-core' ),
				'number',
				array(
					'min'          => 0,
					'step'         => 0.01,
					'instructions' => __( 'З цього поля збирається сторінка «Прозорість».', 'brix-core' ),
					'wrapper'      => array( 'width' => '25' ),
				)
			),
			self::field(
				'roast_date',
				__( 'Дата обсмаження', 'brix-core' ),
				'date_picker',
				array(
					'display_format' => 'd.m.Y',
					'return_format'  => 'Ymd',
					'first_day'      => 1,
					'instructions'   => __( 'Від неї рахується вікно свіжості: тиждень дегазації, далі три тижні піку.', 'brix-core' ),
					'wrapper'        => array( 'width' => '25' ),
				)
			),
			self::field(
				'lot_of_week',
				__( 'Лот тижня', 'brix-core' ),
				'true_false',
				array(
					'ui'           => 1,
					'instructions' => __( 'Плашка в каталозі. Вмикати варто одному товару.', 'brix-core' ),
				)
			),
		);
	}

	/**
	 * Складає опис поля.
	 *
	 * Ім'я поля збігається з мета-ключем, який читає LotMeta, — щоб
	 * дані лишились читабельними навіть без SCF.
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
				'key'   => 'field_' . LotMeta::key( $name ),
				'name'  => LotMeta::key( $name ),
				'label' => $label,
				'type'  => $type,
			),
			$extra
		);
	}
}
