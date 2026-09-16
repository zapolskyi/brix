<?php
/**
 * Конструктор підписки BRIX Club.
 *
 * Стан конструктора, як і у квізі, лежить в адресному рядку — тож він
 * працює без JavaScript і посиланням на зібрану підписку можна
 * поділитись.
 *
 * Сама підписка (повторні списання, пауза, пропуск) — фаза 6. Зараз
 * конструктор веде на товар з уже обраними вагою й помелом.
 *
 * @package BRIX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Кроки конструктора.
 *
 * @return array<string, array<string, mixed>>
 */
function brix_club_steps(): array {
	return array(
		'what'  => array(
			'label'   => __( 'Що надсилати', 'brix' ),
			'options' => array(
				'roaster' => array(
					'label' => __( 'На вибір ростера', 'brix' ),
					'note'  => __( 'Щоразу новий сезонний лот', 'brix' ),
				),
				'core'    => array(
					'label' => __( 'Тільки Core', 'brix' ),
					'note'  => __( 'Everyday або Open Filter', 'brix' ),
				),
			),
		),
		'size'  => array(
			'label'   => __( 'Скільки', 'brix' ),
			'options' => array(
				'250'  => array(
					'label'  => __( '250 г', 'brix' ),
					'note'   => __( 'Одна пачка', 'brix' ),
					'weight' => '250-h',
					'qty'    => 1,
				),
				'500'  => array(
					'label'  => __( '2 × 250 г', 'brix' ),
					'note'   => __( 'Два різні лоти', 'brix' ),
					'weight' => '250-h',
					'qty'    => 2,
				),
				'1000' => array(
					'label'  => __( '1 кг', 'brix' ),
					'note'   => __( 'Для сімʼї або офісу', 'brix' ),
					'weight' => '1-kh',
					'qty'    => 1,
				),
			),
		),
		'every' => array(
			'label'   => __( 'Як часто', 'brix' ),
			'options' => array(
				'2' => array(
					'label' => __( 'Кожні 2 тижні', 'brix' ),
					'note'  => __( 'Якщо пʼєте 1–2 чашки на день', 'brix' ),
				),
				'4' => array(
					'label' => __( 'Кожні 4 тижні', 'brix' ),
					'note'  => __( 'Якщо чашка не щодня', 'brix' ),
				),
			),
		),
		'grind' => array(
			'label'   => __( 'Помел', 'brix' ),
			'options' => array(), // Заповнюється з таксономії помелу.
		),
	);
}

/**
 * Кроки з підставленими варіантами помелу.
 *
 * @return array<string, array<string, mixed>>
 */
function brix_club_config(): array {
	$steps = brix_club_steps();
	$terms = get_terms(
		array(
			'taxonomy'   => 'pa_grind',
			'hide_empty' => false,
		)
	);

	if ( is_wp_error( $terms ) ) {
		return $steps;
	}

	// Порядок помелу той самий, що в картці товару: WooCommerce тримає
	// його в мета-полі, але get_terms() його не застосовує.
	usort(
		$terms,
		static function ( WP_Term $a, WP_Term $b ): int {
			return (int) get_term_meta( $a->term_id, 'order_pa_grind', true )
				<=> (int) get_term_meta( $b->term_id, 'order_pa_grind', true );
		}
	);

	foreach ( $terms as $term ) {
		$steps['grind']['options'][ $term->slug ] = array(
			'label' => $term->name,
			'note'  => '',
		);
	}

	return $steps;
}

/**
 * Обраний варіант кроку. Порожній рядок — ще не обрано.
 *
 * @param string               $key   Ключ кроку.
 * @param array<string, mixed> $step  Опис кроку.
 * @return string
 */
function brix_club_choice( string $key, array $step ): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Читання стану конструктора з URL.
	$raw = isset( $_GET[ 'c_' . $key ] ) ? sanitize_key( wp_unslash( $_GET[ 'c_' . $key ] ) ) : '';

	return isset( $step['options'][ $raw ] ) ? $raw : '';
}

/**
 * Усі обрані варіанти.
 *
 * @return array<string, string>
 */
function brix_club_selection(): array {
	$selection = array();

	foreach ( brix_club_config() as $key => $step ) {
		$choice = brix_club_choice( $key, $step );

		if ( '' !== $choice ) {
			$selection[ $key ] = $choice;
		}
	}

	return $selection;
}

/**
 * Адреса сторінки клубу з іншим значенням одного кроку.
 *
 * @param string $key   Крок.
 * @param string $value Значення.
 * @return string
 */
function brix_club_url( string $key, string $value ): string {
	$args = array();

	foreach ( brix_club_selection() as $step => $choice ) {
		$args[ 'c_' . $step ] = $choice;
	}

	$args[ 'c_' . $key ] = $value;

	return add_query_arg( $args, brix_page_url( 'club' ) ) . '#brix-club-builder';
}

/**
 * Ціна зібраної підписки з урахуванням знижки.
 *
 * @param array<string, string> $selection Вибір.
 * @return array{regular: float, club: float, discount: int}
 */
function brix_club_price( array $selection ): array {
	$discount = (int) apply_filters( 'brix_club_discount', 10 );
	$config   = brix_club_config();
	$size     = $config['size']['options'][ $selection['size'] ?? '' ] ?? null;

	if ( ! $size ) {
		return array(
			'regular'  => 0.0,
			'club'     => 0.0,
			'discount' => $discount,
		);
	}

	// Орієнтир — середня ціна лотів потрібної ваги: конкретний лот
	// щоразу інший, тож підписка показує саме орієнтир, а не точну суму.
	$lots  = brix_home_lots( 8 );
	$sum   = 0.0;
	$count = 0;

	foreach ( $lots as $lot ) {
		$price = $lot->is_type( 'variable' )
			? (float) ( '1-kh' === $size['weight'] ? $lot->get_variation_price( 'max' ) : $lot->get_variation_price( 'min' ) )
			: (float) $lot->get_price();

		if ( $price > 0 ) {
			$sum += $price;
			++$count;
		}
	}

	$regular = $count > 0 ? round( $sum / $count ) * (int) $size['qty'] : 0.0;

	return array(
		'regular'  => $regular,
		'club'     => round( $regular * ( 100 - $discount ) / 100 ),
		'discount' => $discount,
	);
}
