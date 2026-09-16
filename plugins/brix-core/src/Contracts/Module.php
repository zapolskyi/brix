<?php
/**
 * Контракт модуля плагіна.
 *
 * @package Brix\Core
 */

declare( strict_types=1 );

namespace Brix\Core\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Модуль — самодостатній шматок логіки: паспорт лоту, фільтри, квіз.
 *
 * Модулі нічого не роблять у конструкторі: уся підписка на хуки
 * відбувається в register(). Так їх можна створити й не запускати —
 * це потрібно тестам і CLI-командам.
 */
interface Module {

	/**
	 * Підписує модуль на хуки WordPress.
	 *
	 * @return void
	 */
	public function register(): void;
}
