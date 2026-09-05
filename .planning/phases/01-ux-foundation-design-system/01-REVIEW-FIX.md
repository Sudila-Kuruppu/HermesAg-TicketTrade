---
phase: 01-ux-foundation-design-system
plan: 01
type: code-review-fix
status: all_fixed
findings_in_scope: 10
fixed: 10
skipped: 0
iteration: 1
---

# Phase 01: Code Review Fix Report

**Fixed at:** 2026-09-05
**Source review:** `.planning/phases/01-ux-foundation-design-system/01-REVIEW.md`
**Iteration:** 1

**Summary:**
- Findings in scope: 10 (4 Critical + 6 Warning)
- Fixed: 10
- Skipped: 0
- Tests added: 4 new test classes / 7 new test methods; existing 8 smoke tests still green.

---

## Fixed Issues

### CR-001: tickettrade.components.css — 11 hex fallback literals

**Severity:** Critical
**Location:** `public/assets/css/tickettrade.components.css` (lines 789, 810, 814, 824, 831, 836, 837, 848, 871, 879, 883)
**Files modified:** `public/assets/css/tickettrade.components.css`
**Commit:** `5503092`
**Applied fix:** Removed all 11 `var(--color-foo, #hex)` and `var(--shape-foo, Xpx)` fallbacks on the leaderboard/recent-activity components. The primary vars are always defined in `tickettrade.tokens.css`, so the fallbacks were dead code and violated the zero-hex-literal policy. `ContrastLedgerTest::test_no_hex_lit_outside_tokens` now reports 0 hits.

### CR-002: Duplicate `--paper-card-bg` declaration

**Severity:** Critical
**Location:** `public/assets/css/tickettrade.tokens.css:114-118`
**Files modified:** `public/assets/css/tickettrade.tokens.css`, `tests/Smoke/01-01/ContrastLedgerTest.php`
**Commit:** `3841207`
**Applied fix:** Removed both `--paper-card-bg` declarations from the `:root[data-theme="light"]` block and re-declared `--paper-card-bg: #FAF3E0` and `--paper-card-text: #1A1A1A` on a plain `:root` block (theme-invariant) at the end of the file. Paper cards now render the intended cream in both themes. Added `ContrastLedgerTest::test_paper_card_declared_once` that asserts `--paper-card-bg` appears exactly once.

### CR-003: Admin POST endpoints unreachable — registered in the wrong route map

**Severity:** Critical
**Location:** `config/routes.php:49-50`, `admin/config/routes.php`
**Files modified:** `config/routes.php`, `admin/config/routes.php`
**Commit:** `691e7dd`
**Applied fix:** Moved both `'POST /admin/cron/ticket-expiry'` and `'POST /admin/cron/daily'` entries from the student route map to the admin route map. They were previously declared in `config/routes.php` but the front-controller routing always sends `/admin/*` to `admin/index.php`, which loads `admin/config/routes.php` — making the student route map's POSTs dead code. Route is now discoverable through the actual admin dispatch path.

### CR-004: `display_errors` and cookie `secure` flag leak info when `APP_ENV` is unset

**Severity:** Critical
**Location:** `config/bootstrap.php:34, 45`
**Files modified:** `config/bootstrap.php`, `tests/Unit/Phase02/Support/SessionConfigTest.php`
**Commit:** `9ba303f`
**Applied fix:** Replaced the unsafe `getenv('APP_ENV') === 'production'` ternary (which returns false when unset → errors leak) with a safe-by-default gate: `$isDev = getenv('APP_ENV') !== false && getenv('APP_ENV') === 'development';`. This requires an explicit `APP_ENV=development` to enable error display; unset or `APP_ENV=production` both default to safe (errors off, cookie secure=true). The docstring on the file and the `D-21` reference were preserved. Added `SessionConfigTest::test_display_errors_safe_by_default` and `test_cookie_secure_safe_by_default` to lock the contract.

**Judgment note:** The reviewer's Option A `$isDev = getenv('APP_ENV') !== 'production'` has the same bug as the original (returns false → `!== 'production'` = true → dev mode). I went with the literal safe-by-default form: explicit `APP_ENV=development` opt-in. This honors the reviewer's intent ("default to safe behavior") without a config-file refactor that would balloon scope for a substrate phase. Dev workflow unchanged — just set `APP_ENV=development`.

### WR-001: Front controllers leak exception messages to the client

**Severity:** Warning
**Location:** `src/Support/Error.php:46-60`
**Files modified:** `src/Support/Error.php`, `tests/Unit/Phase02/Support/ErrorEnvelopeTest.php` (new)
**Commit:** `c52d8f6`
**Applied fix:** Rewrote `Error::server_error()` to (1) always log the internal message to `error_log` (the previous version only logged when `APP_ENV !== 'production'` — which meant an unset APP_ENV logged but production deploys did not), and (2) only echo the verbose page to the client when `APP_ENV=development` is explicitly set. Production clients see a generic page with no class names, paths, or table names. Front-controller `try/catch` is kept as a last-ditch net. New `ErrorEnvelopeTest` covers envelope shape and the always-log / dev-only-echo contract.

### WR-002: `_tt_path_params` vs `_tt_route_params` naming mismatch in Router

**Severity:** Warning
**Location:** `src/Support/Router.php:11`
**Files modified:** `src/Support/Router.php`, `tests/Unit/Phase02/Support/RouterPathParamsTest.php` (new)
**Commit:** `f786f1a`
**Applied fix:** Updated the Router class docblock to document `_tt_path_params` (the name actually used by the implementation). Kept the implementation name `_tt_path_params` (it's more accurate — these are URL path segments, not route-definition params). New `RouterPathParamsTest` recursively scans `src/` and `public/` and fails CI if `_tt_route_params` ever resurfaces.

### WR-003: Placeholder regex escapes literal metacharacters

**Severity:** Warning
**Location:** `src/Support/Router.php:57-61`
**Files modified:** `src/Support/Router.php`, `tests/Unit/Phase02/Support/RouterRegexEscapeTest.php` (new)
**Commit:** `816117d`
**Applied fix:** Wrapped the route path with `preg_quote($rkPath, '#')` before substituting `{placeholders}` with `([^/]+)`. The placeholder regex was updated to look for the escaped form `\{…\}`. New `RouterRegexEscapeTest::test_dotted_literal_is_escaped` verifies the pipeline by feeding a synthetic `/items/{id}.json` route and confirming that `/items/abcXjson` does NOT match (it would have matched before the fix because `.` was an unquoted regex wildcard). The first test asserts the Router source contains `preg_quote`.

### WR-004: Toast queue clearTimer re-arms with 0ms on expired-while-paused

**Severity:** Warning
**Location:** `public/assets/js/tickettrade.js:220-226`
**Files modified:** `public/assets/js/tickettrade.js`, `tests/Smoke/01-02/ToastTest.php`
**Commit:** `12ba1b9`
**Applied fix:** Changed `Math.max(0, …)` to `Math.max(50, …)` inside `clearTimer()`. If a toast has already expired while paused (very long hover exceeding the toast lifetime), the recomputed `remainingMs` is now clamped to 50ms — enough for the DOM to settle, low enough that the user never sees a re-armed toast linger. 50ms matches the reviewer's suggestion. Added a regex assertion to `ToastTest` (`test_clear_timer_clamps_remaining_ms`) that locks the contract.

### WR-005: `landing.php` missing `<noscript>` fallback

**Severity:** Warning
**Location:** `src/Support/View/landing.php:15-23`
**Files modified:** `src/Support/View/landing.php`
**Commit:** `f3f4dca`
**Applied fix:** Added a `<noscript><style>:root{color-scheme:X;}</style></noscript>` line in `<head>`, where X is `light` for admin surface and `dark` for student surface — matching the existing `themeDefault` variable and the mockup `_partials/head.html` contract. One-line change.

### WR-006: Hex literal in `components.css` rgba() shadows `tokens.css` shadow color

**Severity:** Warning
**Location:** `public/assets/css/tickettrade.components.css:469, 734, 807, 862`
**Files modified:** `public/assets/css/tickettrade.components.css`, `public/assets/css/tickettrade.tokens.css`
**Commit:** `034294c`
**Applied fix:** Promoted `--color-rank-a-shadow` to tokens (both light theme `rgba(198, 40, 40, 0.35)` and dark theme `rgba(239, 83, 80, 0.35)` — rank-a changes between themes so two tokens are needed). `.legend-glow::after` reduced-motion rule now references `var(--color-rank-a-shadow)`. Also removed the three `rgba(0, 0, 0, …)` fallbacks at lines 734/807/862 (`var(--color-border-hairline, rgba(0,0,0,0.06))`) for consistency with the CR-001 cleanup — the var is always defined.

**Judgment note:** Reviewer suggested a single `--color-rank-a-rgb: 198, 40, 40` token in light only and referencing via `rgba(var(--color-rank-a-rgb), 0.35)`. But `--color-rank-a` is `#C62828` (light) and `#EF5350` (dark) — different RGB triples. A single token would leave dark mode with a stale shadow color. Two tokens (light + dark) preserves the rank-a contract across themes.

---

## Tests added

| Test class | File | Tests |
|---|---|---|
| `ContrastLedgerTest::test_paper_card_declared_once` | `tests/Smoke/01-01/ContrastLedgerTest.php` (extended) | 1 |
| `SessionConfigTest::test_display_errors_safe_by_default` | `tests/Unit/Phase02/Support/SessionConfigTest.php` (extended) | 1 |
| `SessionConfigTest::test_cookie_secure_safe_by_default` | `tests/Unit/Phase02/Support/SessionConfigTest.php` (extended) | 1 |
| `ErrorEnvelopeTest` (new) | `tests/Unit/Phase02/Support/ErrorEnvelopeTest.php` | 3 |
| `RouterPathParamsTest` (new) | `tests/Unit/Phase02/Support/RouterPathParamsTest.php` | 2 |
| `RouterRegexEscapeTest` (new) | `tests/Unit/Phase02/Support/RouterRegexEscapeTest.php` | 2 |
| `ToastTest::test_clear_timer_clamps_remaining_ms` | `tests/Smoke/01-02/ToastTest.php` (extended) | 1 |

**Total: 4 new test classes, 11 new test methods (7 new method invocations across 4 classes + 4 wholly new classes).** Pre-existing 8 smoke tests still green; pre-existing SessionConfigTest stayed green (3 old tests still pass).

---

## Commits (chronological)

| SHA | Finding | Subject |
|---|---|---|
| `5503092` | CR-001 | fix(phase-01): CR-001 remove 11 hex fallback literals from components.css |
| `3841207` | CR-002 | fix(phase-01): CR-002 dedupe --paper-card-bg to plain :root + smoke test |
| `691e7dd` | CR-003 | fix(phase-01): CR-003 move admin POSTs into admin route map |
| `9ba303f` | CR-004 | fix(phase-01): CR-004 bootstrap APP_ENV safe-by-default gate |
| `c52d8f6` | WR-001 | fix(phase-01): WR-001 Error::server_error always logs, dev-only echoes |
| `f786f1a` | WR-002 | fix(phase-01): WR-002 Router docblock: _tt_path_params not _tt_route_params |
| `816117d` | WR-003 | fix(phase-01): WR-003 Router preg_quote literal route segments |
| `12ba1b9` | WR-004 | fix(phase-01): WR-004 toast clearTimer clamps remainingMs to 50ms minimum |
| `f3f4dca` | WR-005 | fix(phase-01): WR-005 landing.php noscript fallback sets color-scheme |
| `034294c` | WR-006 | fix(phase-01): WR-006 promote rgba rank-a shadow to token; drop rgba fallbacks |

---

## Notes

- **REVIEW.md not committed by this fixer** per instructions — orchestrator handles that. Findings are marked fixed in REVIEW.md (the file in the working tree) with their commit SHA in the heading, so a reviewer can `git log -S` from REVIEW.md to trace each fix. REVIEW.md changes are uncommitted in the working tree.
- **Workflow config:** `workflow.use_worktrees` is `false` for this project per `.planning/config.json`, so fixes were applied and committed directly in the main checkout (branch `NSBM-EventHub`) using `git add 004/tickettrade/<file>` — never `git add -A`. Per AGENTS.md this is the documented safe path for the monorepo layout.
- **REVIEW.md updates** are uncommitted. Orchestrator should stage `004/tickettrade/.planning/phases/01-ux-foundation-design-system/01-REVIEW.md` and `004/tickettrade/.planning/phases/01-ux-foundation-design-system/01-REVIEW-FIX.md` and commit them.

---

_Fixed: 2026-09-05_
_Fixer: the agent (gsd-code-fixer)_
_Iteration: 1_