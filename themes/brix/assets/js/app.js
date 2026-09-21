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

  /**
   * Живий пошук: підказки під полем у шапці.
   *
   * Без цього скрипта пошук усе одно працює — панель розкриває
   * <details>, а Enter надсилає форму на сторінку результатів. Тут
   * лише підказки, і розмітку для них малює сервер тією самою
   * шаблонною частиною, що й сторінка результатів.
   */
  function initSearch() {
    var search = document.querySelector('[data-brix-search]');

    if (!search || !window.brixData || !window.fetch) {
      return;
    }

    var field = search.querySelector('[data-brix-search-field]');
    var box = search.querySelector('[data-brix-suggest]');

    if (!field || !box) {
      return;
    }

    var timer = null;
    var request = 0;
    var current = -1;

    /** Позначає обраний варіант, не забираючи фокус із поля. */
    function highlight(index) {
      var items = box.querySelectorAll('.brix-suggest__item');

      if (!items.length) {
        return;
      }

      // Обхід по колу: з останнього вниз — знову на перший.
      current = (index + items.length) % items.length;

      Array.prototype.forEach.call(items, function (item, i) {
        var on = i === current;

        item.classList.toggle('is-current', on);
        item.setAttribute('aria-selected', String(on));

        if (on) {
          field.setAttribute('aria-activedescendant', item.id);
          item.scrollIntoView({ block: 'nearest' });
        }
      });
    }

    /** Питає в сервера підказки під поточний запит. */
    function suggest() {
      var term = field.value.trim();
      var ticket = ++request;

      current = -1;
      field.removeAttribute('aria-activedescendant');

      if (term.length < 2) {
        box.innerHTML = '';
        field.setAttribute('aria-expanded', 'false');
        return;
      }

      fetch(window.brixData.restUrl + 'search?q=' + encodeURIComponent(term), {
        headers: { Accept: 'application/json' },
      })
        .then(function (response) {
          return response.ok ? response.json() : Promise.reject(new Error('HTTP ' + response.status));
        })
        .then(function (data) {
          // Відповідь на давно застарілий запит не має перетирати
          // підказки до того, що покупець набирає зараз.
          if (ticket !== request) {
            return;
          }

          box.innerHTML = data.suggestions;
          field.setAttribute('aria-expanded', box.innerHTML.trim() ? 'true' : 'false');
        })
        .catch(function () {
          // Підказки — приємність, а не функція: якщо сервер мовчить,
          // лишається звичайний пошук через Enter.
          box.innerHTML = '';
          field.setAttribute('aria-expanded', 'false');
        });
    }

    field.setAttribute('role', 'combobox');
    field.setAttribute('aria-expanded', 'false');
    field.setAttribute('aria-autocomplete', 'list');

    field.addEventListener('input', function () {
      window.clearTimeout(timer);
      // Затримка, щоб не слати запит на кожну літеру.
      timer = window.setTimeout(suggest, 220);
    });

    field.addEventListener('keydown', function (event) {
      var items = box.querySelectorAll('.brix-suggest__item');

      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        if (!items.length) {
          return;
        }

        event.preventDefault();
        highlight(current + (event.key === 'ArrowDown' ? 1 : -1));
        return;
      }

      if (event.key === 'Enter' && current > -1 && items[current]) {
        // Обраний варіант веде прямо на товар, а не на сторінку
        // результатів — саме цього чекають від підказки.
        event.preventDefault();
        window.location.assign(items[current].href);
        return;
      }

      if (event.key === 'Escape') {
        if (box.innerHTML.trim()) {
          box.innerHTML = '';
          field.setAttribute('aria-expanded', 'false');
          current = -1;
        } else {
          search.open = false;
        }
      }
    });

    // Натиск поза панеллю закриває її — інакше вона висить над
    // сторінкою, поки не клікнеш саме в іконку.
    document.addEventListener('click', function (event) {
      if (search.open && !search.contains(event.target)) {
        search.open = false;
      }
    });

    // Курсор одразу в полі: інакше після кліку по іконці треба
    // клікати вдруге.
    search.addEventListener('toggle', function () {
      if (search.open) {
        field.focus();
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initMobileNav();
    initSteppers();
    initSearch();
  });

  // Модулі, які перемальовують шматки сторінки, повідомляють про це
  // сюди. Кнопки степера приходять із сервера схованими, і без
  // повторного запуску вони лишилися б схованими після заміни.
  document.addEventListener('brix:refresh', initSteppers);
})();
