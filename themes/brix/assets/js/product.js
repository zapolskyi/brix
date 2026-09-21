/**
 * Картка товару: вибір ваги й помелу без перезавантаження.
 *
 * Той самий підхід, що в каталозі. Кожен варіант ваги й помелу — це
 * вже робоче посилання на ту саму сторінку з іншим набором параметрів.
 * Скрипт бере адресу, яку браузер і так би відкрив, просить у сервера
 * перемальований блок покупки й підставляє його. Ціну, наявність і
 * доступність комбінацій рахує сервер — другої реалізації на JS немає.
 */
(function () {
  'use strict';

  var host = document.querySelector('.brix-product__top');

  if (!host || !window.brixData || !window.fetch || !window.history.pushState) {
    return;
  }

  var block = host.querySelector('[data-brix-buy]');

  if (!block) {
    return;
  }

  var productId = null;
  var request = 0;

  /** Ідентифікатор товару беремо з прихованого поля форми покупки. */
  function readId() {
    var field = host.querySelector('input[name="add-to-cart"]');

    return field ? field.value : null;
  }

  productId = readId();

  if (!productId) {
    return;
  }

  /** Оновлює блок покупки під адресу, яку відкрив би браузер. */
  function load(url, push) {
    var params = new URLSearchParams(url.search);
    var ticket = ++request;

    params.set('id', productId);

    // Висота фіксується на час запиту, щоб сторінка не стрибнула:
    // у різних комбінацій різна кількість рядків.
    block.style.minHeight = block.offsetHeight + 'px';
    block.classList.add('is-loading');

    fetch(window.brixData.restUrl + 'product?' + params.toString(), {
      headers: { Accept: 'application/json' },
    })
      .then(function (response) {
        return response.ok ? response.json() : Promise.reject(new Error('HTTP ' + response.status));
      })
      .then(function (data) {
        if (ticket !== request) {
          return;
        }

        // Запамʼятовуємо, на чому був фокус: після заміни треба
        // повернути його на той самий варіант, а не в нікуди.
        var active = document.activeElement;
        var key = active && active.getAttribute('data-brix-attr')
          ? '[data-brix-attr="' + active.getAttribute('data-brix-attr') + '"][data-brix-value="' + active.getAttribute('data-brix-value') + '"]'
          : null;

        block.outerHTML = data.buy;
        block = host.querySelector('[data-brix-buy]');
        productId = readId() || productId;

        if (push) {
          window.history.pushState({ brix: true }, '', data.url);
        }

        var again = key ? block.querySelector(key) : null;

        if (again) {
          again.focus();
        }

        // Кнопки степера приходять схованими й чекають на скрипт.
        document.dispatchEvent(new CustomEvent('brix:refresh'));
      })
      .catch(function () {
        window.location.assign(url.href);
      })
      .then(function () {
        if (ticket === request && block) {
          block.classList.remove('is-loading');
          block.style.minHeight = '';
        }
      });
  }

  host.addEventListener('click', function (event) {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
      return;
    }

    var link = event.target.closest('a[data-brix-attr]');

    if (!link || link.origin !== window.location.origin) {
      return;
    }

    // Комбінації, якої не існує, немає й на сервері — сторінку
    // перемальовувати нема сенсу.
    if (link.getAttribute('aria-disabled') === 'true') {
      event.preventDefault();
      return;
    }

    event.preventDefault();
    load(new URL(link.href), true);
  });

  window.addEventListener('popstate', function () {
    load(new URL(window.location.href), false);
  });
})();
