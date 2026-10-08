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
    var setNavHeight = function () {
      document.documentElement.style.setProperty('--myo-nav-height', header.offsetHeight + 'px');
    };

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
    window.addEventListener('resize', setNavHeight);
    setNavHeight();
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

// Customer stories: native scrolling keeps every story usable without JavaScript.
(function () {
  'use strict';
  var carousel = document.querySelector('[data-review-carousel]');
  if (!carousel) return;
  var track = carousel.querySelector('.myo-stories__track');
  var cards = Array.from(track.children);
  var pause = carousel.querySelector('[data-carousel-pause]');
  var status = carousel.querySelector('[data-carousel-status]');
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  var paused = reduced.matches;
  var hovered = false;
  var visible = false;
  var timer;
  carousel.querySelector('.myo-stories__controls').hidden = false;
  function position() {
    var step = cards[1].offsetLeft - cards[0].offsetLeft;
    return Math.round(track.scrollLeft / step);
  }
  function move(direction) {
    var step = cards[1].offsetLeft - cards[0].offsetLeft;
    var max = track.scrollWidth - track.clientWidth;
    var target = track.scrollLeft + direction * step;
    if (direction > 0 && track.scrollLeft >= max - 2) target = 0;
    if (direction < 0 && track.scrollLeft <= 2) target = max;
    track.scrollTo({ left: target, behavior: reduced.matches ? 'instant' : 'smooth' });
  }
  function schedule() {
    clearInterval(timer);
    pause.textContent = paused ? 'Play' : 'Pause';
    pause.setAttribute('aria-label', paused ? 'Play customer stories' : 'Pause customer stories');
    status.setAttribute('aria-live', paused ? 'polite' : 'off');
    if (!paused && !hovered && visible && !document.hidden) timer = setInterval(function () { move(1); }, 7000);
  }
  pause.addEventListener('click', function () { paused = !paused; schedule(); });
  carousel.querySelector('[data-carousel-prev]').addEventListener('click', function () { paused = true; move(-1); schedule(); });
  carousel.querySelector('[data-carousel-next]').addEventListener('click', function () { paused = true; move(1); schedule(); });
  carousel.addEventListener('mouseenter', function () { hovered = true; schedule(); });
  carousel.addEventListener('mouseleave', function () { hovered = false; schedule(); });
  carousel.addEventListener('focusin', function (event) { if (event.target !== pause) { paused = true; schedule(); } });
  track.addEventListener('pointerdown', function () { paused = true; schedule(); }, { passive: true });
  track.addEventListener('keydown', function (event) {
    if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
      event.preventDefault(); paused = true; move(event.key === 'ArrowRight' ? 1 : -1); schedule();
    }
  });
  track.addEventListener('scroll', function () { status.textContent = String(position() + 1).padStart(2, '0') + ' / 10'; }, { passive: true });
  reduced.addEventListener('change', function () { if (reduced.matches) paused = true; schedule(); });
  document.addEventListener('visibilitychange', schedule);
  new IntersectionObserver(function (entries) { visible = entries[0].isIntersecting; schedule(); }, { threshold: 0.2 }).observe(carousel);
  schedule();
})();
