---
phase: 06-points-ranks-leaderboards
plan: 01
subsystem: points + ranks + leaderboards
tags: [points, ranks, migrations, partials, velocity, freeze, AD-10, D-03, D-08, D-15]
requires: []
provides:
  - points-log-indexes
  - last-active-trigger
  - award-listing-approval
  - award-report-validated
  - award-streak-bonus
  - void-points
  - clear-points-freeze
  - tier-progress-partial
  - on-break-pill-partial
  - velocity-flag-pill-partial
  - rank-badge-canonical-tooltip
  - leaderboard-context
affects:
  - points-service
  - user-model
  - contexts-config
  - components-css
  - js-bundle
tech-stack:
  added: []
  patterns:
    - "Pre-write trigger refreshes denormalized column (last_active_at) — eliminates a write path on the gamification side"
    - "Cap-hit rows are inserted with delta=0 and metadata.{cap_hit:true} so the audit trail is preserved (D-08)"
    - "Tier-up toast queued via \$GLOBALS['_tt_toast_queue']; View layer reads on next page load (D-15)"
key-files:
  created:
    - migrations/018_points_log_indexes.sql
    - migrations/019_users_last_active.sql
    - src/Support/View/partials/tier_progress.php
    - src/Support/View/partials/on_break_pill.php
    - src/Support/View/partials/velocity_flag_pill.php
    - tests/Integration/Phase06/Fixtures/Fixtures.php
    - tests/Unit/Phase06/Points/TierFromPointsTest.php
    - tests/Unit/Phase06/Points/AwardListingApprovalTest.php
    - tests/Unit/Phase06/Points/AwardReportValidatedTest.php
    - tests/Unit/Phase06/Points/AwardStreakBonusTest.php
    - tests/Unit/Phase06/Points/VoidAndClearFreezeTest.php
    - tests/Integration/Phase06/MigrationIndexesTest.php
    - tests/Integration/Phase06/MigrationLastActiveTest.php
  modified:
    - src/Points/Model/points_log_model.php
    - src/Points/Service/points_service.php
    - src/User/Model/user_model.php
    - src/Support/View/partials/rank_badge.php
    - public/assets/css/tickettrade.components.css
    - public/assets/js/tickettrade.js
    - config/contexts.php
    - phpunit.xml
key-decisions:
  - decision: "MariaDB 10.0.2+ native `ADD COLUMN IF NOT EXISTS` / `CREATE INDEX IF NOT EXISTS` used in 019 instead of the 014 INFORMATION_SCHEMA + PREPARE/EXECUTE pattern."
    rationale: "The migrate.php runner uses PDO unbuffered mode; PREPARE/EXECUTE with `(SELECT COUNT(*))` subqueries leaves a pending result set and DEALLOCATE PREPARE throws `2014 Cannot execute queries while other unbuffered queries are active`. Native IF NOT EXISTS is portable on MariaDB 10.0.2+ (the project's deployed version is 11.4.5) and avoids the PDO deadlock. The 014 pattern remains intact for legacy coverage."
  - decision: "Single-statement trigger body for trg_points_log_refresh_last_active (no BEGIN/END block)."
    rationale: "migrate.php splits SQL on `;` outside string literals. A multi-statement trigger body would require DELIMITER which the runner does not support. The single-statement form is valid MariaDB syntax and keeps the migration compatible with the `;`-splitting runner."
  - decision: "voidPoints() floored at 0 (never below); E_VOID_INSUFFICIENT_BALANCE only on the zero-balance edge case."
    rationale: "Per the plan's acceptance criteria — when delta > points AND points == 0, return E_VOID_INSUFFICIENT_BALANCE. When delta > points > 0, the void is floored (returns data.voided = current balance, balance_after = 0). This avoids data loss while keeping the audit trail clean."
  - decision: "Tier-up toast queued via \$GLOBALS['_tt_toast_queue'] only on visible transitions (E->D, D->C, C->B, B->A, A->S)."
    rationale: "Per D-15 — same-tier re-renders do not fire. The View layer reads the global on the next page load and renders the existing Phase 1 toast component. Plan 06-03 wires the View to consume the queue."
  - decision: "findForLeaderboard() returns user_id, nickname, points, tier, full_name — program/year columns do not exist on users in this codebase."
    rationale: "The plan suggested program/year for leaderboard rows but the users table only has full_name. The leaderboard Service in Plan 06-03 will adapt — the helper shape is forward-compatible. Defensive read avoids schema-error regressions."
  - decision: "Data providers in tests marked `public static` (PHPUnit 11 requirement)."
    rationale: "PHPUnit 11 deprecated non-static data providers (E_DEPRECATED). Static is the required shape going forward. Triggers 2 PHPUnit deprecation warnings on the test run, no behavioral impact."
coverage:
  - deliverable: "018 adds idx_points_user_event (user_id, event_at DESC) + idx_points_pair (user_id, reference_id, event_at DESC)"
    kind: integration-test
    ref: tests/Integration/Phase06/MigrationIndexesTest.php
    status: pass
  - deliverable: "019 adds users.last_active_at + frozen_at + last_unfrozen_at + idx_users_last_active + trg_points_log_refresh_last_active"
    kind: integration-test
    ref: tests/Integration/Phase06/MigrationLastActiveTest.php
    status: pass
  - deliverable: "Trigger refreshes users.last_active_at on every points_log INSERT"
    kind: integration-test
    ref: tests/Integration/Phase06/MigrationLastActiveTest.php::test_trigger_refreshes_last_active_at_on_points_log_insert
    status: pass
  - deliverable: "tierFromPoints() resolves 12 boundary cases (0/49/50/149/150/399/400/799/800/1499/1500/50000)"
    kind: unit-test
    ref: tests/Unit/Phase06/Points/TierFromPointsTest.php
    status: pass
  - deliverable: "awardListingApproval() +5 happy path, no halving, frozen short-circuit, tier transition"
    kind: unit-test
    ref: tests/Unit/Phase06/Points/AwardListingApprovalTest.php
    status: pass
  - deliverable: "awardReportValidated() +20 happy path, no halving, frozen short-circuit"
    kind: unit-test
    ref: tests/Unit/Phase06/Points/AwardReportValidatedTest.php
    status: pass
  - deliverable: "awardStreakBonus() 7-day +15 + 30-day +50 + E_VALIDATION for invalid + frozen short-circuit"
    kind: unit-test
    ref: tests/Unit/Phase06/Points/AwardStreakBonusTest.php
    status: pass
  - deliverable: "voidPoints() deducts + tier recompute + floored at 0 + E_VOID_INSUFFICIENT_BALANCE + clearPointsFreeze() + audit row"
    kind: unit-test
    ref: tests/Unit/Phase06/Points/VoidAndClearFreezeTest.php
    status: pass
  - deliverable: "rank_badge tooltip derives from config/ranks.php (no ladder duplication)"
    kind: unit-test
    ref: tests/Unit/Phase06/Points/TierFromPointsTest.php::test_tier_from_config_ladder_matches
    status: pass
  - deliverable: "Phase 5 (56 tests, 204 assertions) no regression"
    kind: integration-test
    ref: vendor/bin/phpunit --testsuite=phase-5
    status: pass
requirements-completed:
  - PTS-01
  - PTS-02
  - PTS-03
  - PTS-04
  - PTS-08
  - PTS-10
  - PER-05
duration: ~45 min
status: complete
actuals:
  tokens: 52000
  tasks: 4
  commits: 4
---

# Phase 6 Plan 01: Points Engine + Ranks + Partials Summary

Phase 6 Plan 06-01 ships the **points engine substrate** that Plan 06-02 layers velocity/pair-cap onto, plus the rank-badge and tier-progress partials that Plan 06-03 mounts on Profile and Leaderboards.

## Accomplishments

1. **Migrations 018 + 019** applied cleanly + idempotently:
   - `migrations/018_points_log_indexes.sql` adds `idx_points_user_event (user_id, event_at DESC)` + `idx_points_pair (user_id, reference_id, event_at DESC)` for the velocity and same-pair cap reads in Plan 06-02.
   - `migrations/019_users_last_active.sql` adds `users.last_active_at DATETIME NULL` + `users.frozen_at` + `users.last_unfrozen_at` + `idx_users_last_active` + the `BEFORE INSERT` trigger `trg_points_log_refresh_last_active` that refreshes `last_active_at` on every `points_log` INSERT (D-03).
2. **`Points\Model\points_log_model`** gains `sumForUserInWindow()` (velocity cap reads), `countPairInDay()` (same-pair cap reads), and `recentForUser()` (Profile recent-activity section). All three exclude `metadata.{velocity_cap_hit, pair_cap_hit}` rows per D-08 so the velocity calculation reflects only counted deltas.
3. **`Points\Service\points_service`** gains 5 new writers:
   - `awardListingApproval(int $userId, int $listingId): array` — +5, no halving per D-15 (the multiplier is transaction-only), frozen-gate short-circuit.
   - `awardReportValidated(int $userId, int $reportId): array` — +20, no halving, frozen-gate.
   - `awardStreakBonus(int $userId, int $streakDays): array` — +15 (7-day, `reference_type='streak_7day'`) or +50 (30-day, `reference_type='streak_30day'`), E_VALIDATION for any other value, no halving, frozen-gate.
   - `voidPoints(int $userId, int $delta, string $reason): array` — locks user row, computes `new_balance = max(0, points - delta)`, inserts negative-delta `points_log` row with `metadata.{reason, voided:true, requested_delta:...}`, updates `users.points + tier`, writes `audit_log` row `'points.void'`. `E_VOID_INSUFFICIENT_BALANCE` on the zero-balance edge case. **Phase 8 admin caller; this plan ships the method.**
   - `clearPointsFreeze(int $userId): array` — UPDATE `users.points_frozen=FALSE, frozen_at=NULL, last_unfrozen_at=NOW()`, writes `audit_log` row `'points.unfrozen'`. **Phase 8 admin caller.**
   - Tier-up toast queued in `$GLOBALS['_tt_toast_queue']` on visible transitions (E→D, D→C, C→B, B→A, A→S) for the View layer.
4. **`User\Model\user_model`** gains `updateLastActive(PDO, int $userId): bool` (the auth login path per D-03; the gamification path uses the 019 trigger) and `findForLeaderboard(array $criteria): array` (top-N helper, leaderboard Service reads in Plan 06-03).
5. **4 partials**:
   - `rank_badge.php` — tooltip now derives from `config/ranks.php` and formats per 06-UI-SPEC.md Copywriting Contract: `"{name} ({code}) — {min} to {max} points"` for E..A, `"{name} ({code}) — {max}+ points"` for S. No ladder duplication.
   - `tier_progress.php` (NEW) — 8px rounded-full bar, track `var(--color-surface-container)`, fill `var(--color-rank-{tier})`. Tooltip `"{X} of {Y} toward {next tier name}"` (X = `points - currentMin`, Y = `nextMin - currentMin`); `'Top tier reached'` at S with 100% fill.
   - `on_break_pill.php` (NEW) — renders only when `NOW() - lastActiveAt >= 14 days`, copy `"Inactive 14+ days — next action restores full badge"` per EXPERIENCE.md L153. Wrapping the rank badge in `.on-break` applies the grayscale + opacity filter.
   - `velocity_flag_pill.php` (NEW) — renders only when `$isFrozen === true`, copy `"Earning paused — admin review"` per D-02 (gentler variant of UX-DR-16). Links nowhere in Phase 6 (admin Phase 7/8 wires the resolution).
6. **CSS** (`public/assets/css/tickettrade.components.css`): adds `.tier-progress`, `.tier-progress__fill--{tier}`, `.on-break`, `.on-break-pill`, `.velocity-flag-pill`. Honors `prefers-reduced-motion: reduce` (transitions + `legend-glow` static `box-shadow: 0 0 12px rgba(198,40,40,0.35)` fallback). Uses existing `--color-*` tokens only.
7. **JS** (`public/assets/js/tickettrade.js`): adds `tierProgress` component (~15 LOC) that wires Bootstrap 5 stock tooltip on `data-component="tier-progress"` elements. Guarded against missing `bootstrap` global.
8. **`config/contexts.php`**: adds `'Leaderboard'` bounded context (Plan 06-03 ships the Service).
9. **`phpunit.xml`**: adds `phase-6-unit`, `phase-6-integration`, `phase-6` testsuites.

## Verification Results

| Command | Result |
|---------|--------|
| `php migrate.php` | `Applied: 018_points_log_indexes.sql` then `Applied: 019_users_last_active.sql`. Re-run: `Already up-to-date (0 files to apply)`. |
| `vendor/bin/phpunit --testsuite=phase-6` | `OK` (42 tests, 139 assertions). 2 PHPUnit deprecations (data provider static requirement, non-blocking). |
| `vendor/bin/phpunit --testsuite=phase-5` | `OK (56 tests, 204 assertions)` — no regression. |
| `vendor/bin/phpcs --standard=PSR12 src/Points/Service/points_service.php src/Points/Model/points_log_model.php src/User/Model/user_model.php` | 3 errors (all pre-existing project-wide convention: `points_service`, `points_log_model`, `user_model` are snake_case per AGENTS.md). 0 NEW errors. |
| `vendor/bin/phpcs --standard=PSR12 src/Support/View/partials/rank_badge.php src/Support/View/partials/tier_progress.php src/Support/View/partials/on_break_pill.php src/Support/View/partials/velocity_flag_pill.php config/contexts.php` | 0 errors (line-length warnings in `rank_badge.php` are pre-existing Phase 2 markup templates). |

## Files Changed

4 commits, 14 new files + 8 modified files:

```
4414e9c feat(06-01): phase 6 migrations 018 (points_log indexes) + 019 (users.last_active_at + trigger)
ff266e8 feat(06-01): points_log helpers + 5 new points_service writers + user_model.updateLastActive
5064444 feat(06-01): rank_badge canonical tooltip + 3 new partials + CSS + JS + Leaderboard context
fef522b test(06-01): phase 6 tier + writers + migration tests (42 tests, 139 assertions)
```

All paths inside `/004/tickettrade/` (no parent-repo contamination).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocker] Migration 019 PREPARE/EXECUTE pattern incompatible with PDO unbuffered mode.**
- **Found during:** Task 1 verification — `php migrate.php` failed with `2014 Cannot execute queries while other unbuffered queries are active`.
- **Issue:** The plan's acceptance criteria mirror the Phase 4 `014_users_redemption_count.sql` pattern: `SET @col = (SELECT COUNT(*))` + `SET @sql = IF(@col = 0, ...)` + `PREPARE stmt` + `EXECUTE stmt` + `DEALLOCATE PREPARE stmt`. The `(SELECT COUNT(*))` subquery leaves a pending result set in PDO's unbuffered mode, and the subsequent `DEALLOCATE PREPARE` throws on the active result. The 014 migration also fails this way when run manually against a fresh PDO, but it landed in `.applied` during the original `bin/dev-setup.sh` run on the dev DB before the test path was exercised. The 019 migration would have hit the same issue.
- **Fix:** Replaced the INFORMATION_SCHEMA + PREPARE pattern with native `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` / `CREATE INDEX IF NOT EXISTS` (MariaDB 10.0.2+, project is on 11.4.5). Native IF NOT EXISTS is a single DDL statement with no result set. Idempotency preserved.
- **Files modified:** `migrations/019_users_last_active.sql` (rewritten to use native IF NOT EXISTS)
- **Verified:** `php migrate.php` succeeds on a fresh DB. Re-run is `Already up-to-date (0 files to apply)`. The 014 pattern remains intact for legacy coverage.

**2. [Rule 3 - Blocker] Single-statement trigger body required for `;`-splitting runner.**
- **Found during:** Task 1 verification — `CREATE TRIGGER ... BEGIN UPDATE...; END;` would split into malformed statements.
- **Issue:** The `migrate.php` runner splits SQL on `;` outside string literals. A multi-statement trigger body would require `DELIMITER // ... //` which the runner does not support.
- **Fix:** Wrote the trigger as a single statement: `CREATE TRIGGER ... FOR EACH ROW UPDATE users SET last_active_at = NOW() WHERE user_id = NEW.user_id;` (no `BEGIN`/`END` block). This is valid MariaDB syntax for a single-statement trigger body.
- **Files modified:** `migrations/019_users_last_active.sql`
- **Verified:** Trigger created, `SHOW TRIGGERS` shows the trigger, `INSERT INTO points_log` refreshes `users.last_active_at` (verified by `MigrationLastActiveTest::test_trigger_refreshes_last_active_at_on_points_log_insert`).

**3. [Rule 2 - Missing] Added `users.frozen_at` + `users.last_unfrozen_at` columns to migration 019.**
- **Found during:** Task 2 implementation — `clearPointsFreeze()` writes `frozen_at = NULL, last_unfrozen_at = NOW()` per the plan's contract, but the columns did not exist in any migration.
- **Issue:** The plan's `clearPointsFreeze` spec referenced `frozen_at` and `last_unfrozen_at` columns. Migration 002 only defines `points_frozen` (BOOLEAN). The plan's `acceptance_criteria` section names the columns explicitly.
- **Fix:** Added two more `ALTER TABLE users ADD COLUMN IF NOT EXISTS frozen_at DATETIME NULL AFTER last_active_at;` / `last_unfrozen_at` lines to migration 019. Re-applied the migration on the dev DB (removed 019 from `.applied`, re-ran `php migrate.php`).
- **Files modified:** `migrations/019_users_last_active.sql`
- **Verified:** `DESCRIBE users` shows all three new columns. `MigrationLastActiveTest::test_users_last_active_at_column_exists` asserts all three exist.

**4. [Rule 1 - Bug] `findForLeaderboard()` referenced non-existent `users.program` and `users.year` columns.**
- **Found during:** Task 2 implementation — the plan's PHPDoc showed `program:?string, year:?string` return fields.
- **Issue:** The `users` table in this codebase does NOT have `program` or `year` columns. The current schema has `full_name` only. The leaderboard Service in Plan 06-03 will need to add these columns or derive the display from `full_name` + nickname.
- **Fix:** Replaced `program` + `year` with `full_name` in the SELECT and return shape. The helper is forward-compatible — Plan 06-03 can extend the return shape when the leaderboard columns land.
- **Files modified:** `src/User/Model/user_model.php`
- **Verified:** The helper runs without SQL errors. The return shape is consistent with the actual `users` schema.

**5. [Rule 1 - Bug] Tier test seed value mismatch — `seedUser(['points' => 100, 'tier' => 'E'])` is actually D.**
- **Found during:** Task 4 verification — `VoidAndClearFreezeTest::test_void_deducts_and_writes_negative_delta_row` expected tier `'E'` after the void, got `'D'`.
- **Issue:** 100 points crosses the E->D boundary at 50 points. The test seeded `tier='E'` but the actual tier for 100 points is `'D'`. The test was a seed-data mismatch.
- **Fix:** Updated the seed to `tier='D'` to match the actual 100-point tier. Same fix for `test_void_drops_tier_when_balance_below_threshold` (200 points = C, not D as the seed claimed).
- **Files modified:** `tests/Unit/Phase06/Points/VoidAndClearFreezeTest.php`
- **Verified:** Test passes. The fixture seeds match the canonical `tierFromPoints()` resolution.

**6. [Rule 3 - Blocker] PHPUnit 11 requires `public static` data providers.**
- **Found during:** Task 4 verification — `Tests: 27, Errors: 2` with "Data Provider method ... is not static".
- **Issue:** PHPUnit 11 deprecates non-static data providers. The Phase 5 tests use `@dataProvider` annotations with non-static methods (Phase 5 ran on PHPUnit 10 or earlier).
- **Fix:** Changed `public function` to `public static function` for `boundaryProvider` and `invalidStreakDaysProvider`. Test count went from 27 to 42 (data provider expanded).
- **Files modified:** `tests/Unit/Phase06/Points/TierFromPointsTest.php`, `tests/Unit/Phase06/Points/AwardStreakBonusTest.php`
- **Verified:** All 42 tests pass with 2 deprecation notices (the `@dataProvider` annotation itself is deprecated in favor of `#[DataProvider]` attribute — kept for consistency with Phase 5's style).

### Pre-existing PSR-12 warnings (not fixed)

- `Class name "points_service" / "points_log_model" / "user_model" is not in PascalCase format`: project-wide convention per AGENTS.md (snake_case classes, file-per-class). Out of scope — same as Phase 5.
- `Header blocks must be separated by a single blank line` in `config/contexts.php` (and other files): pre-existing across the codebase. Not introduced by this plan.
- Line-length warnings in `rank_badge.php` SVG markup lines: pre-existing Phase 2 markup templates. Not introduced by this plan.

### Pre-existing test issues (out of scope)

- `phase-3-unit` and `phase-4-unit` have pre-existing failures on `Textbooks` category duplicate (the `ensureCategories()` helper inserts 7 seed categories, but the truncate-or-IGNORE pattern leaves stale rows in some interleavings). These failures exist without my changes and are documented as pre-existing by Phase 4 and Phase 5.
- `phase-2` and `phase-3-integration` test suites time out (likely many tests with long runtime). Pre-existing — not introduced by this plan.
- The full `vendor/bin/phpunit` (no suite filter) takes >5 minutes due to the slow phase-2 / phase-3-integration runs. Running specific suites (`phase-5` + `phase-6`) is the documented fast path.

## Test Coverage Summary

| Suite | Tests | Assertions |
|-------|-------|------------|
| `tests/Unit/Phase06/Points/TierFromPointsTest.php` | 14 | 14 (12 boundaries + negative + ladder agreement) |
| `tests/Unit/Phase06/Points/AwardListingApprovalTest.php` | 4 | 24 (happy, no-halving, frozen, tier-transition) |
| `tests/Unit/Phase06/Points/AwardReportValidatedTest.php` | 3 | 18 (happy, no-halving, frozen) |
| `tests/Unit/Phase06/Points/AwardStreakBonusTest.php` | 7 | 28 (7-day, 30-day, 3 invalid days, frozen, no-halving) |
| `tests/Unit/Phase06/Points/VoidAndClearFreezeTest.php` | 6 | 30 (deduct, floored, insufficient, cross-tier, clear-freeze, unknown-user) |
| `tests/Integration/Phase06/MigrationIndexesTest.php` | 3 | 9 (idx_user_event, idx_pair, idempotency) |
| `tests/Integration/Phase06/MigrationLastActiveTest.php` | 5 | 16 (columns, index, trigger, trigger-refreshes, idempotency) |

**Total Phase 6: 42 tests, 139 assertions, all pass.**

## Known Stubs

- `voidPoints()` and `clearPointsFreeze()` are Service-public methods (per AD-1) but no HTTP Action exposes them in Phase 6. Phase 8 admin Actions call them. Per T-06-06 threat register, the methods are surface-reserved in this plan.
- The `_tt_toast_queue` global is written by `points_service` but the View layer that reads it lands in Plan 06-03. Queue entries persist for the request lifetime only.

## Threat Flags

| Flag | File | Description |
|------|------|-------------|
| `threat_flag:points_frozen_short_circuit` | `src/Points/Service/points_service.php` | All 3 new writers (`awardListingApproval`, `awardReportValidated`, `awardStreakBonus`) short-circuit on `users.points_frozen=TRUE` per T-06-01. Existing writers (`awardTransaction`, `awardReviewPoints`, `awardVerificationBonus`) already had this behavior — preserved. |
| `threat_flag:trigger_owner_of_last_active_at` | `migrations/019_users_last_active.sql` | The `BEFORE INSERT` trigger is the SOLE writer of `users.last_active_at` on the gamification path. Application code (`points_service`) does NOT update the column — defense-in-depth per D-03. The login path uses `user_model::updateLastActive()` which writes the column directly (separate from the gamification path). |
| `threat_flag:voidPoints_audit_trail` | `src/Points/Service/points_service.php` | `voidPoints()` and `clearPointsFreeze()` write `audit_log` rows with `actor_user_id=null` (Phase 6 stub per T-06-03). Phase 8 wraps the audit hash chain and passes the admin's user_id. |
| `threat_flag:cap_enforcement_deferred` | `src/Points/Service/points_service.php` | `awardTransaction` and `awardReviewPoints` still have `// TODO: Phase 6 Plan 06-02` comments for velocity + pair-cap enforcement. The cap helpers (`points_log_model::sumForUserInWindow`, `countPairInDay`) ship in this plan; the enforcement call sites land in Plan 06-02. |

## Next Steps

- **Plan 06-02**: layer velocity (300 pts/day, 150 pts/hour) and same-pair cap (2 transactions/day/buyer-seller) enforcement into `awardTransaction()` and `awardReviewPoints()` using the `sumForUserInWindow` + `countPairInDay` helpers from this plan. Also land `auth_service::recordLogin()` calling `user_model::updateLastActive()`.
- **Plan 06-03**: leaderboard Service + Model + Action + View. Reads `points_service::voidPoints` / `clearPointsFreeze` only via the daily cron. Mounts the 4 partials (rank_badge, tier_progress, on_break_pill, velocity_flag_pill) on the Profile and `/leaderboards` pages.
- **Phase 8**: admin console. Wires `voidPoints` + `clearPointsFreeze` to the admin UI with the real `actor_user_id` for the hash chain.
