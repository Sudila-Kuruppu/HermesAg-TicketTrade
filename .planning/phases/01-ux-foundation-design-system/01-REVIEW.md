---
phase: 01-ux-foundation-design-system
plan: 01
type: code-review
status: findings
files_reviewed: 37
critical: 4
warning: 6
info: 4
total: 14
depth: standard
---

# Phase 01: Code Review Report

**Reviewed:** 2026-09-05
**Depth:** standard
**Files Reviewed:** 37
**Status:** issues_found

## Summary

Phase 01 ships the UX substrate: design tokens, Bootstrap overrides, a CSS bundle, vanilla-JS components, three mockups + seven partials, a thin routing skeleton, and 8 PHPUnit smoke tests. Other phases (auth, listings, tickets, points, reviews) will build on this — bugs in the substrate propagate.

The substrate is mostly sound: PSR-12 + strict_types on every PHP file, declare(strict_types=1) present in bootstrap + router + route maps + View; tokens carry all color values; Bootstrap re-skin is var-only; JS uses textContent (no innerHTML) in the toast builder; the skip-link is first focusable and points at a tabindex=-1 main; the bottom-nav aria-current contract is honored in the mockups and reset by the JS on each page.

Four criticals are real and need fixing before later phases inherit them:

1. **`tickettrade.components.css` ships ~11 hex literals** as `var()` fallbacks (e.g. `var(--color-surface-raised, #fff)`). The repo's own smoke test (`ContrastLedgerTest::test_no_hex_lit_outside_tokens`) is supposed to fail on this — when I reproduce the exact grep it returns 11 matches. Either the test is not being run, or the dev/CI shell differs from the local shell in a way that bypasses it. Either way, the policy is violated and the test is dead-green.
2. **`tickettrade.tokens.css` lines 114-118** declare `--paper-card-bg` TWICE in the same `:root[data-theme="light"]` block (first `#FFF8E7`, then `#FAF3E0` immediately overwrites it). All `.listing-card` backgrounds render the second value. Either remove the duplicate or pick one intentionally.
3. **Admin POSTs (`/admin/cron/ticket-expiry`, `/admin/cron/daily`) are registered in the student route map only.** `.htaccess` and `public/router.php` both send every `/admin/*` path to `public/admin/index.php`, which loads `admin/config/routes.php`. That map has six GETs and no POSTs. The POSTs are unreachable in any environment that follows the Apache rule. This is a dead-code + security-shape bug: the route map says "available" but the request never reaches it.
4. **`display_errors` driven by `getenv('APP_ENV')` rather than `getenv('APP_ENV') !== 'production'`** would have been the right form — `getenv()` returns `false` when unset, and `false === 'production'` is false → display_errors=1. If APP_ENV is not set in production (the common config-drift case), error details leak to the client. Both `bootstrap.php` and the cookie `secure` flag use this same pattern.

Plus six warnings (placeholder regex robustness, internal exception message leaked via `Error::server_error`, missing noscript in landing.php, globals-name mismatch `_tt_path_params` vs the docblock's `_tt_route_params`, hex literals in tokens.css rgba(), toast-queue race on rapid double-show, board-grid z-index ordering with pushpin).

The 8 smoke tests are well-targeted: they assert the static contract (CSS contains the selector, JS contains the function, partial contains the row count) rather than rendering in a browser. They are correct for Phase 1 — Phase 3+ will need real browser smoke tests (Playwright/Puppeteer) for the keyboard/modal/toast interactions.

Overall verdict: **needs-fix**. Fix the 4 criticals and the substrate is solid.

---

## Critical Issues

### CR-001: tickettrade.components.css violates the zero-hex-literal policy with 11 fallback literals

**Severity:** Critical
**Location:** `public/assets/css/tickettrade.components.css:789, 810, 814, 824, 831, 836, 837, 848, 871, 879, 883`
**Issue:** The "no hex literal outside tokens.css" policy is violated 11 times. Every offender is a `var(--color-foo, #abc123)` fallback. The smoke test `ContrastLedgerTest::test_no_hex_lit_outside_tokens` runs the exact grep that finds them:

```bash
grep -RIn --include=*.css --include=*.js --include=*.php --include=*.html \
  -E '#[0-9A-Fa-f]{3,8}\b' public/ config/ --exclude=tickettrade.tokens.css
# returns 11 hits in tickettrade.components.css
```

The test is therefore dead-green if it has ever been run, or it's never been run. Either way the policy is broken and downstream phases will see fallbacks baked into the source.

Concrete examples:
- Line 789: `background: var(--color-surface-raised, #fff);`
- Line 836: `border-left: 2px solid var(--color-primary, #1B5E20);`
- Line 837: `background: color-mix(in srgb, var(--color-primary, #1B5E20) 6%, transparent);`

The CSS is safe at runtime (the primary var always resolves in tokens.css), so it's not a runtime bug — it's a contract violation that the regression test should catch and does not.

**Fix:** Remove the fallback values. The primary var is always defined in `tickettrade.tokens.css`, so the fallback is dead code anyway. Diff:

```css
/* before */
.leaderboard-card { background: var(--color-surface-raised, #fff); }
/* after */
.leaderboard-card { background: var(--color-surface-raised); }
```

Apply the same removal to all 11 lines. After the cleanup, re-run `vendor/bin/phpunit tests/Smoke/01-01/ContrastLedgerTest.php` — it should report 0 hex literals. If it still reports 0 without the cleanup, investigate why the local shell's grep differs from the test's shell-escaped grep.

**Confidence:** high

---

### CR-002: Duplicate `--paper-card-bg` declaration silently overrides the intended cream value

**Severity:** Critical
**Location:** `public/assets/css/tickettrade.tokens.css:114-118`
**Issue:** Inside the single `:root[data-theme="light"]` block, `--paper-card-bg` is declared twice:

```css
--paper-card-bg: #FFF8E7;     /* line 114 — intended cream */
--paper-card-text: #1A1A1A;   /* line 115 */
--paper-card-bg: #FAF3E0;     /* line 117 — silently wins */
--paper-card-text: #1A1A1A;   /* line 118 */
```

CSS cascade: later declarations win in the same selector. `.listing-card { background-color: var(--paper-card-bg); }` (line 131 of `tickettrade.css`) therefore renders `#FAF3E0` (warmer beige), not `#FFF8E7` (paler cream). Every listing card mockup has the wrong tone. Worse, the dark theme does not redefine `--paper-card-bg` at all, so in dark mode the listing cards inherit the light override by default (since `var()` falls through) — but the design intent appears to be that paper cards stay paper-cream in both themes. This contradicts the design contract.

The duplicate is a leftover from a paste/edit. Either a merge conflict wasn't resolved, or one declaration was meant for the dark block and accidentally landed in the light block.

**Fix:** Decide the intended value, delete the duplicate, and (if paper is meant to be theme-invariant) declare `--paper-card-bg` once on `:root` (un-themed) instead of inside the light block. The cleanest version:

```css
/* tokens.css — outside any [data-theme] block, on plain :root */
:root {
  --paper-card-bg: #FAF3E0;
  --paper-card-text: #1A1A1A;
}
```

Then delete the four lines in the `[data-theme="light"]` block. The card now has a stable color across themes, which is the right read of "paper note on a cork board".

Add a smoke test: scan `tickettrade.tokens.css` for `--paper-card-bg` and assert it appears exactly once.

**Confidence:** high

---

### CR-003: Admin POST endpoints (`/admin/cron/ticket-expiry`, `/admin/cron/daily`) are unreachable — registered in the wrong route map

**Severity:** Critical
**Location:** `config/routes.php:49-50` vs `admin/config/routes.php` (full file) vs `public/.htaccess:15` vs `public/router.php:44-47`
**Issue:** Two POST endpoints are declared with `'admin' => true` in the student route map:

```php
// config/routes.php:49-50
'POST /admin/cron/ticket-expiry' => [..., ['auth' => true, 'admin' => true, 'csrf' => true, ...]],
'POST /admin/cron/daily'         => [..., ['auth' => true, 'admin' => true, 'csrf' => true, ...]],
```

But the front-controller routing sends every `/admin/*` path to `admin/index.php` first:

- **Apache**: `RewriteRule ^admin/(.*)$ admin/index.php [QSA,L]` (htaccess line 15)
- **Dev server**: `if (str_starts_with($path, '/admin/') || $path === '/admin') { require admin/index.php; }` (router.php line 44-47)
- **`admin/index.php`**: calls `Router::dispatch('admin', $requestPath)`.
- **`Router::loadRoutes($surface)`**: only loads the map for that surface (Router.php line 193). For `surface === 'admin'` it loads `admin/config/routes.php`, NOT `config/routes.php`.

`admin/config/routes.php` only registers six GETs (Dashboard, Users, Listings, Reports, Cron, Audit). It does NOT contain the two POSTs.

Result: any POST to `/admin/cron/ticket-expiry` or `/admin/cron/daily` lands on `admin/index.php` → `Router::dispatch('admin', '/admin/cron/ticket-expiry')` → no match in `admin/config/routes.php` → returns the generic 404 page. The POSTs are dead code. Worse, they're declared with `'csrf' => true` and `'rate_limit' => 'admin_cron'` — meaning a Phase 8 admin who wires the UI to call these POSTs will discover they don't work, and the failure mode (404 from generic error page) hides the actual cause.

**Fix:** Move both POSTs into `admin/config/routes.php` and align the action handler. Diff:

```php
// admin/config/routes.php — add after line 19
'POST /admin/cron/ticket-expiry' => ['App\Admin\Action\CronAction', 'handle', ['auth' => true, 'admin' => true, 'csrf' => true, 'rate_limit' => 'admin_cron']],
'POST /admin/cron/daily'         => ['App\Admin\Action\CronAction', 'handleDaily', ['auth' => true, 'admin' => true, 'csrf' => true, 'rate_limit' => 'admin_cron']],
```

Then delete the two entries from `config/routes.php:49-50`. Verify by adding a smoke test that POSTs to both paths return non-404 in the integration suite.

**Confidence:** high

---

### CR-004: `display_errors` and cookie `secure` flag leak info when `APP_ENV` is unset

**Severity:** Critical
**Location:** `config/bootstrap.php:34, 45`
**Issue:** Two display-vs-production gates read `getenv('APP_ENV')`:

```php
// bootstrap.php:34
ini_set('display_errors', getenv('APP_ENV') === 'production' ? '0' : '1');

// bootstrap.php:45
$secure = getenv('APP_ENV') === 'production';
```

`getenv()` returns `false` when the variable is unset. `false === 'production'` is `false`, so the ternary picks `'1'` (display errors ON) and `$secure = false` (cookie sent over HTTP). If production deploys without `APP_ENV=production` set — a common config-drift bug — PHP error messages (including stack-trace fragments) render in HTML responses, and the session cookie is sent over plaintext HTTP.

This is a defense-in-depth problem. The check should be the opposite: ONLY enable production hardening when explicitly opted in, default to safe behavior.

**Fix:** Use a "safe by default" check. Two options:

```php
// Option A — explicit opt-in to development mode
$isDev = getenv('APP_ENV') !== 'production';
ini_set('display_errors', $isDev ? '1' : '0');
$secure = !$isDev;

// Option B — config file
$env = require __DIR__ . '/env.php'; // returns ['display_errors' => bool, 'cookie_secure' => bool]
ini_set('display_errors', $env['display_errors'] ? '1' : '0');
session_set_cookie_params([..., 'secure' => $env['cookie_secure'], ...]);
```

Add a deploy-doc line: `APP_ENV=production` MUST be set in production, or the safe-by-default config will (correctly) refuse to enable dev-mode features. Pair with a smoke test that calls `bootstrap.php` with `APP_ENV=production` unset and asserts `display_errors=0` and the session cookie params have `secure=1`.

**Confidence:** medium (depends on production deployment practice, but the current code's default is unsafe)

---

## Warnings

### WR-001: Front controllers leak exception messages to the client

**Severity:** Warning
**Location:** `public/index.php:21`, `public/admin/index.php:19`
**Issue:** Both front controllers catch `\Throwable` and call `\App\Support\Error::server_error($e->getMessage())`. If `Error::server_error` echoes the message verbatim to the client (a common implementation — the file isn't in scope but is small), then an unhandled exception in any handler dumps the error text into the HTML response. In dev that's desirable; in production it's an info leak (table names, file paths, class names).

**Fix:** Decide by env. If `APP_ENV === 'production'`, log the full message to `error_log` and render a generic "Server error" page; otherwise echo the message. Do this inside `Error::server_error` itself, not the front controller. Diff:

```php
// src/Support/Error.php — server_error()
public static function server_error(string $message): void {
    error_log('[server_error] ' . $message);
    if (getenv('APP_ENV') === 'production') {
        http_response_code(500);
        echo '<!DOCTYPE html><title>Server error</title><main><h1>Something went wrong.</h1></main>';
        return;
    }
    // dev path: re-throw or echo with stack
    throw new \RuntimeException($message);
}
```

Then the front-controller try/catch becomes redundant — but keep it as a last-ditch net.

**Confidence:** medium (depends on Error.php behavior, not reviewed)

---

### WR-002: `_tt_path_params` vs `_tt_route_params` naming mismatch in Router

**Severity:** Warning
**Location:** `src/Support/Router.php:11, 83`
**Issue:** The Router's docblock (line 11) says route params are exposed via `$GLOBALS['_tt_route_params']`. The implementation (line 83) sets `$GLOBALS['_tt_path_params']`. Every Action that wants to read a URL placeholder will write the wrong global. This is a Phase 2 wiring hazard.

**Fix:** Pick one. Since "path params" is more accurate (they're URL path segments, not route definition params), keep `_tt_path_params` and update the docblock:

```php
/**
 * ...
 * Path placeholders like {nickname} are matched and exposed via
 * $GLOBALS['_tt_path_params'] as a [name => capturedValue] map.
 */
```

Add a `grep` test that fails CI if either string is reintroduced.

**Confidence:** high

---

### WR-003: Placeholder regex is not anchored per-segment and matches `//` accidentally

**Severity:** Warning
**Location:** `src/Support/Router.php:57-72`
**Issue:** `preg_replace('#\\{[^}]+\\}#', '([^/]+)', $rkPath)` produces `^pattern$`. The `[^/]+` correctly forbids slashes inside a placeholder value, so `/listings/abc/edit` won't match `/listings/{id}/edit` differently than it should — that's fine. But: if a future route is defined like `'/foo/{bar}/baz'` and someone hits `/foo//baz`, the regex will fail to match (good), but a route `/foo` with a placeholder `/foo/{bar}` will accept `/foo/abc` correctly. The actual bug here is more subtle:

`$rkPath = substr($routeKey, $spacePos + 1)` — but if `$spacePos` is `false` (no space), we skip with `continue`. After splitting METHOD and PATH, `$routeKey` always has a space, so `$spacePos === false` is unreachable. Safe.

What IS a problem: the regex is rebuilt on every dispatch for every placeholder route. For 30 routes that's 30 `preg_replace` + `preg_match` pairs per request. Not a perf bug at this scale (we're not in v1 scope) but worth a note.

The real bug: the placeholder regex doesn't escape regex metacharacters that might appear in literal parts of the route. If anyone adds a route like `/items/{id}.json`, the `.` will be a regex wildcard. None of the current routes have metachars, but this is a foot-gun.

**Fix:** Escape the literal parts before substitution:

```php
$pattern = preg_quote($rkPath, '#');
$pattern = preg_replace('#\\\\{[^}]+\\\\}#', '([^/]+)', $pattern);
$fullPattern = '#^' . $pattern . '$#';
```

Note: `preg_quote` will escape `{` and `}` to `\{` `\}` so the placeholders regex needs to look for the escaped form.

**Confidence:** medium (no current bug, but a maintainability hazard)

---

### WR-004: Toast queue cap races on rapid double-show with no timer arming

**Severity:** Warning
**Location:** `public/assets/js/tickettrade.js:265-293`
**Issue:** In `show()`, the queue-cap eviction (`while (_queue.length >= QUEUE_CAP) removeEntry(_queue[0])`) happens BEFORE the new entry is pushed. But `removeEntry` is synchronous and clears the timer, so this is safe. What is NOT safe: if `getContainer()` creates a new container element on first show and the synchronous DOM append happens before `attachHoverHandlers` is wired, a hover event fired during the same tick won't find the dismiss button (`entry.el.__dismissBtn`). In practice the events fire async after the tick, so the dismiss button is wired in time — fine.

The real issue: `entry.expiresAt = Date.now() + entry.remainingMs` is set BEFORE `armTimer` runs. On `pauseEntry` then immediate `resumeEntry`, `clearTimer` computes `entry.remainingMs = expiresAt - Date.now()`. But the FIRST time `armTimer` runs, `remainingMs` was set to the full duration in `show()`, and `clearTimer` hasn't been called yet. So the timer fires at the right time on first arm. OK.

The actual subtle bug: in `pauseEntry`, the code does `clearTimer(entry); entry.paused = true;`. If `entry.paused` is already true (called twice), the early return prevents double-pausing. But if `pauseEntry` is called while a timer is already cleared (e.g. right after `clearTimer` from `removeEntry`'s `clearTimer`), then `entry.remainingMs` has been recomputed to `Math.max(0, ...)`. If `expiresAt < Date.now()` (very long pause), `remainingMs = 0`, and the next `armTimer` fires immediately. Unlikely in practice — toasts aren't paused for >4s — but worth a guard.

**Fix:** Add a minimum to the recomputed remaining:

```js
function clearTimer(entry) {
  if (entry.timer) {
    clearTimeout(entry.timer);
    entry.timer = null;
    entry.remainingMs = Math.max(0, entry.expiresAt - Date.now());
  }
}
```

becomes:

```js
function clearTimer(entry) {
  if (entry.timer) {
    clearTimeout(entry.timer);
    entry.timer = null;
    // If the toast has already expired while paused, drop it instead of re-arming with 0ms.
    entry.remainingMs = Math.max(50, entry.expiresAt - Date.now());
  }
}
```

50ms gives the DOM a tick to settle. Or, in `removeEntry`, just splice the entry first and never call `clearTimer` afterward. Add a smoke test that pauses for >remainingMs and asserts no double-fire.

**Confidence:** medium

---

### WR-005: `landing.php` is missing `<noscript>` fallback the mockups have

**Severity:** Warning
**Location:** `src/Support/View/landing.php:15-23`
**Issue:** The mockup `_partials/head.html` ships a `<noscript><style>:root{color-scheme:dark;}</style></noscript>` (or `color-scheme:light` for admin) so a JS-disabled browser sees the right surface immediately. `landing.php` (the actual Phase 1 stub served by `Router::renderStubLanding`) has no equivalent. JS-disabled visitors see the default browser styles, which is fine functionally but inconsistent with the mockup contract.

**Fix:** Add to `landing.php`'s `<head>`:

```php
<?php $noscriptScheme = $surface === 'admin' ? 'light' : 'dark'; ?>
<noscript><style>:root{color-scheme:<?= htmlspecialchars($noscriptScheme, ENT_QUOTES, 'UTF-8') ?>;}</style></noscript>
```

**Confidence:** high

---

### WR-006: Hex literal in `tickettrade.components.css` rgba() shadows `tokens.css` shadow color

**Severity:** Warning
**Location:** `public/assets/css/tickettrade.components.css:469`
**Issue:** Line 469:

```css
.legend-glow::after { animation: none; box-shadow: 0 0 12px rgba(198, 40, 40, 0.35); }
```

The RGB triple `(198, 40, 40)` is the rank-a red (`#C62828`) hardcoded — not derived from `var(--color-rank-a)` like the non-reduced-motion sibling rule on line 458 does (`background: var(--color-rank-a);`). If the brand red changes in tokens.css, this shadow stays stale. Same pattern appears in the rgba-with-fallbacks I flagged in CR-001 (lines 791, 807, 862 all use `var(--color-X, rgba(0,0,0,...))`).

**Fix:** Promote `--color-rank-a-rgb` and `--color-shadow-color-rgb` to tokens (if not already), or use the existing `--color-shadow-color` token with an `opacity` wrapper:

```css
/* tokens.css */
--color-rank-a-rgb: 198, 40, 40;

/* components.css */
.legend-glow::after { animation: none; box-shadow: 0 0 12px rgba(var(--color-rank-a-rgb), 0.35); }
```

The grep test (`#[0-9A-Fa-f]{3,8}\b`) won't catch `rgba(198, 40, 40)` — it only catches `#hex`. So this isn't a test-policy violation, but it IS a token-policy violation by intent.

**Confidence:** medium

---

## Info

### IN-001: console.* calls in production JS

**Severity:** Info
**Location:** `public/assets/js/tickettrade.js:28, 103, 267, 488`
**Issue:** Four `console.error/warn/info` calls. These are dev-time diagnostics and intentional (`component init failed`, `invalid theme mode`, `unknown toast type`, `retry`). They aren't bugs but a production build step would strip them. Since there's no build step (matches the assignment constraint), they're a known minor leak.

**Fix:** Leave as-is for Phase 1. If a `dist/` or `min.js` is added later, strip them. Note in AGENTS.md that production JS includes console calls.

**Confidence:** high

---

### IN-002: Mockup `<a class="skip-link">` is hardcoded but the JS overwrites it

**Severity:** Info
**Location:** `public/mockups/my-tickets.html:212`, `public/assets/js/tickettrade.js:316-329`
**Issue:** `my-tickets.html` line 212 sets `aria-current="page"` on the My-Tickets nav link. The bottomNav JS (line 316-329) calls `item.removeAttribute('aria-current')` first, then re-applies based on `href` basename match. The hardcoded `aria-current="page"` is overwritten. No bug — the JS is the source of truth — but the static-HTML hint is misleading to a future maintainer who reads the mockup without running JS.

**Fix:** Either remove the hardcoded `aria-current` from the mockup (let JS add it) or add a comment explaining the JS resets it.

**Confidence:** high

---

### IN-003: Tier-progress `transition: width var(--motion-hover, 200ms)` has a dead fallback

**Severity:** Info
**Location:** `public/assets/css/tickettrade.components.css:487`
**Issue:** `var(--motion-hover, 200ms)` — `--motion-hover` IS defined in tokens.css (`200ms ease`), so the fallback is dead code. Not a bug.

**Fix:** Leave as-is. If `--motion-hover` is ever removed, the fallback would activate and the bar would still animate. This is defensive code.

**Confidence:** high

---

### IN-004: `listing-card` z-index ordering with `.pushpin` and `.corkboard-surface`

**Severity:** Info
**Location:** `public/assets/css/tickettrade.css:104-126`, 174-185`
**Issue:** `.corkboard-surface` has `z-index: 0`, `.board-grid` has `z-index: 1`. `.pushpin` is `position: absolute; top: -8px;` inside `.listing-card` with no explicit z-index. The pushpin sticks out the top of the card by 8px and may be clipped by the corkboard container's overflow (none set explicitly, so it bleeds into surrounding rows). On smaller viewports with `grid-template-columns: 1fr`, the pushpin of card #2 may overlap the title of card #1. Visual nit only.

**Fix:** Add `overflow: visible` explicitly on `.corkboard` (default in CSS but worth pinning), and `z-index: 2` on `.pushpin` if pushpins should layer above adjacent cards.

**Confidence:** medium

---

## Reviewer notes

- The smoke tests (8 of them) target static contracts correctly for a substrate phase. They assert "the file contains this regex", which is brittle to whitespace but works for Phase 1. Phase 3+ needs real browser-driven tests for the dynamic behaviors (toast queue cap, scrim guard, keyboard floor).
- `bootstrap.php` runs `Csrf::verify()` unconditionally on every request, including GETs. The docblock says "POST/PUT/PATCH/DELETE" but the verify is called before the Router. Either Csrf::verify() is internally cheap on GETs (returns immediately), or it's wasted work per GET. Worth a quick check on Csrf.php behavior — not reviewed.
- `Router::renderGenericError` echoes the layout view which itself includes the same bootstrap chain. If `layout.php` includes `bootstrap.php` itself, you can get a re-entrance cycle. Not reviewed — out of file scope — but worth flagging for Phase 2.

---

_Reviewed: 2026-09-05_
_Reviewer: the agent (gsd-code-reviewer)_
_Depth: standard_