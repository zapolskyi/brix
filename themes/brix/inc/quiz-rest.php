<?php
/**
 * REST квізу: крок і результат без перезавантаження.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Реєструє маршрут квізу.
 *
 * @return void
 */
function brix_register_quiz_route(): void {
	register_rest_route(
		'brix/v1',
		'/quiz',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'brix_quiz_rest_response',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'brix_register_quiz_route' );

/**
 * Крок або результат квізу одним шматком розмітки.
 *
 * Як і в каталозі, ендпоінт нічого не рахує сам: підставляє відповіді
 * в те саме джерело, з якого їх читає сторінка, і малює ту саму
 * шаблонну частину.
 *
 * @param WP_REST_Request $request Запит.
 * @return WP_REST_Response|WP_Error
 */
function brix_quiz_rest_response( WP_REST_Request $request ) {
	if ( ! brix_quiz_ready() ) {
		return new WP_Error( 'brix_no_quiz', __( 'Квіз недоступний.', 'brix' ), array( 'status' => 503 ) );
	}

	brix_quiz_request( $request->get_params() );

	$answers = brix_quiz_answers();
	$part    = brix_quiz_finished() ? 'result' : 'step';

	// Профіль смаку має запам'ятовуватись і тоді, коли квіз пройдено
	// без перезавантаження сторінки.
	brix_quiz_remember();

	ob_start();
	get_template_part( 'template-parts/quiz/' . $part, null, array( 'answers' => $answers ) );
	$html = (string) ob_get_clean();

	return rest_ensure_response(
		array(
			'html'     => $html,
			'finished' => brix_quiz_finished(),
			'url'      => brix_quiz_url( $answers, brix_quiz_finished() ? array( 'result' => '1' ) : array( 'step' => (string) brix_quiz_step() ) ),
		)
	);
}
