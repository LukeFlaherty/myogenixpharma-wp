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

  // ── Smart sticky navbar ───────────────────────────────
  // Hide on downward scroll, then reveal as soon as the user scrolls up.
  var header = document.querySelector('.home-nav');

  if (header) {
    var lastScrollY = window.scrollY || 0;
    var ticking = false;
    var threshold = 96;
    var minDelta = 6;

    var updateHeader = function () {
      var currentScrollY = window.scrollY || 0;
      var delta = currentScrollY - lastScrollY;
      var menuIsOpen = mobileMenu && mobileMenu.classList.contains('is-open');

      if (menuIsOpen || currentScrollY <= threshold || delta < -minDelta) {
        header.classList.remove('home-nav--hidden');
      } else if (delta > minDelta && currentScrollY > threshold) {
        header.classList.add('home-nav--hidden');
      }

      lastScrollY = Math.max(currentScrollY, 0);
      ticking = false;
    };

    window.addEventListener('scroll', function () {
      if (!ticking) {
        window.requestAnimationFrame(updateHeader);
        ticking = true;
      }
    }, { passive: true });
  }

  // ── FAQ accordion (site-wide: home page, category pages, and any PDP that
  //    doesn't load its own pdp.js/peptide-pdp.js/sexual-health-pdp.js) ──
  // home.js is enqueued unconditionally on every page, so this is the single
  // shared accordion handler for .myo-faq__btn. PDP-specific scripts must NOT
  // duplicate this logic (see pdp.js) — two click listeners on the same button
  // toggle aria-expanded twice per click and cancel each other out.
  var faqBtns = Array.prototype.slice.call(document.querySelectorAll('.myo-faq__btn'));

  faqBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var isExpanded = this.getAttribute('aria-expanded') === 'true';
      var panel = document.getElementById(this.getAttribute('aria-controls'));
      if (!panel) return;

      faqBtns.forEach(function (other) {
        if (other === btn) return;
        other.setAttribute('aria-expanded', 'false');
        var otherPanel = document.getElementById(other.getAttribute('aria-controls'));
        if (otherPanel) otherPanel.classList.remove('is-open');
      });

      this.setAttribute('aria-expanded', String(!isExpanded));
      panel.classList.toggle('is-open', !isExpanded);
    });
  });
})();
