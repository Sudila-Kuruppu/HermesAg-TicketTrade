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
  // toast — Plan 01-02 full implementation
  //   show(message, type)        returns a numeric id
  //   dismiss(id)                removes the toast with that id
  //   Container is cached on init; cap = 3; type whitelist = success|error|warning|info
  // ---------------------------------------------------------------------------
  ComponentRegistry.register('toast', function () {
    var TYPES = ['success', 'error', 'warning', 'info'];
    var QUEUE_CAP = 3;
    var DEFAULT_MS = 4000;
    var LONG_MS = 8000;
    var _container = null;
    var _queue = [];          // [{id, el, timer, remainingMs, expiresAt, paused}]
    var _nextId = 1;

    function getContainer() {
      if (_container && document.body.contains(_container)) return _container;
      _container = document.querySelector('[data-component="toast"]');
      if (!_container) {
        _container = document.createElement('div');
        _container.setAttribute('data-component', 'toast');
        _container.setAttribute('role', 'status');
        _container.setAttribute('aria-live', 'polite');
        _container.setAttribute('aria-atomic', 'true');
        _container.className = 'toast-container';
        document.body.appendChild(_container);
      }
      return _container;
    }

    function syncContainerRole() {
      var container = getContainer();
      var hasAlert = _queue.some(function (q) { return q.el.getAttribute('role') === 'alert'; });
      container.setAttribute('role', hasAlert ? 'alert' : 'status');
    }

    function buildEl(message, type) {
      var el = document.createElement('div');
      el.className = 'toast toast-' + type;
      var isAlert = type === 'error' || type === 'warning';
      el.setAttribute('role', isAlert ? 'alert' : 'status');
      el.setAttribute('data-toast-id', String(_nextId));
      el.setAttribute('data-toast-type', type);

      var msg = document.createElement('span');
      msg.className = 'toast__message';
      msg.textContent = String(message);   // textContent: no HTML injection
      el.appendChild(msg);

      if (isAlert) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'toast__dismiss';
        btn.setAttribute('aria-label', 'Dismiss');
        btn.setAttribute('data-toast-dismiss', '');
        btn.textContent = '×';
        el.appendChild(btn);
      }

      return el;
    }

    function armTimer(entry) {
      entry.timer = setTimeout(function () {
        removeEntry(entry);
      }, entry.remainingMs);
    }

    function clearTimer(entry) {
      if (entry.timer) {
        clearTimeout(entry.timer);
        entry.timer = null;
        // Clamp to 50ms minimum: if the toast already expired while
        // paused (very long hover), re-arming with 0ms fires immediately
        // and can race with the click handler. 50ms gives the DOM a
        // tick to settle.
        entry.remainingMs = Math.max(50, entry.expiresAt - Date.now());
      }
    }

    function pauseEntry(entry) {
      if (entry.paused) return;
      clearTimer(entry);
      entry.paused = true;
      entry.el.classList.add('toast-paused');
    }

    function resumeEntry(entry) {
      if (!entry.paused) return;
      entry.paused = false;
      entry.el.classList.remove('toast-paused');
      armTimer(entry);
    }

    function removeEntry(entry) {
      if (!entry || !entry.el) return;
      clearTimer(entry);
      if (entry.el.parentNode) entry.el.parentNode.removeChild(entry.el);
      var idx = _queue.indexOf(entry);
      if (idx >= 0) _queue.splice(idx, 1);
      syncContainerRole();
    }

    function attachHoverHandlers(entry) {
      var onEnter = function () { pauseEntry(entry); };
      var onLeave = function () { resumeEntry(entry); };
      entry.el.addEventListener('mouseenter', onEnter);
      entry.el.addEventListener('mouseleave', onLeave);
      entry.el.addEventListener('focusin', onEnter);
      entry.el.addEventListener('focusout', onLeave);
      if (entry.el.__dismissBtn) {
        entry.el.__dismissBtn.addEventListener('click', function () {
          removeEntry(entry);
        });
      }
    }

    function show(message, type) {
      if (TYPES.indexOf(type) < 0) {
        console.warn('TicketTrade.toast: unknown type', type, '- falling back to info');
        type = 'info';
      }
      while (_queue.length >= QUEUE_CAP) {
        removeEntry(_queue[0]);
      }
      var container = getContainer();
      var el = buildEl(message, type);
      var id = _nextId++;
      var entry = {
        id: id,
        el: el,
        timer: null,
        remainingMs: (type === 'error' || type === 'warning') ? LONG_MS : DEFAULT_MS,
        expiresAt: 0,
        paused: false
      };
      entry.expiresAt = Date.now() + entry.remainingMs;
      var dismissBtn = el.querySelector('[data-toast-dismiss]');
      entry.el.__dismissBtn = dismissBtn;
      _queue.push(entry);
      container.appendChild(el);
      syncContainerRole();
      attachHoverHandlers(entry);
      armTimer(entry);
      return id;
    }

    function dismiss(id) {
      var entry = _queue.find(function (q) { return q.id === id; });
      if (entry) removeEntry(entry);
    }

    window.TicketTrade = window.TicketTrade || {};
    window.TicketTrade.toast = { show: show, dismiss: dismiss };

    // Touch the container now so it's mounted and aria attributes are set
    getContainer();
  });

  // ---------------------------------------------------------------------------
  // bottomNav — sets aria-current="page" on the item matching window.location
  // ---------------------------------------------------------------------------
  ComponentRegistry.register('bottomNav', function () {
    var navs = document.querySelectorAll('[data-component="bottom-nav"]');
    var path = (window.location.pathname || '/').toLowerCase();
    var currentFile = path.substring(path.lastIndexOf('/') + 1) || path;

    navs.forEach(function (nav) {
      var items = nav.querySelectorAll('.bottom-nav__item');
      items.forEach(function (item) {
        item.removeAttribute('aria-current');
        var href = (item.getAttribute('href') || '').toLowerCase();
        var hrefFile = href.substring(href.lastIndexOf('/') + 1);
        // Match if href resolves to current file (board-mobile.html, my-tickets.html, etc.)
        // or if href is the root / and current path is /
        if (hrefFile && hrefFile === currentFile) {
          item.setAttribute('aria-current', 'page');
        } else if ((href === '/' || href === '') && (path === '/' || path === '/index.php')) {
          item.setAttribute('aria-current', 'page');
        }
      });
    });
  });

  // ---------------------------------------------------------------------------
  // skeleton — applies the shimmer to elements with [data-skeleton] or .skeleton
  // ---------------------------------------------------------------------------
  ComponentRegistry.register('skeleton', function () {
    var nodes = document.querySelectorAll('[data-skeleton], .skeleton');
    nodes.forEach(function (el) {
      if (!el.classList.contains('skeleton')) {
        el.classList.add('skeleton');
      }
    });
  });

  // ---------------------------------------------------------------------------
  // listViewToggle — persists cork/list state to sessionStorage.tickettrade.listView
  // ---------------------------------------------------------------------------
  ComponentRegistry.register('listViewToggle', function () {
    var STORAGE_KEY = 'tickettrade.listView';
    var toggles = document.querySelectorAll('[data-component="list-view-toggle"]');

    function readStored() {
      try {
        var v = window.sessionStorage.getItem(STORAGE_KEY);
        return v === 'cork' || v === 'list' ? v : null;
      } catch (e) {
        return null;
      }
    }

    function writeStored(value) {
      try { window.sessionStorage.setItem(STORAGE_KEY, value); } catch (e) { /* no-op */ }
    }

    toggles.forEach(function (toggle) {
      var buttons = toggle.querySelectorAll('button');
      buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          buttons.forEach(function (b) { b.setAttribute('aria-pressed', 'false'); });
          btn.setAttribute('aria-pressed', 'true');
          var value = btn.getAttribute('data-value') || (btn.classList.contains('list-view-toggle__list') ? 'list' : 'cork');
          writeStored(value);
          document.documentElement.setAttribute('data-list-view', value);
        });
      });

      var stored = readStored();
      if (stored) {
        buttons.forEach(function (b) {
          var v = b.getAttribute('data-value') || (b.classList.contains('list-view-toggle__list') ? 'list' : 'cork');
          b.setAttribute('aria-pressed', v === stored ? 'true' : 'false');
        });
        document.documentElement.setAttribute('data-list-view', stored);
      }
    });
  });

  // ---------------------------------------------------------------------------
  // modalScrimGuard — ignore scrim clicks for the duration specified in the attribute
  // ---------------------------------------------------------------------------
  ComponentRegistry.register('modalScrimGuard', function () {
    var guards = document.querySelectorAll('[data-scrim-guard]');
    guards.forEach(function (el) {
      var ms = parseInt(el.getAttribute('data-scrim-guard'), 10);
      if (!ms || ms <= 0) ms = 2000;
      var armed = false;
      var activateTimer = null;

      function activate() {
        el.classList.add('modal-scrim-guard-active');
        armed = true;
        if (activateTimer) clearTimeout(activateTimer);
        activateTimer = setTimeout(function () {
          el.classList.remove('modal-scrim-guard-active');
          armed = false;
        }, ms);
      }

      el.addEventListener('mousedown', function (e) {
        if (armed && e.target === el) {
          e.stopPropagation();
          e.preventDefault();
        }
      });
      el.addEventListener('click', function (e) {
        if (armed && e.target === el) {
          e.stopPropagation();
          e.preventDefault();
        }
      });

      // If a modal is shown, reactivate on each show event
      el.addEventListener('shown.bs.modal', activate);
      activate();
    });
  });

  // ---------------------------------------------------------------------------
  // starRating — keyboard support for the fieldset-based star input
  // ---------------------------------------------------------------------------
  ComponentRegistry.register('starRating', function () {
    var fieldsets = document.querySelectorAll('[data-component="star-rating"]');
    fieldsets.forEach(function (fs) {
      var inputs = fs.querySelectorAll('input[type="radio"]');
      if (!inputs.length) return;
      var sorted = Array.prototype.slice.call(inputs).sort(function (a, b) {
        return parseInt(a.value, 10) - parseInt(b.value, 10);
      });

      function setRating(n) {
        var target = sorted.find(function (i) { return parseInt(i.value, 10) === n; });
        if (target) {
          target.checked = true;
          target.focus();
        }
      }

      fs.addEventListener('keydown', function (e) {
        var current = parseInt((fs.querySelector('input[type="radio"]:checked') || {}).value || '0', 10);
        var next = current;
        if (e.key === 'ArrowUp' || e.key === 'ArrowRight') {
          next = Math.min(5, current + 1 || 1);
        } else if (e.key === 'ArrowDown' || e.key === 'ArrowLeft') {
          next = Math.max(0, current - 1);
        } else if (e.key === 'Home') {
          next = 1;
        } else if (e.key === 'End') {
          next = 5;
        } else if (e.key === 'Delete' || e.key === 'Backspace') {
          next = 0;
        }
        if (next !== current) {
          e.preventDefault();
          if (next === 0) {
            sorted.forEach(function (i) { i.checked = false; });
            return;
          }
          setRating(next);
        }
      });

      inputs.forEach(function (input) {
        input.addEventListener('change', function () {
          var n = parseInt(input.value, 10);
          input.setAttribute('aria-label', 'Rating: ' + n + ' of 5');
        });
      });
    });
  });

  // ---------------------------------------------------------------------------
  // empty/error state — wires the retry button (data-error-state) on click
  // ---------------------------------------------------------------------------
  ComponentRegistry.register('emptyErrorRetry', function () {
    var retries = document.querySelectorAll('[data-error-state] .error-state__retry');
    retries.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var container = btn.closest('[data-error-state]');
        console.info('TicketTrade retry:', container);
        if (container) {
          container.dispatchEvent(new CustomEvent('tickettrade:retry', {
            bubbles: true,
            detail: { source: 'error-state', target: container }
          }));
        }
      });
    });
  });


  // ---------------------------------------------------------------------------
  // ticket-code-block — mask/reveal + copy + WhatsApp share (Plan 04-01)
  //
  // Reads data-code-value + data-seller-whatsapp from each element.
  // On Reveal click: replaces the masked text with the full code.
  // On Copy click: writes the full code to the clipboard and emits an
  //   aria-live confirmation. The WhatsApp share URL is built server-
  //   side in the PHP partial — the JS does NOT touch the href.
  // ---------------------------------------------------------------------------
  ComponentRegistry.register('ticket-code-block', function (root) {
    root = root || document;
    var blocks = root.querySelectorAll('[data-component="ticket-code-block"]');
    if (!blocks.length) return;
    blocks.forEach(function (block) {
      var codeEl = block.querySelector('[data-role="code"]');
      var toggleBtn = block.querySelector('[data-role="toggle"]');
      var copyBtn = block.querySelector('[data-role="copy"]');
      var confirmEl = block.querySelector('[data-role="confirmation"]');
      var fullCode = block.getAttribute('data-code-value') || '';
      if (!codeEl || !toggleBtn || !copyBtn || !fullCode) return;

      function showConfirmation(msg) {
        if (!confirmEl) return;
        confirmEl.textContent = msg;
        // Clear after a moment so the aria-live region does not spam
        // screen readers on subsequent copies.
        setTimeout(function () { confirmEl.textContent = ''; }, 1500);
      }

      function copyToClipboard(text) {
        if (navigator && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
          return navigator.clipboard.writeText(text);
        }
        // Fallback: a hidden textarea + execCommand.
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'absolute';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) { /* noop */ }
        document.body.removeChild(ta);
        return Promise.resolve();
      }

      toggleBtn.addEventListener('click', function () {
        var isPressed = toggleBtn.getAttribute('aria-pressed') === 'true';
        if (isPressed) {
          // Mask again
          toggleBtn.setAttribute('aria-pressed', 'false');
          toggleBtn.textContent = 'Reveal';
          codeEl.textContent = 'TK-****-****-****-****-****';
          block.classList.add('ticket-code-block--masked');
          copyBtn.setAttribute('hidden', '');
        } else {
          // Reveal
          toggleBtn.setAttribute('aria-pressed', 'true');
          toggleBtn.textContent = 'Hide';
          codeEl.textContent = fullCode;
          block.classList.remove('ticket-code-block--masked');
          copyBtn.removeAttribute('hidden');
        }
      });

      copyBtn.addEventListener('click', function () {
        copyToClipboard(fullCode).then(function () {
          showConfirmation('Copied');
        }).catch(function () {
          showConfirmation('Copy failed');
        });
      });
    });
  });


  // ---------------------------------------------------------------------------
  // starRatingInput — Phase 5 Plan 05-01. Hover/preview swap + Clear button.
  // Mirrors the visual contract of the existing `starRating` (Phase 1)
  // keyboard handler but uses Bootstrap Icons (`bi-star`/`bi-star-fill`)
  // instead of SVG. Distinct data-component name ("star-rating-input")
  // so the two patterns can coexist.
  //
  //   - On radio :hover / :focus-within, swap .bi-star -> .bi-star-fill
  //     on the icon label up to the hovered/checked value.
  //   - On radio change, commit the visual state.
  //   - On Clear link click, uncheck all radios + reset icons.
  // ---------------------------------------------------------------------------
  ComponentRegistry.register('starRatingInput', function () {
    var fieldsets = document.querySelectorAll('[data-component="star-rating-input"]');
    fieldsets.forEach(function (fs) {
      var icons = fs.querySelectorAll('.star-rating-input__icon');
      var inputs = fs.querySelectorAll('input[type="radio"]');
      if (!inputs.length) return;

      // Order icons ascending (1..5). They render in DOM order 5..1
      // (CSS row-reverse flips them visually) so map by data attribute.
      function setVisualState(n) {
        for (var k = 0; k < icons.length; k++) {
          var icon = icons[k];
          var value = parseInt(icon.getAttribute('data-rating-icon'), 10);
          if (!isNaN(value) && value <= n) {
            icon.classList.remove('bi-star');
            icon.classList.add('bi-star-fill');
          } else {
            icon.classList.remove('bi-star-fill');
            icon.classList.add('bi-star');
          }
        }
      }

      // Initialize from the currently-checked radio.
      var checked = fs.querySelector('input[type="radio"]:checked');
      setVisualState(checked ? parseInt(checked.value, 10) : 0);

      inputs.forEach(function (input) {
        input.addEventListener('change', function () {
          var n = parseInt(input.value, 10);
          if (!isNaN(n)) {
            setVisualState(n);
            input.setAttribute('aria-label', 'Rating: ' + n + ' of 5');
          }
        });
        // Mouse hover preview — swap icons live, commit on change.
        var label = fs.querySelector('label[for="' + input.id + '"]');
        if (label) {
          label.addEventListener('mouseenter', function () {
            var n = parseInt(input.value, 10);
            if (!isNaN(n)) setVisualState(n);
          });
          label.addEventListener('mouseleave', function () {
            var current = fs.querySelector('input[type="radio"]:checked');
            setVisualState(current ? parseInt(current.value, 10) : 0);
          });
        }
      });

      // Clear link: uncheck all radios and reset icons.
      var clear = fs.querySelector('[data-action="clear"]');
      if (clear) {
        clear.addEventListener('click', function (e) {
          e.preventDefault();
          inputs.forEach(function (i) { i.checked = false; });
          setVisualState(0);
        });
      }
    });
  });


  // ---------------------------------------------------------------------------
  // reviewModal — Phase 5 Plan 05-01. Live char counter for the comment
  // textarea + bootstrap auto-clear of the Submit-button disabled state.
  // ---------------------------------------------------------------------------
  ComponentRegistry.register('reviewModal', function () {
    var modals = document.querySelectorAll('[data-component="review-modal"]');
    modals.forEach(function (modal) {
      var textarea = modal.querySelector('[data-review-text]');
      var counter = modal.querySelector('[data-review-counter]');
      if (textarea && counter) {
        var maxLen = parseInt(textarea.getAttribute('maxlength'), 10) || 0;
        textarea.addEventListener('input', function () {
          var remaining = Math.max(0, maxLen - textarea.value.length);
          counter.textContent = String(remaining);
        });
      }
    });
  });


  // ---------------------------------------------------------------------------
  // tierProgress — Phase 6 Plan 06-01. Wires Bootstrap 5 stock tooltip on
  // elements with data-component="tier-progress" (the only Bootstrap
  // dependency the partial ships). The rank-badge partial already uses
  // data-bs-toggle="tooltip" so we only need to ensure new tier-progress
  // elements get initialized at boot. Guarded so a missing Bootstrap
  // global doesn't blow up the page.
  // ---------------------------------------------------------------------------
  ComponentRegistry.register('tierProgress', function (root) {
    root = root || document;
    if (typeof window.bootstrap === 'undefined' || !window.bootstrap.Tooltip) {
      return;
    }
    var nodes = root.querySelectorAll('[data-component="tier-progress"][data-bs-toggle="tooltip"]');
    nodes.forEach(function (el) {
      // eslint-disable-next-line no-new
      new window.bootstrap.Tooltip(el);
    });
  });

  // ---------------------------------------------------------------------------
  // buyConfirmModal — Phase 4 Plan 04-02 ROADMAP #1. The Buy Now button
  // opens a Bootstrap confirmation modal (data-scrim-guard="2" handled by
  // modalScrimGuard above). On Confirm click, submit the underlying
  // form referenced by data-buy-form-id. No new scrim handler — reuse.
  // ---------------------------------------------------------------------------
  ComponentRegistry.register('buyConfirmModal', function () {
    var triggers = document.querySelectorAll('[data-action="buy-confirm"]');
    triggers.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var formId = btn.getAttribute('data-buy-form-id');
        if (!formId) return;
        var form = document.getElementById(formId);
        if (form) form.submit();
      });
    });
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
