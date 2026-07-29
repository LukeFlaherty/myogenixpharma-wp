(function () {
  'use strict';

  // Isolated to page-home-redesign.php — see home-redesign.css for the
  // matching "rdx-" namespace. Does not touch/duplicate home.js's nav logic.

  // ── Hide the site-wide exit-intent popup + GHL chat widget on this page ──
  // Both are injected globally by a Code Snippet (wp_footer output) outside
  // this repo, so they can't be removed at the source without affecting the
  // rest of the site. home-redesign.css hides them by selector; this catches
  // anything added after load or styled with inline styles that could beat
  // the CSS (e.g. the chat widget's loader script runs async).
  var WIDGET_SELECTOR = [
    '#lcp-overlay',
    '[id*="leadconnector" i]',
    '[class*="leadconnector" i]',
    '[id*="chat-widget" i]',
    '[class*="chat-widget" i]',
    'iframe[src*="leadconnectorhq"]'
  ].join(',');

  var hideWidgets = function () {
    document.querySelectorAll(WIDGET_SELECTOR).forEach(function (el) {
      el.style.setProperty('display', 'none', 'important');
    });
  };

  hideWidgets();
  var widgetObserver = new MutationObserver(hideWidgets);
  widgetObserver.observe(document.body, { childList: true, subtree: true });

  var hamburger = document.querySelector('.rdx-nav__hamburger');
  var drawer = document.getElementById('rdx-mobile-menu');
  var overlay = document.getElementById('rdx-nav-overlay');
  var drawerClose = document.querySelector('.rdx-nav__drawer-close');

  if (!hamburger || !drawer || !overlay) return;

  var lastFocused = null;

  var openDrawer = function () {
    lastFocused = document.activeElement;
    drawer.hidden = false;
    overlay.hidden = false;
    // Force layout before adding the transition classes so the slide-in animates.
    drawer.getBoundingClientRect();
    drawer.classList.add('is-open');
    overlay.classList.add('is-visible');
    document.body.classList.add('rdx-nav-open');
    hamburger.setAttribute('aria-expanded', 'true');
    if (drawerClose) drawerClose.focus();
  };

  var closeDrawer = function () {
    drawer.classList.remove('is-open');
    overlay.classList.remove('is-visible');
    document.body.classList.remove('rdx-nav-open');
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
})();
