<?php
/**
 * Реєстрація груп полів SCF.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Fields;

use Brix\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Віддає групи полів у SCF.
 *
 * Групи описані кодом, тому редагувати їх в адмінці не можна — і це
 * навмисно: інакше база й репозиторій розійшлися б, а зміни полів
 * не було б видно в дифі.
 */
final class Registrar implements Module {

	/**
	 * Підписує модуль на хуки.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'acf/init', array( $this, 'register_groups' ) );
	}

	/**
	 * Додає всі групи.
	 *
	 * @return void
	 */
	public function register_groups(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		/**
		 * Дозволяє додати або змінити групи полів.
		 *
		 * @param array<int, array<string, mixed>> $groups Описи груп.
		 */
		$groups = apply_filters(
			'brix_core_field_groups',
			array(
				LotFields::group(),
				FarmFields::group(),
				GuideFields::group(),
			)
		);

		foreach ( $groups as $group ) {
			acf_add_local_field_group( $group );
		}
	}
}
