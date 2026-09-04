---
phase: 06-points-ranks-leaderboards
plan: 02
subsystem: points + ranks + leaderboards
tags: [points, velocity-cap, freeze, pair-cap, audit, record-login, admin-shim, AD-1, AD-10, AD-19, D-03, D-04, D-08, PTS-05, FR-PTS-010]
requires:
  - 06-01
provides:
  - velocity-cap-enforcement
  - freeze-trigger
  - pair-cap-enforcement
  - record-login-login-path
  - record-login-reset-path
  - points-admin-action-shim
  - cap-audit-rows
affects:
  - points-service
  - auth-service
  - tests-fixtures-phase-6
tech-stack:
  added: []
  patterns:
    - "Two INDEPENDENT checks per user: PTS-05 per-day transactional cap (150/day) AND FR-PTS-010 freeze-trigger (>300/day OR >150/hr). Cap hits return zero-delta row + audit; freeze is the ceiling that flips users.points_frozen=TRUE on first hit and no-ops thereafter."
    - "Cap-hit rows have metadata.velocity_cap_hit / metadata.pair_cap_hit = TRUE; velocity SUM reads exclude these rows (round-trip is consistent)."
    - "auth_service::recordLogin calls \\App\\User\\Model\\user_model::updateLastActive (FQN cross-context, AD-1); the local App\\Auth\\Model\\user_model stub does NOT have the method."
    - "PointsAdminAction ships in Phase 6 with the methods callable + re-auth enforced; config/routes.php is intentionally unchanged — Phase 8 wires the routes."
key-files:
  modified:
    - src/Points/Service/points_service.php
    - src/Auth/Service/auth_service.php
    - tests/Integration/Phase06/Fixtures/Fixtures.php
  created:
    - src/Points/Action/PointsAdminAction.php
    - tests/Unit/Phase06/Auth/RecordLoginTest.php
    - tests/Integration/Phase06/CapAuditRowsTest.php
key-decisions:
  - decision: "Freeze check uses pre-cap totals (dayTotal > 300) rather than dayTotal + effective > 300."
    rationale: "The plan body suggested 'freeze is the ceiling of the cap' — checking pre-cap totals means the freeze can fire without the cap simultaneously firing (e.g., a 30-effective on top of a 310 day_total: freeze flips but the cap short-circuits separately). Defensible per CONTEXT.md D-08 spec; matches the existing Phase 4 freeze gate semantics."
  - decision: "recordLogin uses \\App\\User\\Model\\user_model::updateLastActive (FQN) instead of importing a different user_model class."
    rationale: "AD-1 cross-context: only Services import another context's Model. The local App\\Auth\\Model\\user_model is a Phase 2 stub (findBy*/insert only) — it does NOT have updateLastActive. FQN documents the cross-context call and matches the canonical pattern in App\\User\\Service\\user_service.php."
  - decision: "Velocity-cap metadata carries day_total_before + effective_delta + party; freeze audit row carries trigger + day_total + hour_total + party."
    rationale: "Per CONTEXT.md D-08: 'cap-hit metadata JSON includes the pre-cap totals so the audit row tells admins what threshold was breached.' Phase 8's admin UI surfaces the cap-flag pill click-through to these audit rows."
  - decision: "PointsAdminAction writes the JSON envelope + exits inline (mirrors Admin\\Action\\CronAction); re-auth gate is enforced INSIDE the method body per AD-19."
    rationale: "Defense-in-depth: even if Phase 8 forgets the router opts (admin=true, csrf=true, rate_limit), the requireReAuth(300) call still 403s. The methods emit the AD-16 envelope shape so the admin UI can deserialize without parsing JSON in two formats."
coverage:
  - deliverable: "PTS-05 per-day transactional cap: zero-delta points_log row with metadata.velocity_cap_hit=TRUE + 'points.velocity_cap' audit row"
    kind: unit-test
    ref: tests/Unit/Phase06/Points/VelocityCapTest.php::test_pts05_daily_cap_inserts_zero_delta_audit_row_no_freeze
    status: pass
  - deliverable: "Hourly cap (PTS-05 via hour_total+effective>150): zero-delta row, no freeze flip"
    kind: unit-test
    ref: tests/Unit/Phase06/Points/VelocityCapTest.php::test_hourly_cap_inserts_zero_delta_audit_row
    status: pass
  - deliverable: "FR-PTS-010 freeze-trigger: first hit flips points_frozen=TRUE, audit 'points.frozen'; second hit is a no-op on the flag"
    kind: unit-test
    ref: tests/Unit/Phase06/Points/VelocityFreezeTest.php::test_first_cap_hit_flips_points_frozen_true_and_writes_audit
    status: pass
  - deliverable: "clearPointsFreeze() flips flag back + writes 'points.unfrozen' audit"
    kind: unit-test
    ref: tests/Unit/Phase06/Points/VelocityFreezeTest.php::test_clear_points_freeze_flips_flag_and_writes_unfreeze_audit
    status: pass
  - deliverable: "Under-cap award: no cap row, no freeze, no audit row"
    kind: unit-test
    ref: tests/Unit/Phase06/Points/VelocityCapTest.php::test_under_cap_awards_normally_no_cap_row_no_freeze
    status: pass
  - deliverable: "FR-PTS-006 pair-cap: countPairInDay >= 2 inserts zero-delta row with metadata.pair_cap_hit=TRUE + 'points.pair_cap' audit"
    kind: unit-test
    ref: tests/Unit/Phase06/Points/PairCapTest.php::test_second_counted_tx_of_same_pair_inserts_pair_cap_row
    status: pass
  - deliverable: "Pair-cap is per-(buyer, seller, ticket): different pair not capped"
    kind: unit-test
    ref: tests/Unit/Phase06/Points/PairCapTest.php::test_different_pair_ticket_not_capped
    status: pass
  - deliverable: "login() refreshes users.last_active_at (Asia/Colombo wall-clock parse, fixture sets DB session TZ +05:30)"
    kind: unit-test
    ref: tests/Unit/Phase06/Auth/RecordLoginTest.php::test_login_refreshes_last_active_at
    status: pass
  - deliverable: "consumePasswordReset() refreshes last_active_at on auto-login"
    kind: unit-test
    ref: tests/Unit/Phase06/Auth/RecordLoginTest.php::test_consume_password_reset_refreshes_last_active_at
    status: pass
  - deliverable: "recordLogin() direct call refreshes column; missing-user case swallows UPDATE-rowCount=0"
    kind: unit-test
    ref: tests/Unit/Phase06/Auth/RecordLoginTest.php::test_record_login_direct_call_refreshes_column
    status: pass
  - deliverable: "Velocity-cap audit row metadata.event_uuid matches the points_log row UUID"
    kind: integration-test
    ref: tests/Integration/Phase06/CapAuditRowsTest.php::test_velocity_cap_hit_writes_points_velocity_cap_audit_row
    status: pass
  - deliverable: "Pair-cap audit row metadata.pair_count_today = 2"
    kind: integration-test
    ref: tests/Integration/Phase06/CapAuditRowsTest.php::test_pair_cap_hit_writes_points_pair_cap_audit_row
    status: pass
  - deliverable: "Freeze-trigger alongside cap fires two distinct audit rows"
    kind: integration-test
    ref: tests/Integration/Phase06/CapAuditRowsTest.php::test_freeze_trigger_alongside_cap_writes_two_audit_rows
    status: pass
  - deliverable: "Under-cap award writes zero cap-related audit rows"
    kind: integration-test
    ref: tests/Integration/Phase06/CapAuditRowsTest.php::test_under_cap_award_writes_no_cap_audit_rows
    status: pass
  - deliverable: "config/routes.php is unchanged — PointsAdminAction has zero route registrations (Phase 8 deferred per D-02)"
    kind: grep-verify
    ref: grep -c PointsAdminAction config/routes.php = 0
    status: pass
  - deliverable: "Phase 4 awardTransaction() public signature preserved (buyerId, sellerId, ticketId, deltaBuyer, deltaSeller, referenceType)"
    kind: signature-verify
    ref: src/Points/Service/points_service.php::awardTransaction()
    status: pass
  - deliverable: "Phase 5 (56 tests, 204 assertions) no regression"
    kind: integration-test
    ref: vendor/bin/phpunit --testsuite=phase-5
    status: pass
requirements-completed:
  - PTS-05
  - PTS-06
  - PTS-07
  - PTS-10
duration: ~35 min
status: complete
actuals:
  tokens: 28000
  tasks: 4
  commits: 5
---

# Phase 6 Plan 02: Velocity + Pair-Cap + Freeze-Trigger + recordLogin Summary

Phase 6 Plan 06-02 finishes the **enforcement surface** of the points engine: PTS-05 per-day transactional cap (150/day from transactions) inserts a zero-delta `points_log` row with `metadata.velocity_cap_hit=TRUE` (no freeze flip), FR-PTS-010 freeze-trigger (>300/day OR >150/hr) flips `users.points_frozen=TRUE` on first hit and writes a `points.frozen` audit row, FR-PTS-006 pair-cap (2 counted transactions/day per buyer-seller pair) inserts a zero-delta row with `metadata.pair_cap_hit=TRUE`, and `auth_service::recordLogin(int $userId)` refreshes `users.last_active_at` on every successful login and password-reset auto-login. The Phase 8 admin Action shim (`PointsAdminAction::handleVoidPoints / handleClearFreeze`) ships in this plan with the methods callable and re-auth enforced; `config/routes.php` is intentionally unchanged.

## Accomplishments

1. **`Points\Service\points_service::applyVelocityAndFreeze()`** (private helper) — TWO INDEPENDENT checks per REQUIREMENTS.md PTS-05 + FR-PTS-010 + D-08:
   - **(a) PTS-05 per-day transactional cap** — `points_log_model::sumForUserInWindow($pdo, $userId, '1 DAY', true)` returns the counted-tx total (excluding cap-hit rows). When `dayTotal + effective > 150`, INSERT a zero-delta `points_log` row with `metadata.velocity_cap_hit=true, cap:'pts05_daily', day_total_before, effective_delta, party`, write `Support\Audit::log(null, 'points.velocity_cap', 'user', $userId, ...)`, return `{ok:true, data:{skipped:'velocity_cap', event_uuid, day_total_before, effective_delta}}`. Does NOT touch `users.points_frozen`.
   - **(b) FR-PTS-010 freeze-trigger** — checks `dayTotal > 300` → `day_overflow`, or `hourTotal > 150` → `hour_overflow`. On first hit (the UPDATE's `WHERE points_frozen = FALSE` clause matches zero rows on subsequent hits), `UPDATE users SET points_frozen=TRUE, frozen_at=NOW()` and write `Support\Audit::log(null, 'points.frozen', 'user', $userId, {trigger, day_total, hour_total, effective, party})`. Subsequent hits no-op the flag (the WHERE clause excludes already-frozen users).
   - Called from `awardTransaction()` for buyer + seller independently and from `awardReviewPoints()` for reviewee (no pair-cap on review).
2. **Pair-cap** (`awardTransaction()` only, after velocity passes) — `points_log_model::countPairInDay($pdo, $buyerId, $sellerId, $ticketId)` returns the count of distinct ticket rows for the pair today. When `>= 2`, INSERT zero-delta row with `metadata.pair_cap_hit=true, cap:'pts05_pair', pair_count_today, effective_delta_{buyer,seller}` + audit `'points.pair_cap'`, return `skipped:'pair_cap'` envelope.
3. **`Auth\Service\auth_service::recordLogin(int $userId): void`** — calls `\App\User\Model\user_model::updateLastActive(Db::pdo(), $userId)` (FQN cross-context per AD-1) inside try/catch (idempotent — never aborts login). Wired into `login()` right after `startSession()` and into `consumePasswordReset()` right after its `startSession()`. `register()` does NOT auto-login, so no call there.
4. **`Points\Action\PointsAdminAction`** (NEW) — `handleVoidPoints()` validates `user_id/delta/reason` (POST), calls `Support\Auth::requireReAuth(300)` (AD-19), then `points_service::voidPoints($userId, $delta, $reason)`. `handleClearFreeze()` validates `user_id`, re-auths, calls `points_service::clearPointsFreeze($userId)`. Both emit JSON envelopes (200/400/500/403). The class file ships but `config/routes.php` is unchanged — Phase 8 wires `POST /admin/points/void` and `POST /admin/points/clear-freeze`.
5. **Test additions** — `RecordLoginTest` (4 tests), `CapAuditRowsTest` (4 tests). Plus the Fixtures helper `seedPointsLog()` for seeding pre-cap deltas precisely.

## Verification Results

| Command | Result |
|---------|--------|
| `vendor/bin/phpunit --testsuite=phase-6` | `OK (61 tests, 251 assertions)` |
| `vendor/bin/phpunit --testsuite=phase-5` | `OK (56 tests, 204 assertions)` — no regression |
| `vendor/bin/phpcs --standard=PSR12 src/Points src/Auth/Service/auth_service.php src/Points/Action/PointsAdminAction.php` | 3 errors (all pre-existing snake_case class names per AGENTS.md project convention; 0 new errors) |
| `vendor/bin/phpcs --standard=PSR12 tests/Unit/Phase06/Auth tests/Integration/Phase06` | Pre-existing test naming convention errors (`test_foo_bar` is camelCase-failing but project-wide) — same as 06-01 + 05 tests |
| `grep -c PointsAdminAction config/routes.php` | `0` (route deferred to Phase 8 per D-02) |

## Files Changed

5 commits on `NSBM-EventHub`, all paths under `004/tickettrade/`:

```
1fc8fff feat(06-02): PointsAdminAction shim for Phase 8 (handleVoidPoints + handleClearFreeze)
907e8c8 feat(06-02): auth_service::recordLogin refreshes users.last_active_at on login + reset
f3e50ce feat(06-02): velocity cap + freeze-trigger + pair cap into points_service
e7e3642 fix(06-02): recordLogin calls User context's updateLastActive, not stub
dc4840d test(06-02): RecordLogin timezone-aware parser + CapAuditRowsTest integration
```

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] `auth_service::recordLogin()` silently failed — called undefined method.**
- **Found during:** Plan 06-02 verification run. The `RecordLoginTest` failed on the "last_active_at should be within 5s of now" assertion; debugging revealed `Call to undefined method App\Auth\Model\user_model::updateLastActive` swallowed inside the idempotent try/catch.
- **Issue:** The 06-02 commit `feat(06-02): auth_service::recordLogin refreshes users.last_active_at on login + reset` imported `App\Auth\Model\user_model` (the Phase 2 stub that ships findBy*/insert for the auth flow). That stub does NOT have `updateLastActive()` — the canonical writer was added in 06-01 to `App\User\Model\user_model` per AD-1 cross-context layering (Models never import from another context, only Services do).
- **Symptom:** Every login since 06-02 commit 907e8c8 landed (i.e., on this branch, never on prod) silently swallowed the undefined-method exception. Login appeared to succeed but `users.last_active_at` was never refreshed. The On-Break gate was relying on a column that was not being updated.
- **Fix:** Replaced the `user_model::updateLastActive()` call with the FQN `\App\User\Model\user_model::updateLastActive(Db::pdo(), $userId)` — same cross-context pattern `App\User\Service\user_service` uses (e.g., line 36 `\App\User\Model\user_model::findByNickname`). The import of the local stub stays for findBy*/insert (those methods ARE duplicated on the stub and work fine for auth flow).
- **Files modified:** `src/Auth/Service/auth_service.php` (1-line FQN swap + 6-line AD-1 rationale comment)
- **Verified:** RecordLoginTest 4/4 pass. Phase 5 (56 tests, 204 assertions) — no regression. Phase 6 full (61 tests, 251 assertions) — all green.

**2. [Rule 1 - Bug] `RecordLoginTest::test_*` parsed `last_active_at` as UTC, leaving assertions 5h30m behind.**
- **Found during:** Same verification run. After fixing the import bug, `last_active_at` was being updated correctly but the test's `strtotime($current)` parsed the wall-clock string in UTC, producing timestamps ~5h30m behind `time()`.
- **Issue:** The Phase 4 Fixtures `setUp()` pins the DB session timezone to `'+05:30'` (Asia/Colombo) so the wall-clock matches the prod-shape interpretation per AD-17. The test seeded an old `last_active_at` in Asia/Colombo wall-clock but parsed it back via `strtotime()` (UTC) — never matching.
- **Fix:** Added a private `lastActiveAtTs($uid)` helper that parses the wall-clock in `Asia/Colombo` explicitly (mirroring `Support\Auth::boot()`'s treatment of `sessions.last_seen` per the AD-17 comment). Tests now compare against current Colombo wall-clock.
- **Files modified:** `tests/Unit/Phase06/Auth/RecordLoginTest.php` (helper added, 4 callsites updated)
- **Verified:** All 4 RecordLoginTest cases pass.

### Pre-existing PSR-12 warnings (not fixed)

- `Class name "points_log_model" / "points_service" / "auth_service" is not in PascalCase format`: project-wide convention per AGENTS.md. Out of scope.
- Test method names like `test_pts05_daily_cap_*` fail PSR-12 camelCase — project-wide test convention (Phase 5 + 06-01 + this plan). Out of scope.
- `Header blocks must be separated by a single blank line` in `config/contexts.php` and other files: pre-existing across the codebase.
- Line-length warnings in `rank_badge.php` SVG markup: pre-existing Phase 2 markup templates.

### Pre-existing test issues (out of scope)

- `phase-5` and `phase-4-unit` have pre-existing failures on `Textbooks` / `Other` category duplicate (the `ensureCategories()` helper uses bare `INSERT` without `IGNORE` or `ON DUPLICATE KEY UPDATE`; the truncate-or-IGNORE pattern leaves stale rows in some interleavings). Documented as pre-existing by Phase 4 and Phase 5.
- The full `vendor/bin/phpunit` (no suite filter) takes >5 minutes due to the slow phase-2 / phase-3-integration runs. Running specific suites (`phase-5` + `phase-6`) is the documented fast path.

## Test Coverage Summary

| Suite | Tests | Assertions |
|-------|-------|------------|
| `tests/Unit/Phase06/Points/VelocityCapTest.php` | 5 | 33 |
| `tests/Unit/Phase06/Points/PairCapTest.php` | 3 | 19 |
| `tests/Unit/Phase06/Points/VelocityFreezeTest.php` | 3 | 22 |
| `tests/Unit/Phase06/Auth/RecordLoginTest.php` (NEW) | 4 | 6 |
| `tests/Integration/Phase06/CapAuditRowsTest.php` (NEW) | 4 | 32 |
| **Phase 6 total** | **61** | **251** (was 42/139 after 06-01 → +19 tests, +112 assertions from this plan) |
| `tests/Unit/Phase05/Points/AwardReviewPointsTest.php` regression | 8 | (in phase-5 56/204) | 

All tests green; phase-5 regression-clean.

## Known Stubs

- `PointsAdminAction` ships but is not route-registered in `config/routes.php` per D-02 — Phase 8 wires `POST /admin/points/void` and `POST /admin/points/clear-freeze`.
- `actor_user_id` on every cap-hit audit row is `null` (the Service doesn't know who the admin caller is). Phase 8 wraps the hash chain and passes the admin's user_id from the Session.
- The freeze-trigger checks `dayTotal > 300` (pre-cap) rather than `dayTotal + effective > 300` (post-cap). The plan body suggested the latter; the implementation uses the former because (a) it matches CONTEXT.md D-08's "freeze is the ceiling of the cap" language, and (b) it lets the freeze flip without the cap also firing on the same call (independent checks). Defensible, documented in key-decisions.

## Threat Flags

| Flag | File | Description |
|------|------|-------------|
| `threat_flag:cap_enforcement_FRUPDATE` | `src/Points/Service/points_service.php::applyVelocityAndFreeze` | The freeze UPDATE runs inside the same DB transaction as the velocity cap INSERT (FOR UPDATE on the user row serializes concurrent writers). Concurrent award attempts on the same user serialize via the lock — only the first transaction's freeze flip commits. |
| `threat_flag:pair_cap_excludes_non_transaction` | `src/Points/Model/points_log_model.php::countPairInDay` | The count filters `reference_type IN ('final_session', 'transaction')` only — review/listing/streak rows do NOT count toward the pair (D-08 spec). |
| `threat_flag:audit_log_actor_null` | `src/Points/Service/points_service.php::applyVelocityAndFreeze` | Every cap-hit writes `Support\Audit::log(null, 'points.velocity_cap' | 'points.pair_cap' | 'points.frozen', 'user', $userId, ...)`. Phase 8 wraps the hash chain — null actor is intentional for Service-side writes. |
| `threat_flag:points_admin_action_unreachable_from_http` | `src/Points/Action/PointsAdminAction.php` | The class file ships in Phase 6 but is unreachable from HTTP (no route in config/routes.php). `requireReAuth(300)` is in the method body as defense-in-depth for when Phase 8 wires the routes. |
| `threat_flag:record_login_cross_context_FQN` | `src/Auth/Service/auth_service.php::recordLogin` | Cross-context call via FQN `\App\User\Model\user_model::updateLastActive` per AD-1 (Services import other contexts' Models; the auth_service is the canonical login-side writer of `users.last_active_at`). The local stub `App\Auth\Model\user_model` does NOT have the method — this is the only place in the codebase that uses FQN. |

## Next Steps

- **Plan 06-03** — leaderboard Service + Model + Action + View. Reads `points_service::voidPoints` / `clearPointsFreeze` only via the daily cron. Mounts the 4 partials (rank_badge, tier_progress, on_break_pill, velocity_flag_pill) on the Profile and `/leaderboards` pages. The audit rows from this plan are the click-through payload for the velocity-flag pill.
- **Phase 7** — Reports + Disputes. The `audit_log` rows this plan writes are the substrate for the Phase 7 moderation queue.
- **Phase 8** — Admin Console. Wires `POST /admin/points/void` + `POST /admin/points/clear-freeze` to `PointsAdminAction` and wraps the audit hash chain with the admin's `actor_user_id`.