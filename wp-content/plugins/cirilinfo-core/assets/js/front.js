/* CirilInfo Core – front-end behaviour (no dependencies) */
(function () {
  'use strict';
  var cfg = window.CirilInfo || {};
  var i18n = cfg.i18n || {};
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* Scroll reveal */
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('is-visible'); io.unobserve(e.target); } });
    }, { threshold: 0.12 });
    $$('.reveal').forEach(function (el) { io.observe(el); });
  } else {
    $$('.reveal').forEach(function (el) { el.classList.add('is-visible'); });
  }

  /* Pricing tabs */
  $$('[data-ci-tabs]').forEach(function (root) {
    var tabs = $$('[role=tab]', root), panels = $$('[role=tabpanel]', root);
    function show(i) {
      tabs.forEach(function (t, n) { t.classList.toggle('is-active', n === i); t.setAttribute('aria-selected', n === i ? 'true' : 'false'); });
      panels.forEach(function (p, n) { p.hidden = n !== i; });
    }
    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { show(i); });
      t.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight') { show((i + 1) % tabs.length); tabs[(i + 1) % tabs.length].focus(); }
        if (e.key === 'ArrowLeft') { show((i - 1 + tabs.length) % tabs.length); tabs[(i - 1 + tabs.length) % tabs.length].focus(); }
      });
    });
  });

  /* FAQ search */
  $$('[data-ci-faq]').forEach(function (root) {
    var input = $('[data-ci-faq-search]', root);
    if (!input) { return; }
    var items = $$('.ci-faq__item', root), empty = $('.ci-faq__empty', root);
    input.addEventListener('input', function () {
      var q = input.value.trim().toLowerCase(), shown = 0;
      items.forEach(function (it) {
        var hit = !q || it.textContent.toLowerCase().indexOf(q) !== -1;
        it.hidden = !hit; if (hit) { shown++; }
        if (q && hit) { it.open = true; } else if (!q) { it.open = false; }
      });
      if (empty) { empty.hidden = shown !== 0; }
    });
  });

  /* Portfolio filter */
  $$('.ci-filter').forEach(function (bar) {
    var grid = bar.nextElementSibling;
    bar.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) { return; }
      $$('button', bar).forEach(function (x) { x.classList.toggle('is-active', x === b); });
      var f = b.getAttribute('data-filter');
      $$('.ci-work', grid).forEach(function (w) {
        var cats = (w.getAttribute('data-cats') || '').split(' ');
        w.hidden = f !== '*' && cats.indexOf(f) === -1;
      });
    });
  });

  /* Live estimate on package cards (display only – server recalculates price) */
  function money(n) {
    var s = Math.round(n * 100) / 100, parts = s.toFixed(s % 1 ? 2 : 0).split('.'), x = parts[0], last = x.slice(-3), rest = x.slice(0, -3);
    if (rest) { last = rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + last; }
    return '₹' + last + (parts[1] ? '.' + parts[1] : '');
  }
  $$('[data-ci-plan]').forEach(function (plan) {
    var out = $('[data-ci-total]', plan); if (!out) { return; }
    var unit = parseFloat(plan.getAttribute('data-unit')) || 0;
    function calc() {
      var termEl = $('[data-ci-term]', plan), term = termEl ? parseInt(termEl.value, 10) || 1 : 1, total = unit * term;
      $$('input[name="ci_addons[]"]:checked', plan).forEach(function (c) {
        var p = parseFloat(c.getAttribute('data-price')) || 0; total += c.getAttribute('data-recurring') === '1' ? p * term : p;
      });
      out.textContent = (i18n.estimate || 'Estimated total:') + ' ' + money(total);
    }
    plan.addEventListener('change', calc); calc();
  });

  /* Fresh nonces for cached pages */
  function refreshNonce(form, type, field) {
    if (form.__ciFresh || !cfg.ajax) { return; }
    form.__ciFresh = true;
    fetch(cfg.ajax + '?action=ci_nonce&form=' + encodeURIComponent(type), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (j && j.success) { var f = form.querySelector('[name="' + field + '"]'); if (f) { f.value = j.data.nonce; } } })
      .catch(function () {});
  }
  $$('form[data-ci-form]').forEach(function (f) {
    ['focusin', 'pointerdown'].forEach(function (ev) { f.addEventListener(ev, function () { refreshNonce(f, f.getAttribute('data-ci-form'), '_ci_nonce'); }, { once: true }); });
  });
  $$('form[data-ci-cart]').forEach(function (f) {
    ['focusin', 'pointerdown'].forEach(function (ev) { f.addEventListener(ev, function () { refreshNonce(f, 'cart', '_ci_cart_nonce'); }, { once: true }); });
  });

  /* AJAX form submit with graceful fallback */
  $$('form[data-ci-form]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.fetch || !window.FormData) { return; }
      if (!form.checkValidity()) { e.preventDefault(); form.reportValidity(); return; }
      e.preventDefault();
      var btn = form.querySelector('[type=submit]'), label = btn ? btn.innerHTML : '';
      form.classList.add('is-busy'); if (btn) { btn.textContent = i18n.sending || 'Sending…'; }
      var type = form.getAttribute('data-ci-form');
      fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          var box = document.createElement('div'); box.setAttribute('role', j.success ? 'status' : 'alert');
          box.className = 'ci-notice ' + (j.success ? 'ci-notice--ok' : 'ci-notice--err');
          box.textContent = (j.data && j.data.message) || (i18n.error || 'Error');
          var old = form.parentNode.querySelector('.ci-notice[data-ci-js]'); if (old) { old.remove(); }
          box.setAttribute('data-ci-js', '1'); form.parentNode.insertBefore(box, form);
          if (j.success) {
            form.reset();
            if (window.ciEvent && (type === 'contact' || type === 'quote' || type === 'newsletter')) {
              window.ciEvent('generate_lead', { form: type }, { fb: 'Lead' });
            }
            if (type === 'ticket' || type === 'ticket_reply' || type === 'project_message' || type === 'requirements') { setTimeout(function () { location.reload(); }, 900); }
          }
        })
        .catch(function () {
          var box = document.createElement('div'); box.className = 'ci-notice ci-notice--err'; box.textContent = i18n.error || 'Error'; form.parentNode.insertBefore(box, form);
        })
        .finally(function () { form.classList.remove('is-busy'); if (btn) { btn.innerHTML = label; } });
    });
  });

  /* Cookie consent (only when tracking IDs are configured and consent is required) */
  if (cfg.consent) {
    var state = null; try { state = localStorage.getItem('ci_consent'); } catch (e) {}
    if (!state) {
      var bar = document.createElement('div'); bar.className = 'ci-consent'; bar.setAttribute('role', 'dialog'); bar.setAttribute('aria-label', 'Cookie consent');
      bar.innerHTML = '<p></p><button type="button" class="ci-consent__no"></button><button type="button" class="ci-consent__ok"></button>';
      bar.querySelector('p').textContent = i18n.cookie || '';
      bar.querySelector('.ci-consent__ok').textContent = i18n.accept || 'Accept';
      bar.querySelector('.ci-consent__no').textContent = i18n.decline || 'Decline';
      document.body.appendChild(bar);
      bar.addEventListener('click', function (e) {
        var ok = e.target.classList.contains('ci-consent__ok'), no = e.target.classList.contains('ci-consent__no');
        if (!ok && !no) { return; }
        try { localStorage.setItem('ci_consent', ok ? 'granted' : 'denied'); } catch (x) {}
        bar.remove(); if (ok && window.ciLoadTracking) { window.ciLoadTracking(); }
      });
    }
  }
})();
