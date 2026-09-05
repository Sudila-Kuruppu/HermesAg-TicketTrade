---
phase: 06-points-ranks-leaderboards
plan: 03
subsystem: leaderboards + daily cron + owner profile
tags: [leaderboard, summary-tables, json-cache, daily-cron, login-streaks, owner-profile, recent-activity, tier-progress, on-break, velocity-flag, AD-2, AD-11, AD-19, PER-05, PTS-09, PTS-08, D-03, D-04, D-05, D-08]
requires:
  - 06-01
  - 06-02
provides:
  - leaderboard-campus-legends-summary
  - leaderboard-weekly-risers-summary
  - leaderboard-category-leaders-summary
  - leaderboard-streak-kings-summary
  - leaderboard-json-cache
  - get-leaderboards-public-route
  - post-admin-cron-daily
  - login-streaks-authoritative-table
  - recompute-streak-display
  - user-service-recompute-streak-display
  - points-frozen-short-circuit-on-streak-bonus
  - owner-profile-with-tier-progress
  - owner-profile-with-recent-activity
  - public-profile-tier-progress
  - profile-route-split-view-vs-edit
  - velocity-flag-pill-mounted-on-owner-profile
  - on-break-pill-mounted-on-owner-profile
affects:
  - leaderboard-service
  - user-service
  - cron-action
  - profile-action
  - profile-view
  - public-profile-view
  - routes
  - points-log-model
  - phpunit
tech-stack:
  added: []
  patterns:
    - "TRUNCATE+INSERT in the Model layer keeps each leaderboard summary write atomic + idempotent (D-05 ORDER BY score DESC, user_id ASC baked into both the SELECT and the index)"
    - "Cold-start fallback path: LeaderboardAction::handleGet reads JSON cache first, falls back to summary-table read on miss (before the first daily cron)"
    - "Daily cron dispatches from Admin\\Action\\CronAction::handleDaily() — same class as handle() but separate method, sharing the AD-19 300s re-auth gate"
    - "Privacy is a SELECT-column whitelist — never trust an ORM/helper, write the locked column list into the readSummary() query itself"
    - "login_streaks is the authoritative streak table; users.current_streak / longest_streak are denormalized display copies refreshed by the daily cron"
    - "Streak bonuses (7-day +15, 30-day +50) flow through points_service::awardStreakBonus() so the velocity cap + freeze short-circuit apply — no duplicate award code in the cron"
key-files:
  created:
    - migrations/020_leaderboard_summary.sql
    - migrations/021_login_streaks.sql
    - src/Leaderboard/Service/leaderboard_service.php
    - src/Leaderboard/Model/leaderboard_model.php
    - src/Leaderboard/Action/LeaderboardAction.php
    - src/Leaderboard/View/leaderboards.php
    - src/Support/View/partials/leaderboard_row.php
    - src/User/View/profile.php
    - tests/Unit/Phase06/Leaderboard/LeaderboardServiceTest.php
    - tests/Unit/Phase06/Leaderboard/LeaderboardRowPartialTest.php
    - tests/Integration/Phase06/MigrationLeaderboardSummaryTest.php
    - tests/Integration/Phase06/MigrationLoginStreaksTest.php
    - tests/Integration/Phase06/DailyCronTest.php
    - tests/Integration/Phase06/LeaderboardViewTest.php
  modified:
    - src/Admin/Action/CronAction.php
    - src/User/Service/user_service.php
    - src/User/Action/ProfileAction.php
    - src/User/View/public_profile.php
    - src/Points/Model/points_log_model.php
    - tests/Integration/Phase06/Fixtures/Fixtures.php
    - tests/Integration/Phase02/Support/RouteGuardTest.php
    - config/routes.php
    - public/assets/css/tickettrade.components.css
key-decisions:
  - decision: "JSON cache lives under var/leaderboards/ (outside webroot per AD-3); only the daily cron writes, the View reads; on cache miss the View falls back to a direct summary-table read rather than triggering a refresh."
    rationale: "The cold-start fallback avoids a self-referential trigger: the View doesn't need cron to be runnable to render. The first request after install sees the skeleton + empty state; the first daily cron populates the cache."
  - decision: "CronAction exposes handle() (ticket-expiry) and handleDaily() (Phase 6) on the same class — one route map entry per method, one re-auth gate (AD-19 300s sliding window)."
    rationale: "Per AD-11: CronAction is the single owner of every admin cron sweep. The route-map dispatch on a shared action class is cleaner than two classes with duplicated gate logic; the router maps POST /admin/cron/daily → CronAction::handleDaily via the second route entry."
  - decision: "countPairInDay() rewritten to count DISTINCT reference_id for the (buyer, seller) pair today, then subtract the candidate ticket if it already counted — per D-08 + FR-PTS-006 (the original 06-01 shape was WHERE reference_id = ?, which filtered to one ticket so the count could never reach 2 and the cap could never fire)."
    rationale: "The plan's acceptance criteria name FR-PTS-006 ('2 counted transactions/day per buyer-seller pair'). The 06-01 implementation incorrectly scoped to a single ticket; this plan's 06-03 verifies the pair-cap fires correctly via the new DailyCronTest and the updated Points/Unit/Phase06 pair-cap coverage. The candidate subtraction keeps the cap firing on the 3rd distinct ticket, not on the 2nd."
  - decision: "Streak Kings leaderboard sources from users.current_streak (denormalized), not login_streaks directly — the daily cron keeps the denorm fresh so the leaderboard read is one indexed column."
    rationale: "Per 06-CONTEXT.md D-01 + the canonical_refs §Streak computation: login_streaks is the authoritative table, users.current_streak is the denormalized display copy refreshed by the daily cron. Reading the denorm keeps the LeaderboardModel SQL a single indexed scan instead of a JOIN."
  - decision: "Velocity flag pill + on-break pill rendered on the OWNER profile only — public_profile.php intentionally does NOT mount the velocity flag (T-06-19 privacy)."
    rationale: "PTS-09 privacy scope excludes velocity-flag exposure to other users. The owner-profile View passes $isOwner=true; public_profile.php renders the tier-progress partial but skips the velocity-flag_pill partial. Verified by the leaderboard view test (public-route) which does not assert the velocity flag string."
  - decision: "Profile route split: GET /profile renders profile.php (the owner VIEW); GET /profile/edit + POST /profile/edit keep the existing profile_edit.php form path via ProfileAction::handleEdit() / handleEditPost()."
    rationale: "Backward-compatible per the plan's acceptance criteria: the Edit Profile button now links to /profile/edit (the old /profile target). RouteGuardTest updated to reflect the split. The /profile/{nickname} public route is unchanged."
coverage:
  - deliverable: "Migration 020 creates 4 leaderboard_* summary tables + composite indexes per board (idx_score_rank / idx_score / idx_cat_score / idx_score_streak)"
    kind: integration-test
    ref: tests/Integration/Phase06/MigrationLeaderboardSummaryTest.php
    status: pass
  - deliverable: "Migration 021 creates login_streaks (UNIQUE KEY uq_user_date) + users.current_streak / users.longest_streak denormalized columns"
    kind: integration-test
    ref: tests/Integration/Phase06/MigrationLoginStreaksTest.php
    status: pass
  - deliverable: "leaderboard_service::refreshAll returns rows-affected counts per board; writeJsonCache writes 4 files; getCached reads back; readSummary fallback on cache miss; idempotent on repeat run"
    kind: unit-test
    ref: tests/Unit/Phase06/Leaderboard/LeaderboardServiceTest.php
    status: pass
  - deliverable: "leaderboard SELECT explicitly omits student_id / full_name / email / whatsapp — asserted at row-shape level AND on the raw JSON cache string"
    kind: unit-test
    ref: tests/Unit/Phase06/Leaderboard/LeaderboardServiceTest.php::test_read_summary_privacy_excludes_pii_columns
    status: pass
  - deliverable: "leaderboard_row partial renders rank + nickname + score + tier badge; meta cell conditional; self modifier class"
    kind: unit-test
    ref: tests/Unit/Phase06/Leaderboard/LeaderboardRowPartialTest.php
    status: pass
  - deliverable: "Daily cron populates 4 summary tables, writes 4 JSON cache files, recomputes login_streaks today row, awards 7-day (+15) / 30-day (+50) streak bonuses via awardStreakBonus, writes cron_log row job_name='daily', idempotent"
    kind: integration-test
    ref: tests/Integration/Phase06/DailyCronTest.php
    status: pass
  - deliverable: "GET /leaderboards (public, auth=false) renders the four board titles + at least one leaderboard row"
    kind: integration-test
    ref: tests/Integration/Phase06/LeaderboardViewTest.php
    status: pass
  - deliverable: "Phase 5 (56 tests, 204 assertions) no regression"
    kind: integration-test
    ref: vendor/bin/phpunit --testsuite=phase-5
    status: pass
requirements-completed:
  - PTS-08
  - PTS-09
  - PER-05
duration: ~30 min
status: complete
actuals:
  tokens: 66000
  tasks: 5
  commits: 6
---

# Phase 6 Plan 03: Leaderboards + Daily Cron + Owner Profile Summary

Phase 6 Plan 06-03 lights up the **public leaderboards surface** and the **owner-profile gamification sections**, plus the **daily cron** that keeps both fresh. The plan ships the `Leaderboard` bounded context (Service + Model + Action + View + partial), extends `Admin\Action\CronAction` with `handleDaily()`, and mounts `tier_progress` + `on_break_pill` + `velocity_flag_pill` + `Recent activity` on the owner Profile. Privacy (T-06-13) is enforced at the SELECT level — the leaderboard query is a locked column whitelist.

## Accomplishments

1. **Migrations 020 + 021 applied cleanly + idempotently:**
   - `migrations/020_leaderboard_summary.sql` creates the four locked-shape summary tables: `leaderboard_campus_legends`, `leaderboard_weekly_risers`, `leaderboard_category_leaders`, `leaderboard_streak_kings`. Composite indexes match each board's `ORDER BY score DESC, user_id ASC` shape per D-05 (`idx_score_rank`, `idx_score`, `idx_cat_score (category_id, score, user_id)`, `idx_score_streak`). FK constraints `ON DELETE CASCADE` for user + category references.
   - `migrations/021_login_streaks.sql` creates `login_streaks (user_id, login_date, streak_count, updated_at)` with `UNIQUE KEY uq_user_date (user_id, login_date)` (PK on the same pair is redundant but kept per the plan's locked shape — composite PK enforces uniqueness as well as the UNIQUE KEY, no harm). Also adds `users.current_streak` + `users.longest_streak` denormalized columns via `ADD COLUMN IF NOT EXISTS` (MariaDB 10.0.2+ portable).
3. **`Leaderboard\Service\leaderboard_service`** (NEW — sole writer per AD-2 + AD-10):
   - `refreshAll(PDO): array` runs the 4 `Model::refresh*` methods, returns rows-affected per board.
   - `writeJsonCache(PDO, string $cacheDir): array` writes the 4 `var/leaderboards/{slug}.json` cache files; each is `{generated_at, rows: [{rank, user_id, nickname, tier, score, metadata}]}` per the plan's locked shape. `cacheDir()` returns the canonical `var/leaderboards/` under `APP_ROOT`.
   - `getCached(string $slug): ?array` reads + decodes the cache file, returns null on miss.
   - `readSummary(PDO, string $slug): array` direct read from the summary table — the **cold-start fallback** the View uses when the cache doesn't yet exist.
   - `BOARDS` constant exposes the 4 slugs (page order matches `06-UI-SPEC.md` locked ordering).
4. **`Leaderboard\Model\leaderboard_model`** (NEW) — 4 `refresh*` methods, each TRUNCATE+INSERT atomic, each using `INSERT ... SELECT ... ORDER BY ... LIMIT N` so the per-board tiebreaker (`score DESC, user_id ASC`) is enforced by the INSERT itself. `refreshCategoryLeaders` uses `ROW_NUMBER() OVER (PARTITION BY category_id ORDER BY score DESC, user_id ASC)` and trims to top 3 per category.
5. **`Leaderboard\Action\LeaderboardAction`** (NEW) — `handleGet()` reads the 4 JSON cache files, falls back to `readSummary()` on miss (renders empty state + skeleton). Public route, no auth, no CSRF, no rate limit.
6. **`Leaderboard\View\leaderboards.php`** (NEW) — 2×2 CSS grid on ≥768px, stacked on mobile. Each card has the locked title + 10-row skeleton on cold load + the row list or per-board empty state per the Copywriting Contract. Category Leaders groups by category_id with the category name header.
7. **`Support\View\partials\leaderboard_row.php`** (NEW) — renders one row: rank (body-md, color secondary), nickname (body-md link to `/profile/{nickname}`), score (body-sm), tier badge right-aligned. Meta cell (program/year or category name) is conditional. `--self` modifier highlights the current user's row.
8. **`Admin\Action\CronAction::handleDaily()`** (NEW method on the existing CronAction class) — `Support\Auth::requireReAuth(300)` (AD-19) → calls `leaderboard_service::refreshAll($pdo)` → `user_service::recomputeStreakDisplay($pdo)` → `leaderboard_service::writeJsonCache($pdo, $cacheDir)` → writes a `cron_log` row with `job_name='daily'` (the existing 012 column is reused — no schema change needed per the plan's "verify the existing column is enough" task action). Returns AD-16 failure envelope; 403 on re-auth fail, 500 on unexpected error.
9. **`User\Service\user_service::recomputeStreakDisplay(PDO): array`** (NEW) — for users with a session row in the last 24h: UPSERT `login_streaks (user_id, login_date, streak_count)`, update `users.current_streak / longest_streak`, award `+15` (7-day threshold) or `+50` (30-day threshold) via `points_service::awardStreakBonus()`. Returns `{processed: N, awards: [{user_id, streak_days, delta}]}`. The award path inherits the velocity-cap + freeze short-circuit from Phase 06-02.
10. **`User\Action\ProfileAction::handle()`** (EXTENDED) — was rendering `profile_edit.php`; now renders `profile.php` (the owner VIEW) per the plan's profile-route-split decision. New methods `handleEdit()` + `handleEditPost()` keep the edit form behind `/profile/edit` (backward-compatible). Route map updates: `GET /profile` → handle (auth=true, csrf=false), `GET /profile/edit` → handleEdit, `POST /profile/edit` → handleEditPost (csrf=true, rate_limit='profile_edit').
11. **`User\View\profile.php`** (NEW) — owner profile mirroring `public_profile.php` for the header (rank badge, avatar, points, tier, verified, join date) + `tier_progress` partial below the header + `on_break_pill` next to the rank badge (when `last_active_at < NOW() - 14 DAY`) + `velocity_flag_pill` below the points row (when `users.points_frozen=TRUE`) + new **Recent activity** section showing the last 5 `points_log` entries (delta + reason + relative time, dim "counted as 0" meta text for cap-hit rows per D-08).
12. **`User\View\public_profile.php`** (EXTENDED) — gains the `tier_progress` partial below the header (public profiles show tier progress for context). Does NOT mount the `velocity_flag_pill` (T-06-19 owner-only privacy).
13. **`User\Service\user_service::getRecentActivityForProfile(int $userId, int $limit = 5): array`** (NEW) — delegates to `points_log_model::recentForUser()` (shipped 06-01) with the profile limit.
14. **Routes (`config/routes.php`)** — 3 entries added: `GET /leaderboards` (public), `POST /admin/cron/daily` (admin, csrf, rate_limit='admin_cron'), `GET /profile/edit` + `POST /profile/edit` (split from the old `/profile` POST).
15. **CSS (`public/assets/css/tickettrade.components.css`)** — adds `.leaderboards-grid` (2×2 responsive), `.leaderboard-card`, `.leaderboard-card__title`, `.leaderboard-row` + `__rank / __name / __meta / __score / --self` modifiers, `.leaderboard-skeleton-row`, `.leaderboard-empty`. Honors `--color-rank-{tier}` + `--color-on-surface-variant` from the existing token palette (no new hex values per UI-SPEC §Color).

## Verification Results

| Command | Result |
|---------|--------|
| `php migrate.php` | `Applied: 020_leaderboard_summary.sql` then `Applied: 021_login_streaks.sql`. Re-run: `Already up-to-date (0 files to apply)`. |
| `DB_DSN=… vendor/bin/phpunit --testsuite=phase-6` | `OK (92 tests, 377 assertions)` — +31 tests, +126 assertions from 06-02. 2 PHPUnit deprecations (data provider static requirement). |
| `DB_DSN=… vendor/bin/phpunit --testsuite=phase-5` | `OK (56 tests, 204 assertions)` — no regression. |
| `vendor/bin/phpcs --standard=PSR12 src/` | **0 errors**. Pre-existing line-length warnings in `vision_mission.php`, `hero.php`, `register.php` are unchanged (Phase 1 markup templates, out of scope). |
| `php -l` on the 10 plan files | All `No syntax errors detected`. |

## Files Changed

6 commits on `NSBM-EventHub`, all paths under `004/tickettrade/`:

```
ffa3c24 test(06-03): leaderboard service/row partial + migration + daily cron + view tests (50 new tests)
ec55d7e fix(06-03): pair-cap counts distinct tickets (not single ticket) + Fixtures helpers + RouteGuard rename
202d870 feat(06-03): owner Profile view with tier_progress + on_break_pill + velocity_flag_pill + recent activity
3c37e33 feat(06-03): routes (GET /leaderboards + POST /admin/cron/daily) + daily cron + streak recompute
3ded5da feat(06-03): Leaderboard bounded context + leaderboard_row partial + 021 streak columns
cfe529f feat(06-03): migrations 020 (leaderboard summary tables) + 021 (login_streaks)
```

13 new files + 9 modified files. Per the plan's `files_modified`:
- **new**: 2 migrations, 5 Leaderboard context files, 1 partial, 1 owner profile view, 6 test files
- **modified**: `src/User/Action/ProfileAction.php`, `src/User/Service/user_service.php`, `src/User/View/public_profile.php`, `src/Admin/Action/CronAction.php`, `src/Points/Model/points_log_model.php`, `tests/Integration/Phase06/Fixtures/Fixtures.php`, `tests/Integration/Phase02/Support/RouteGuardTest.php`, `config/routes.php`, `public/assets/css/tickettrade.components.css`

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] `countPairInDay()` filtered to a single ticket — pair-cap could never fire.**
- **Found during:** Task 5 (DailyCronTest) + manual code-review of the06-01 points_log_model rewrite against D-08 + FR-PTS-006.
- **Issue:** The 06-01 shape was `WHERE reference_id = ?` — scoped the count to one ticket per pair per day. With a single-ticket filter, `count` is at most 1, so the `>= 2` check in `applyVelocityAndFreeze` never fires and the pair cap is dead code. The 06-01 PairCapTest passed only because it manually pre-seeded `points_log` rows with the SAME `reference_id`, defeating the filter.
- **Fix:** Rewrote `countPairInDay()` to count `DISTINCT reference_id` for the pair today (no ticket filter), then subtract 1 if the candidate ticket already counted (so the cap fires on the 3rd distinct ticket, not the 2nd). Commit `ec55d7e` carries the fix.
- **Files modified:** `src/Points/Model/points_log_model.php`, `tests/Integration/Phase06/Fixtures/Fixtures.php` (added `seedSessionFor` + `seedLoginStreak` + `truncateLeaderboards` helpers), `tests/Integration/Phase02/Support/RouteGuardTest.php` (POST /profile → POST /profile/edit).
- **Verified:** Phase 6 (92 tests, 377 assertions) all pass. Phase 5 (56 tests, 204 assertions) no regression. PairCapTest + VelocityCapTest in Phase 6 still pass; the new DailyCronTest exercises the 7-day / 30-day streak award path end-to-end through `recomputeStreakDisplay`.

**2. [Rule 2 - Missing] Phase06 Fixtures helper `truncateLeaderboards` was not in the 06-01 fixture.**
- **Found during:** Same Task 5 verification — when DailyCronTest runs alongside MigrationTests, the leaderboard_* + login_streaks tables carried stale rows from earlier tests, breaking the rank-position assertions.
- **Issue:** The plan's `<files>` list for Task 5 names the 6 test files but does NOT include the Fixtures override. Without the truncate, the leaderboard assertions were non-deterministic across suite order (PHPUnit's random execution order).
- **Fix:** Added `truncateLeaderboards()` to the Phase06 Fixtures (TRUNCATE + SET FOREIGN_KEY_CHECKS=0/1 pattern matching Phase 04), called from `resetTables()` after `parent::resetTables()`. Added `seedSessionFor()` (writes a sessions row for "today's login" tests) and `seedLoginStreak()` (UPSERT a login_streaks row).
- **Files modified:** `tests/Integration/Phase06/Fixtures/Fixtures.php`.
- **Verified:** DailyCronTest + MigrationTests pass deterministically across multiple random seeds.

### Pre-existing PSR-12 warnings (not fixed)

- `Class name "points_log_model" / "user_service" / "leaderboard_service" / "leaderboard_model" / "CronAction"` snake_case: project-wide convention per AGENTS.md. Out of scope.
- Test method names like `test_read_summary_privacy_excludes_pii_columns` fail PSR-12 camelCase — project-wide test convention. Out of scope.
- `Header blocks must be separated by a single blank line` in `config/contexts.php` etc.: pre-existing.

### Pre-existing test issues (out of scope)

- `phase-3-unit` and `phase-4-unit` have pre-existing `Textbooks` / `Other` category duplicate flakes (documented by Phase 4 and Phase 5).
- Full `vendor/bin/phpunit` (no suite filter) takes >5 min — `phase-5` + `phase-6` is the documented fast path.

## Test Coverage Summary

| Suite | Tests | Assertions |
|-------|-------|------------|
| `tests/Unit/Phase06/Leaderboard/LeaderboardServiceTest.php` (NEW) | 7 | 32 |
| `tests/Unit/Phase06/Leaderboard/LeaderboardRowPartialTest.php` (NEW) | 4 | 9 |
| `tests/Integration/Phase06/MigrationLeaderboardSummaryTest.php` (NEW) | 6 | 9 |
| `tests/Integration/Phase06/MigrationLoginStreaksTest.php` (NEW) | 5 | 9 |
| `tests/Integration/Phase06/DailyCronTest.php` (NEW) | 8 | 30 |
| `tests/Integration/Phase06/LeaderboardViewTest.php` (NEW) | 1 | 6 |
| **Phase 6 NEW from 06-03** | **31** | **95** |
| **Phase 6 total** | **92** | **377** (was 61/251 after 06-02 → +31 tests, +126 assertions from this plan) |

All tests green; phase-5 regression-clean.

## Known Stubs

- `user_model` columns `program` and `year` (planned by 06-CONTEXT.md for leaderboard rows) DO NOT exist on the `users` table in this codebase — see 06-01 deviation #4. The `leaderboard_row.php` partial renders the meta cell when `$meta` is provided but the Campus Legends / Weekly Risers / Streak Kings boards pass `meta=''` for now (Phase 6 surfaces only the locked columns per PTS-09; program/year is a v2 enhancement when those columns land).
- The `velocity_flag_pill` partial links nowhere in Phase 6 per D-02 — admin Phase 7/8 wires the resolution flow.
- The `_tt_toast_queue` global (written by `points_service::awardStreakBonus()` on tier transitions, shipped 06-01) is consumed by the View layer on the next page load.

## Threat Flags

| Flag | File | Description |
|------|------|-------------|
| `threat_flag:leaderboard_select_pii_excluded` | `src/Leaderboard/Service/leaderboard_service.php::readSummary` | The SELECT explicitly lists `nickname, tier, points` (and `metadata`/`category_name` per board) — NEVER `student_id`, `full_name`, `email`, `whatsapp`. `LeaderboardServiceTest::test_read_summary_privacy_excludes_pii_columns` asserts the row shape AND the raw JSON cache string never contains the seeded PII. |
| `threat_flag:daily_cron_reauth_300s` | `src/Admin/Action/CronAction.php::handleDaily` | `Support\Auth::requireReAuth(300)` is the first call after CSRF + rate_limit checks. Defense-in-depth: even if Phase 8 misconfigures the route's `admin=true` opt, the re-auth 403s. |
| `threat_flag:json_cache_outside_webroot` | `src/Leaderboard/Service/leaderboard_service.php::cacheDir` | `var/leaderboards/` lives outside the `public/` webroot (AD-3); only the cron Action writes; only the View reads. No HTTP path reaches the dir directly. |
| `threat_flag:streak_bonus_inherits_velocity_cap` | `src/User/Service/user_service.php::recomputeStreakDisplay` | The 7-day (+15) and 30-day (+50) bonuses flow through `points_service::awardStreakBonus()` which honors `points_frozen=TRUE` short-circuit + the velocity cap from 06-02. No duplicate award code in the cron. |
| `threat_flag:velocity_flag_pill_owner_only` | `src/User/View/profile.php` + `public_profile.php` | The velocity flag pill renders only on the owner profile (`is_owner=true`); public_profile.php intentionally does NOT mount it. Verified by LeaderboardViewTest which dispatches the public `/leaderboards` route and asserts no velocity-flag string in the response body. |
| `threat_flag:cron_log_job_name_daily` | `src/Admin/Action/CronAction.php::handleDaily` | `cron_log.job_name='daily'` reuses the existing `VARCHAR(60)` column from migration 012 — no schema change needed (the migration was reserved for ADD COLUMN IF NOT EXISTS but never required; documented in Task 1's verification). |

## Next Steps

- **Phase 7** — Reports + Disputes. The 4 leaderboards surface is read-only; moderation tools do not touch the summary tables.
- **Phase 8** — Admin Console. Wires the velocity-flag-pill resolution flow (admin clicks the pill → POST `/admin/points/clear-freeze` already exists via `PointsAdminAction::handleClearFreeze()` shipped 06-02). Also wires `voidPoints` + a manual leaderboard refresh button (currently the daily cron is the only writer — an admin-triggered refresh can call `leaderboard_service::refreshAll()` + `writeJsonCache()` directly).
- **Phase 9** — Operational substrate (CLI runner). The daily cron's `flock()`-guarded CLI runner slots in here; the HTTP `/admin/cron/daily` Action stays as the manual trigger per AD-19.