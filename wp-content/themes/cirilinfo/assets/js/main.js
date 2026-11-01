/* CirilInfo theme behaviour */
(function () {
  'use strict';
  var header = document.querySelector('[data-ci-header]');
  var burger = document.querySelector('[data-ci-burger]');
  var nav = document.getElementById('ci-nav');
  function onScroll() { if (header) { header.classList.toggle('is-stuck', window.scrollY > 8); } }
  onScroll(); window.addEventListener('scroll', onScroll, { passive: true });
  if (burger && nav) {
    burger.addEventListener('click', function () {
      var open = burger.getAttribute('aria-expanded') !== 'true';
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      nav.classList.toggle('is-open', open);
      document.body.classList.toggle('ci-noscroll', open);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && nav.classList.contains('is-open')) { burger.click(); burger.focus(); }
    });
    nav.querySelectorAll('.menu-item-has-children > a').forEach(function (a) {
      a.addEventListener('click', function (e) {
        if (window.innerWidth < 960 && !a.parentNode.classList.contains('is-sub-open')) { e.preventDefault(); a.parentNode.classList.add('is-sub-open'); }
      });
    });
  }
})();
