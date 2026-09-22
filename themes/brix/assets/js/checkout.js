/**
 * Checkout: вибір міста й відділення Нової Пошти.
 *
 * Власний випадайко, а не <datalist>. Причини дві.
 *
 * Рідний список браузера не дає перевірити вибір: у полі лишається
 * довільний текст, і замовлення приходить із адресою, якої немає.
 * А ще він показується не завжди й по-різному в різних браузерах —
 * покупець просто не бачить підказок і вирішує, що їх немає.
 *
 * Тут список свій: видно завжди, працює з клавіатури, а вибір пише
 * ідентифікатор у приховане поле, яке перевіряє сервер.
 *
 * Без JavaScript обидва поля лишаються звичайними текстовими —
 * замовлення оформлюється, просто адресу покупець пише руками.
 */
(function () {
  'use strict';

  var city = document.getElementById('billing_city');
  var warehouse = document.getElementById('billing_address_1');

  if (!city || !warehouse || !window.brixData || !window.fetch) {
    return;
  }

  /** Обгортає поле й вішає на нього список підказок. */
  function attach(field, name) {
    var box = document.createElement('div');
    var hidden = document.createElement('input');
    var holder = document.createElement('div');

    holder.className = 'brix-np';
    field.parentNode.insertBefore(holder, field);
    holder.appendChild(field);

    box.className = 'brix-suggest brix-np__list';
    box.setAttribute('role', 'listbox');
    holder.appendChild(box);

    hidden.type = 'hidden';
    hidden.name = name;
    holder.appendChild(hidden);

    field.setAttribute('autocomplete', 'off');
    field.setAttribute('role', 'combobox');
    field.setAttribute('aria-expanded', 'false');
    field.setAttribute('aria-autocomplete', 'list');

    return { field: field, box: box, hidden: hidden, current: -1, items: [] };
  }

  var cityBox = attach(city, 'brix_np_city_ref');
  var whBox = attach(warehouse, 'brix_np_warehouse_ref');

  /** Малює список варіантів. */
  function render(state, items) {
    state.items = items;
    state.current = -1;
    state.box.innerHTML = '';

    if (!items.length) {
      state.field.setAttribute('aria-expanded', 'false');
      return;
    }

    items.forEach(function (item, index) {
      var option = document.createElement('a');

      option.className = 'brix-suggest__item';
      option.href = '#';
      option.setAttribute('role', 'option');
      option.setAttribute('aria-selected', 'false');
      option.dataset.index = String(index);
      option.innerHTML = '<span class="brix-suggest__name"></span>'
        + (item.area ? '<span class="brix-suggest__meta"></span>' : '');
      option.querySelector('.brix-suggest__name').textContent = item.name;

      if (item.area) {
        option.querySelector('.brix-suggest__meta').textContent = item.area;
      }

      state.box.appendChild(option);
    });

    state.field.setAttribute('aria-expanded', 'true');
  }

  /** Підсвічує варіант, не забираючи фокус із поля. */
  function highlight(state, index) {
    var options = state.box.querySelectorAll('.brix-suggest__item');

    if (!options.length) {
      return;
    }

    state.current = (index + options.length) % options.length;

    Array.prototype.forEach.call(options, function (option, i) {
      var on = i === state.current;

      option.classList.toggle('is-current', on);
      option.setAttribute('aria-selected', String(on));

      if (on) {
        option.scrollIntoView({ block: 'nearest' });
      }
    });
  }

  /** Записує вибір у поле й у приховане поле з ідентифікатором. */
  function choose(state, item, after) {
    state.field.value = item.name;
    state.hidden.value = item.ref;
    state.box.innerHTML = '';
    state.field.setAttribute('aria-expanded', 'false');
    state.current = -1;

    if (after) {
      after(item);
    }
  }

  /** Питає сервер і показує варіанти. */
  function ask(state, url, after) {
    fetch(url, { headers: { Accept: 'application/json' } })
      .then(function (response) {
        return response.ok ? response.json() : Promise.reject(new Error('HTTP ' + response.status));
      })
      .then(function (data) {
        render(state, data.items || []);

        if (after) {
          after(data);
        }
      })
      .catch(function () {
        // Довідник недоступний — лишається звичайне текстове поле.
        render(state, []);
      });
  }

  /** Спільна поведінка клавіатури й мишки. */
  function wire(state, onChoose) {
    state.box.addEventListener('mousedown', function (event) {
      var option = event.target.closest('.brix-suggest__item');

      if (!option) {
        return;
      }

      // mousedown, а не click: до click поле встигає втратити фокус,
      // і список закривається раніше, ніж вибір спрацює.
      event.preventDefault();
      choose(state, state.items[Number(option.dataset.index)], onChoose);
    });

    state.field.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        if (!state.items.length) {
          return;
        }

        event.preventDefault();
        highlight(state, state.current + (event.key === 'ArrowDown' ? 1 : -1));
        return;
      }

      if (event.key === 'Enter' && state.current > -1) {
        event.preventDefault();
        choose(state, state.items[state.current], onChoose);
        return;
      }

      if (event.key === 'Escape') {
        render(state, []);
      }
    });

    state.field.addEventListener('blur', function () {
      // Затримка, щоб встиг спрацювати вибір мишкою.
      window.setTimeout(function () {
        render(state, []);
      }, 150);
    });
  }

  var timer = null;

  city.addEventListener('input', function () {
    // Ручна правка знецінює попередній вибір: поки місто не обране
    // зі списку, відділення шукати нема де.
    cityBox.hidden.value = '';
    whBox.hidden.value = '';
    render(whBox, []);

    window.clearTimeout(timer);
    timer = window.setTimeout(function () {
      if (city.value.trim().length < 2) {
        render(cityBox, []);
        return;
      }

      ask(cityBox, window.brixData.restUrl + 'np/cities?q=' + encodeURIComponent(city.value.trim()));
    }, 220);
  });

  city.addEventListener('focus', function () {
    if (city.value.trim().length >= 2 && !cityBox.hidden.value) {
      ask(cityBox, window.brixData.restUrl + 'np/cities?q=' + encodeURIComponent(city.value.trim()));
    }
  });

  wire(cityBox, function () {
    // Щойно місто обране — одразу показуємо його відділення.
    warehouse.value = '';
    warehouse.focus();
    loadWarehouses();
  });

  /** Відділення обраного міста. */
  function loadWarehouses() {
    if (!cityBox.hidden.value) {
      render(whBox, []);
      return;
    }

    ask(whBox, window.brixData.restUrl + 'np/warehouses?city=' + encodeURIComponent(cityBox.hidden.value));
  }

  warehouse.addEventListener('focus', loadWarehouses);

  warehouse.addEventListener('input', function () {
    whBox.hidden.value = '';

    if (!cityBox.hidden.value) {
      return;
    }

    var needle = warehouse.value.trim().toLowerCase();

    window.clearTimeout(timer);
    timer = window.setTimeout(function () {
      ask(whBox, window.brixData.restUrl + 'np/warehouses?city=' + encodeURIComponent(cityBox.hidden.value), function () {
        if (!needle) {
          return;
        }

        // Фільтруємо вже отриманий список на місці: відділень у
        // місті кількасот, і смикати сервер на кожну літеру немає
        // сенсу.
        render(whBox, whBox.items.filter(function (item) {
          return item.name.toLowerCase().indexOf(needle) !== -1;
        }).slice(0, 40));
      });
    }, 180);
  });

  wire(whBox, null);
})();
