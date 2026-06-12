/* PubWeb frontend JS — tiny, dependency-free, deferred.
   Only interactive concerns: mobile nav, search toggle. Ads are handled
   entirely by the external loader; nothing here blocks paint. */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') { fn(); }
    else { document.addEventListener('DOMContentLoaded', fn); }
  }

  ready(function () {
    var navToggle = document.querySelector('.nav-toggle');
    var nav = document.querySelector('.primary-nav');
    if (navToggle && nav) {
      navToggle.addEventListener('click', function () {
        var open = nav.classList.toggle('is-open');
        navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }

    var searchToggle = document.querySelector('.search-toggle');
    var searchBar = document.querySelector('.header-search');
    if (searchToggle && searchBar) {
      searchToggle.addEventListener('click', function () {
        var hidden = searchBar.hasAttribute('hidden');
        if (hidden) { searchBar.removeAttribute('hidden'); }
        else { searchBar.setAttribute('hidden', ''); }
        searchToggle.setAttribute('aria-expanded', hidden ? 'true' : 'false');
        if (hidden) {
          var field = searchBar.querySelector('input[type="search"]');
          if (field) { field.focus(); }
        }
      });
    }

    // Scroll-driven UI: header shrink, back-to-top, reading progress.
    var header = document.querySelector('.site-header');
    var shrink = document.body.classList.contains('pw-shrink');
    var toTop = document.querySelector('.pw-to-top');
    var progress = document.querySelector('.pw-progress__bar');
    var ticking = false;

    function onScroll() {
      var y = window.pageYOffset || document.documentElement.scrollTop;
      if (shrink && header) { header.classList.toggle('is-shrunk', y > 12); }
      if (toTop) {
        var show = y > 600;
        toTop.classList.toggle('is-visible', show);
        if (show) { toTop.removeAttribute('hidden'); }
      }
      if (progress) {
        var h = document.documentElement.scrollHeight - window.innerHeight;
        progress.style.width = (h > 0 ? (y / h) * 100 : 0) + '%';
      }
      ticking = false;
    }
    if (shrink || toTop || progress) {
      window.addEventListener('scroll', function () {
        if (!ticking) { window.requestAnimationFrame(onScroll); ticking = true; }
      }, { passive: true });
      onScroll();
    }
    if (toTop) {
      toTop.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    }

    // Dismiss the sticky anchor ad.
    var anchorClose = document.querySelector('.pw-anchor__close');
    if (anchorClose) {
      anchorClose.addEventListener('click', function () {
        var anchor = document.getElementById('pw-anchor');
        if (anchor) { anchor.classList.add('is-closed'); }
      });
    }

    // Close the mobile menu when a link is tapped.
    if (nav) {
      nav.addEventListener('click', function (e) {
        if (e.target.closest('a') && nav.classList.contains('is-open')) {
          nav.classList.remove('is-open');
          if (navToggle) { navToggle.setAttribute('aria-expanded', 'false'); }
        }
      });
    }
  });
})();
