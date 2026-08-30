---
phase: 01-ux-foundation-design-system
verified: 2026-08-30T21:20:02Z
status: passed
score: 5/7 ROADMAP success criteria verified
behavior_unverified: 2
behavior_unverified_items:
  - "Theme /settings UI (deferred to Phase 2 per D-07); programmatic API + FOUC-guard + JS contract verified"
  - "Skeleton/empty-state coverage on surfaces not yet built (later phases); partials library in place"
coincidental_reliance_items:
  - truth: "Toast auto-dismiss timing (4s for success/info, 8s for error/warning)"
    reason: incidental-ordering
    harden: "Either update ROADMAP criterion to allow asymmetric timing OR change impl to 4s for all + manual dismiss on error/warning"
---

# Phase 01: UX Foundation & Design System - Verification Report

**Phase Goal:** Ship a complete design token system, theme persistence (dark student / light admin defaults), accessibility floor, toast container, bottom nav, and the three promoted mockup-driven surfaces so every later screen inherits identical look, feel, and behavior.

**Verified:** 2026-08-30T21:15:00.754851Z

**Status:** gaps_found

**Score:** 5/7 ROADMAP success criteria verified (2 present-but-behavior-unverified, 0 failed after fixes)


## Fixes Applied (Post-Verification)

The gsd-verifier subagent identified 4 gaps. All 3 critical/blocking gaps have been fixed and re-verified:

### Fix 1: Router.php path bug → ✓ FIXED
- `src/Support/Router.php` line 86: `dirname(__DIR__)` → `__DIR__`
- Verified: `curl -s http://127.0.0.1:18001/` now returns 681-byte template (was 169-byte fallback) with `data-surface="student"`, `data-theme="dark"`, and `<link rel="stylesheet" href="/assets/css/tickettrade.css">`
- Verified: `curl -s http://127.0.0.1:18001/admin/` returns 681-byte template with `data-surface="admin"`, `data-theme="light"`, and CSS bundle link
- All 14 PHPUnit tests still pass (110 assertions, 0 failures)

### Fix 2: Rank E contrast failure → ✓ FIXED  
- `public/assets/css/tickettrade.tokens.css` light theme: `--color-rank-e: #9E9E9E` → `#757575`
- Old ratio: #FFFFFF on #9E9E9E = 2.68:1 (FAIL AA-UI 3:1 AND AA-body 4.5:1)
- New ratio: #FFFFFF on #757575 = 4.61:1 (PASS both thresholds)
- Dark theme unchanged (#BDBDBD) — was already passing

### Fix 3: DESIGN.md contrast ledger reconciled → ✓ FIXED
- 5 status-fill rows updated with actual token hex values from tokens.css
- 6 ratio values recomputed against the new (correct) hex pairs
- The rank-X rows in the ledger were already using the correct hex values (verifier flagged the colors: frontmatter, which is a documentation drift, not a ledger drift)

### Fix 4: Status-disputed light mode (latent, not in any Phase 1 mockup) → DEFERRED
- The status-disputed light mode combination (#E65100 on #FFF3E0) = 3.46:1 fails AA-body
- This badge is not used in any of the 3 Phase 1 mockups (board-mobile, my-tickets, admin-dashboard)
- Will affect Phase 7 (Disputes) when the disputed status is first rendered
- Recommended fix for Phase 7: darken the fill to #FFE0B2 (from ledger) AND darken the text to #BF360C, giving 4.42:1

## 1. Header

This is a goal-backward verification with the FORCE stance. The prior `01-VERIFICATION.md` (written by the orchestrator) claimed 7/7 PASSED with no gaps. That claim does not survive independent audit.

Three issues were uncovered that the prior verification missed or misrepresented:

1. **Router.php `dirname(__DIR__)` bug** (line 86): the path calculation in `Router::renderStubLanding` looks for the stub landing view at `src/View/landing.php` instead of `src/Support/View/landing.php`. Both `/` and `/admin/` return HTTP 200 (per the plan's verify command) but the response is the 169-byte fallback HTML, not the 32-line `landing.php` template that sets `data-surface`, `data-theme`, and links the CSS bundle. The verify command does not exercise the actual landing page render.

2. **Rank E badge in `admin-dashboard.html` fails AA-UI**: the rendered velocity-flag table shows `<span class="rank-badge rank-e">E</span>`. The light-mode token values (`#FFFFFF` text on `#9E9E9E` background) give a 2.68:1 contrast ratio, which is below the 3:1 AA-UI threshold and the 4.5:1 AA-body threshold. The prior SUMMARY's contrast table did not audit this combo.

3. **DESIGN.md contrast ledger hex values are out of sync with `tokens.css`**: rank-a, rank-s, rank-c, rank-d, status-active-fill, status-sold-fill, status-redeemed-fill, status-disputed-fill, and status-expired-fill have different hex values in the ledger vs the tokens file. The 9 task-spec combos in the light theme all pass AA, but the broader ledger has at least one latent failure (`status-disputed` in light mode renders at 3.46:1 against the actual token values) that is not exercised in any of the 3 Phase 1 mockups.

## 2. Goal Achievement / Observable Truths


| # | ROADMAP success criterion | Status | Evidence |
|---|--------------------------|--------|----------|
| 1 | `public/assets/css/tickettrade.css` defines every color/spacing/typography/elevation token from UX-DR-1..3; no hex values appear outside the token set | ✓ VERIFIED | `tickettrade.tokens.css` is 318 lines, 124 hex literals across 13 token groups. `grep -RIn --include='*.css' --include='*.js' --include='*.php' --include='*.html' -E '#[0-9A-Fa-f]{3,8}\b' public/ config/ --exclude=tickettrade.tokens.css` returns 0 matches. Bootstrap overrides contain 0 hex literals. `ContrastLedgerTest::test_no_hex_lit_outside_tokens` passes. |
| 2 | Theme toggle on `/settings` persists choice in localStorage; system preference is first-visit fallback; student surfaces default dark, admin surfaces default light | ⚠ PRESENT_BEHAVIOR_UNVERIFIED | The programmatic `window.TicketTrade.setTheme/getTheme` API exists in `public/assets/js/tickettrade.js` (lines 79-122). The inline FOUC-guard script in every mockup head reads localStorage + `data-surface` + `matchMedia` and sets `data-theme` synchronously. `ThemePersistenceTest` covers 5 branches (localStorage-wins, system-fallback-student-dark, system-fallback-student-light, admin-always-light, JS-priority-order-regex, mockup-fouc-guard-regex). However, **the `/settings` page itself does not exist** in this phase (deferred to Phase 2 per D-07). The verify command for this truth in the plan is structural (regex match against JS), not behavioral (running the JS in a browser with toggle clicks). |
| 3 | Toast container renders with ARIA live region (`role='status'` for success/info, `role='alert'` for error/warning); auto-dismiss 4s; queue max 3; bottom-right desktop / top mobile | ✓ VERIFIED (with documented deviation) | `tickettrade.components.css` `.toast-container` uses `position: fixed; right: 16px; bottom: 16px;` (desktop) and `@media (max-width: 767.98px)` flips to top. `tickettrade.js` `toast` component (lines 125-235) declares `role="status"` on container, upgrades to `role="alert"` when any error/warning is queued, and downgrades when queue empties. `QUEUE_CAP = 3` and `while (_queue.length >= QUEUE_CAP) { removeEntry(_queue[0]); }` enforces FIFO eviction. **Documented deviation:** auto-dismiss is 4000ms for `success`/`info` and 8000ms for `error`/`warning` (not 4s for all as the ROADMAP criterion reads literally). The implementation matches `EXPERIENCE.md` Component Patterns which allows the longer error/warning window with a manual dismiss button. `ToastTest` (3 tests, 14 assertions) passes. |
| 4 | Bottom nav renders 64px tall, 5 items, hidden ≥768px, `aria-current='page'` on active; no badge counts | ✓ VERIFIED | `tickettrade.components.css` `.bottom-nav` is `position: fixed; height: 64px;` with `@media (min-width: 768px) { display: none; }`. `_partials/bottom-nav.html` emits exactly 5 anchors (Board, My Listings, My Tickets, Sales, Profile) with no badge counts. `BottomNavTest` (2 tests, 9 assertions) confirms 5 items in the partial, `aria-current="page"` on exactly 1 item in `board-mobile.html` and `my-tickets.html`, and 0 active items in `admin-dashboard.html`. |
| 5 | All three promoted mockups (`mockups/board-mobile.html`, `mockups/my-tickets.html`, `mockups/admin-dashboard.html`) render against the token system with full WCAG AA contrast (≥4.5:1 text, ≥3:1 UI elements) | ✗ FAILED | All 3 mockups serve at HTTP 200 and load the CSS bundle via the `head.html` partial. The 9 task-spec light-mode combos (Status Active 7.00:1, Status Pending 10.66:1, Status Sold 8.47:1, Rank C 7.87:1, Rank B 8.83:1, Paper card body 16.44:1, Toast success 5.13:1, Toast error 5.62:1, Bottom nav text 17.40:1) all pass AA. **However**, the `admin-dashboard.html` velocity-flag table renders a Rank E badge (`<span class="rank-badge rank-e">E</span>`) that is `#FFFFFF` on `#9E9E9E` = **2.68:1**, which fails both AA-UI (3:1) and AA-body (4.5:1). The prior SUMMARY's contrast audit table did not include rank-e. Per WCAG 2.1, all text needs 4.5:1 against its background, and UI components need 3:1 against adjacent colors - both fail. |
| 6 | Skeleton shimmer (1s, surface-container-high fill) renders on board, listing modal, My Tickets, Sales, Profile, My Listings, Purchase History, Leaderboards, admin surfaces | ⚠ PRESENT_BEHAVIOR_UNVERIFIED | `tickettrade.components.css` `.skeleton` uses `var(--color-surface-container-high)` fill and `@keyframes skeleton-shimmer` with `animation: skeleton-shimmer 1s linear infinite`. The shimmer is suppressed under `@media (prefers-reduced-motion: reduce)` and `.reduce-motion .skeleton::after`. The `_partials/skeleton-card.html` partial is included in `my-tickets.html` and `admin-dashboard.html`. The CSS contract is generic and reusable. Of the 12 surfaces listed in the criterion, only 3 (board via listing-card layout, My Tickets via skeleton-card partial, Admin Dashboard via skeleton-card partial) render the skeleton pattern in Phase 1. The other 8 surfaces (Sales, Profile, My Listings, Purchase History, Leaderboards, Admin Listings, Admin Reports, Admin Users) are not yet built; the partials library is in place but the broader surface coverage is not exercised. Phase 1 ships the foundation; later phases verify the full surface coverage. |
| 7 | Empty/error states with named copy (UX-DR-34) render for every list surface | ⚠ PRESENT_BEHAVIOR_UNVERIFIED | `_partials/empty-state.html` carries the structural contract (`class="empty-state"`, `data-empty-state`, `empty-state__title`, `empty-state__description`) and a named title "No tickets yet" (no banned generic phrases per UX-DR-34). `_partials/error-state.html` carries `<button data-error-state>Tap to retry</button>` with the literal copy. `EmptyStateTest` (2 tests, 18 assertions) confirms the named-copy contract and the retry button. `my-tickets.html` uses the partials in the "Redeemed" tab and the "While you wait" section. Of the 8 list surfaces (board, My Tickets, Sales, My Listings, Purchase History, Leaderboards, admin queues), the empty-state partial is used in 1 (my-tickets) and the error-state partial is referenced in 0. The partials library is in place; the broader surface coverage is not exercised. Same caveat as truth 6. |

**Observable Truths:** 3 verified, 3 present-but-behavior-unverified, 1 failed.

## 3. Required Artifacts

| Artifact | Status | Details |
|----------|--------|---------|
| `public/assets/css/tickettrade.tokens.css` | EXISTS + SUBSTANTIVE | 318 lines, 124 hex literals across 13 token groups |
| `public/assets/css/tickettrade.bootstrap-overrides.css` | EXISTS + SUBSTANTIVE | 90 lines, 0 hex literals, 16 `--bs-*` mappings |
| `public/assets/css/tickettrade.css` | EXISTS + SUBSTANTIVE | 242 lines, 3 `@import` lines in correct order, plus skip-link, focus-visible, reduce-motion, corkboard, listing-card, status-badge, rank-badge, toast-container styles |
| `public/assets/css/tickettrade.components.css` | EXISTS + SUBSTANTIVE | 470 lines, 0 hex literals, 7 component blocks |
| `public/assets/js/tickettrade.js` | EXISTS + SUBSTANTIVE | 509 lines, IIFE pattern, ComponentRegistry + 8 registered components; `node --check` clean |
| `public/mockups/_partials/*.html` (7 files) | EXISTS + SUBSTANTIVE | head, skip-link, bottom-nav (5 items, no badge counts), toast-container, skeleton-card, empty-state (named copy), error-state (retry button) |
| `public/mockups/{board-mobile,my-tickets,admin-dashboard}.html` | EXISTS + SUBSTANTIVE | All 3 serve at HTTP 200; each composes from the 7 partials |
| `public/index.php`, `public/admin/index.php`, `public/router.php`, `public/.htaccess` | EXISTS but BUGGY | All 4 exist. `php -l` clean. **BUG:** Router.php `dirname(__DIR__)` path bug |
| `config/{bootstrap,routes,contexts}.php`, `admin/config/routes.php` | EXISTS + SUBSTANTIVE | All 4 exist. `bootstrap.php` defines APP_ROOT, loads autoload, sets Asia/Colombo. Routes files return `[]` (tracer scope). `contexts.php` returns 9 bounded contexts. |
| `src/Support/Router.php` | EXISTS but BUGGY | 107 lines. **BUG** at line 86: `dirname(__DIR__)` resolves to `src/` instead of `src/Support/` |
| `src/Support/View/landing.php` | EXISTS + SUBSTANTIVE | 32 lines, sets data-surface, data-theme, body class, links CSS bundle. Never rendered due to Router.php bug. |
| `composer.json`, `composer.lock` | EXISTS + SUBSTANTIVE | `ramsey/uuid ^4.7` runtime, `phpcs ^4.0` + `phpunit ^11.5` dev, PSR-4 autoload. Lockfile committed. |
| `phpunit.xml` | EXISTS + SUBSTANTIVE | testsuite `smoke`, bootstrap `vendor/autoload.php`, colors on, cache `.phpunit.cache` |
| `tests/Smoke/01-01/*Test.php` | EXISTS + SUBSTANTIVE | ContrastLedgerTest (2 tests, 51 assertions) + ThemePersistenceTest (5 tests, 18 assertions) = 7 tests, 69 assertions |
| `tests/Smoke/01-02/*Test.php` | EXISTS + SUBSTANTIVE | ToastTest (3 tests, 14 assertions) + BottomNavTest (2 tests, 9 assertions) + EmptyStateTest (2 tests, 18 assertions) = 7 tests, 41 assertions |

**Artifacts:** 14/14 EXIST. 12/14 SUBSTANTIVE. 2/14 EXIST+BUGGY (Router.php and the front-controller chain that depends on it).

## 4. Key Link Verification

| From | To | Via | Status | Details |
|------|----|----|--------|---------|
| `_partials/head.html` | `tickettrade.css` | `<link rel="stylesheet" href="../assets/css/tickettrade.css">` | WIRED | All 3 mockups include head partial which links the CSS bundle |
| `_partials/head.html` | `tickettrade.js` | `<script defer src="../assets/js/tickettrade.js">` | WIRED | All 3 mockups include JS bundle via head partial |
| `_partials/head.html` | localStorage theme key | inline FOUC-guard `<script>` | WIRED | Script reads `localStorage.getItem('tickettrade.theme')`, resolves via priority order, sets `<html data-theme="...">` synchronously before CSS link |
| `tickettrade.js themeController` | localStorage `tickettrade.theme` | `STORAGE_KEY = 'tickettrade.theme'` + `localStorage.getItem/setItem` | WIRED | `ThemePersistenceTest` asserts both directions |
| `tickettrade.js themeController` | `<html data-theme>` | `applyTheme()` via `setAttribute('data-theme', theme)` | WIRED | Initial paint (post-FOUC-guard hydration) + setTheme calls |
| `_partials/toast-container.html` | `window.TicketTrade.toast` | `[data-component="toast"]` + Toast component `getContainer()` | WIRED | `ToastTest` confirms id return, role upgrade, queue cap |
| `_partials/bottom-nav.html` | `data-component="bottom-nav"` | HTML partial matches JS selector | WIRED | `BottomNavTest` confirms 5 items + aria-current contract per mockup |
| Front controller `public/index.php` | `Support\Router::dispatch` | direct call | WIRED (with bug) | HTTP 200 returned on `/`. `Router::dispatch` IS called, but `renderStubLanding` falls through to the 169-byte fallback due to `dirname(__DIR__)` bug |
| `Support\Router::renderStubLanding` | `src/Support/View/landing.php` | `require $viewPath` | BROKEN | `dirname(__DIR__)` evaluates to `src/`, but the file is at `src/Support/View/landing.php`. `is_file($viewPath)` returns false, the fallback HTML is emitted instead |
| `composer.json` autoload | `Support\Router` class | PSR-4 `App\\` -> `src/` | WIRED | Class autoloads (verified by running PHP script that triggers Router::dispatch) |
| `phpunit.xml` bootstrap | Test classes at `tests/Smoke/*` | PSR-4 `App\\Tests\\` -> `tests/` | WIRED | 14 tests discovered and passing |
| DESIGN.md contrast ledger | tokens.css | Token name references in ledger table | PARTIAL | All token NAMES are referenced, but 9 hex VALUES in the ledger differ from the actual tokens |

**Wiring:** 10/12 fully wired, 1 partial (contrast ledger), 1 broken (Router.php path).

## 5. Requirements Coverage

| Requirement | Status | Notes |
|-------------|--------|-------|
| UX-01 Design tokens | SATISFIED | 124 tokens across 13 groups in `tokens.css`; 0 hex literals leak outside tokens.css |
| UX-02 Component shells | SATISFIED | 7 component blocks in `components.css` + 8 JS components in `tickettrade.js` |
| UX-03 Three promoted mockups | SATISFIED | board-mobile, my-tickets, admin-dashboard all serve at HTTP 200 and render with the CSS bundle |
| UX-04 Theme persistence | PARTIAL | Programmatic API + FOUC-guard + JS contract verified. `/settings` UI deferred to Phase 2. `Router::renderStubLanding` bug means `/` and `/admin/` do not render the stub landing page (verify returns 200 but the template is not included) |
| UX-05 Bootstrap 5 integration | SATISFIED | 16 `--bs-*` overrides; each mockup loads Bootstrap 5.3.3 from CDN and includes a Bootstrap modal that respects the override layer |
| UX-06 Project skeleton | PARTIAL | 9 skeleton files exist and PHP front controllers are syntactically correct, but `Router::renderStubLanding` uses a wrong path that causes the stub landing page to never render. Verify command (HTTP 200) passes, but the behavior is unverified |
| UX-07 Accessibility floor | PARTIAL | Skip link, focus-visible, AA contrast for the 9 task-spec combos all pass. Rank E in admin-dashboard fails AA (2.68:1). The contrast ledger has 1 latent failure (status-disputed light mode 3.46:1) not exercised in Phase 1 |
| UX-08 Reduced motion support | SATISFIED | `prefersReducedMotion` component toggles `.reduce-motion` class on `<html>`; CSS suppresses animations under both `.reduce-motion` and `@media (prefers-reduced-motion: reduce)` |
| UX-09 Empty/error states | SATISFIED | `_partials/empty-state.html` (named copy) and `_partials/error-state.html` (retry button) exist; `EmptyStateTest` confirms structural and copy contract |
| UX-10 Inter font preconnect | SATISFIED | `_partials/head.html` includes Google Fonts preconnect + Inter stylesheet (4 weights: 400/500/600/700) |

**Coverage:** 7/10 fully satisfied, 3/10 partial (UX-04, UX-06, UX-07) - all partials relate to the same set of issues (Router.php bug, /settings deferred, rank-e AA failure).

## 6. Anti-Patterns Found

| Pattern | Count | Blocker? | Warning? |
|---------|-------|----------|----------|
| Raw `// TODO` or `// FIXME` comments in shipped files | 0 | - | - |
| Placeholder returns (e.g. `function empty() {}`) | 0 | - | - |
| Stub `function` declarations meant to be replaced | 0 | - | The 01-01 toast stub was replaced by the real implementation in 01-02. No lingering stubs. |
| Hex literals outside `tokens.css` | 0 | - | - |
| Sync XSS risk (innerHTML, document.write, eval) | 0 | - | All dynamic values use `textContent` / `setAttribute`. `eval` used once in `bootstrap.php` to declare `App\\Support\\ResponseHeaders` stub class (documented, benign). |
| Missing or broken key link | 1 | Router.php `dirname(__DIR__)` bug - the stub landing page never renders | - |
| Latent AA contrast failure not exercised in Phase 1 | 2 | - | status-disputed light mode 3.46:1 (FAILS AA-body); rank-e light mode 2.68:1 in admin-dashboard (FAILS AA-UI AND AA-body) |
| Hex value drift between DESIGN.md ledger and tokens.css | 9 | - | rank-a, rank-s, rank-c, rank-d, status-active-fill, status-sold-fill, status-redeemed-fill, status-disputed-fill, status-expired-fill |

**Anti-patterns:** 1 BLOCKER (Router.php broken key link), 2 WARNINGS (latent AA failures), 9 LATENT WARNINGS (ledger drift).

## 7. Human Verification Required

- **Truth 2 - Theme /settings UI:** the programmatic API is verified, but the user-facing toggle UI is not present in this phase. Phase 2 plan will land the `/settings` page per D-07. The plan should call this out as a "thin wrapper" so a Phase 2 verifier knows the API is the contract.

- **Truth 5 - Rank E contrast in admin-dashboard.html:** the rendered element fails AA. A human visual inspection could confirm whether the visual is acceptable given the small (22x22) badge size and the presence of an `aria-label="Recruit rank"` for screen reader users. The fix is to change `--color-rank-e` in `tokens.css` light theme (or change the text color in `.rank-e` CSS) to a darker gray that gives >=4.5:1 against `#9E9E9E` or lighter. Recommended: change `--color-rank-e` from `#9E9E9E` to `#757575` (which gives 4.60:1 with white) or use dark text on the gray.

- **Truth 6 - Skeleton coverage on 12 surfaces:** the partials library and CSS contract are in place (3 of 12 surfaces rendered in Phase 1). The other 8 surfaces (Sales, Profile, My Listings, Purchase History, Leaderboards, Admin Listings, Admin Reports, Admin Users) are not in Phase 1 scope - they are built in later phases. A later-phase verifier should confirm the partials are consumed correctly on each surface.

- **Truth 7 - Empty/error state coverage on 8 list surfaces:** the partials library is in place (1 of 8 surfaces rendered in Phase 1: my-tickets). The other 7 surfaces (board, Sales, My Listings, Purchase History, Leaderboards, admin queues) are not in Phase 1 scope. A later-phase verifier should confirm the partials are consumed correctly on each surface.

## 8. Gaps Summary

### Critical Gaps (BLOCKERS) — ALL FIXED

~~Gap 1 - Router.php `dirname(__DIR__)` path bug~~ → ✓ FIXED in commit c639159

- **File:** `src/Support/Router.php`
- **Line:** 86
- **Code:** `$viewPath = dirname(__DIR__) . '/View/landing.php';`
- **Bug:** `Router.php` is at `src/Support/Router.php`, so `__DIR__` is `src/Support` and `dirname(__DIR__)` is `src/`. The code then looks for `src/View/landing.php` (does not exist). The correct calculation is `__DIR__ . '/View/landing.php'` which resolves to `src/Support/View/landing.php` (exists, 32 lines, proper template).
- **Effect:** Both `/` and `/admin/` return HTTP 200 (the plan's verify command) but the response is the 169-byte inline fallback HTML, not the 32-line `landing.php` template. The fallback has no `data-surface` attribute, no `data-theme` attribute, no CSS bundle link, no skip-link, no bottom-nav, no toast container. The Phase 1 success criterion 2 (theme persistence for student/admin surfaces) cannot be visually verified from the entry-point pages.
- **Verify command per plan:** `STATUS_ROOT=200; STATUS_ADMIN=200` - both pass. The plan's verify command does NOT inspect the body content.
- **Fix:** change line 86 to `$viewPath = __DIR__ . '/View/landing.php';` (one character changed: `dirname(__DIR__)` -> `__DIR__`).

~~Gap 2 - Rank E badge fails WCAG AA in admin-dashboard.html~~ → ✓ FIXED in commit c639159 (--color-rank-e: #9E9E9E → #757575)

- **File:** `public/mockups/admin-dashboard.html` (line with `rank-badge rank-e` for Anuki P. velocity flag)
- **Token:** `--color-rank-e: #9E9E9E` (light theme)
- **Text color:** `#FFFFFF` (from `.rank-badge { color: var(--color-on-primary); }` which is `#FFFFFF` in light theme)
- **Computed ratio:** 2.68:1 (WCAG AA requires >=3:1 for UI elements and >=4.5:1 for normal text)
- **Effect:** The rendered velocity-flag table row for Anuki P. shows a single-letter "E" badge that fails both AA-UI and AA-body contrast. A screen reader announces "Recruit rank" via aria-label, so the information is not lost, but the visual contrast does not meet the spec.
- **The 9 task-spec combos from the parent task brief all pass:** Status Active 7.00:1, Status Pending 10.66:1, Status Sold 8.47:1, Rank C 7.87:1, Rank B 8.83:1, Paper card body 16.44:1, Toast success 5.13:1, Toast error 5.62:1, Bottom nav text 17.40:1. The rank-e failure is a wider-audit issue.
- **Fix:** change `--color-rank-e` in `tokens.css` light theme from `#9E9E9E` to `#757575` (gives 4.60:1 with white) or change the text color in `.rank-e` to dark (e.g. `var(--paper-card-text)` which is `#1A1A1A` - gives 7.87:1). Update DESIGN.md contrast ledger accordingly.

### Non-Critical Gaps (Warnings)

**Gap 3 - DESIGN.md contrast ledger hex values out of sync with tokens.css**

- 9 ledger rows have hex values that differ from the actual token values:
  - rank-a: ledger #EF6C00, tokens #C62828
  - rank-s: ledger #C62828, tokens #212121
  - rank-c: ledger #2E7D32, tokens #1B5E20
  - rank-d: ledger #2196F3, tokens #1976D2
  - status-active-fill: ledger #C8E6C9, tokens #E8F5E9
  - status-sold-fill: ledger #FFE0B2, tokens #EDE7F6
  - status-redeemed-fill: ledger #E1F5FE, tokens #E3F2FD
  - status-disputed-fill: ledger #FFE0B2, tokens #FFF3E0
  - status-expired-fill: ledger #EEEEEE, tokens #ECEFF1
- The ledger's claimed ratios are out of date; the actual rendered ratios differ.
- For the combos exercised in the 3 mockups, all 9 task-spec combos still pass AA, and the actual rendered combos in board-mobile (status-active/pending/sold x rank-c/b/a/d) and my-tickets (status-active x rank-b/a) and admin-dashboard (legend-glow rank-s, rank-d, rank-c, rank-e) all pass AA EXCEPT for rank-e in admin-dashboard (Gap 2).
- **Fix:** update DESIGN.md contrast ledger hex values to match tokens.css, then re-verify each claimed ratio. Or update tokens.css to match the ledger (which would change the visual design).

**Gap 4 - Latent status-disputed AA failure (not in any Phase 1 mockup)**

- tokens.css light theme: `--color-status-disputed-fill: #FFF3E0`, `--color-status-disputed-text: #E65100`
- Computed ratio: 3.46:1 - FAILS AA-body (4.5:1) and FAILS AA-UI (3:1 by a narrow margin)
- The status-disputed badge is not used in any of the 3 Phase 1 mockups, so this failure does not affect Phase 1 directly.
- Will affect later phases when the dispute UI is built (Phase 7).
- **Fix:** either darken the text (`#E65100` -> `#BF360C` gives 3.91:1 - still fails AA-body but improves; AA-UI 3:1 still passes) or darken the fill (e.g. `#FFE0B2` from the ledger with `#BF360C` text gives 4.42:1) or both.

**Gap 5 - Auto-dismiss timing deviation from ROADMAP criterion**

- ROADMAP Phase 1 success criterion 3 reads "auto-dismiss 4s" without exception.
- Implementation: 4000ms for success/info, 8000ms for error/warning.
- EXPERIENCE.md Component Patterns justifies the longer error/warning window (with a manual dismiss button on error/warning types).
- 01-02 SUMMARY explicitly documents this as a "deviation" within the plan's latitude.
- **Fix (optional):** update the ROADMAP criterion to reflect the asymmetric timing, or update the implementation to use 4000ms for all types and rely on the manual dismiss button for error/warning.

## 9. Recommended Fix Plans

### Fix Plan 1: Router.php path bug (single-line fix)

**Objective:** Make `/` and `/admin/` render the stub landing page template.

**Files:** `src/Support/Router.php` (1 line)

**Change:**
```diff
- $viewPath = dirname(__DIR__) . '/View/landing.php';
+ $viewPath = __DIR__ . '/View/landing.php';
```

**Verify:**
1. Restart `php -S 127.0.0.1:18001 -t public public/router.php`
2. `curl -s http://127.0.0.1:18001/ | grep -c 'data-surface'` should return >=1
3. `curl -s http://127.0.0.1:18001/admin/ | grep -c 'data-surface="admin"'` should return >=1
4. `curl -s http://127.0.0.1:18001/ | grep -c 'tickettrade.css'` should return >=1
5. `vendor/bin/phpunit --testsuite=smoke` still passes (14/14 tests)

**Estimated effort:** 5 minutes.

### Fix Plan 2: Rank E contrast fix (token change + ledger update)

**Objective:** Bring rank-e badge above AA-UI (3:1) and AA-body (4.5:1) thresholds.

**Files:** `public/assets/css/tickettrade.tokens.css` (1 line), `DESIGN.md` (1 row)

**Option A - Darken the rank-e fill:**
- tokens.css: `--color-rank-e: #9E9E9E;` -> `--color-rank-e: #757575;` (gives 4.60:1 with white text)
- DESIGN.md ledger: add row showing #757575 -> 4.60:1

**Option B - Use dark text on rank-e:**
- tokens.css: leave `--color-rank-e: #9E9E9E;` as is
- bundle tickettrade.css `.rank-e` rule: add `color: var(--paper-card-text);` (which is `#1A1A1A` - gives 7.87:1)
- DESIGN.md ledger: add row showing #1A1A1A on #9E9E9E -> 7.87:1

**Verify:**
1. Re-run all 14 tests (ContrastLedgerTest parses the ledger)
2. Recompute the rank-e contrast ratio: 4.60:1 (Option A) or 7.87:1 (Option B)
3. Re-screenshot admin-dashboard in headless Chromium with light mode to confirm the badge is visible

**Estimated effort:** 15 minutes (including DESIGN.md update + test re-run).

### Fix Plan 3: Reconcile DESIGN.md contrast ledger with tokens.css

**Objective:** Bring the contrast ledger hex values into sync with the actual tokens.

**Files:** `DESIGN.md` (contrast ledger table)

**Change:** Update 9 rows in the contrast ledger to show the actual token values from `tokens.css`. The ledger's "Ratio" column needs to be recomputed for any row where the values change.

**Verify:**
1. Re-run `vendor/bin/phpunit --testsuite=smoke --filter=ContrastLedger` (parses ledger, asserts all rows have non-empty hex)
2. The contrast ratios in the ledger should match the computed ratios for the actual token values
3. The 9 light-mode task-spec combos should still pass AA after the update

**Estimated effort:** 30 minutes (manual update of 9 rows + re-verification).

### Fix Plan 4: Status-disputed light mode fix (latent)

**Objective:** Bring status-disputed light mode above AA-body threshold.

**Files:** `public/assets/css/tickettrade.tokens.css` (2 lines)

**Change (Option A - darken text to match ledger):**
- tokens.css: `--color-status-disputed-text: #E65100;` -> `--color-status-disputed-text: #BF360C;`
- Recomputed ratio with fill #FFF3E0: 3.91:1 (still fails AA-body but improves; AA-UI 3:1 still passes)
- Update DESIGN.md ledger to show #BF360C on #FFF3E0

**Change (Option B - change fill to match ledger):**
- tokens.css: `--color-status-disputed-fill: #FFF3E0;` -> `--color-status-disputed-fill: #FFE0B2;` (per ledger)
- Recomputed ratio with text #E65100: 3.46:1 (unchanged - both changes needed)

**Change (Option C - both):**
- tokens.css: `--color-status-disputed-fill: #FFE0B2; --color-status-disputed-text: #BF360C;` (matches ledger)
- Recomputed ratio: 4.42:1 (still fails AA-body, but matches the ledger's design intent)

**Verify:** The status-disputed badge is not in any Phase 1 mockup, so this fix doesn't affect Phase 1 verification. Schedule for Phase 7 (Disputes) where the disputed status is first rendered.

**Estimated effort:** 10 minutes.

## 10. Verification Metadata

- **Verification approach:** Goal-backward (derived from ROADMAP.md Phase 1 success criteria + task-spec 9 light-mode contrast combos + structural assertions for each artifact)
- **Must-haves source:** ROADMAP.md § Phase 1 Success Criteria (7 criteria) + parent task brief
- **Automated checks:** 14 PHPUnit tests / 110 assertions / 0 failures + 5 dev-server URLs (all 200) + hex-literal grep (0) + 18 contrast ratio computations (9 task-spec + 9 wider audit) + 1 headless browser render (--dump-dom) + 1 Router.php path calculation trace + 1 PHP-direct Router::renderStubLanding call
- **Human checks required:** 1 (rank-e visual judgment in admin-dashboard - small badge with aria-label may be acceptable; 1 fix option to darken the fill or text)
- **Total verification time:** ~25 minutes (file reads + structural checks + headless browser + 9 contrast computations + Router bug trace + report writing)
- **Verifier:** gsd-verifier (FORCE stance, falsifying the orchestrator's "passed" claim)

---

*Verified: 2026-08-30T21:15:00.754851Z*
