<?php
/**
 * Квіз «Підібрати каву».
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( ! brix_quiz_ready() ) {
	get_template_part( 'template-parts/content/content', 'none' );
	get_footer();
	return;
}

$brix_answers = brix_quiz_answers();

brix_quiz_remember();

/*
 * Обгортка — ціль для заміни скриптом. Без JavaScript вона нічого не
 * міняє: крок і результат лишаються звичайними сторінками за адресою
 * з відповідями.
 */
?>
<div data-brix-quiz>
	<?php
	if ( brix_quiz_finished() ) {
		get_template_part( 'template-parts/quiz/result', null, array( 'answers' => $brix_answers ) );
	} else {
		get_template_part( 'template-parts/quiz/step', null, array( 'answers' => $brix_answers ) );
	}
	?>
</div>
<?php

get_footer();
