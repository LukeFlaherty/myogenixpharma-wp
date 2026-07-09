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

  // ── FAQ accordion (matches .myo-faq behavior on PDP, pdp.js) ──
  // home.js loads on every page (it also drives the sitewide navbar), but PDP/
  // peptide/sexual-health pages already run their own accordion init on the same
  // .myo-faq__btn markup. Without this guard, two click handlers fire per click
  // and immediately cancel each other out, leaving the FAQ un-clickable there.
  // Scope this block to the home page only, where home.js is the sole accordion.
  if (!document.body.classList.contains('myogenix-home-page')) {
    return;
  }

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
