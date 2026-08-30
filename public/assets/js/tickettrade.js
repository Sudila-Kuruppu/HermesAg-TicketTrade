/* =============================================================================
   TicketTrade — JavaScript Bundle
   -----------------------------------------------------------------------------
   Single vanilla-JS file, no build step. Components self-register on
   `[data-component]` selectors via a ComponentRegistry. Phase 1 ships
   prefersReducedMotion and themeController; Plan 01-02 adds the
   remaining six components (toast, bottomNav, skeleton, listViewToggle,
   modalScrimGuard, starRating).
   ============================================================================= */

(function () {
  'use strict';

  // ---------------------------------------------------------------------------
  // ComponentRegistry — collects components registered on DOMContentLoaded
  // ---------------------------------------------------------------------------
  const ComponentRegistry = {
    _components: new Map(),
    register: function (name, fn) {
      this._components.set(name, fn);
    },
    initAll: function (root) {
      root = root || document;
      this._components.forEach(function (fn, name) {
        try {
          fn(root);
        } catch (err) {
          console.error('TicketTrade component init failed:', name, err);
        }
      });
    }
  };

  // ---------------------------------------------------------------------------
  // prefersReducedMotion — toggles .reduce-motion on <html>
  // ---------------------------------------------------------------------------
  ComponentRegistry.register('prefersReducedMotion', function () {
    var mq = window.matchMedia('(prefers-reduced-motion: reduce)');
    var apply = function (matches) {
      if (matches) {
        document.documentElement.classList.add('reduce-motion');
      } else {
        document.documentElement.classList.remove('reduce-motion');
      }
    };
    apply(mq.matches);
    // addListener is deprecated but still widely supported; use addEventListener when available
    if (typeof mq.addEventListener === 'function') {
      mq.addEventListener('change', function (e) { apply(e.matches); });
    } else if (typeof mq.addListener === 'function') {
      mq.addListener(function (e) { apply(e.matches); });
    }
  });

  // ---------------------------------------------------------------------------
  // themeController — priority order:
  //   localStorage.tickettrade.theme > data-surface on <html> > matchMedia
  // ---------------------------------------------------------------------------
  ComponentRegistry.register('themeController', function () {
    var STORAGE_KEY = 'tickettrade.theme';
    var VALID_MODES = ['light', 'dark', 'system'];

    function readStored() {
      try {
        var v = window.localStorage.getItem(STORAGE_KEY);
        return VALID_MODES.indexOf(v) >= 0 ? v : null;
      } catch (e) {
        return null;
      }
    }

    function systemPrefersDark() {
      return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    function surfaceDefault() {
      var surface = document.documentElement.getAttribute('data-surface');
      return surface === 'admin' ? 'light' : 'dark';
    }

    /**
     * Resolve the theme to either 'light' or 'dark'.
     * 'system' resolves through matchMedia.
     */
    function resolveTheme() {
      var stored = readStored();
      var mode = stored || 'system';
      if (mode === 'system') {
        return systemPrefersDark() ? 'dark' : 'light';
      }
      return mode;
    }

    function applyTheme(theme) {
      document.documentElement.setAttribute('data-theme', theme);
    }

    /**
     * Set the theme mode. mode = 'light' | 'dark' | 'system'.
     */
    function setTheme(mode) {
      if (VALID_MODES.indexOf(mode) < 0) {
        console.warn('TicketTrade.setTheme: invalid mode', mode);
        return;
      }
      try {
        if (mode === 'system') {
          window.localStorage.removeItem(STORAGE_KEY);
        } else {
          window.localStorage.setItem(STORAGE_KEY, mode);
        }
      } catch (e) {
        // localStorage may be blocked; non-fatal
      }
      applyTheme(resolveTheme());
    }

    /**
     * Get the resolved theme ('light' or 'dark').
     */
    function getTheme() {
      return resolveTheme();
    }

    // Apply on init (FOUC-guard script in <head> already applied first paint)
    applyTheme(resolveTheme());

    // Re-apply when system preference changes (only if user has not explicitly chosen)
    if (window.matchMedia) {
      var mq = window.matchMedia('(prefers-color-scheme: dark)');
      var handler = function () {
        if (!readStored()) {
          applyTheme(resolveTheme());
        }
      };
      if (typeof mq.addEventListener === 'function') {
        mq.addEventListener('change', handler);
      } else if (typeof mq.addListener === 'function') {
        mq.addListener(handler);
      }
    }

    // Public API
    window.TicketTrade = window.TicketTrade || {};
    window.TicketTrade.setTheme = setTheme;
    window.TicketTrade.getTheme = getTheme;
    window.TicketTrade.prefersReducedMotion = function () {
      return document.documentElement.classList.contains('reduce-motion');
    };
  });

  // ---------------------------------------------------------------------------
  // toast (stub for Plan 01-01) — Plan 01-02 replaces with full impl
  // ---------------------------------------------------------------------------
  ComponentRegistry.register('toast', function () {
    // Phase 1 stub: no real container, just console-log for the demo call
    var stub = {
      show: function (message, type) {
        console.log('TicketTrade.toast (stub, full impl in 01-02):', type || 'info', message);
      },
      dismiss: function (id) {
        console.log('TicketTrade.toast.dismiss (stub):', id);
      }
    };
    window.TicketTrade = window.TicketTrade || {};
    if (!window.TicketTrade.toast) {
      window.TicketTrade.toast = stub;
    }
  });

  // ---------------------------------------------------------------------------
  // DOMContentLoaded: init all components
  // ---------------------------------------------------------------------------
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      ComponentRegistry.initAll(document);
    });
  } else {
    ComponentRegistry.initAll(document);
  }
})();
