/**
 * Checkout: спосіб отримання перемикає поля адреси.
 *
 * Без скрипта те саме робить кнопка «Оновити» — сторінка
 * перемальовується з новим станом. Скрипт лише прибирає
 * перезавантаження.
 *
 * Поля не видаляються, а ховаються: видалені довелося б відтворювати
 * з нуля, разом із уже набраним текстом. Разом із полем знімається
 * обов'язковість — інакше браузер відмовився б надсилати форму через
 * порожнє поле, якого ніхто не бачить.
 */
(function () {
  'use strict';

  var address = document.getElementById('brix-address');

  if (!address) {
    return;
  }

  var required = ['billing_city', 'billing_address_1'];

  /** Показує те, що потрібне обраному способу. */
  function apply(pickup) {
    var panel = document.getElementById('brix-pickup');

    address.hidden = pickup;

    if (panel) {
      panel.hidden = !pickup;
    }

    required.forEach(function (id) {
      var field = document.getElementById(id);

      if (!field) {
        return;
      }

      field.required = !pickup;

      var row = field.closest('.form-row');

      if (row) {
        row.classList.toggle('validate-required', !pickup);
      }
    });

    if (pickup) {
      // Вибір міста й відділення більше не діє: наступного разу
      // покупець обиратиме їх заново.
      ['brix_np_city_ref', 'brix_np_warehouse_ref'].forEach(function (name) {
        var hidden = document.querySelector('input[name="' + name + '"]');

        if (hidden) {
          hidden.value = '';
        }
      });
    }
  }

  /** Стан за поточною відміткою. */
  function sync() {
    var section = document.getElementById('brix-delivery');

    if (!section) {
      return;
    }

    var checked = section.querySelector('input[name^="shipping_method"]:checked')
      || section.querySelector('input[name^="shipping_method"][type="hidden"]');

    apply(!!checked && checked.dataset.pickup === '1');
  }

  /*
   * Слухаємо документ, а не самі радіокнопки. Блок способу отримання
   * WooCommerce перемальовує при зміні адреси — країна вирішує, які
   * способи доставки існують, — і підписка на конкретну кнопку
   * пережила б це рівно один раз.
   */
  document.addEventListener('change', function (event) {
    if (event.target && event.target.matches('#brix-delivery input[name^="shipping_method"]')) {
      sync();
    }
  });

  if (window.jQuery) {
    window.jQuery(document.body).on('updated_checkout', sync);
  }

  sync();
})();

/**
 * Checkout: вибір міста й відділення Нової Пошти.
 *
 * Власний випадайко, а не <datalist>. Рідний список браузера не дає
 * перевірити вибір: у полі лишається довільний текст, і замовлення
 * приходить із адресою, якої немає. А ще він показується не завжди й
 * по-різному в різних браузерах — покупець просто не бачить підказок
 * і вирішує, що їх немає.
 *
 * Відділення міста запитуються один раз і фільтруються на місці.
 * Доти кожна літера в полі відділення означала окремий запит по всі
 * чотириста точок міста: поки покупець дописував «Відділення №12»,
 * він витрачав півтора десятка запитів, упирався в обмеження частоти
 * й бачив порожній список — рівно тоді, коли список був найпотрібніший.
 *
 * Жодна помилка не мовчить. Порожній список без пояснення покупець
 * читає як «тут нічого немає», тож під полем завжди написано, що
 * саме сталося і що з цим робити.
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

  var texts = (window.brixCheckout && window.brixCheckout.strings) || {};
  var home = (window.brixCheckout && window.brixCheckout.home) || 'UA';
  var country = document.getElementById('billing_country');

  /**
   * Чи їде посилка країною, де працює Нова Пошта.
   *
   * Довідник український, тож для будь-якої іншої країни підказок
   * немає зовсім: покупець із Варшави шукав би «Wars» серед
   * українських міст і не знаходив нічого.
   */
  function atHome() {
    return !country || country.value === home;
  }

  /** Обгортає поле, вішає на нього список підказок і рядок стану. */
  function attach(field, name) {
    var holder = document.createElement('div');
    var box = document.createElement('div');
    var hidden = document.createElement('input');
    var note = document.createElement('p');

    holder.className = 'brix-np';
    field.parentNode.insertBefore(holder, field);
    holder.appendChild(field);

    box.className = 'brix-suggest brix-np__list';
    box.setAttribute('role', 'listbox');
    holder.appendChild(box);

    hidden.type = 'hidden';
    hidden.name = name;
    holder.appendChild(hidden);

    note.className = 'brix-np__note';
    note.hidden = true;
    // Рядок стану читається вголос, щойно змінився: людина зі
    // скрінрідером не бачить, що список спорожнів.
    note.setAttribute('role', 'status');
    holder.appendChild(note);

    field.setAttribute('autocomplete', 'off');
    field.setAttribute('role', 'combobox');
    field.setAttribute('aria-expanded', 'false');
    field.setAttribute('aria-autocomplete', 'list');

    return { field: field, box: box, hidden: hidden, note: note, current: -1, items: [] };
  }

  var cityBox = attach(city, 'brix_np_city_ref');
  var whBox = attach(warehouse, 'brix_np_warehouse_ref');

  /** Пише під полем, що відбувається. */
  function say(state, text) {
    state.note.textContent = text || '';
    state.note.hidden = !text;
  }

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
    say(state, '');

    if (after) {
      after(item);
    }
  }

  /** Запит до довідника. Розрізняє «забагато запитів» і решту бід. */
  function request(url) {
    return fetch(url, { headers: { Accept: 'application/json' } }).then(function (response) {
      if (response.status === 429) {
        throw new Error('too-many');
      }

      if (!response.ok) {
        throw new Error('http-' + response.status);
      }

      return response.json();
    });
  }

  /** Текст помилки під полем. */
  function excuse(error) {
    return error && 'too-many' === error.message ? texts.tooMany : texts.failed;
  }

  var cityCache = {};
  var whCache = {};
  var timer = null;

  /** Міста за запитом. Однакові запити другий раз не питаємо. */
  function findCities(query) {
    if (!atHome()) {
      return;
    }

    if (Object.prototype.hasOwnProperty.call(cityCache, query)) {
      show(cityBox, cityCache[query], texts.noCity);
      return;
    }

    say(cityBox, texts.loading);

    request(window.brixData.restUrl + 'np/cities?q=' + encodeURIComponent(query))
      .then(function (data) {
        cityCache[query] = data.items || [];
        show(cityBox, cityCache[query], texts.noCity);
      })
      .catch(function (error) {
        render(cityBox, []);
        say(cityBox, excuse(error));
      });
  }

  /** Відділення обраного міста: один запит на місто, далі з пам'яті. */
  function findWarehouses(filter) {
    if (!atHome()) {
      return;
    }

    var ref = cityBox.hidden.value;

    if (!ref) {
      render(whBox, []);
      say(whBox, texts.pickCity);
      return;
    }

    if (Object.prototype.hasOwnProperty.call(whCache, ref)) {
      showWarehouses(ref, filter);
      return;
    }

    say(whBox, texts.loading);

    request(window.brixData.restUrl + 'np/warehouses?city=' + encodeURIComponent(ref))
      .then(function (data) {
        whCache[ref] = data.items || [];
        showWarehouses(ref, filter);
      })
      .catch(function (error) {
        render(whBox, []);
        say(whBox, excuse(error));
      });
  }

  /** Фільтрує вже отримані відділення на місці. */
  function showWarehouses(ref, filter) {
    var needle = (filter || '').trim().toLowerCase();
    var list = whCache[ref];

    if (needle) {
      list = list.filter(function (item) {
        return item.name.toLowerCase().indexOf(needle) !== -1;
      });
    }

    // Обмеження показу, а не пошуку: у великому місті відділень
    // кілька сотень, і малювати їх усі немає сенсу.
    show(whBox, list.slice(0, 60), texts.noPlace);
  }

  /** Показує список або пояснює, чому він порожній. */
  function show(state, items, empty) {
    render(state, items);
    say(state, items.length ? '' : empty);
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
        say(state, '');
      }
    });

    state.field.addEventListener('blur', function () {
      // Затримка, щоб встиг спрацювати вибір мишкою.
      window.setTimeout(function () {
        render(state, []);
      }, 150);
    });
  }

  city.addEventListener('input', function () {
    // Ручна правка знецінює попередній вибір: поки місто не обране
    // зі списку, відділення шукати нема де.
    cityBox.hidden.value = '';
    whBox.hidden.value = '';
    render(whBox, []);
    say(whBox, '');

    window.clearTimeout(timer);
    timer = window.setTimeout(function () {
      var query = city.value.trim();

      if (query.length < 2) {
        render(cityBox, []);
        say(cityBox, '');
        return;
      }

      findCities(query);
    }, 220);
  });

  city.addEventListener('focus', function () {
    if (city.value.trim().length >= 2 && !cityBox.hidden.value) {
      findCities(city.value.trim());
    }
  });

  wire(cityBox, function () {
    // Щойно місто обране — одразу показуємо його відділення.
    warehouse.value = '';
    warehouse.focus();
    findWarehouses('');
  });

  warehouse.addEventListener('focus', function () {
    findWarehouses(warehouse.value);
  });

  warehouse.addEventListener('input', function () {
    whBox.hidden.value = '';
    findWarehouses(warehouse.value);
  });

  wire(whBox, null);

  /**
   * Зміна країни перебудовує поле адреси.
   *
   * Разом із підписами треба прибрати й те, що встигло набратися:
   * обране відділення Нової Пошти в полі польської адреси — не
   * адреса, а сміття, яке поїхало б у замовлення.
   *
   * Стираємо тільки те, що покупець обрав зі списку: якщо він писав
   * адресу руками, вона лишається його.
   */
  function onCountry() {
    if (atHome()) {
      return;
    }

    if (whBox.hidden.value) {
      warehouse.value = '';
    }

    if (cityBox.hidden.value) {
      city.value = '';
    }

    cityBox.hidden.value = '';
    whBox.hidden.value = '';

    render(cityBox, []);
    render(whBox, []);
    say(cityBox, '');
    say(whBox, '');
  }

  if (country) {
    country.addEventListener('change', onCountry);
    // select2 підміняє <select> своїм віджетом і шле подію через
    // jQuery — нативний слухач її не бачить.
    if (window.jQuery) {
      window.jQuery(country).on('change', onCountry);
    }
  }
})();
