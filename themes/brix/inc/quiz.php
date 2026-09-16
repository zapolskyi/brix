<?php
/**
 * Квіз: читання відповідей з адресного рядка.
 *
 * Крок їде в URL разом з усіма попередніми відповідями, тож квіз
 * проходиться без JavaScript, кнопка «Назад» у браузері працює
 * як належить, а посиланням на результат можна поділитись.
 *
 * AJAX і збереження профілю в кабінеті — фаза 6.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Чи доступний квіз.
 *
 * @return bool
 */
function brix_quiz_ready(): bool {
	return brix_has_core() && class_exists( \Brix\Core\Quiz\Questions::class );
}

/**
 * Відповіді, зібрані з адресного рядка.
 *
 * @return array<string, array<int, string>>
 */
function brix_quiz_answers(): array {
	if ( ! brix_quiz_ready() ) {
		return array();
	}

	$answers = array();

	foreach ( \Brix\Core\Quiz\Questions::all() as $question ) {
		$key = 'q_' . $question['key'];

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Читання відповідей з URL, не зміна даних.
		if ( ! isset( $_GET[ $key ] ) ) {
			continue;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw    = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
		$values = array_filter( array_map( 'sanitize_key', explode( ',', $raw ) ) );
		$valid  = array_values( array_intersect( $values, array_keys( $question['options'] ) ) );

		if ( $valid ) {
			$answers[ $question['key'] ] = ! empty( $question['multiple'] )
				? array_slice( $valid, 0, (int) ( $question['max'] ?? 2 ) )
				: array( $valid[0] );
		}
	}

	return $answers;
}

/**
 * Номер поточного кроку, від 1.
 *
 * @return int
 */
function brix_quiz_step(): int {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Читання кроку з URL.
	$step = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;

	return max( 1, min( \Brix\Core\Quiz\Questions::count(), $step ) );
}

/**
 * Чи показувати результат.
 *
 * @return bool
 */
function brix_quiz_finished(): bool {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Читання прапорця з URL.
	return isset( $_GET['result'] ) && brix_quiz_answers();
}

/**
 * Адреса квізу з відповідями.
 *
 * @param array<string, array<int, string>> $answers Відповіді.
 * @param array<string, string>             $extra   Додаткові параметри.
 * @return string
 */
function brix_quiz_url( array $answers, array $extra = array() ): string {
	$args = array();

	foreach ( $answers as $key => $values ) {
		$args[ 'q_' . $key ] = implode( ',', $values );
	}

	return add_query_arg( array_merge( $args, $extra ), brix_page_url( 'quiz' ) );
}

/**
 * Адреса з перемкнутим варіантом відповіді.
 *
 * Для питань з одним вибором — це просто перехід на наступний крок.
 * Для множинних варіант вмикається й вимикається, а далі веде
 * окрема кнопка.
 *
 * @param array<string, mixed>              $question Питання.
 * @param array<string, array<int, string>> $answers  Поточні відповіді.
 * @param string                            $value    Варіант.
 * @param int                               $step     Поточний крок.
 * @return string
 */
function brix_quiz_option_url( array $question, array $answers, string $value, int $step ): string {
	$key     = $question['key'];
	$current = (array) ( $answers[ $key ] ?? array() );

	if ( empty( $question['multiple'] ) ) {
		$answers[ $key ] = array( $value );

		return brix_quiz_url( $answers, brix_quiz_next_args( $step ) );
	}

	$index = array_search( $value, $current, true );

	if ( false !== $index ) {
		unset( $current[ $index ] );
	} elseif ( count( $current ) < (int) ( $question['max'] ?? 2 ) ) {
		$current[] = $value;
	}

	if ( $current ) {
		$answers[ $key ] = array_values( $current );
	} else {
		unset( $answers[ $key ] );
	}

	return brix_quiz_url( $answers, array( 'step' => (string) $step ) );
}

/**
 * Параметри переходу на наступний крок або на результат.
 *
 * @param int $step Поточний крок.
 * @return array<string, string>
 */
function brix_quiz_next_args( int $step ): array {
	return $step >= \Brix\Core\Quiz\Questions::count()
		? array( 'result' => '1' )
		: array( 'step' => (string) ( $step + 1 ) );
}
