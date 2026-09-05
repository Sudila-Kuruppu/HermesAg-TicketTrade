---
phase: 06-points-ranks-leaderboards
fixed_at: 2026-09-05T00:00:00Z
review_path: 004/tickettrade/.planning/phases/06-points-ranks-leaderboards/06-REVIEW.md
iteration: 1
findings_in_scope: 5
fixed: 5
skipped: 0
status: all_fixed
---

# Phase 6: Code Review Fix Report

**Fixed at:** 2026-09-05
**Source review:** `004/tickettrade/.planning/phases/06-points-ranks-leaderboards/06-REVIEW.md`
**Iteration:** 1

**Summary:**
- Findings in scope: 5 (2 critical + 3 warning)
- Fixed: 5
- Skipped: 0

## Fixed Issues

### CR-01: `points_log_model::recentForUser` interpolated LIMIT into SQL string

**Files modified:** `004/tickettrade/src/Points/Model/points_log_model.php`, `004/tickettrade/tests/Unit/Phase06/Points/RecentForUserTest.php`
**Commit:** `4d48b7c`
**Applied fix:** Replaced `'ORDER BY event_at DESC, id DESC LIMIT ' . $limit` with a bound positional parameter via `bindValue(2, $limit, PDO::PARAM_INT)`. Uses positional placeholders to match the rest of the SELECT (no mixed positional + named — PDO rejects that). Added 5 new tests covering ordering, limit honoring, clamping, empty-history, and the full bound-param-int range.

### CR-02: `recomputeStreakDisplay` "logged in today" detection silently skipped idle users

**Files modified:** `004/tickettrade/src/User/Service/user_service.php`, `004/tickettrade/tests/Integration/Phase06/DailyCronTest.php`
**Commit:** `1592ae2`
**Applied fix:** Widened the `WHERE DATE(s.last_seen) = today` predicate to `s.last_seen >= yesterday` — a 48-hour window that catches the realistic "back-tab" case where a user had a valid cookie but no page load today. The source of truth stays `sessions` (cold-start safe); `login_streaks` is updated AFTER the loop. Added 2 new tests: idle-user with yesterday-only session is counted + has login_streaks row written; >48h session is correctly excluded.

### WR-01: Partial-failure window in `recomputeStreakDisplay`

**Files modified:** `004/tickettrade/src/User/Service/user_service.php`, `004/tickettrade/tests/Integration/Phase06/DailyCronTest.php`
**Commit:** `5a9dc29`
**Applied fix:** Wrapped each user's UPSERT + UPDATE in a transaction inside the foreach. Commit on success, rollBack + `continue` on inner exception. The audit docstring flagged this as `m6` (deferred to Phase 9); the WR-01 review entry moved it forward. `awardStreakBonus()` participates in the outer transaction via `simpleAward`'s `ownsTransaction` pattern, so the bonus INSERT + `users.points` UPDATE commit/rollback atomically with the `login_streaks` row. Per-user error logs identify the offending user_id without aborting the loop. Added 2 new tests: bonus-short-circuit (frozen user) commits the outer work; 3-user happy-path batch atomicity.

### WR-02: Buyer-only cap-hit silently dropped seller's award

**Files modified:** `004/tickettrade/src/Points/Service/points_service.php`, `004/tickettrade/tests/Unit/Phase06/Points/PairCapTest.php`
**Commit:** `66d4863`
**Applied fix:** Replaced the early-return-on-first-cap-hit loop with a per-party result tracking pattern — both buyer and seller are evaluated independently. If BOTH parties cap, return the buyer's cap envelope (preserves the prior single-cap call-site contract). If only ONE party caps, the OTHER party still gets a normal INSERT + `users.points` UPDATE. The capped party's zero-delta cap row + audit are already in place from `applyVelocityAndFreeze`. Response envelope gains `partial_cap_party`, `delta_buyer`/`delta_seller` (0 on capped party), `event_uuid_buyer`/`event_uuid_seller` (null on capped party). Added 3 new tests in PairCapTest: buyer-caps-seller-awarded, seller-caps-buyer-awarded, both-cap-single-envelope.

### WR-03: `cron_log.processed_count` inflated by 4 cache files

**Files modified:** `004/tickettrade/src/Admin/Action/CronAction.php`, `004/tickettrade/tests/Integration/Phase06/DailyCronTest.php`
**Commit:** `778e119`
**Applied fix:** Removed `count($cacheFiles)` from `processed_total`. The cache writes happen unconditionally on every run (4 files), so counting them inflated the metric by 4 even when no rows changed. `processed_count` now reflects actual rows-touched only (leaderboard summary rows + streak users processed). Cache write count is still surfaced in the response envelope (`sweeps.cache_write.files`) for observability. No schema change needed. Updated `test_cron_log_row_with_daily_job_name` to assert the new value (1 streak user → `processed_count = 1`, not 5). Added 2 new tests: empty-DB → `processed_count = 0` (was 4); one-streak-user → `processed_count = 1` (was 5).

## Test Results

| Suite | Before | After | Delta |
|-------|--------|-------|-------|
| `phase-6` (full) | 93 tests / 387 assertions | **107 tests / 451 assertions** | +14 tests / +64 assertions |

**phpcs PSR-12 (`src/`):** 0 errors. 2 pre-existing line-length warnings in `src/Auth/View/register.php` (last touched in Phase 2, unrelated to this fix).

## Per-Fix Commit SHAs

| ID | Severity | File | Commit |
|----|----------|------|--------|
| CR-01 | critical | `src/Points/Model/points_log_model.php` | `4d48b7c` |
| CR-02 | critical | `src/User/Service/user_service.php` | `1592ae2` |
| WR-01 | warning | `src/User/Service/user_service.php` | `5a9dc29` |
| WR-02 | warning | `src/Points/Service/points_service.php` | `66d4863` |
| WR-03 | warning | `src/Admin/Action/CronAction.php` | `778e119` |

---

_Fixed: 2026-09-05_
_Fixer: the agent (gsd-code-fixer)_
_Iteration: 1_