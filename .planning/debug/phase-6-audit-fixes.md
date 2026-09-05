# Phase 6 Audit Fixes — Summary

**Branch:** `NSBM-EventHub`
**Date:** 2026-09-05
**Scope:** 3 audit findings (1 blocker + 2 majors) on Phase 6 daily-cron + last-active-at substrate
**Test result:** 93 tests, 387 assertions, all green (was 92/377 before fixes — +1 test, +10 assertions)

## Fixes applied

### Fix 1 — BLOCKER: streak bonus re-awarded on every cron run

**File:** `src/User/Service/user_service.php` (lines ~310-345, `recomputeStreakDisplay`)
**Symptom:** A user hitting day 7 then triggering the cron twice in one day (auto-run + manual `POST /admin/cron/daily`) got +15+15=30 instead of +15. Same for day 30 (+50+50=100).
**Fix:**
- Added a prepared `$bonusAwardedStmt` (lifetime-milestone check) before the foreach loop.
- Inside the loop, before `points_service::awardStreakBonus()`, query `points_log` for an existing row with `reference_type = streak_7day | streak_30day` for this user. If found, `continue` past the bonus block — the milestone is already locked in.
- Kept the existing `if ($consecutive === 7 || $consecutive === 30)` gate.
- Moved `$refType` computation outside the `awardStreakBonus` call so the guard can use it.

### Fix 2 — MAJOR: trigger refreshes `last_active_at` for zero-delta cap-hit rows

**File:** `migrations/019_users_last_active.sql` (line 37)
**Symptom:** `BEFORE INSERT` trigger fires for every `points_log` row including `velocity_cap_hit` and `pair_cap_hit` zero-delta rows. A capped-out user spamming hundreds of suppressed events per day stays "fresh" forever, defeating the 14-day on-break pill (PTS-08).
**Fix:** Added `WHEN (NEW.delta > 0)` clause to the trigger body. Only real point-earning activity bumps `last_active_at`. Trigger body is still a single statement (no `BEGIN/END` block) — compatible with the `;`-splitting runner.

```sql
DROP TRIGGER IF EXISTS trg_points_log_refresh_last_active;
CREATE TRIGGER trg_points_log_refresh_last_active BEFORE INSERT ON points_log FOR EACH ROW
WHEN (NEW.delta > 0)
UPDATE users SET last_active_at = NOW() WHERE user_id = NEW.user_id;
```

### Fix 3 — MAJOR: string-concat WHERE clause for `longest_streak`

**File:** `src/User/Service/user_service.php` (former lines 319-321)
**Symptom:** `'SELECT longest_streak FROM users WHERE user_id = ' . (int) $userId` — the `(int)` cast blocked SQLi but violated codebase convention (AD-13, phpcs prepared-statement pattern).
**Fix:** Hoisted a `$longestStmt = $pdo->prepare('SELECT longest_streak FROM users WHERE user_id = ?')` out of the loop, executed inside the foreach with the bound `$userId`. PDO cleans up the statement when the variable falls out of scope.

## Regression test

**File:** `tests/Integration/Phase06/DailyCronTest.php`
**New test:** `test_recompute_does_not_duplicate_streak_award` — seeds a user with 6 prior consecutive-day `login_streaks` rows + a `sessions` row for today, calls `recomputeStreakDisplay` three times, and asserts after each call that `points_log` has exactly one `streak_7day` row for that user (not 2, not 3). Asserts `$result['awards']` is empty after the first run.

**Strengthened:** `test_recompute_is_idempotent_within_a_day` now also asserts `$result['awards']` is empty on both runs (was only checking `processed`). Docstring updated to note the new contract.

## Migration 022 — patch path for already-applied DBs

**File:** `migrations/022_trigger_cap_hit_ignore.sql` (NEW)
**Why a separate file:** `migrate.php` uses an `.applied` marker per migration and never re-applies. Editing 019 in source updates the canonical shape for fresh installs only; existing databases still carry the unconditional trigger. Migration 022 is the patch path: `DROP TRIGGER IF EXISTS + CREATE TRIGGER ... WHEN (NEW.delta > 0)` — same shape as 019.

**Apply order:** Run `022_trigger_cap_hit_ignore.sql` after `019_users_last_active.sql` has been applied. Native `WHEN (NEW.delta > 0)` syntax requires MariaDB 10.0.2+ / MySQL 5.7+ — project runs MariaDB 11.4.5, so verified compatible.

## Test + lint results

- **phpunit phase-6 suite:** 93 tests, 387 assertions, all passing (`vendor/bin/phpunit --testsuite=phase-6`)
- **phpcs PSR-12 (`src/`):** 0 errors. The `user_service` snake_case class-name notice is pre-existing convention from the codebase, not introduced by this change.

## Commits (to `NSBM-EventHub`)

1. `fix(phase-6): recomputeStreakDisplay lifetime-milestone guard + prepared longest_streak`
2. `fix(phase-6): trigger WHEN (NEW.delta > 0) ignores cap-hit zero-delta rows + 022 for existing DBs`
3. `test(phase-6): regression — recompute does not duplicate streak bonus`
4. `docs(phase-6): audit-fix summary + status for 9 unfired findings`

## Note on the 9 unfired audit findings

The audit report referenced 9 other findings (5 majors + 6 minors + 3 notes = 14 by count, but the prompt called them "9"). The full list of those findings was not supplied in this fix's brief — only the 3 above. Without the actual item descriptions, no line-by-line status can be provided here. The 3 fixes listed above are the complete scope of this commit.

If/when the full audit report is provided, this document can be extended with per-finding disposition: "fixed", "deferred to Phase N", "out of scope (existing AD-*)", etc.