/**
 * Каталог: фільтри без перезавантаження.
 *
 * Скрипт нічого не вирішує сам. Кожен фільтр, чип, сторінка й варіант
 * сортування — це вже готове посилання або форма, які працюють без
 * JavaScript. Тут лише перехоплення: беремо адресу, яку браузер і так
 * би відкрив, питаємо в сервера ті самі шматки розмітки й підставляємо
 * їх на місце. Логіка фільтрації лишається на сервері — інакше вона
 * подвоїлась би, і дві копії з часом розійшлися б.
 *
 * Тому й відкат простий: щойно щось не так — віддаємо керування
 * браузеру, і сторінка перезавантажується як завжди.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-brix-catalog]');

  if (!root || !window.brixData || !window.fetch || !window.history.pushState) {
    return;
  }

  var targets = {
    count: root.querySelector('[data-brix-count]'),
    chips: root.querySelector('[data-brix-chips]'),
    filters: root.querySelector('[data-brix-filters]'),
    results: root.querySelector('[data-brix-results]'),
  };

  if (!targets.results || !targets.filters) {
    return;
  }

  var taxonomy = root.getAttribute('data-brix-tax') || '';
  var term = root.getAttribute('data-brix-term') || '';
  var request = 0;

  /** Номер сторінки з адреси: /shop/page/2/ або ?paged=2. */
  function pagedFrom(url) {
    var match = url.pathname.match(/\/page\/(\d+)\/?$/);

    if (match) {
      return match[1];
    }

    return url.searchParams.get('paged') || '1';
  }

  /** Параметри для ендпоінта з тієї адреси, яку відкрив би браузер. */
  function paramsFrom(url) {
    var params = new URLSearchParams();

    url.searchParams.forEach(function (value, key) {
      if (key !== 'paged' && value !== '') {
        params.set(key, value);
      }
    });

    params.set('paged', pagedFrom(url));

    if (taxonomy && term) {
      params.set('tax', taxonomy);
      params.set('term', term);
    }

    return params;
  }

  /**
   * Запамʼятовує, на чому був фокус, щоб повернути його після заміни.
   *
   * Чип шукається не за посиланням, а за парою «фільтр + значення»:
   * після перемикання його href стає іншим, бо тепер він знімає те,
   * що щойно ввімкнув.
   */
  function focusKey() {
    var active = document.activeElement;

    if (!active || !root.contains(active)) {
      return null;
    }

    var filter = active.getAttribute('data-brix-filter');

    if (filter) {
      return '[data-brix-filter="' + filter + '"][data-brix-value="' + active.getAttribute('data-brix-value') + '"]';
    }

    if (active.hasAttribute('data-brix-reset')) {
      return '[data-brix-reset]';
    }

    return null;
  }

  /** Повертає фокус або відводить його в область результатів. */
  function restoreFocus(key) {
    var again = key ? root.querySelector(key) : null;

    if (again) {
      again.focus();
      return;
    }

    // Фокус був на сторінці пагінації або на чипі, який зник разом
    // зі своїм фільтром. Лишати його в нікуди не можна — інакше
    // наступний Tab почне обхід із початку документа.
    targets.results.focus();
  }

  /** Оновлює каталог під адресу, яку відкрив би браузер. */
  function load(url, push) {
    var params = paramsFrom(url);
    var ticket = ++request;
    var key = focusKey();

    // Висота фіксується на час запиту, щоб сітка не схлопнулась
    // і сторінка не стрибнула під курсором.
    targets.results.style.minHeight = targets.results.offsetHeight + 'px';
    root.classList.add('is-loading');

    fetch(window.brixData.restUrl + 'catalog?' + params.toString(), {
      headers: { Accept: 'application/json' },
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('HTTP ' + response.status);
        }

        return response.json();
      })
      .then(function (data) {
        // Поки летів цей запит, користувач міг клікнути ще двічі.
        // Застаріла відповідь не має перемальовувати свіжішу.
        if (ticket !== request) {
          return;
        }

        if (targets.count) {
          targets.count.textContent = data.count;
        }

        if (targets.chips) {
          targets.chips.innerHTML = data.chips;
        }

        targets.filters.innerHTML = data.filters;
        targets.results.innerHTML = data.results;

        if (push) {
          window.history.pushState({ brix: true }, '', data.url);
        }

        restoreFocus(key);
      })
      .catch(function () {
        // Мережа впала або сервер віддав помилку: просто йдемо за
        // посиланням, як зробив би браузер без цього скрипта.
        window.location.assign(url.href);
      })
      .then(function () {
        if (ticket === request) {
          root.classList.remove('is-loading');
          targets.results.style.minHeight = '';
        }
      });
  }

  /** Чи це посилання каталогу, яке варто перехопити. */
  function isCatalogLink(link) {
    return (
      link
      && link.origin === window.location.origin
      && !link.target
      && !link.hasAttribute('download')
    );
  }

  root.addEventListener('click', function (event) {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
      return;
    }

    var link = event.target.closest('a[href]');

    if (!link || !root.contains(link)) {
      return;
    }

    // Картки товарів і хлібні крихти ведуть геть із каталогу —
    // їх перехоплювати нема чого.
    var inControls = link.closest('[data-brix-filters], [data-brix-chips], .woocommerce-pagination');

    if (!inControls || !isCatalogLink(link)) {
      return;
    }

    event.preventDefault();
    load(new URL(link.href), true);
  });

  // Форма фільтрів — ціна й галочка наявності.
  root.addEventListener('submit', function (event) {
    var form = event.target;

    if (!form.matches('.brix-filters, [data-brix-ordering]')) {
      return;
    }

    event.preventDefault();

    var url = new URL(form.action, window.location.href);
    var data = new FormData(form);

    data.forEach(function (value, key) {
      if (String(value) !== '') {
        url.searchParams.append(key, String(value));
      }
    });

    load(url, true);
  });

  // Сортування застосовується одразу при виборі, тож кнопка потрібна
  // лише без JavaScript — тут її можна прибрати.
  root.addEventListener('change', function (event) {
    var select = event.target;

    if (!select.matches('[data-brix-ordering] select')) {
      return;
    }

    var form = select.closest('form');

    if (form) {
      form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
    }
  });

  var apply = root.querySelector('[data-brix-ordering-apply]');

  if (apply) {
    apply.hidden = true;
  }

  // Кнопки «назад» і «вперед» мусять вертати той самий каталог,
  // а не сторінку з попередніми фільтрами з кешу.
  window.addEventListener('popstate', function () {
    load(new URL(window.location.href), false);
  });
})();
