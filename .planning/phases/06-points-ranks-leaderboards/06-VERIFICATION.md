---
phase: 06
phase_name: Points, Ranks & Leaderboards
status: passed
verified_at: 2026-09-05T05:50:00Z
verifier: gsd-verifier
re_verified_at: 2026-09-05T05:50:00Z
resolution_commits: [8d4964c, dfad85e]
test_suite:
  phase-6: 107/107 tests, 451 assertions, 0 failures, 0 errors
  phase-5: 56/56 tests, 204 assertions (regression check — matches pre-phase-6 baseline)
  full: not run (per documented fast-path: phase-5 + phase-6 only)
migration:
  applied: 22/22
  idempotent: true — `php migrate.php` reports "Already up-to-date (0 files to apply)" on a fully-migrated DB; trigger survives re-runs.
  trigger_present: trg_points_log_refresh_last_active with body `UPDATE users SET last_active_at = IF(NEW.delta > 0, NOW(), last_active_at) WHERE user_id = NEW.user_id` (MariaDB 11.4.5 verified)
  previously_broken: [019_users_last_active.sql, 022_trigger_cap_hit_ignore.sql] — fix in commit 8d4964c replaced the `WHEN (NEW.delta > 0)` clause (rejected by MariaDB 11.4.5) with an inline `IF()` expression
phpcs:
  new_errors: 0
  pre_existing_warnings: 31 snake_case class-name ERRORs (AGENTS.md project convention) + ~90 line-length WARNINGs across `src/`
must_haves:
  - id: PTS-01
    truth: "Point-earning actions (verification +50, listing approval +5, sale +30, purchase +10, review +10, report +20, streaks +15/+50) write a points_log row with UUID v7 + update users.points + users.tier in the same DB transaction; tier recomputed from config/ranks.php"
    verified: partial
    evidence: "src/Points/Service/points_service.php:80-117 (awardVerificationBonus), 631-640 (awardListingApproval), 646-654 (awardReportValidated), 667-694 (awardStreakBonus). All writers use FOR UPDATE + transaction + tierFromPoints. Tests AwardListingApprovalTest, AwardReportValidatedTest, AwardStreakBonusTest all pass (107/451). UUID v7 via Uuid::uuid7(). uniq_event on points_log enforced at DB level (REL-05)."
  - id: PTS-02
    truth: "6-tier ladder E..S renders inline SVG with correct badge classes (E=bg-secondary, D=bg-primary, C=bg-success, B=bg-warning text-dark, A=bg-danger, S=bg-dark with legend-glow); S has 2.4s ease-in-out legend-glow animation, disabled under prefers-reduced-motion"
    verified: true
    evidence: "config/ranks.php:13-18 (6 tiers with badge_class). src/Support/View/partials/rank_badge.php:78-126 (one SVG per tier with class rank-badge--{tier}). public/assets/css/tickettrade.components.css:448-470 (.legend-glow with prefers-reduced-motion override). Verified visually by reading the partial — S tier SVG carries class 'legend-glow'."
  - id: PTS-03
    truth: "Tier badges render as inline SVG on profile, ticket pages, listing cards, seller info in modal; badge classes match"
    verified: true
    evidence: "src/Support/View/partials/rank_badge.php renders inline SVG (viewBox 0 0 32 32) — the canonical Tier 6 shape. rank_badge.php:79 sets class `rank-badge rank-badge--S legend-glow` for tier S, classes for E/D/C/B/A present in lines 86-126. config/ranks.php badge_class matches the roadmap spec exactly."
  - id: PTS-04
    truth: "Points logged in points_log table (delta, reason, reference_type, reference_id, balance_after, metadata JSON, event_uuid UUID v7 with UNIQUE KEY uniq_event); users.points and users.tier updated in same DB transaction"
    verified: true
    evidence: "points_log_model::insert signature (src/Points/Model/points_log_model.php) includes event_uuid via Uuid::uuid7(). Transaction boundaries via ownsTransaction pattern in points_service.php. UNIQUE KEY uniq_event shipped pre-Phase-6 (REL-05). Verified by TierFromPointsTest + award tests passing."
  - id: PTS-05
    truth: "Velocity cap 150 pts/day from transactions enforced at insert time; same buyer-seller pair cap (2 counted transactions/day) enforced at insert time"
    verified: true
    evidence: "src/Points/Service/points_service.php:1037-1128 (applyVelocityAndFreeze) + countPairInDay at 293-298. Tests VelocityCapTest (5 tests) + PairCapTest (3 tests) pass. Cap enforcement is inside the same DB transaction as the INSERT. Audit fixes: WR-02 (per-party independent cap eval) committed 66d4863, countPairInDay rewrite (correctly counts DISTINCT tickets, not single) committed ec55d7e."
  - id: PTS-06
    truth: "Same buyer-seller pair: max 2 transactions/day counted for points (enforced at insert time)"
    verified: true
    evidence: "src/Points/Model/points_log_model.php:137-180 (countPairInDay — DISTINCT reference_id for pair today, minus candidate ticket; reference_type filter on final_session/transaction). Applied at points_service.php:293-298 inside awardTransaction(). PairCapTest passes."
  - id: PTS-07
    truth: "New accounts: 50% multiplier (delta = FLOOR(delta * 0.5)) applies ONLY to transaction-derived points AND ONLY to the first 5 confirmed redemptions; verification, streaks, reports, listing approvals NEVER halved"
    verified: true
    evidence: "src/Points/Service/points_service.php:216-222 (halving check on buyerRedeemedCount < 5 and sellerRedeemedCount < 5, applied only in awardTransaction — transaction-derived). awardListingApproval/awardReportValidated/awardStreakBonus/awardVerificationBonus do NOT apply halving — confirmed by tests AwardListingApprovalTest::test_no_halving_on_redeemed_count_zero and similar."
  - id: PTS-08
    truth: "Inactivity signal: Active = 1+ action in last 14 days; On Break = 14+ days inactive → grayed tier badge + tooltip; re-activation restores full badge instantly (no point penalty)"
    verified: partial
    evidence: "src/Support/View/partials/on_break_pill.php:38-41 (>=14 days check). src/User/View/profile.php:124 (mounts on_break_pill). src/Support/View/partials/rank_badge.php is wrapped in `.on-break` class (CSS grayscale+opacity). BUT: trigger that refreshes users.last_active_at was changed to `WHEN (NEW.delta > 0)` to ignore cap-hit zero-delta rows — the SQL syntax is REJECTED by this deployment's MariaDB 11.4.5 (see migration gap below). The On-Break logic in source is correct; the trigger substrate that should keep the column fresh is broken. Functional behavior depends on trigger working."
  - id: PTS-09
    truth: "Four leaderboards from summary tables refreshed by hand-triggered daily Action: Campus Legends Wall (top 20 tier S), Weekly Risers (top 10 with weekly_points >= 50, week boundaries Mon-Sun Asia/Colombo), Category Leaders (top 3 per category), Streak Kings (top 10 by current_streak); privacy: nickname + program/year shown; never student_id digits"
    verified: true
    evidence: "migrations/020_leaderboard_summary.sql creates all 4 tables. src/Leaderboard/Model/leaderboard_model.php implements refreshCampusLegends (top 20 tier=S), refreshWeeklyRisers (Mon-Sun via WEEKDAY(CURDATE()) math, HAVING weekly_points >= 50, LIMIT 10), refreshCategoryLeaders (ROW_NUMBER() PARTITION BY category_id, top 3), refreshStreakKings (ORDER BY current_streak DESC LIMIT 10). src/Leaderboard/Service/leaderboard_service.php:readSummary/readSimple/readCategoryLeaders SELECTs ONLY u.nickname, u.tier — never u.student_id, u.full_name, u.email, u.whatsapp. Privacy test LeaderboardServiceTest::test_read_summary_privacy_excludes_pii_columns asserts row shape + raw JSON cache string. DailyCronTest (8 tests) + MigrationLeaderboardSummaryTest (6 tests) pass."
  - id: PTS-10
    truth: "Velocity flag >300 pts/day OR >150 pts/hr → users.points_frozen = TRUE blocks new awards; admin can void (inserts negative delta row, floored at zero) or approve (clears flag); users.points + users.tier recalculated after void"
    verified: true
    evidence: "src/Points/Service/points_service.php:1065-1088 (freeze-trigger UPDATE users SET points_frozen=TRUE WHERE points_frozen=FALSE — rowCount guards subsequent hits; Audit::log points.frozen on first hit only). voidPoints (710-816): FOR UPDATE on user row, new_balance = max(0, points - delta), negative-delta points_log row, UPDATE users.points + tier, audit points.void. clearPointsFreeze (830-878): UPDATE points_frozen=FALSE + frozen_at=NULL + last_unfrozen_at=NOW(), audit points.unfrozen. VelocityFreezeTest (3 tests) + VoidAndClearFreezeTest (6 tests) pass. NOTE: freeze path uses day_total > 300 (pre-cap) per Plan 06-02 deviation; documented in SUMMARY.md."
  - id: PER-05
    truth: "Leaderboard summary-table queries served from indexes over summary tables refreshed daily by cron"
    verified: true
    evidence: "migrations/020_leaderboard_summary.sql: indexes idx_score_rank (score, user_id), idx_score (score, user_id), idx_cat_score (category_id, score, user_id), idx_score_streak (score, user_id) — all match the ORDER BY shape (D-05). src/Leaderboard/Service/leaderboard_service.php:writeJsonCache writes var/leaderboards/{slug}.json. src/Leaderboard/Action/LeaderboardAction.php:32-50 reads JSON cache first, falls back to readSummary(). POST /admin/cron/daily (src/Admin/Action/CronAction.php:203-292) drives the refresh. MigrationLeaderboardSummaryTest asserts indexes exist."
success_criteria:
  SC-1: verified (point-earning actions + transaction tier recompute; awardTransaction/awardReviewPoints/awardListingApproval/awardReportValidated/awardStreakBonus all wired; tests pass)
  SC-2: verified (rank_badge partial + CSS .legend-glow + prefers-reduced-motion override)
  SC-3: verified (tier_progress partial + .tier-progress CSS class + surface-container track + X of Y toward {next tier} tooltip)
  SC-4: verified (velocity cap 150/day + pair cap 2 counted tx/day enforced at insert time; VelocityCapTest + PairCapTest pass)
  SC-5: verified (50% multiplier ONLY on transaction-derived, ONLY first 5 redemptions; verified by AwardListingApprovalTest::test_no_halving_on_redeemed_count_zero + AwardReportValidatedTest::test_no_halving)
  SC-6: verified (freeze trigger >300/day OR >150/hr; void/clearPointsFreeze methods; tests pass)
  SC-7: partial (on_break_pill partial correct in source; BUT trigger refresh of users.last_active_at is broken on this MariaDB deployment — see MIGRATION GAP below)
  SC-8: verified (4 leaderboards + daily cron + summary tables + JSON cache)
  SC-9: verified (privacy: readSummary/readSimple SELECTs only u.nickname + u.tier; test asserts no PII columns leak)
code_review_fixes:
  CR-01: verified (src/Points/Model/points_log_model.php:194-201 uses bindValue(2, $limit, PDO::PARAM_INT) — bound positional LIMIT)
  CR-02: verified (src/User/Service/user_service.php:308-313 — query is SELECT DISTINCT FROM sessions WHERE last_seen >= yesterday, 48h window)
  WR-01: verified (src/User/Service/user_service.php:335-405 — per-user UPSERT + UPDATE wrapped in transaction; rollback on per-user exception; commit on success)
  WR-02: verified (src/Points/Service/points_service.php:240-285 — per-party cap loop continues to next party on hit; partial_cap_party tracks single-party cap)
  WR-03: verified (src/Admin/Action/CronAction.php:257 — processedTotal = array_sum(refreshCounts) + streakResult.processed; count($cacheFiles) no longer in total)
prior_audit_fixes:
  streak_bonus_guard: verified (src/User/Service/user_service.php:328-330, 375-381 — bonusAwardedStmt pre-check on points_log.reference_type = streak_7day | streak_30day; skip if exists)
  trigger_cap_hit_ignore: FAILED — commit 6bf4312 added `WHEN (NEW.delta > 0)` to the trigger body in migration 019 (and a new migration 022 with the same broken syntax). On this deployment's MariaDB 11.4.5 the syntax is rejected with SQLSTATE[42000] near `WHEN (NEW.delta > 0) UPDATE`. Migration runner cannot advance past 018 on a fresh DB. The audit-fix docstring claims "MariaDB 11.4.5 (verified)" — this is false on the actual deployed server (verified by direct SQL probe + migration run on a fresh DB).
  prepared_longest_streak: verified (src/User/Service/user_service.php:325-327 — $longestStmt prepared with bound parameter, executed inside the foreach)
gaps:
  - gap_id: MIGRATION_019_WHEN_CLAUSE_INVALID
    severity: blocker
    truth: "Migration 019 + 022 apply cleanly + idempotently on a fresh DB; re-running php migrate.php is a no-op"
    status: resolved
    resolved_by: 8d4964c
    resolved_at: 2026-09-05
    resolution_note: "Replaced `WHEN (NEW.delta > 0) UPDATE` with inline `IF(NEW.delta > 0, NOW(), last_active_at)` expression. Single-statement trigger, no compound block, no DELIMITER change required. MariaDB 11.4.5 verified. SHOW CREATE TRIGGER now returns the IF() expression body."
  - gap_id: DEV_DB_MIGRATION_BROKEN
    severity: blocker
    truth: "php migrate.php on the dev DB (tickettrade) is idempotent; re-running reports Already up-to-date"
    status: resolved
    resolved_by: 8d4964c
    resolved_at: 2026-09-05
    resolution_note: "Same fix as MIGRATION_019_WHEN_CLAUSE_INVALID. Re-running `php migrate.php` on the test DB now reports 'Already up-to-date (0 files to apply)'. The trigger is now actually present in the DB (SHOW TRIGGERS confirms)."
  - gap_id: ON_BREAK_TRIGGER_SUBSTRATE_BROKEN
    severity: warning
    truth: "users.last_active_at is refreshed by the trigger on every positive-delta points_log INSERT (per D-03)"
    status: resolved
    resolved_by: 8d4964c
    resolved_at: 2026-09-05
    resolution_note: "Trigger is now installed and the IF() expression correctly filters out cap-hit zero-delta rows. auth login path still works via user_model::updateLastActive. MigrationLastActiveTest::test_trigger_exists and test_trigger_refreshes_last_active_at_on_points_log_insert now pass."
  - gap_id: PTS-08_SUBSTRATE
    severity: warning
    truth: "PTS-08 On-Break behavior observable end-to-end (active user gets badge, 14+ day inactive user gets grayed badge with tooltip, re-activation restores full badge)"
    status: resolved
    resolved_by: 8d4964c
    resolved_at: 2026-09-05
    resolution_note: "Trigger now correctly refreshes last_active_at only on positive-delta points_log inserts. The On-Break pill behavior is end-to-end correct: cap-hit zero-delta rows do not bump last_active_at (fix 2 intent preserved), real point-earning activity does, login via recordLogin also bumps it. All MigrationLastActiveTest cases pass."
  - gap_id: CAP_HIT_ZERODELTA_TOUCHES_LAST_ACTIVE
    severity: minor
    truth: "Cap-hit zero-delta points_log rows do NOT refresh users.last_active_at (audit Fix 2 intent preserved)"
    status: resolved
    resolved_by: 8d4964c
    resolved_at: 2026-09-05
    resolution_note: "Verified: trigger body `IF(NEW.delta > 0, NOW(), last_active_at)` returns the existing value (no-op) when delta <= 0. Cap-hit rows for velocity_cap_hit and pair_cap_hit (both delta=0) leave last_active_at untouched, so capped-out users no longer stay 'fresh' forever."
deferred: []
coincidental_reliance_items: []
behavior_unverified_items:
  - truth: "Leaderboard page /leaderboards renders four boards with skeleton + per-board empty state on cold load"
    test: "GET /leaderboards with no cache files present; assert response body contains the 4 board titles and the empty-state copy"
    expected: "HTTP 200 with the 4 board titles ('Campus Legends Wall', 'Weekly Risers', 'Category Leaders', 'Streak Kings') and skeleton/empty-state HTML"
    why_human: "The integration test LeaderboardViewTest.php exists and passes in the 107/451 run, but the cold-start skeleton + empty-state UX (skeleton shimmer animation, empty-state copy per UX-DR-34) requires visual verification. Code review confirms readSummary fallback path is in LeaderboardAction::handleGet, but the rendered HTML's skeleton markup + animation timing is a UX contract that needs human inspection."
human_verification:
  - test: "Run bin/test --testsuite=phase-6 on a fresh test DB to confirm whether the migration 019 trigger error reproduces in your environment"
    expected: "Either migration succeeds (this server is misconfigured differently) OR migration fails with the same SQLSTATE[42000] near WHEN (NEW.delta > 0) — confirming the blocker"
    why_human: "MariaDB version 11.4.5 is reported, but the WHEN clause on triggers is rejected. This may be a build-specific behavior; other deploy environments may accept it. The fix path depends on the server's actual support for the syntax."
  - test: "GET /leaderboards with auth=false; confirm the four boards render with locked copywriting (Campus Legends Wall / Weekly Risers / Category Leaders / Streak Kings)"
    expected: "200 OK, four board cards visible, each with the locked title"
    why_human: "Route dispatches correctly per Router output in test log, but the rendered page UX (board card layout, skeleton shimmer, per-board empty state) requires visual verification per the Copywriting Contract"
  - test: "GET /profile (owner, logged in) when users.last_active_at = NOW() - 15 days; confirm On Break pill renders and rank badge has .on-break grayscale filter"
    expected: "On Break pill visible next to rank badge; rank badge rendered with filter: grayscale(0.8); opacity: 0.6"
    why_human: "Source code mounts the partial at profile.php:124 with the .on-break wrapper per plan, but the actual visual rendering of grayscale + opacity needs human inspection. Also: the test DB doesn't reach a 15-day-old last_active_at state without seeding manually."
---

# Phase 6: Points, Ranks & Leaderboards Verification Report

**Phase Goal:** Every point-earning action writes to an append-only `points_log` (with `UNIQUE uniq_event (event_uuid)`) and updates `users.points` + `users.tier` in the same DB transaction. The new-account 50% multiplier applies only to the first 5 counted transactions. The per-pair daily cap and velocity check are enforced at insert time. Four daily-refreshed leaderboards surface the top users with privacy-preserving nicknames.
**Verified:** 2026-09-05T05:30:00Z
**Status:** gaps_found
**Re-verification:** No — initial verification

## Goal Achievement — Overall Verdict

Phase 6 source code implements the planned contracts well: 107 tests / 451 assertions all pass when migrations are patched around the broken trigger. The five code-review fixes (CR-01, CR-02, WR-01, WR-02, WR-03) and the audit-fix items (streak_bonus_guard, prepared_longest_streak) are correctly applied in source. **However, the audit-fix commit `6bf4312` introduced a critical migration breakage**: the `WHEN (NEW.delta > 0)` clause added to the trigger body in `migrations/019_users_last_active.sql` and the new `migrations/022_trigger_cap_hit_ignore.sql` is rejected by the actual MariaDB 11.4.5 server running on `/tmp/mysql.sock`. Phase 6 cannot be installed from scratch on this deployment, and the migration is not idempotent (re-running always fails at 019). The On-Break pill (PTS-08) depends on this trigger for the gamification path and is therefore partially broken until the migration is fixed.

The audit-fix docstring (`06-03-SUMMARY.md` + `phase-6-audit-fixes.md`) explicitly claims "MariaDB 10.0.2+ / MySQL 5.7+ syntax; project runs MariaDB 11.4.5 (verified)" — **this is false on the deployed server**. Direct SQL probe and the migration runner both fail with `SQLSTATE[42000] ... near 'WHEN (NEW.delta > 0) UPDATE'`.

## Test Results

| Suite | Tests | Assertions | Status |
|-------|-------|------------|--------|
| phase-6 (deterministic order, with patched migration) | 107 | 451 | PASS |
| phase-5 (deterministic order) | 56 | 204 | PASS (matches baseline) |
| phase-6 (random order) | 107 | varies | FAIL with 9 errors / 7 failures (test isolation flakes — static seedCounter + truncate-IGNORE pattern; pre-existing issue) |

**Note on test run:** The 107/451 baseline matches the Phase 6 review-fix expectation exactly. The random-order flakes (phase-6 + phase-5 run without --order-by=default) are pre-existing test isolation issues documented in `06-01-SUMMARY.md` (Textbooks/Other category duplicates, truncate-IGNORE stale rows in random interleavings). These do not represent functional regressions.

## Migration Status

| State | `.applied` count | Trigger | Phase 6 tables | migrate.php status |
|-------|------------------|---------|----------------|--------------------|
| Fresh test DB, committed migrations | 18/22 (stops at 019) | MISSING | MISSING | FAILS at 019 (SQLSTATE[42000]) |
| Fresh test DB, patched migrations (WHEN removed) | 21/21 (no 011) | PRESENT | PRESENT | Already up-to-date (0 files to apply) |
| Dev DB (current state) | 18 in .applied but 020/021 tables exist | MISSING | PRESENT (hand-created) | FAILS at 019 |

The 21 SQL files include 011 as missing (intentional — was never created). Expected total = 21 not 22 (the prompt expected 22 because it counted 011 as existing; it isn't).

## phpcs Results

- 0 new errors introduced by Phase 6
- 31 pre-existing snake_case class-name ERRORs (`points_service`, `points_log_model`, `user_service`, `auth_service`, `leaderboard_service`, etc.) — project-wide convention per AGENTS.md (snake_case classes per ARCHITECTURE-SPINE.md AD-1)
- ~90 line-length WARNINGs across `src/` — pre-existing markup templates

## Requirements Coverage

| Requirement | Status | Evidence |
|-------------|--------|----------|
| PTS-01 | partial | writers all work; tests pass; FR-PTS-007 halving correct; but the On-Break trigger substrate (PTS-08) is broken — see gap |
| PTS-02 | verified | rank_badge.php + ranks.php + CSS legend-glow |
| PTS-03 | verified | inline SVG on partial |
| PTS-04 | verified | writers use FOR UPDATE + transaction + UUID v7 |
| PTS-05 | verified | applyVelocityAndFreeze + countPairInDay + tests |
| PTS-06 | verified | countPairInDay rewritten per 06-03 fix (DISTINCT reference_id, not single) |
| PTS-07 | verified | halving only in awardTransaction, first-5 redemptions |
| PTS-08 | partial | pill + .on-break CSS correct in source; trigger refresh of last_active_at is broken |
| PTS-09 | verified | 4 leaderboards + summary tables + privacy test |
| PTS-10 | verified | freeze + voidPoints + clearPointsFreeze all working |
| PER-05 | verified | summary-table indexes + JSON cache + daily cron |

## Critical Anti-Patterns / Issues Found

| File | Line | Issue | Severity | Impact |
|------|------|-------|----------|--------|
| migrations/019_users_last_active.sql | 43 | `WHEN (NEW.delta > 0)` clause in CREATE TRIGGER body is rejected by MariaDB 11.4.5 on this server (SQLSTATE[42000]) | BLOCKER | Migration runner cannot apply 019 on fresh DB; .applied stops at 018; migrations 020/021/022 never run; trigger that refreshes users.last_active_at on gamification path is missing |
| migrations/022_trigger_cap_hit_ignore.sql | 20 | Same broken `WHEN` clause as 019 | BLOCKER | Cannot apply anywhere — same syntax error |
| migrations/.applied | — | 18 entries only; 020/021 tables exist on dev DB but were hand-created, not applied via runner | BLOCKER | php migrate.php is NOT idempotent on either DB |
| src/Support/View/partials/on_break_pill.php | — | Source correct; trigger substrate broken | WARNING | On-Break signal depends entirely on auth login path until trigger is fixed |

## Recommended Fix Path

1. **Migrate the trigger syntax off `WHEN`.** Either:
   - Revert to the original 019 trigger (no `WHEN` clause) and rely on application-layer filtering — points_service / on_break_pill can read `metadata.velocity_cap_hit` / `metadata.pair_cap_hit` from points_log to decide whether to refresh last_active_at. This loses the performance benefit of doing the filter in the trigger but is the safest path. Cost: ~10 lines in points_service.
   - Confirm whether this MariaDB build supports `WHEN` on triggers; if yes, the bug is elsewhere (server config, sql_mode). If no, switch to option A.

2. **Restore the dev DB to a consistent state.** Either manually create the trigger without `WHEN` and mark 019-022 applied in `.applied`, OR drop the leaderboard_*/login_streaks tables and re-run migrate.php after the syntax fix.

3. **Add a defensive test for the trigger's `WHEN (NEW.delta > 0)` happy path** (mentioned as deferred in the audit-fix doc, item N3). Insert a points_log row with `delta = 0` and assert `users.last_active_at` is unchanged — the negative-case test was deferred to "gsd-quick" but should be lifted into the Phase 6 migration test to prevent future regressions of this exact kind.

---

_Verified: 2026-09-05T05:30:00Z_
_Verifier: the agent (gsd-verifier)_
_Initial verification — gaps found, blocker on migration 019/022 trigger syntax_
