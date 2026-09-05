---
phase: 02-student-authentication-profiles
fixed_at: 2026-09-05T00:00:00Z
review_path: 02-REVIEW.md
iteration: 1
findings_in_scope: 17
fixed: 14
skipped: 3
status: partial
---

# Phase 2: Code Review Fix Report

**Fixed at:** 2026-09-05T00:00:00Z
**Source review:** `02-REVIEW.md`
**Iteration:** 1

**Summary:**
- Findings in scope: 17 (4 Critical, 8 Warning, 5 Info)
- Fixed: 14 (4 Critical, 7 Warning, 3 Info)
- Skipped: 3 (1 Warning, 2 Info — see reasons below)

## Fixed Issues

### CR-01: Public profile View's `on_break_pill` always renders nothing

**Files modified:** `src/User/Service/user_service.php`
**Commit:** `d585e84`
**Applied fix:** Added `last_active_at` to the `getByNicknameForPublicProfile()` SELECT projection. The column is created by migration `019_users_last_active.sql` (Phase 6) so no schema change was needed — the projection was simply incomplete.

### CR-02: `.gitignore` does not exclude `config/db.php` and `config/db.test.php`

**Files modified:** `.gitignore`
**Commit:** `ff8c9ce`
**Applied fix:** Appended `config/db.php` and `config/db.test.php` to the root `.gitignore` per AD-17. Verified with `git check-ignore -v` that both files match (lines 34 + 35).

### CR-03: `register()` race-condition catch returns misleading `E_AUTH_ALLOWLIST`

**Files modified:** `src/Auth/Service/auth_service.php`
**Commits:** `120243d`, `5f29c2e` (PSR-12 follow-up)
**Applied fix:** Inspects the `PDOException`'s SQLSTATE (`23000`) and message for `uniq_nickname`. If matched, returns `E_NICKNAME_TAKEN` (matches the pre-check copy; nicknames are intentionally public per D-13, so no enumeration concern). Email/student_id races still collapse to the combined `E_AUTH_ALLOWLIST` copy. Anti-enumeration guarantees are preserved for the private fields.

### CR-04: Public profile View parses `created_at` as UTC but DB stores Asia/Colombo

**Files modified:** `src/User/View/public_profile.php`
**Commit:** `d1cb5c0`
**Applied fix:** Changed `new DateTimeZone('UTC')` to `new DateTimeZone('Asia/Colombo')` for the parse, dropped the redundant `->setTimezone()` chain. Comment updated to explain why (the wall-clock string is Colombo time per `users.created_at`'s writer).

### WR-02: `migrate.php` truncates `migrations/.applied` in `bin/dev-setup.sh`

**Files modified:** `bin/dev-setup.sh`
**Commit:** `c4a892b`
**Applied fix:** Removed the `: > migrations/.applied` line. The migrate.php runner already reads `.applied` and skips applied files; the truncate was only needed for the previous "fail on UNIQUE" bootstrap symptom.

### WR-03: `bin/dev-setup.sh` shell-quoting risk on `$DB_USER` / `$DSN`

**Files modified:** `bin/dev-setup.sh`
**Commit:** `d7c9ce6`
**Applied fix:** Routed DSN and DB_USER through `php -r 'echo var_export(...)'` to produce a string-literalized form, then interpolated the result into the heredoc. Verified by round-tripping the generated PHP — produces the expected config array. Single-quote / backslash / dollar in `$USER` or `$DSN` no longer corrupt the generated file.

### WR-04: `migrate.php` SQL comment-stripping is fragile

**Files modified:** `migrate.php`
**Commit:** `45d5281`
**Applied fix:** Anchored the `--` strip regex to `(^|\s)` so it no longer matches inside string literals like `'--foo'`. Verified by test cases: real comments still strip, string content preserved.

### WR-05: `migrate.php` runs concurrent invocations without locking

**Files modified:** `migrate.php`
**Commit:** `90796a0`
**Applied fix:** Added `flock(LOCK_EX)` on `migrations/.applied.lock` (per-surface under WR-07). Released via `register_shutdown_function` so every `exit(0)/exit(1)` path in the script unlocks cleanly. Today's IF NOT EXISTS DDL is safe; the next non-idempotent migration (a seed INSERT) won't double-execute.

### WR-06: `Support\Router::dispatch()` emits verbose `error_log` calls

**Files modified:** `src/Support/Router.php`
**Commit:** `2cf9e84`
**Applied fix:** Gated all four dispatch-time `error_log` calls on `APP_ENV === 'development'`. Production logs no longer accumulate per-request noise and no longer carry request-path data that may include URL-encoded sensitive tokens.

### WR-07: `migrate.php` and `bin/dev-setup.sh` race on `migrations/.applied` (dev vs test DB)

**Files modified:** `migrate.php`, `migrations/.gitignore`
**Commit:** `01ddaae`
**Applied fix:** Migrate.php now uses `.applied.$APP_ENV` (e.g. `.applied.development`, `.applied.test`) so the dev DB and test DB track independent state. Updated `migrations/.gitignore` to match both `.applied` and `.applied.*`. WR-05's flock now applies within a single surface; the per-surface split eliminates the cross-surface race.

### WR-08: `config/ranks.php` dual-implementation of `tierFromPoints`

**Files modified:** `src/Auth/Service/auth_service.php`
**Commit:** `345ed5b`
**Applied fix:** Replaced `auth_service::tierFromPoints`'s parallel `foreach` ladder with an unconditional delegate to the global `tierFromPoints()` function. The require_once loads the function (and the $ranks array) on the rare code path where `ranks.php` hasn't been required yet. Eliminated the dual-implementation and its maintenance trap.

### IN-02: `config/db.php` and `config/db.test.php` empty password default

**Files modified:** `config/db.php`, `config/db.test.php`
**Commit:** *(no commit — files are gitignored per CR-02 + AD-17)*
**Applied fix:** Added a guard at the top of both files: in `APP_ENV === 'production'`, throw `RuntimeException('DB_PASS must be set in production.')` if `$pass` is empty/false. Verified the guard fires with `APP_ENV=production php -r 'require "config/db.php"'` (prints the rejection). Dev / test environments keep the empty fallback because MariaDB on the unix socket accepts password-less root. The change lives only on disk; `bin/dev-setup.sh` overwrites these files on next run anyway.

### IN-03: `Support\Auth::boot()` TZ comparison brittleness

**Files modified:** `src/Support/Auth.php`
**Commit:** `62e52a2`
**Applied fix:** Replaced the `strtotime($row['last_seen']) < time() - 300` comparison with explicit `DateTime` objects parsed with `new DateTimeZone('Asia/Colombo')` on both sides. Works correctly regardless of the script's default TZ (today pinned by bootstrap.php).

### IN-04: `auth_service::startSession()` redundant `$GLOBALS` mutation

**Files modified:** `src/Auth/Service/auth_service.php`
**Commit:** `7ebf198`
**Applied fix:** Rewrote the misleading "Force Auth::boot() to re-read on the next request" comment to clarify the assignment is a defensive refresh for the current request (boot() runs once per request, not on demand). Did not drop the assignment itself because the user's instruction was to "drop the redundant line" only if appropriate; the line is harmless and the rewrite clarifies the actual semantics.

## Skipped Issues

### WR-01: `register()` does not check `is_banned`

**File:** `src/Auth/Service/auth_service.php:240-265`
**Reason:** **Deferred — YAGNI.** The proposed fix introduces a brand-new `users.deleted_at` column (a new `023_users_deleted_at.sql` migration) plus a WHERE-clause change to `user_model::findByEmail` to filter on `deleted_at IS NULL`. The reviewer correctly flags this as "latent" — Phase 2 has no unban/purge flow; Phase 8 (admin console) is the first phase that would create banned-then-deleted rows. Adding an unused column with no callers is a YAGNI violation. The fix should land alongside the Phase 8 admin user-management flow, which has the actual use case. Note: the migration filename slot 023 is available (`008_listings.sql` through `022_trigger_cap_hit_ignore.sql` are taken, plus `011` is missing).

### IN-01: `Support\Auth::adminGuard()` is dead code

**File:** `src/Support/Auth.php:95-101`
**Reason:** **Not actually dead — defer per reviewer's option (a).** The reviewer's grep found Router::dispatch() inlines the admin check (lines 105-112) and the Auth class call sites only use `requireAuth()`. However, `tests/Integration/Phase02/Support/AuthGuardTest.php:59` references `adminGuard` and the test (`test_admin_guard_404s_non_admin`) verifies the Error::not_found() wiring that `adminGuard` calls. While `AuthGuardTest` only grep-asserts on `Error.php` rather than invoking `adminGuard` directly, the function is contract-documented and the Phase 8 admin console will need it. Keeping it per the reviewer's option (a) recommendation. Router's inline check mirrors the same semantics, so the duplication is acceptable.

### IN-05: `Router::renderGenericError()` fallback doesn't render through the layout

**File:** `src/Support/Router.php:162-175`
**Reason:** **No action needed.** The reviewer explicitly recommended "No action needed. The fallback is intentional defense-in-depth." The layout always exists in production; the fallback only kicks in when the layout file is unreachable (e.g., test runs with `View/layout.php` deleted). No fix applied.

## Files Modified

| Path | Commit(s) | Phpcs Status |
|------|-----------|--------------|
| `004/tickettrade/.gitignore` | `ff8c9ce` | clean |
| `004/tickettrade/bin/dev-setup.sh` | `c4a892b`, `d7c9ce6` | n/a (bash) |
| `004/tickettrade/migrate.php` | `45d5281`, `90796a0`, `01ddaae` | pre-existing header PSR-12 issue (unchanged) |
| `004/tickettrade/migrations/.gitignore` | `01ddaae` | clean |
| `004/tickettrade/src/Auth/Service/auth_service.php` | `120243d`, `345ed5b`, `7ebf198`, `5f29c2e` | pre-existing PascalCase class name (unchanged) |
| `004/tickettrade/src/Support/Auth.php` | `62e52a2` | clean |
| `004/tickettrade/src/Support/Router.php` | `2cf9e84` | clean |
| `004/tickettrade/src/User/Service/user_service.php` | `d585e84` | pre-existing PascalCase class name (unchanged) |
| `004/tickettrade/src/User/View/public_profile.php` | `d1cb5c0` | clean |
| `004/tickettrade/config/db.php` | (gitignored, no commit) | clean |
| `004/tickettrade/config/db.test.php` | (gitignored, no commit) | clean |

Pre-existing phpcs warnings are unchanged from the original files — none were introduced by the fixes.

---

_Fixed: 2026-09-05T00:00:00Z_
_Fixer: the agent (gsd-code-fixer)_
_Iteration: 1_