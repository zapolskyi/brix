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
 * Джерело стану квізу.
 *
 * Зазвичай це адресний рядок, але той самий набір відповідей приходить
 * і REST-запитом, коли квіз проходять без перезавантаження. Щоб підбір
 * лишався одним кодом на обидва шляхи, читачі беруть параметри звідси.
 *
 * @param array<string, mixed>|null $params Параметри або null для читання.
 * @return array<string, mixed>
 */
function brix_quiz_request( ?array $params = null ): array {
	static $override = null;

	if ( null !== $params ) {
		$override = $params;
	}

	if ( null !== $override ) {
		return $override;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Читання відповідей квізу з адреси, не зміна даних.
	return wp_unslash( $_GET );
}

/**
 * Відповіді покупця.
 *
 * @return array<string, array<int, string>>
 */
function brix_quiz_answers(): array {
	if ( ! brix_quiz_ready() ) {
		return array();
	}

	$request = brix_quiz_request();

	$answers = array();

	foreach ( \Brix\Core\Quiz\Questions::all() as $question ) {
		$key = 'q_' . $question['key'];

		if ( ! isset( $request[ $key ] ) ) {
			continue;
		}

		$raw    = sanitize_text_field( (string) $request[ $key ] );
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
	$request = brix_quiz_request();
	$step    = isset( $request['step'] ) ? absint( $request['step'] ) : 1;

	return max( 1, min( \Brix\Core\Quiz\Questions::count(), $step ) );
}

/**
 * Чи показувати результат.
 *
 * @return bool
 */
function brix_quiz_finished(): bool {
	$request = brix_quiz_request();

	return isset( $request['result'] ) && brix_quiz_answers();
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

/**
 * Мета-ключ зі збереженим результатом квізу.
 */
const BRIX_QUIZ_META = 'brix_quiz_result';

/**
 * Запам'ятовує результат квізу для залогіненого покупця.
 *
 * Сенс не в аналітиці, а в тому, щоб кабінет пам'ятав профіль смаку:
 * наступного разу не доведеться проходити шість питань заново.
 *
 * @return void
 */
function brix_quiz_remember(): void {
	if ( ! is_user_logged_in() || ! brix_quiz_ready() || ! brix_quiz_finished() ) {
		return;
	}

	$answers = brix_quiz_answers();

	if ( ! $answers ) {
		return;
	}

	update_user_meta(
		get_current_user_id(),
		BRIX_QUIZ_META,
		array(
			'answers' => $answers,
			'grind'   => \Brix\Core\Quiz\Recommender::grind( $answers ),
			'saved'   => time(),
		)
	);
}

/**
 * Збережений результат квізу.
 *
 * @param int $user_id Користувач.
 * @return array<string, mixed>
 */
function brix_quiz_saved( int $user_id = 0 ): array {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	$saved = $user_id ? get_user_meta( $user_id, BRIX_QUIZ_META, true ) : array();

	return is_array( $saved ) ? $saved : array();
}
