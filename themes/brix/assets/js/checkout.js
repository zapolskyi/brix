/**
 * Checkout: підказки міста й відділення Нової Пошти.
 *
 * Поля лишаються звичайними текстовими: без JavaScript і без ключа
 * API покупець просто пише адресу руками, і замовлення оформлюється.
 * Підказки додаються через <datalist> — рідний елемент браузера, тож
 * клавіатура, читалка й мобільна клавіатура працюють без нашого коду.
 */
(function () {
  'use strict';

  // Підказки чіпляються до наявних полів WooCommerce, а не до власних:
  // тема вже перейменувала «Адресу» на «Адреса або відділення» саме
  // під Нову Пошту, і дублювати ці поля означало б питати двічі.
  var city = document.getElementById('billing_city');
  var warehouse = document.getElementById('billing_address_1');

  if (!city || !warehouse || !window.brixData || !window.fetch) {
    return;
  }

  var cityRefs = {};
  var timer = null;
  var chosen = '';

  /** Створює список підказок і чіпляє його до поля. */
  function listFor(field) {
    var id = field.id + '-options';
    var list = document.getElementById(id);

    if (!list) {
      list = document.createElement('datalist');
      list.id = id;
      field.parentNode.appendChild(list);
      field.setAttribute('list', id);
    }

    return list;
  }

  /** Наповнює список варіантами. */
  function fill(list, items, label) {
    list.innerHTML = '';

    items.forEach(function (item) {
      var option = document.createElement('option');

      option.value = label(item);
      list.appendChild(option);
    });
  }

  function loadCities() {
    var term = city.value.trim();

    if (term.length < 2) {
      return;
    }

    fetch(window.brixData.restUrl + 'np/cities?q=' + encodeURIComponent(term), {
      headers: { Accept: 'application/json' },
    })
      .then(function (response) {
        return response.ok ? response.json() : Promise.reject(new Error('HTTP ' + response.status));
      })
      .then(function (data) {
        // Ключа API немає — підказок не буде, і це не помилка:
        // поле лишається звичайним текстовим.
        if (!data.configured) {
          return;
        }

        cityRefs = {};
        data.items.forEach(function (item) {
          cityRefs[item.name + (item.area ? ', ' + item.area : '')] = item.ref;
        });

        fill(listFor(city), data.items, function (item) {
          return item.name + (item.area ? ', ' + item.area : '');
        });
      })
      .catch(function () {
        // Довідник недоступний — мовчимо. Поле й так працює.
      });
  }

  function loadWarehouses() {
    var ref = cityRefs[city.value.trim()];

    if (!ref || ref === chosen) {
      return;
    }

    chosen = ref;

    fetch(window.brixData.restUrl + 'np/warehouses?city=' + encodeURIComponent(ref), {
      headers: { Accept: 'application/json' },
    })
      .then(function (response) {
        return response.ok ? response.json() : Promise.reject(new Error('HTTP ' + response.status));
      })
      .then(function (data) {
        if (!data.configured) {
          return;
        }

        fill(listFor(warehouse), data.items, function (item) {
          return item.name;
        });
      })
      .catch(function () {});
  }

  city.addEventListener('input', function () {
    window.clearTimeout(timer);
    timer = window.setTimeout(function () {
      loadCities();
      loadWarehouses();
    }, 250);
  });

  city.addEventListener('change', loadWarehouses);
})();
