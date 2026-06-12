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
