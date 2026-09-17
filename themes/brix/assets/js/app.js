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

  /**
   * Поле кількості: розкриває кнопки «−» і «+» та обробляє кліки.
   *
   * Кнопки приходять із сервера з атрибутом hidden — без цього скрипта
   * їх просто немає, і працює звичайне числове поле. Тому тут спершу
   * розкриття, і лише потім поведінка.
   */
  function initSteppers() {
    var steppers = document.querySelectorAll('[data-brix-stepper]');

    Array.prototype.forEach.call(steppers, function (stepper) {
      var input = stepper.querySelector('input[type="number"]');
      var buttons = stepper.querySelectorAll('[data-brix-step]');

      if (!input || !buttons.length) {
        return;
      }

      var min = input.min === '' ? 1 : Number(input.min);
      var max = input.max === '' ? Infinity : Number(input.max);

      /** Вимикає кнопку, яка вже нічого не змінить. */
      function syncButtons() {
        var value = Number(input.value);

        Array.prototype.forEach.call(buttons, function (button) {
          var step = Number(button.getAttribute('data-brix-step'));
          var next = value + step;

          button.disabled = next < min || next > max;
        });
      }

      Array.prototype.forEach.call(buttons, function (button) {
        button.hidden = false;

        button.addEventListener('click', function () {
          var step = Number(button.getAttribute('data-brix-step'));
          var value = Number(input.value);

          if (!isFinite(value)) {
            value = min;
          }

          var next = Math.min(max, Math.max(min, value + step));

          if (next === value) {
            return;
          }

          input.value = String(next);
          syncButtons();

          // Подію треба надіслати самим: програмна зміна value її
          // не викликає, а на неї спираються і наші модулі, і Woo.
          input.dispatchEvent(new Event('change', { bubbles: true }));
        });
      });

      input.addEventListener('input', syncButtons);
      syncButtons();
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initMobileNav();
    initSteppers();
  });
})();
