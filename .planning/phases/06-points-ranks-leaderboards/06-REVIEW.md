---
phase: 06-points-ranks-leaderboards
reviewed: 2026-09-05T00:00:00Z
depth: standard
files_reviewed: 49
files_reviewed_list:
  - 004/tickettrade/config/contexts.php
  - 004/tickettrade/config/routes.php
  - 004/tickettrade/migrations/018_points_log_indexes.sql
  - 004/tickettrade/migrations/019_users_last_active.sql
  - 004/tickettrade/migrations/020_leaderboard_summary.sql
  - 004/tickettrade/migrations/021_login_streaks.sql
  - 004/tickettrade/migrations/022_trigger_cap_hit_ignore.sql
  - 004/tickettrade/phpunit.xml
  - 004/tickettrade/public/assets/css/tickettrade.components.css
  - 004/tickettrade/public/assets/js/tickettrade.js
  - 004/tickettrade/src/Admin/Action/CronAction.php
  - 004/tickettrade/src/Auth/Service/auth_service.php
  - 004/tickettrade/src/Leaderboard/Action/LeaderboardAction.php
  - 004/tickettrade/src/Leaderboard/Model/leaderboard_model.php
  - 004/tickettrade/src/Leaderboard/Service/leaderboard_service.php
  - 004/tickettrade/src/Leaderboard/View/leaderboards.php
  - 004/tickettrade/src/Points/Action/PointsAdminAction.php
  - 004/tickettrade/src/Points/Model/points_log_model.php
  - 004/tickettrade/src/Points/Service/points_service.php
  - 004/tickettrade/src/Support/View/partials/leaderboard_row.php
  - 004/tickettrade/src/Support/View/partials/on_break_pill.php
  - 004/tickettrade/src/Support/View/partials/rank_badge.php
  - 004/tickettrade/src/Support/View/partials/tier_progress.php
  - 004/tickettrade/src/Support/View/partials/velocity_flag_pill.php
  - 004/tickettrade/src/User/Action/ProfileAction.php
  - 004/tickettrade/src/User/Model/user_model.php
  - 004/tickettrade/src/User/Service/user_service.php
  - 004/tickettrade/src/User/View/profile.php
  - 004/tickettrade/src/User/View/public_profile.php
  - 004/tickettrade/tests/Integration/Phase02/Support/RouteGuardTest.php
  - 004/tickettrade/tests/Integration/Phase06/CapAuditRowsTest.php
  - 004/tickettrade/tests/Integration/Phase06/DailyCronTest.php
  - 004/tickettrade/tests/Integration/Phase06/Fixtures/Fixtures.php
  - 004/tickettrade/tests/Integration/Phase06/LeaderboardViewTest.php
  - 004/tickettrade/tests/Integration/Phase06/MigrationIndexesTest.php
  - 004/tickettrade/tests/Integration/Phase06/MigrationLastActiveTest.php
  - 004/tickettrade/tests/Integration/Phase06/MigrationLeaderboardSummaryTest.php
  - 004/tickettrade/tests/Integration/Phase06/MigrationLoginStreaksTest.php
  - 004/tickettrade/tests/Unit/Phase06/Auth/RecordLoginTest.php
  - 004/tickettrade/tests/Unit/Phase06/Leaderboard/LeaderboardRowPartialTest.php
  - 004/tickettrade/tests/Unit/Phase06/Leaderboard/LeaderboardServiceTest.php
  - 004/tickettrade/tests/Unit/Phase06/Points/AwardListingApprovalTest.php
  - 004/tickettrade/tests/Unit/Phase06/Points/AwardReportValidatedTest.php
  - 004/tickettrade/tests/Unit/Phase06/Points/AwardStreakBonusTest.php
  - 004/tickettrade/tests/Unit/Phase06/Points/TierFromPointsTest.php
  - 004/tickettrade/tests/Unit/Phase06/Points/VoidAndClearFreezeTest.php
findings:
  critical: 2
  warning: 3
  info: 4
  total: 9
status: issues_found
---

# Phase 6: Code Review Report

**Reviewed:** 2026-09-05
**Depth:** standard
**Files Reviewed:** 49
**Status:** issues_found

## Summary

Reviewed the full Phase 6 diff (49 files: 5 SQL migrations, 12 PHP source files
across Points/Leaderboard/Auth/Admin/User, 5 View partials, 2 Views, 8 test
files, 1 modified Phase 2 test, configs, public assets). The audit-fix branch
(addressing 1 blocker + 2 majors from the 2026-09-05 forensic audit) is
applied correctly: the streak-bonus duplicate guard, the prepared
`longest_streak` statement, and the `WHEN (NEW.delta > 0)` trigger patch
path (migration 022) are all in place and correctly tested.

Two real issues remain: (1) `points_log_model::recentForUser` concatenates
the LIMIT into SQL via string interpolation, violating AD-13's prepared-
statement convention and the same pattern that was just fixed elsewhere
in this phase; (2) `recomputeStreakDisplay` derives "users who logged in
today" from `sessions.last_seen = CURDATE()`, but `last_seen` is bumped by
`session_model::touch()` on every page load — a user with a long-lived
session that wasn't touched today (idle, app closed but cookie kept) is
silently missed by the daily cron and never gets their login_streaks row
or 7-day/30-day bonus awarded.

Three warnings (non-blocking, but worth tracking) follow. The remainder is
info-level. The fix-by-str-concat item from the prior audit (the
`longest_streak` SELECT) is genuinely fixed — confirmed in user_service.php:309-311
(`$longestStmt` is prepared, not string-interpolated). The audit fix
commit messages match the diff.

**Privacy posture is correct:** `readSimple`/`readCategoryLeaders` and the
JSON cache write never select `student_id` / `email` / `whatsapp` /
`full_name` (only `nickname` + `tier` + `score` + locked metadata). The
`test_read_summary_privacy_excludes_pii_columns` test verifies this
end-to-end including the cache file content.

**Path-traversal posture is correct:** `writeJsonCache` takes `$cacheDir`
from the caller (production = hardcoded `APP_ROOT . '/var/leaderboards'`;
tests = `sys_get_temp_dir()`). `$slug` is whitelisted through `BOARDS`
const. There is no user-controlled path component, so traversal is not
possible.

**Auth on cron endpoint is correct:** `POST /admin/cron/daily` opts in
routes.php are `auth=true, admin=true, csrf=true, rate_limit='admin_cron'`,
and `CronAction::handleDaily` re-checks `requireReAuth(300)` per AD-19.
Defense-in-depth holds.

**Error envelope (AD-16):** `handleDaily` returns the canonical
`{ok, sweeps, errors}` shape. `PointsAdminAction` returns AD-16 envelopes
on both success and validation error paths.

## Critical Issues

### CR-01: `points_log_model::recentForUser` interpolates LIMIT into SQL string

**File:** `004/tickettrade/src/Points/Model/points_log_model.php:194-199`
**Issue:** The query builds `'ORDER BY event_at DESC, id DESC LIMIT ' . $limit`
with string concatenation. `$limit` is mathematically clamped (`max(1,
min(100, $limit))`) so it can never be injection — but this is the
EXACT anti-pattern the audit just fixed in `user_service::recomputeStreakDisplay`
(former lines 319-321). AD-13 + phpcs PSR-12 require prepared statements
with bound parameters. The fix should be a bound parameter, not just a
clamp.
**Impact:** Style/spec violation that the codebase explicitly flagged.
Inconsistent with the very fix shipped in this audit.
**Fix:**

```php
$stmt = $pdo->prepare(
    'SELECT delta, reference_type, event_at, metadata '
    . 'FROM points_log WHERE user_id = ? '
    . 'ORDER BY event_at DESC, id DESC LIMIT :lim'
);
$stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$stmt->execute([$userId]);
```

---

### CR-02: `recomputeStreakDisplay` "logged in today" detection misses users whose session wasn't touched today

**File:** `004/tickettrade/src/User/Service/user_service.php:292-298`
**Issue:** The cron selects `'SELECT DISTINCT s.user_id FROM sessions s
JOIN users u ... WHERE DATE(s.last_seen) = ?'`. But `sessions.last_seen`
is updated by `Support\Auth::boot()` → `auth_service::updateLastSeen()`
→ `session_model::touch()` every time a request lands (5-minute window).
A user who closed their tab yesterday morning and the cookie persisted
in their browser has `last_seen` = yesterday — even though their cookie
session is still valid and `login_streaks` already has yesterday's row.
The daily cron will silently skip them: no `login_streaks` UPSERT for
today, no streak_count bump, no 7/30-day bonus. The same gap fires the
other direction — a user with `last_seen` = today was already counted
but if `session_model::touch()` failed or was not wired in some path,
they're silently mis-detected.

The authoritative source of "user logged in today" should be
`login_streaks` itself (once seeded), or `sessions.created_at`, or
explicit "login" event rows. The current query conflates "session was
touched today" with "user logged in today" — a real correctness gap
that produces silent streak under-counting.
**Impact:** Active users with stale `last_seen` get no daily
`login_streaks` row, no streak counter bump, and never hit the 7/30-day
milestone. Silent UX regression on the gamification surface.
**Fix (one of):**

Option A — query `login_streaks` for the prior day and explicitly
UPSERT today on its presence (chained approach):

```php
// Find users whose login_streaks has a row for *yesterday* or today
// OR who had any session activity today (auth-derived).
$rows = $pdo->prepare(
    'SELECT DISTINCT u.user_id FROM users u '
    . 'WHERE u.is_banned = FALSE '
    . 'AND EXISTS (SELECT 1 FROM login_streaks ls '
    . '  WHERE ls.user_id = u.user_id '
    . '  AND ls.login_date >= DATE_SUB(?, INTERVAL 1 DAY))'
);
$rows->execute([$today]);
```

Option B — keep the sessions query but UNION with a sessions.created_at
predicate so newly-created sessions are always counted even if touch
was missed.

## Warnings

### WR-01: Partial-failure window in `recomputeStreakDisplay` (already tracked as m6)

**File:** `004/tickettrade/src/User/Service/user_service.php:300-353`
**Issue:** The loop UPSERTs `login_streaks` row, then UPDATEs
`users.current_streak/longest_streak`, then potentially calls
`awardStreakBonus()`. If the cron crashes between the UPSERT and the
UPDATE, the user has a today's `login_streaks` row but stale
`current_streak` on `users` — Streak Kings reads from `users` per the
denormalization contract. The audit docstring flagged this as `m6` and
marked it Phase-9 deferred. Confirmed still present. Acceptable for WAD
scope; flagged so the Phase 9 hardening doesn't lose it.
**Impact:** Low. Re-running the cron self-heals on the next day.
**Fix:** Wrap the per-user work in a transaction; (deferred to Phase 9).

---

### WR-02: Buyer-only cap-hit silently drops seller's transaction award

**File:** `004/tickettrade/src/Points/Service/points_service.php:230-254`
**Issue:** The two-party velocity+frozen+pair-cap check loop iterates
buyer first; if buyer hits a cap, the function returns
`{ok:true, data: {skipped: ...}}` and the seller's award is never
attempted. Seller gets no `points_log` row, no points added, no audit
trail. This is "by design" per D-08 (one party caps → transaction ends),
but the seller gets ZERO feedback — no row in their recent activity,
no audit row, just a missing credit. The pair-cap row records the buyer
hit but not the seller (metadata only records `effective_delta_seller`,
no row).
**Impact:** A seller who would have legitimately earned points for a
transaction where the buyer hit a cap gets silently under-credited.
Minor but visible.
**Fix:** Either write a zero-delta "transaction_capped_by_other_party"
row for the seller, or document the policy in the recent-activity
section copy ("cap hit on the other side — no points this txn").

---

### WR-03: `cron_log.processed_count` is a misleading metric

**File:** `004/tickettrade/src/Admin/Action/CronAction.php:248-263`
**Issue:** `processedTotal = array_sum($refreshCounts) + $streakResult['processed'] + count($cacheFiles)`.
`$cacheFiles` is always 4 (the cron writes 4 files unconditionally even
when summary tables are empty), so the counter is always inflated by
4. The `cron_log` table is the audit trail; a misleading `processed_count`
is a quality issue (ops dashboards will see "we processed 4 things" when
nothing actually happened).
**Impact:** Misleading ops telemetry; not user-facing.
**Fix:** Track per-sweep counts individually (the response body already
does — promote the same shape into the cron_log row's metadata_json or
add a structured `breakdown` column).

## Info

### IN-01: `points_service` awardTransaction skips the pair-cap check when transaction type is not in the allowed list

**File:** `004/tickettrade/src/Points/Service/points_service.php:262` + `points_log_model::countPairInDay:159`
**Issue:** The pair-cap is only counted for `reference_type IN ('final_session', 'transaction')`.
If a future caller passes `reference_type='session'` (e.g. for a
non-final intermediate session), the cap doesn't apply. Documented as
intentional; flagged so any future reference_type addition remembers to
extend the allowlist.

### IN-02: `recordLogin` swallows exceptions silently

**File:** `004/tickettrade/src/Auth/Service/auth_service.php:483-497`
**Issue:** The catch-all `catch (\Throwable $e)` then returns silently.
The docstring justifies it ("a failed refresh must NOT abort the login")
and `RecordLoginTest::test_record_login_direct_call_refreshes_column` is
the test. Pattern matches `updateLastSeen` / `endSession`. **Acceptable
as documented**, but worth noting in case the team ever wants
debuggability — currently a DB outage on the last_active_at path is
invisible.

### IN-03: `PointsAdminAction` is unreachable from HTTP in Phase 6

**File:** `004/tickettrade/src/Points/Action/PointsAdminAction.php`
**Issue:** Per the route map (`config/routes.php` has no
`/admin/points/*` routes), `handleVoidPoints()` and `handleClearFreeze()`
are dead code in Phase 6. Per the docstring + D-02, this is intentional
— Phase 8 wires the routes. The class still ships and runs the
`requireReAuth(300)` defense-in-depth check, which is correct. Flagging
as info so the Phase 8 wiring doesn't forget the router opts
(`admin=true`, `csrf=true`).

### IN-04: `Ticket\Service\ticket_service` shows in the diff list but has zero changes

**File:** `004/tickettrade/src/Ticket/Service/ticket_service.php`
**Issue:** The Phase 6 plan claimed "read-only wiring" but the file
shows zero net changes between `56cf433feaad2ea9803661106a2ddd192f3b8fa0^`
and HEAD. Either the wiring was reverted or the plan entry was premature.
Either way, the file as shipped does not import or call
`points_service::awardTransaction` — it relies on the prior Phase 4
wiring which was already correct.

---

_Reviewed: 2026-09-05_
_Reviewer: the agent (gsd-code-reviewer)_
_Depth: standard_