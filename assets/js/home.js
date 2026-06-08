(function () {
  'use strict';

  // ── Hamburger / mobile menu ────────────────────────────
  var hamburger = document.querySelector('.home-nav__hamburger');
  var mobileMenu = document.querySelector('.home-nav__mobile-menu');

  if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', function () {
      var isOpen = mobileMenu.classList.contains('is-open');
      mobileMenu.classList.toggle('is-open', !isOpen);
      hamburger.setAttribute('aria-expanded', String(!isOpen));
    });
  }

  // ── FAQ accordion ──────────────────────────────────────
  var items = document.querySelectorAll('.hp-faq-item');

  items.forEach(function (item) {
    var btn = item.querySelector('.hp-faq-btn');
    if (!btn) return;

    btn.addEventListener('click', function () {
      var isOpen = item.classList.contains('is-open');
      // Close all
      items.forEach(function (i) { i.classList.remove('is-open'); });
      // Open this one if it was closed
      if (!isOpen) item.classList.add('is-open');
    });
  });

  // Open first item by default
  if (items.length > 0) {
    items[0].classList.add('is-open');
  }
})();
