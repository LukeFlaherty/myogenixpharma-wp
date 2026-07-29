(function () {
  'use strict';

  // ── Hamburger / mobile drawer ───────────────────────────
  var hamburger = document.querySelector('.home-nav__hamburger');
  var drawer = document.getElementById('home-mobile-menu');
  var overlay = document.getElementById('home-nav-overlay');
  var drawerClose = document.querySelector('.home-nav__drawer-close');

  if (hamburger && drawer && overlay) {
    var lastFocused = null;

    var openDrawer = function () {
      lastFocused = document.activeElement;
      drawer.hidden = false;
      overlay.hidden = false;
      // Force layout before adding the transition classes so the slide-in animates.
      drawer.getBoundingClientRect();
      drawer.classList.add('is-open');
      overlay.classList.add('is-visible');
      document.body.classList.add('home-nav-open');
      hamburger.setAttribute('aria-expanded', 'true');
      if (drawerClose) drawerClose.focus();
    };

    var closeDrawer = function () {
      drawer.classList.remove('is-open');
      overlay.classList.remove('is-visible');
      document.body.classList.remove('home-nav-open');
      hamburger.setAttribute('aria-expanded', 'false');
      window.setTimeout(function () {
        drawer.hidden = true;
        overlay.hidden = true;
      }, 320);
      if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
    };

    hamburger.addEventListener('click', function () {
      var isOpen = drawer.classList.contains('is-open');
      if (isOpen) {
        closeDrawer();
      } else {
        openDrawer();
      }
    });

    if (drawerClose) drawerClose.addEventListener('click', closeDrawer);
    overlay.addEventListener('click', closeDrawer);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && drawer.classList.contains('is-open')) closeDrawer();
    });

    // Simple focus trap: keep Tab cycling within the drawer while it's open.
    drawer.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab') return;
      var focusable = drawer.querySelectorAll('a[href], button:not([disabled])');
      if (!focusable.length) return;
      var first = focusable[0];
      var last = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    });

    // Close the drawer whenever a link inside it is activated.
    drawer.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', closeDrawer);
    });
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
