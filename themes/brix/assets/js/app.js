/**
 * BRIX 22° — фронтенд теми.
 *
 * Ванільний JS без бандлера: файл підключається з defer і не залежить
 * від jQuery. Великі модулі (фільтри каталогу, квіз, Нова Пошта)
 * під'єднуються окремо й лише на тих сторінках, де потрібні.
 */
(function () {
  'use strict';

  /** Мобільне меню: бургер відкриває панель навігації. */
  function initMobileNav() {
    var toggle = document.querySelector('[data-brix-nav-toggle]');
    var panel = document.getElementById('brix-mobile-nav');

    if (!toggle || !panel) {
      return;
    }

    function setOpen(open) {
      panel.hidden = !open;
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute(
        'aria-label',
        open ? window.brixData.strings.menuClose : window.brixData.strings.menuOpen
      );
      document.documentElement.classList.toggle('brix-nav-open', open);
    }

    toggle.addEventListener('click', function () {
      setOpen(panel.hidden);
    });

    // Escape закриває панель і повертає фокус на бургер — інакше
    // користувач клавіатури лишається замкненим у меню.
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !panel.hidden) {
        setOpen(false);
        toggle.focus();
      }
    });
  }

  document.addEventListener('DOMContentLoaded', initMobileNav);
})();
