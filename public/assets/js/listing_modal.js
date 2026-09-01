/* =============================================================================
   TicketTrade — listing_modal.js
   -----------------------------------------------------------------------------
   Self-registering component for the listing modal on the board page.

   Behaviour (per Phase 3 Plan 03-03 + CONTEXT D-20..D-24):
   - Click a corkboard card → open the modal with that listing's content.
   - Modal HTML is pre-rendered server-side for the first visible listing.
     Subsequent prev/next swaps load the new content via fetch() from
     /listings/{id}?fragment=1 (a JSON endpoint — we fetch the rendered
     modal body partial).
   - Keyboard: ← / → prev/next within the modal; Esc closes; Tab cycles
     within the modal (focus trap).
   - Touch: a horizontal swipe (50px threshold) navigates prev/next.
   - URL fragment: /board#listing-{id} on open, removed on close.
   - prefers-reduced-motion: the carousel uses cross-fade instead of slide.
   - On close: focus returns to the originating card.

   The script is registered via the same ComponentRegistry pattern used by
   /assets/js/tickettrade.js, but since that file does not know about this
   component, this file is loaded AFTER tickettrade.js and bootstraps on
   DOMContentLoaded.
   ============================================================================= */
(function () {
  'use strict';

  var COMPONENT = 'listingModal';
  var SWIPE_THRESHOLD_PX = 50;
  var KEY_PREV = ['ArrowLeft'];
  var KEY_NEXT = ['ArrowRight'];
  var KEY_CLOSE = ['Escape', 'Esc'];

  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function setupListingModal(root) {
    var modalEl = root.matches && root.matches('[data-component="' + COMPONENT + '"]')
      ? root
      : qs('[data-component="' + COMPONENT + '"]', root);
    if (!modalEl) return;

    var body = qs('[data-listing-modal-body]', modalEl);
    var prevBtn = qs('[data-listing-nav="prev"]', modalEl);
    var nextBtn = qs('[data-listing-nav="next"]', modalEl);
    var closeBtn = qs('.listing-modal__close', modalEl);

    var openedFromCard = null;
    var lastListingId = null;

    function listingIdFromCard(card) {
      if (!card) return null;
      var id = card.getAttribute('data-listing-id');
      return id ? parseInt(id, 10) : null;
    }

    function updateUrlHash(id) {
      try {
        if (id) {
          if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', '/board#listing-' + id);
          } else {
            window.location.hash = 'listing-' + id;
          }
        } else if (window.history && window.history.replaceState) {
          var path = window.location.pathname + window.location.search;
          window.history.replaceState(null, '', path);
        }
      } catch (e) { /* non-fatal */ }
    }

    function getOpenCard() {
      // Find the most recently focused card (or the one matching the hash)
      var hash = (window.location.hash || '').replace('#listing-', '');
      if (hash) {
        var byHash = qs('.cork-cell[data-listing-id="' + hash + '"]');
        if (byHash) return byHash;
      }
      return openedFromCard;
    }

    function returnFocusToCard() {
      var card = getOpenCard();
      if (card) {
        try { card.focus(); } catch (e) { /* non-fatal */ }
      }
    }

    function showCardOnBoard(id) {
      // Update the cork-cell for the new id so the focus return is correct.
      openedFromCard = qs('.cork-cell[data-listing-id="' + id + '"]');
    }

    function setModalContent(html, listingId) {
      if (!body) return;
      body.innerHTML = html;
      lastListingId = listingId;
      updateUrlHash(listingId);
      // Re-init the carousel if Bootstrap 5 is present
      try {
        var car = qs('.carousel', body);
        if (car && window.bootstrap) {
          // The carousel is already in the markup; nothing to do.
        }
      } catch (e) { /* non-fatal */ }
    }

    function setTitle(text) {
      var titleEl = qs('.listing-modal__title', modalEl);
      if (titleEl) titleEl.textContent = text || 'Listing';
    }

    function navigate(direction) {
      // Walk the modal's current prev/next IDs. The server provides the
      // initial pair; subsequent walks fall back to a fetch if the
      // server-rendered prev/next don't match the live current listing.
      var currentId = lastListingId;
      if (!currentId) return;

      var url = '/listings/' + currentId + '/fragment?nav=' + direction;
      fetch(url, {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin'
      })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (data && data.ok && data.html) {
            setModalContent(data.html, data.listing_id);
            setTitle(data.title);
            showCardOnBoard(data.listing_id);
          } else {
            // End of list (no next/prev). Just close the modal.
            try {
              if (window.bootstrap) {
                var m = window.bootstrap.Modal.getInstance(modalEl);
                if (m) m.hide();
              }
            } catch (e) { /* non-fatal */ }
          }
        })
        .catch(function () {
          // Network failure: close the modal rather than strand the user.
          try {
            if (window.bootstrap) {
              var m = window.bootstrap.Modal.getInstance(modalEl);
              if (m) m.hide();
            }
          } catch (e) { /* non-fatal */ }
        });
    }

    // -------- Card click handler -----------------------------------------
    qsa('.cork-cell .listing-card-cork-link, .cork-cell, [data-bs-toggle="modal"][data-bs-target="#listingModal"]').forEach(function (card) {
      card.addEventListener('click', function (e) {
        // For the cork-cell which has aria-hidden, the link inside is the real target.
        var link = e.currentTarget.matches('.listing-card-cork-link, [data-bs-toggle="modal"]')
          ? e.currentTarget
          : qs('.listing-card-cork-link, [data-bs-toggle="modal"][data-bs-target="#listingModal"]', e.currentTarget);
        if (!link) return;
        var id = listingIdFromCard(link) || (link.closest('[data-listing-id]') || {}).getAttribute;
        var lid = link.getAttribute('data-listing-id');
        if (lid) {
          openedFromCard = link.closest('.cork-cell') || link;
          lastListingId = parseInt(lid, 10);
          // If the modal HTML doesn't match this listing, fetch it.
          var initialId = body.getAttribute('data-initial-listing-id');
          if (!initialId || parseInt(initialId, 10) !== parseInt(lid, 10)) {
            navigate('open');
          } else {
            updateUrlHash(lid);
          }
        }
      }, true);
    });

    // -------- Prev/Next buttons ------------------------------------------
    if (prevBtn) prevBtn.addEventListener('click', function () { navigate('prev'); });
    if (nextBtn) nextBtn.addEventListener('click', function () { navigate('next'); });

    // -------- Keyboard navigation (←, →, Esc) ----------------------------
    document.addEventListener('keydown', function (e) {
      var isOpen = modalEl.classList.contains('show');
      if (!isOpen) return;
      if (KEY_PREV.indexOf(e.key) >= 0) {
        e.preventDefault();
        navigate('prev');
      } else if (KEY_NEXT.indexOf(e.key) >= 0) {
        e.preventDefault();
        navigate('next');
      } else if (KEY_CLOSE.indexOf(e.key) >= 0) {
        e.preventDefault();
        try {
          if (window.bootstrap) {
            var m = window.bootstrap.Modal.getInstance(modalEl);
            if (m) m.hide();
          }
        } catch (err) { /* non-fatal */ }
      } else if (e.key === 'Tab') {
        trapFocus(e, modalEl);
      }
    });

    // -------- Focus trap ------------------------------------------------
    function trapFocus(e, container) {
      var focusables = qsa(
        'a[href], button:not([disabled]), input:not([disabled]), ' +
        'select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
        container
      ).filter(function (el) {
        return el.offsetParent !== null || el === document.activeElement;
      });
      if (focusables.length === 0) {
        e.preventDefault();
        return;
      }
      var first = focusables[0];
      var last = focusables[focusables.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }

    // -------- Touch swipe (50px threshold) -------------------------------
    var touchStartX = null;
    var touchStartY = null;
    if (body) {
      body.addEventListener('touchstart', function (e) {
        if (!e.touches || e.touches.length === 0) return;
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
      }, { passive: true });
      body.addEventListener('touchend', function (e) {
        if (touchStartX === null) return;
        var t = (e.changedTouches && e.changedTouches[0]) || null;
        if (!t) { touchStartX = null; touchStartY = null; return; }
        var dx = t.clientX - touchStartX;
        var dy = t.clientY - touchStartY;
        if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > SWIPE_THRESHOLD_PX) {
          if (dx < 0) {
            navigate('next');
          } else {
            navigate('prev');
          }
        }
        touchStartX = null;
        touchStartY = null;
      }, { passive: true });
    }

    // -------- Modal lifecycle (open/close hooks) -------------------------
    modalEl.addEventListener('hidden.bs.modal', function () {
      updateUrlHash(null);
      // Return focus to the originating card after a tick (Bootstrap may
      // refocus the modal-backdrop otherwise).
      setTimeout(returnFocusToCard, 50);
    });
  }

  // Bootstrap on DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      qsa('[data-component="' + COMPONENT + '"]').forEach(setupListingModal);
    });
  } else {
    qsa('[data-component="' + COMPONENT + '"]').forEach(setupListingModal);
  }
})();
