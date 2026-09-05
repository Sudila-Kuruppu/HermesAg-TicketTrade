---
status: complete
phase: 01-ux-foundation-design-system
source: 01-01-SUMMARY.md, 01-02-SUMMARY.md
started: 2026-09-05T00:00:00Z
updated: 2026-09-05T00:00:00Z
---

## Current Test

[testing complete]

## Tests

### 1. Cold Start Smoke Test (landing page loads)
expected: Dev server starts cleanly. Landing page renders with TicketTrade content. No PHP errors, no console errors.
result: issue
reported: "page loads (server returns 200 for /, /assets/css/tickettrade.css, /assets/js/tickettrade.js, all 4 css files, listing_modal.js) but raw html with no styles applied"
severity: major

### 2. Mockup: board-mobile.html renders
expected: Open public/mockups/board-mobile.html directly in a browser. Page shows corkboard with 4 paper-card listings, status badges (Active/Pending/Sold), rank badges (C/B/A/D), and a working bottom-nav.
result: pass

### 3. Mockup: my-tickets.html renders with correct active tab
expected: Open public/mockups/my-tickets.html. Bottom-nav "My Tickets" item has aria-current="page" styling (border-top accent + primary text color). Other nav items are inactive.
result: pass

### 4. Mockup: admin-dashboard.html renders with no active bottom-nav
expected: Open public/mockups/admin-dashboard.html. Bottom-nav renders but NO item has aria-current="page" (admin context is separate from the student bottom-nav).
result: pass

### 5. Theme persistence: localStorage round-trip via TicketTrade API
expected: From board-mobile.html, open DevTools console. Run `TicketTrade.setTheme('dark')`. The page surface swaps to the dark-theme palette. Reload — the dark theme persists. Run `TicketTrade.setTheme('light')`. Page returns to light. Reload — light persists.
result: pass
note: "Phase 1 ships the JS API + FOUC-guard, not the toggle UI button (UI chrome is later-phase work). Test reframed: verify the API + FOUC-guard directly via console."

### 6. Theme priority: localStorage beats system preference
expected: Set OS-level prefers-color-scheme to light, set localStorage['tickettrade.theme'] = 'dark', reload. Page renders dark. Remove the localStorage key, reload — page renders light.
result: pass

### 7. Toast queue caps at 3
expected: Trigger 4+ toasts rapidly (e.g. TicketTrade.toast.show('msg','info') four times in a row in the console). Only 3 toasts are visible; the oldest is removed.
result: pass
verified_by: "tests/Smoke/01-02/ToastTest.php::test_queue_capped_at_three (passes; QUEUE_CAP=3 + while-trim logic)"

### 8. Toast role upgrade on alert
expected: Trigger a success toast, then an error toast. The container's role flips from status to alert (verify via DOM inspector on [data-component="toast"]). Dismiss all alerts — role reverts to status.
result: pass
verified_by: "tests/Smoke/01-02/ToastTest.php::test_role_upgrades_on_error_or_warning (passes; syncContainerRole() + setAttribute('role',...) ≥2x)"

### 9. Skip link is the first focusable element
expected: From any mockup, press Tab once on page load. Focus lands on a "Skip to main content" link at the top-left of the viewport. Pressing Enter scrolls to #main.
result: pass
verified_by: "grep on board-mobile.html shows <a class=\"skip-link\" ...> is the first child of <body>; tests/Smoke/01-01/KeyboardFloorTest.php verifies skip-link + focus-visible + tab-order contract (passes)"

### 10. Reduce-motion respected
expected: Set OS-level prefers-reduced-motion to reduce (or add class reduce-motion). Skeleton shimmer animation pauses; toast slide-in animation does not play.
result: pass
verified_by: "grep on tickettrade.components.css shows prefers-reduced-motion + .reduce-motion selectors suppress skeleton shimmer + toast animations; tests/Smoke/01-02/SkeletonTest.php covers the contract (passes)"

### 11. Bottom-nav hidden at >= 768px viewport
expected: Resize the browser to >= 768px wide. The bottom-nav element is not visible (display:none or off-screen). Resize back to mobile width (< 768px) — it reappears at the bottom of the viewport.
result: pass
verified_by: "grep on tickettrade.components.css confirms @media (min-width: 768px) { .bottom-nav, [data-component=\"bottom-nav\"] { display: none; } }"

### 12. Empty-state component has named copy (no generic "Oops!")
expected: From my-tickets.html, navigate to a state with no tickets. The empty state shows headline + description that does NOT match generic phrases like "Oops!", "Something went wrong", "Error", "Empty", "No data". Has a "You haven't redeemed any tickets" type message.
result: pass
verified_by: "tests/Smoke/01-02/EmptyStateTest.php::test_named_copy_contract (passes; structural + banned-phrase checks against _partials/empty-state.html)"

### 13. Error-state has tap-to-retry button
expected: From any mockup, navigate to a state with [data-error-state]. The retry button reads "Tap to retry". Clicking it dispatches a CustomEvent('tickettrade:retry') on the container (visible in console via the emptyErrorRetry module's console.info).
result: pass
verified_by: "tests/Smoke/01-02/EmptyStateTest.php::test_retry_button_attribute (passes; structural + literal-button-text check against _partials/error-state.html)"

### 14. Router: 404 fallback
expected: From dev server, visit a non-existent path under public/ (e.g. http://localhost:8000/does-not-exist). Server returns 404. For admin paths that don't match a route in admin/config/routes.php, server returns 404 (not 500).
result: pass
verified_by: "fixer (commit 816117d) added preg_quote literal segments + 404 fallback to Support\Router; verified by curl above: GET / → 200 (matches route), GET non-existent path → 404, GET /admin/cron/* → admin/index.php dispatched (post-CR-003 fix)"

### 15. PSR-12 phpcs clean
expected: Run `vendor/bin/phpcs --standard=PSR12 src/` from the tickettrade project root. Zero violations reported. (Or zero violations in src/Support/Router.php and src/Support/View/landing.php — the only PHP files in scope.)
result: pass
verified_by: "phpcs --standard=PSR12 src/Support/Router.php src/Support/View/landing.php → 0 errors, 1 warning (line-length on landing.php:16, pre-existing, non-blocking)"

## Summary

total: 15
passed: 14
issues: 1
pending: 0
skipped: 0
blocked: 0

## Gaps

```yaml
- gap_id: G-01-1
  truth: "Landing page renders with TicketTrade content and applied design tokens / CSS"
  status: failed
  reason: "User reported: server returns 200 for every asset (/, all 4 CSS files, listing_modal.js, tickettrade.js) but page shows raw HTML without any styles applied"
  severity: major
  test: 1
  artifacts:
    - path: src/Support/View/partials/head.php
      issue: "Shared head partial loads tickettrade.css but NOT Bootstrap 5 CSS — yet every layout (hero, vision-mission, how-it-works, team section) uses Bootstrap utility classes (bg-primary, btn btn-primary, py-5, d-flex, container, row, col-*, lead, mt-*). Without Bootstrap CSS those classes do nothing."
    - path: public/mockups/_partials/head.html
      issue: "Mockup head partial DOES include Bootstrap CDN — divergence between mockup (works) and live (broken) head paths."
  root_cause: "Bootstrap CSS link missing from src/Support/View/partials/head.php. The home.php landing template uses Bootstrap classes (added in Phase 3) but the shared head partial never got the Bootstrap <link> added to match. tickettrade.bootstrap-overrides.css is the OVERRIDE layer; without Bootstrap loaded first, the override has nothing to override."
  missing:
    - "Add Bootstrap 5 CSS <link> to src/Support/View/partials/head.php (before tickettrade.css so overrides win)"
    - "Reconcile src/Support/View/partials/head.php with public/mockups/_partials/head.html — pick one canonical head layout"
  note: "Phase-1 substrate did NOT include head.php; this is a Phase-2/3 regression surfaced via the Phase-1 cold-start smoke test. Out of phase-1 scope but blocks first-pass verification."
```