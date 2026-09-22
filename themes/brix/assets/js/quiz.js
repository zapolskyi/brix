/**
 * Квіз без перезавантаження.
 *
 * Кожен варіант відповіді — звичайне посилання на ту саму сторінку з
 * іншим набором параметрів, і без цього скрипта квіз проходиться
 * повністю. Тут лише заміна кроку на місці: підбір лотів рахує
 * сервер, і другої реалізації на JS немає.
 */
(function () {
  'use strict';

  var host = document.querySelector('[data-brix-quiz]');

  if (!host || !window.brixData || !window.fetch || !window.history.pushState) {
    return;
  }

  var request = 0;

  function load(url, push) {
    var params = new URLSearchParams(url.search);
    var ticket = ++request;

    host.style.minHeight = host.offsetHeight + 'px';
    host.classList.add('is-loading');

    fetch(window.brixData.restUrl + 'quiz?' + params.toString(), {
      headers: { Accept: 'application/json' },
    })
      .then(function (response) {
        return response.ok ? response.json() : Promise.reject(new Error('HTTP ' + response.status));
      })
      .then(function (data) {
        if (ticket !== request) {
          return;
        }

        host.innerHTML = data.html;

        if (push) {
          window.history.pushState({ brix: true }, '', data.url);
        }

        // Наступне питання має опинитись на початку екрана, інакше
        // на телефоні воно лишається нижче згорнутої шапки.
        var heading = host.querySelector('h1, h2');

        if (heading) {
          heading.setAttribute('tabindex', '-1');
          heading.focus({ preventScroll: true });
          heading.scrollIntoView({ block: 'start', behavior: 'smooth' });
        }
      })
      .catch(function () {
        window.location.assign(url.href);
      })
      .then(function () {
        if (ticket === request) {
          host.classList.remove('is-loading');
          host.style.minHeight = '';
        }
      });
  }

  host.addEventListener('click', function (event) {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
      return;
    }

    var link = event.target.closest('a[href]');

    if (!link || link.origin !== window.location.origin) {
      return;
    }

    // Посилання на товари з результату ведуть геть із квізу —
    // їх перехоплювати не треба.
    if (link.search.indexOf('q_') === -1 && link.search.indexOf('step=') === -1 && link.search.indexOf('result=') === -1) {
      return;
    }

    event.preventDefault();
    load(new URL(link.href), true);
  });

  window.addEventListener('popstate', function () {
    load(new URL(window.location.href), false);
  });
})();
