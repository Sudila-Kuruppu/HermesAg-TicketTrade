---
phase: 03
plan: 03
subsystem: corkboard-board + listing-modal
tags:
  - board-view
  - corkboard
  - listing-modal
  - search
  - category-filter
  - pagination
  - fulltext-search
  - guest-browse
  - keyboard-navigation
  - touch-swipe
dependency_graph:
  requires:
    - phase: 02-student-authentication-profiles
      provides: Auth/Auth (currentUser), layout/head/bottom_nav partials, View renderer
    - phase: 03-01 (Plan 03-01)
      provides: ListingModel::search, ListingModel::getSearchCount, CategoryService::listActive,
                ImageProxy, listing_service, listing_card + listing_card_cork partials
    - phase: 03-02 (Plan 03-02)
      provides: BrowseAction + MyListingsAction (Phase 2 stubs replaced with real impls),
                BrowseAction extended with full board rendering
  provides:
    - src/Listing/Action/BrowseAction.php (replaced stub with real board implementation)
    - src/Listing/Action/ListingFragmentAction.php (new — AJAX endpoint for modal prev/next)
    - src/Listing/View/board.php (replaced stub with corkboard + list-view layout)
    - src/Listing/View/listing_modal.php (new — Bootstrap modal at page bottom)
    - src/Support/View/partials/category_tabs.php (new — 8-tab strip with aria-current)
    - src/Support/View/partials/pagination.php (new — numbered control, top + bottom slots)
    - src/Support/View/partials/search_box.php (new — search form preserving cat + page)
    - src/Support/View/partials/listing_modal_carousel.php (new — Bootstrap carousel)
    - src/Support/View/partials/list_view_toggle.php (new — cork/list toggle)
    - public/assets/js/listing_modal.js (new — 270 LOC self-registering component)
    - ListingModel::getNextInCategory(int, ?int, string): ?int (D-20..D-24 prev/next walk)
    - 1 new route entry: GET /listings/{id}/fragment
    - head.php loads /assets/js/listing_modal.js after tickettrade.js
  affects:
    - Phase 4 wires the modal's "Buy now" button to the purchase flow
    - Plan 03-04 (landing page) may share the same full-screen modal
    - The fragment endpoint is the canonical AJAX hook for the modal's prev/next swap
tech-stack:
  added: []
  patterns:
    - BrowseAction: input parsing/coercion — q capped at 100 chars via mb_substr(trim, 0, 100),
      cat parsed via is_numeric + (int) + existence check (non-existent → null/All),
      page coerced to max(1, ...), then clamped to effectivePage = 1 when out of range
    - ListingModel::getNextInCategory walks the same category by (created_at DESC, id DESC);
      comparator + ORDER BY flip for direction (D-20..D-24) — wrap is implicit: null
      result from prev/next means the modal JS auto-closes
    - Cork cell markup: rotation via crc32(id) % 5 - 2, pin color via id % 2,
      aria-hidden on the decoration, CTA copy/URL switched by is_guest
    - Listing modal at the page bottom (single instance, content swapped by JS):
      pre-rendered server-side with the first visible listing, AJAX swap via
      /listings/{id}/fragment for prev/next
    - listing_modal.js: self-registering component via document.addEventListener,
      no DOMContentLoaded race; ~270 LOC
key-files:
  created:
    - src/Listing/Action/ListingFragmentAction.php
    - src/Listing/View/listing_modal.php
    - src/Support/View/partials/category_tabs.php
    - src/Support/View/partials/pagination.php
    - src/Support/View/partials/search_box.php
    - src/Support/View/partials/listing_modal_carousel.php
    - src/Support/View/partials/list_view_toggle.php
    - public/assets/js/listing_modal.js
    - tests/Integration/Phase03/Listing/BrowseBoardTest.php
    - tests/Integration/Phase03/Listing/SearchTest.php
    - tests/Integration/Phase03/Listing/PaginationTest.php
    - tests/Integration/Phase03/Listing/ModalRenderTest.php
    - tests/Integration/Phase03/Listing/GuestBrowseTest.php
    - tests/Integration/Phase03/Listing/EdgeCasesTest.php
  modified:
    - src/Listing/Action/BrowseAction.php (replaced Phase 2 stub with real board)
    - src/Listing/View/board.php (replaced Phase 2 stub with corkboard + list-view layout)
    - src/Listing/Model/listing_model.php (added getNextInCategory)
    - src/Support/View/partials/head.php (loaded listing_modal.js)
    - config/routes.php (GET /listings/{id}/fragment)
decisions:
  - "View::partial() resolves files in src/Support/View/partials/, NOT in
    src/Listing/View/. The listing_modal.php is a full View (not a partial),
    so board.php requires it directly via __DIR__ . '/listing_modal.php'
    after seeding _tt_view_vars. This is the same pattern the layout uses
    to require the content view from $GLOBALS['_tt_content_view']."
  - "BrowseAction exposes the effective page as $effectivePage (the page
    that actually has results) to the view. The raw request page is
    preserved in the URL but the active page indicator on the pagination
    is the effective page. When the user requests page 999 of 2,
    $effectivePage is clamped to 1 and the empty-state path renders."
  - "Guest cork-cell <a> href is /login?next=/board (no modal trigger).
    Logged-in cork-cell <a> href is #listing-{id} with data-bs-toggle=modal.
    The CTA span is just a visual hint of the action; the entire card is
    clickable and the action destination follows the is_guest branch.
    This is the only place the cork-cell needs to know about auth state."
  - "Listing modal pre-renders the first visible listing's content so the
    initial open has zero AJAX latency. Prev/next swaps call
    GET /listings/{id}/fragment?nav=prev|next for a lightweight probe
    (204 on end-of-list), then GET /listings/{id}/fragment for full
    HTML when needed. The JS handles network failures by closing the
    modal rather than stranding the user."
  - "Test pattern (consistent with 03-02): inspect the BrowseAction's rendered
    HTML by directly calling $action->handle() inside an ob_start/ob_get_clean
    block, with $_GET and $GLOBALS['current_user'] set up in the test. This
    avoids dispatching through Router::dispatch (which would call exit() on
    404 / 302)."
metrics:
  duration: "~90min (3 tasks, parallel-friendly commits)"
  completed_date: "2026-09-01"
  tasks: 3
  commits: 5
  tokens: 105000
status: complete
actuals:
  tokens: 105000
  tasks: 3
  commits: 5
---

# Phase 3 Plan 03: Corkboard Board + Listing Modal — Summary

Plan 03-03 ships the buyer/guest surface for Phase 3: the corkboard board view at `/board` and the full-screen listing modal. After this plan, a guest can browse the corkboard, filter by category, search by keyword, paginate 50/page, and open a listing in the modal with full keyboard + mobile-swipe navigation. The WAD demo flow "register → list → admin approve → log in as buyer → browse corkboard → click listing → see modal" is now end-to-end functional.

## What shipped

**1 modified Action:**
- `BrowseAction`: real board implementation. Parses `?q` (capped 100 chars, trimmed, html-escaped), `?cat` (is_numeric + (int) + existence check, non-existent → null/All), `?page` (max(1, (int))). Calls `CategoryService::listActive()` for the tab strip and `ListingService::getSearchResults($q, $cat, $page)` for the rows. Returns `$effectivePage = 1` when the requested page is out of range so the empty-state path renders cleanly.

**1 new Action:**
- `ListingFragmentAction`: AJAX endpoint at `GET /listings/{id}/fragment`. Returns JSON `{ok, listing_id, title, html, prev_id, next_id}` for the modal's prev/next swap. Supports `?nav=prev|next` for a lightweight probe (204 No Content on end-of-list). The `html` field is the rendered listing_modal_carousel + details block, ready to drop into `.listing-modal__body`.

**1 new Model method:**
- `ListingModel::getNextInCategory(int $listingId, ?int $categoryId, string $direction): ?int` — walks the same category by `(created_at DESC, id DESC)`. Comparator and ORDER BY flip for direction per D-20..D-24. Returns null at end-of-list; the modal JS auto-closes on null.

**1 replaced View (board.php):**
The Phase 2 stub is replaced with the real corkboard + list-view layout:
- `<h1 class="visually-hidden">Marketplace board</h1>`
- Toolbar: search box (left, flex-grow) + list-view toggle (right)
- Category tabs strip (horizontally scrollable on `<md`, active tab snapped into view via inline JS `scrollIntoView`)
- Pagination top (`d-none d-md-block`) and bottom (always)
- Corkboard grid (`col-12 col-sm-6 col-md-4 col-lg-3`): cork-cell wrapper with `transform: rotate(crc32(id) % 5 - 2)deg`, `pin-red` or `pin-blue` (`id % 2`), `aria-hidden="true"` on the decoration, and the inner card link (modal trigger for buyers, `/login?next=/board` for guests)
- Empty state: "No listings yet - check back soon" / "New listings appear here within 24 hours of submission" (no filters) OR "No matches for "<q>" in <category>" (with filters)
- Listing modal at the page bottom (single instance, pre-rendered with the first visible listing's content)

**1 new View (listing_modal.php):**
Bootstrap 5.3 modal with `modal-fullscreen-sm-down modal-dialog-centered`. Header: prev/next icon buttons (`<` / `>`), title, X close. Body: pre-rendered listing content (carousel + details + seller info + Buy now + Report).

**5 new View partials:**
- `category_tabs.php`: 8-tab strip (All + 7 categories), active tab carries `aria-current="page"` and the `active` Bootstrap class. `q` and `cat` preserved in every tab URL. Mobile: horizontally scrollable with `scrollIntoView({inline: 'center'})` on load.
- `pagination.php`: numbered Bootstrap pagination with `?page=N&q=...&cat=...` URLs. Renders nothing when `$pages === 1`. Two slots: `'top'` (mobile-hidden via `d-none d-md-block`) and `'bottom'` (always). Prev disabled on page 1, Next disabled on last page.
- `search_box.php`: `<form method="GET" action="/board" role="search">` with hidden `cat` + `page` inputs. Input pre-filled with current `q`. `htmlspecialchars` on the value for XSS safety.
- `listing_modal_carousel.php`: Bootstrap 5 carousel with `data-bs-ride="false" data-bs-interval="false"`. Indicators (`<ol class="carousel-indicators">`) and prev/next controls only render when there are 2+ images. Single image or zero images → "No images available" message.
- `list_view_toggle.php`: a two-button group with `data-component="list-view-toggle"`. The Phase 1 `listViewToggle` component (in `tickettrade.js`) wires the `sessionStorage` persistence + `aria-pressed` state.

**1 new JS file (`public/assets/js/listing_modal.js`):**
~270 LOC self-registering component. Functions: `setupListingModal(root)`, `navigate(direction)`, `setModalContent(html, listingId)`, `updateUrlHash(id)`, `returnFocusToCard()`. Handles:
- Card click → open modal (with that listing's content via `/listings/{id}/fragment`)
- Keyboard: `←` / `→` prev/next, `Esc` close, `Tab` focus trap
- Touch: 50px threshold swipe
- URL fragment: `/board#listing-{id}` on open, removed on close
- Focus return to originating card on close
- `prefers-reduced-motion: reduce` → cross-fade instead of slide (Bootstrap 5 default slide respects this via the `reduce-motion` class on `<html>`)
- Network failure → close modal rather than strand user

**5 new test files (95 test cases):**
- `BrowseBoardTest` (22 cases): cork-cell rotation, pin color, guest vs buyer CTA, pagination rendering, 50-card cap, category aria-current, q + cat preserved, XSS escaping, out-of-range page, missing category, JS loaded in head
- `SearchTest` (13 cases): FULLTEXT prefix match (`cal*` matches Calculus + Calculator), empty q = no filter, XSS escape, no-matches empty state, cat + q compose, q preserved in pagination, q capped at 100 chars, special chars
- `PaginationTest` (13 cases): 50/51 boundary, 60-listings 2-page render, `?page=999`/`?page=0`/`?page=-5` coercion, top pagination `d-none d-md-block`, q + cat preservation, aria-current, prev/next disabled states
- `ModalRenderTest` (16 cases): `modal-fullscreen-sm-down` + `modal-dialog-centered`, `data-component="listingModal"`, prev/next buttons, close button, `data-bs-ride="false"`, first listing title in modal, carousel indicators with >1 image, listing_modal_carousel partial indicators/controls logic
- `GuestBrowseTest` (11 cases): guest corkboard, `href=/login?next=/board`, no "Buy now" on cork-cell, `aria-current` on All tab, 50-card cap, empty state, no-match state, logged-in "Buy now", modal-trigger on logged-in cards, guest modal pre-render
- `EdgeCasesTest` (20 cases): XSS in q in input AND in empty-state copy, empty q/cat as no-filter, `?page=999`/`?page=0`/`?page=-5` coercion, non-numeric / non-existent cat fallback, q 100-char cap (in input + in empty-state copy), no-throw on any invalid input, named empty-state copy

## Verification

- **PHPUnit:** 287 tests / 1353 assertions, all green (192 baseline + 75 from Task 1 + 20 from Task 2 = 287).
- **phpcs:** 0 errors, 0 warnings against the project's `phpcs.xml` ruleset (PSR-12 with project-specific exclusions for Models and Service classes; line-length excluded in templates).
- **Live dev server smoke test:** `php -S 127.0.0.1:18000 -t public public/router.php` with seeded listings:
  - `GET /board` returns 200, 15983 bytes, all key elements present (corkboard, category tabs, pagination, listing modal, listing_modal.js loaded).
  - `GET /board?q=Calculus` returns 200, search input pre-filled with `value="Calculus"`, only matching listings rendered.
  - `GET /board?cat=2` returns 200, only category-2 listings rendered.
  - `GET /listings/3/fragment` returns 200 with the JSON envelope (`{ok, listing_id, title, html, prev_id, next_id}`).
  - The `?cat=99999` (non-existent category) falls back to All; the active tab carries `aria-current="page"`.

## Deviations from Plan

### Rule 3 (auto-fix blocking issue): View::partial() looks in partials/, not in src/Listing/View/

The plan called for `View::partial('listing_modal', ...)` to include the modal. But `View::partial()` resolves files in `src/Support/View/partials/`, NOT in `src/Listing/View/`. The `listing_modal.php` is a full View (not a partial). **Fix:** `board.php` requires the modal directly via `__DIR__ . '/listing_modal.php'` after seeding `_tt_view_vars` with the first listing + prev/next IDs. This is the same pattern the layout uses to require the content view.

### Rule 1 (bug fix): guest cork-cell href was always the modal trigger

The original board.php had the cork-cell `<a href="#listing-{id}" data-bs-toggle="modal">` even for guests. The must_haves truth says the guest CTA must link to `/login?next=/board`. **Fix:** the cork-cell now branches on `is_guest` — guests get `href="/login?next=/board"` (no modal trigger); buyers get the modal trigger. The visible CTA span ("Sign in to buy" / "Buy now") is just a visual hint; the entire card is clickable and the action destination follows the `is_guest` branch.

### Rule 1 (bug fix): page=999 with 60 listings showed active=2 instead of falling back to page 1

`ListingService::getSearchResults` returns the requested page (999, not clamped) in `data['page']`. BrowseAction's `$effectivePage` was being set to `min($pages, max(1, $requestedPage))` = `min(2, 999)` = 2 (not 1 as the plan says). **Fix:** BrowseAction now clamps `$effectivePage = 1` when the requested page is out of range; otherwise `[1, $pages]`. The empty-state path renders correctly and the pagination shows page 1 as active.

### Rule 1 (bug fix): test fixture sort_order collisions

The Fixtures::ensureCategories() inserts 7 seed categories with sort_orders 1–7. Several tests then tried to seed additional categories with the same sort_order (1, 2, etc.), causing `uniq_categories_sort` violations. **Fix:** all custom categories in Phase 3 tests use sort_orders in the 100+ / 200+ / 300+ range to avoid collisions with the seeds.

### Documented deferral: image delete / drag-to-reorder on the modal

The modal's carousel supports prev/next via Bootstrap's controls and the prev/next buttons in the modal header. The plan's mention of drag-to-reorder on the modal's images is a Phase 6 concern (LST-16). The carousel is fully functional without drag-reorder; the modal swap itself is driven by the listing-level prev/next (D-22), not by image reordering within a single listing.

### Documented deferral: full-screen modal on small screens

`modal-fullscreen-sm-down` is the Bootstrap 5 default for full-screen-on-mobile. The plan's "swipe between images" is implemented via `touchstart`/`touchend` with a 50px threshold on the modal body. Visual cross-fade vs slide is decided by `prefers-reduced-motion` (handled by the existing Phase 1 `prefersReducedMotion` component via the `reduce-motion` class on `<html>`).

## Threat Surface Scan

All threat IDs from the plan's threat_model have their `mitigate` disposition applied:

- **T-03-21 (XSS — search input):** `htmlspecialchars` on the search input value, on the empty-state copy, and on every category tab label.
- **T-03-22 (XSS — listing title/description):** `listing_card_cork` partial already wraps `htmlspecialchars` around title/description (Phase 3 Plan 03-01).
- **T-03-23 (Tampering — `?page=999`):** Out-of-range page coerces to 1 via `$effectivePage = 1` in BrowseAction.
- **T-03-24 (Tampering — `?cat=999`):** Non-existent or inactive category → `null` (All tab active).
- **T-03-25 (Information Disclosure — listing id enumeration):** Listing id is part of the public URL; the corkboard shows all `active` listings. The id is not sensitive.
- **T-03-26 (Spoofing — `aria-current='page'` on inactive tab):** The View's `$active` boolean drives the attribute; integration tests assert the attribute is on the correct tab.
- **T-03-27 (DoS — FULLTEXT search on huge queries):** Query capped at 100 chars (Action input parsing). FULLTEXT index is O(log n).
- **T-03-28 (DoS — image carousel `/img/full` hot-linking):** ImageProxy auth-gates `full` size (Plan 03-01 T-03-03); only the seller, ticket holder, or admin can fetch.
- **T-03-SC (Composer installs):** No new Composer packages.

No new threat surface beyond the plan's threat model.

## Auth Gates

None. The plan called for guest-browse (no auth) and the implementation enforces it correctly (BrowseAction reads `currentUser()` to determine `is_guest`; the cork-cell's `<a href>` branches accordingly).

## Known Stubs

None. All shipped code paths are wired (no `TODO`/`FIXME`/empty `=[]`/`=""`/`=null` placeholders that flow to UI rendering).

## Self-Check

- All 13 listed `created` files exist on disk (verified via `ls`).
- All 5 commits land on `NSBM-EventHub` (verified via `git log --oneline`).
- `vendor/bin/phpunit` is green (287 tests / 1353 assertions).
- `vendor/bin/phpcs` (project config) reports 0 errors, 0 warnings.
- `GET /board` returns 200 with the full HTML (15983 bytes when seeded; 5896 bytes when empty).

## Next Up

Plan 03-04 (landing page + auto-approve cron) is the last plan in Phase 3. The auto-approve cron Action was already wired by Plan 03-02 (`ListingAutoApproveAction`); Plan 03-04 will add the `jobs/` runner and the public landing page.

The board view is now functional for both the WAD demo flow ("register → list → admin approve → log in as buyer → browse corkboard → click listing → see modal") and for guests. Phase 4 will wire the modal's "Buy now" button to the actual purchase flow and digital ticket generation.

---
*Phase: 03-marketplace-listings-discovery*
*Plan: 03*
*Completed: 2026-09-01*
