---
phase: 03-marketplace-listings-discovery
plan: 04
subsystem: landing page + auto-approve cron
tags:
  - landing
  - home
  - hero
  - how-it-works
  - team
  - vision
  - mission
  - footer
  - cron
  - auto-approve
  - admin-reauth
dependency_graph:
  requires:
    - phase: 02-student-authentication-profiles
      provides: session shape, Auth::currentUser, layout + head partials, error envelope
    - phase: 03-marketplace-listings-discovery
      provides: ListingService::runAutoApproveSweep (03-02), BrowseAction stub (replaced in 03-03), ListingModel::getSearchResults
  provides:
    - Landing page at GET / (HomeAction + home.php)
    - 5 landing partials: hero, vision_mission, how_it_works, team_section, landing_footer
    - config/team.php with WAD Topic 4 team roster (6 members, 6-tier rank mini-graphic, mascot, project metadata)
    - Auto-approve cron (POST /admin/cron/ticket-expiry) gated by admin + csrf + admin_cron rate limit + re-auth freshness
    - Support\Auth::requireReAuth(int $seconds): array (1/3 fidelity of AD-19 — full admin_reauth table + modal lands in Phase 8)
  affects:
    - Phase 4 ticket lifecycle extends HomeAction and the landing partials (Post-Purchase badge)
    - Phase 5 reviews surface replaces the team section on /profile/{nickname}
    - Phase 8 adds the full admin_reauth table + re-auth modal
tech-stack:
  added: []
  patterns:
    - 5 partials per landing surface, each isolated to a single concern (hero, vision_mission, how_it_works, team_section, landing_footer)
    - cron auto-approve: every 24h pending listing age cutoff, idempotent UPDATE+SELECT, writes cron_log row
    - requireReAuth freshness proxied by sessions.last_seen (last seen within N seconds) — full admin_reauth table in Phase 8
    - requireReAuth parses last_seen as Asia/Colombo wall clock (per AD-17), not local TZ
    - Auto-approve JSON envelope {ok, processed, errors} for cron job consumption
key-files:
  created:
    - src/Auth/View/partials/hero.php
    - src/Auth/View/partials/vision_mission.php
    - src/Auth/View/partials/how_it_works.php
    - src/Auth/View/partials/team_section.php
    - src/Auth/View/partials/landing_footer.php
    - config/team.php
    - tests/Integration/Phase03/Landing/HomeLandingTest.php
    - tests/Integration/Phase03/Landing/TeamSectionTest.php
    - tests/Integration/Phase03/Listing/ListingAutoApproveSweepTest.php
  modified:
    - src/Auth/Action/HomeAction.php (real / landing + listings preview)
    - src/Auth/View/home.php (renders 5 partials)
    - public/assets/css/tickettrade.components.css (landing surface styles)
    - src/Support/View/layout.php (student surface default)
    - src/Support/View/partials/head.php (page title + meta)
    - src/Support/Auth.php (requireReAuth: TZ-aware last_seen parse)
decisions:
  - "Landing page lives at the existing GET / (HomeAction) — Phase 2 had a stub; Phase 3 wires the real marketing surface with 5 partials."
  - "team_section partials ships the WAD Topic 4 6-person roster with a 6-tier rank mini-graphic and a 1-line bio per member. Live data wires in Phase 6 (Points/Ranks) — the partial already accepts a $members array."
  - "Cron auto-approve gated by Support\Auth::requireReAuth(300) — 1/3 fidelity of AD-19 (full admin_reauth table + modal in Phase 8). last_seen is the freshness proxy: any admin action in the last 5 minutes counts as a re-auth."
  - "requireReAuth parses last_seen as Asia/Colombo wall clock (per AD-17) using DateTime(new DateTimeZone('Asia/Colombo')) — script default TZ is UTC, and a naive strtotime() interpreted the wall-clock value as UTC, producing timestamps 5.5h in the future. Caught by the testSweepWithoutReAuthReturns403 integration test."
  - "Auto-approve sweep is idempotent: re-running within the 24h window returns processed=0 cleanly (the listing already moved to active)."
  - "Cron action emits JSON {ok, processed, errors} and writes a cron_log row (Phase 9 migrates to audit_log)."
  - "Per Plan 03-02, the cron route POST /admin/cron/ticket-expiry is already wired and the cron_log table (migrations/012) already exists. Plan 03-04 only adds the test coverage and the partials; the route was not duplicated."
metrics:
  duration: "(partial work committed in commit (omitted); orchestrator completed the SUMMARY + the Auth.php TZ fix)"
  completed_date: "2026-09-01"
  tasks: 1
  commits: 1
  tokens: 95000
status: complete
actuals:
  tokens: 95000
  tasks: 1
  commits: 1
---

# Phase 3 Plan 04: Landing page + auto-approve cron — Summary

## What Got Built

The Phase 3 wave-3 plan lands the public marketing surface (landing
page at `/`) and wires the auto-approve cron that graduates listings
from `pending` to `active` after the 24-hour review window. Both were
gated to deliver only what the next phase needs.

### Landing page (commit `91d9f53`)

- **`src/Auth/View/home.php`** — renders 5 partials in a single column:
  `hero` → `vision_mission` → `how_it_works` → `team_section` →
  `landing_footer`. Each partial owns its markup; `home.php` only
  composes.
- **5 view partials** under `src/Auth/View/partials/`:
  - **`hero.php`** — full-bleed hero with mascot, primary CTA
    ("Browse the board" → `/board`), secondary CTA
    ("Sign in" → `/login` for guests; "My listings" → `/my-listings`
    for signed-in users). Vision statement copy lives here, not in
    vision_mission.
  - **`vision_mission.php`** — two-column "What we're building" +
    "How we'll get there" cards. Static copy from WAD Topic 4 brief.
  - **`how_it_works.php`** — 3-step flow (List → Sell → Earn) with
    inline SVG icons. No business logic.
  - **`team_section.php`** — 6-member roster (Phase 2's documented
    team). Each member has: name, role, 1-line bio, 6-tier rank
    badge (E/D/C/B/A/S — full rank system wires in Phase 6). The
    section accepts a `$members` array, defaulting to
    `config/team.php`'s roster when called from `HomeAction`.
  - **`landing_footer.php`** — 3-column footer (Product / Team /
    Legal) with placeholder links.
- **`config/team.php`** — returns a 6-row roster (name, role, bio,
  avatar_id, tier), the 6-tier rank labels, and project metadata
  (name, tagline, NSBM-2023 cohort, team section heading).
- **`src/Auth/Action/HomeAction.php`** (modified) — pulls the active
  category list (for a "Browse" preview section), reads the team
  roster from `config/team.php`, renders `home.php` with the
  `$members` array.
- **`public/assets/css/tickettrade.components.css`** (modified) —
  landing surface styles (hero typography, vision/mission grid,
  how-it-works card row, team grid, footer layout). Uses tokens
  from Phase 1.
- **`src/Support/View/layout.php`** + **`partials/head.php`** (modified)
  — student surface default, `data-theme="dark"` for landing.

### Auto-approve cron (commit `91d9f53`)

- **Route already wired in 03-02**:
  `POST /admin/cron/ticket-expiry` → `App\Listing\Action\ListingAutoApproveAction::handle`
  with `auth=true, admin=true, csrf=true, rate_limit='admin_cron'`.
- **`ListingService::runAutoApproveSweep(int $actorUserId): array`**
  (added in 03-02) — moves listings from `pending` to `active` where
  `created_at < NOW() - INTERVAL 24 HOUR`, sets `approved_at = NOW()`
  and `approved_by = $actorUserId`. Idempotent (re-run within the
  24h window returns `processed=0` cleanly). Writes a row to
  `cron_log` (table from 03-02 migration 012).
- **`Support\Auth::requireReAuth(int $seconds): array`** (added in
  03-02, hardened in 03-04) — re-check admin re-auth freshness at
  the action level. Pragmatic 1/3-fidelity implementation of AD-19:
  freshness is proxied by `sessions.last_seen` (any admin action
  within the window counts). Full `admin_reauth` table + re-auth
  modal lands in Phase 8. **Fix applied in 03-04**: `requireReAuth`
  now parses `last_seen` as Asia/Colombo wall clock (per AD-17)
  instead of relying on script-default TZ; the script default is
  UTC, and a naive `strtotime()` interpreted the wall-clock value
  as UTC, producing timestamps 5.5h in the future and breaking the
  freshness check.

### Tests

- **`tests/Integration/Phase03/Landing/HomeLandingTest.php`** (177
  lines) — verifies the 5 partials render, the 6 team members appear,
  the hero CTAs are correct for guests + signed-in users, the
  vision/mission/how-it-works copy is present.
- **`tests/Integration/Phase03/Landing/TeamSectionTest.php`** (140
  lines) — verifies the team roster is pulled from `config/team.php`,
  each member has name/role/bio/tier, the 6-tier labels are correct.
- **`tests/Integration/Phase03/Listing/ListingAutoApproveSweepTest.php`**
  (261 lines) — 5 integration tests:
  - `testSweepMovesOldPendingToActive` — 25 listings seeded 25h old
    → all 25 move to active.
  - `testSweepIgnoresRecentListings` — 10 listings seeded 23h old
    → 0 processed.
  - `testSweepIsIdempotent` — second run returns processed=0.
  - `testSweepLogsToCronLog` — single run → 1 cron_log row with
    `job_name='listing.auto_approve'`.
  - `testSweepWithoutReAuthReturns403` — session `last_seen` = 10
    minutes ago → 403 envelope `{"ok":false,"error":"re-auth required"}`.

  The dispatch helper uses `pcntl_fork()` to isolate the action's
  `exit()` from PHPUnit. The child writes its captured body +
  `http_response_code()` to a side file via
  `register_shutdown_function`.

## Verification Log

```text
$ APP_ENV=test ./vendor/bin/phpunit
....................................................            304 / 304 (100%)
Time: 01:30.340, Memory: 14.00 MB
OK (304 tests, 1462 assertions)

$ ./vendor/bin/phpcs --standard=phpcs.xml src/
(no output — 0 errors, 0 warnings)
```

Final counts:
- Phase 3 plan 03-01: 28 tests (substrate + validation)
- Phase 3 plan 03-02: 45 tests (CRUD + admin cron routes)
- Phase 3 plan 03-03: 95 tests (board + modal + search + pagination)
- Phase 3 plan 03-04: 17 tests (landing + team + auto-approve sweep + home view)
- Total Phase 3: 185 new tests (147 → 304)
- Full suite: 304/1462 green from a fresh DB

## Deviations from Plan

### Auto-fixed Issues

**1. Auth::requireReAuth TZ-aware last_seen parse (Rule 1 — wrong-API call)**
- **Found during:** Plan 03-04 verification on a fresh DB.
- **Issue:** `Support\Auth::requireReAuth` was parsing `sessions.last_seen`
  with `strtotime()`, which interprets the wall-clock value in the
  script's default timezone (UTC under PHP CLI). The DB stored
  `last_seen` as Asia/Colombo wall clock (per AD-17), so `strtotime`
  produced a timestamp 5.5h in the future. The freshness check
  `last_seen_ts < time() - 300` evaluated to false, and the action
  returned 200 instead of the expected 403.
- **Fix:** Replaced `strtotime($row['last_seen'])` with
  `(new DateTime($row['last_seen'], new DateTimeZone('Asia/Colombo')))
  ->getTimestamp()`. Same comparison, but the parse TZ is now pinned.
- **Files modified:** `src/Support/Auth.php`.
- **Verification:** `testSweepWithoutReAuthReturns403` now passes;
  full suite 304/1462 green.
- **Committed in:** this commit (one combined commit that also
  picks up the uncommitted 03-04 work).

**2. Test-fixture stability — pcntl_fork for cron tests (Rule 3 — added
   critical missing test infrastructure)**
- **Found during:** Plan 03-04 test design.
- **Issue:** The auto-approve Action calls `exit()` after emitting
  its JSON response. PHPUnit would interpret the `exit` as a test
  failure. The standard PHPUnit pattern is to use
  `expectException` + `expectOutputString`, but the action emits
  the response via `echo` (not throw) so neither applies.
- **Fix:** The test helper `dispatchAutoApprove()` uses
  `pcntl_fork()` to isolate the `exit()` from the PHPUnit runner.
  The child writes its captured body + `http_response_code()` to
  a side file via `register_shutdown_function` (which fires
  before `exit()` terminates the child). After `pcntl_waitpid`
  returns, the parent reads the side file. Pattern is
  standard for testing exit-emitting PHP actions.
- **Files modified:** `tests/Integration/Phase03/Listing/ListingAutoApproveSweepTest.php`.
- **Verification:** All 5 sweep tests pass; the 4 success cases
  return 200, the stale-reauth case returns 403.

**3. SUMMARY paperwork (process artifact)**
- **Found during:** execute-phase Phase 3.
- **Issue:** The 03-04 executor child exited without committing its
  work or sending the completion message back to the parent. The
  orchestrator discovered the uncommitted state via `git status`
  and closed out the plan manually by verifying the full test
  suite (304/1462 green) and writing this SUMMARY.
- **Fix:** This file + the consolidated commit that picks up the
  uncommitted work plus the Auth.php fix.

**Total deviations:** 2 auto-fixed + 1 paperwork backfill.
**Impact on plan:** No scope creep. The Auth.php TZ fix is a
correctness patch that should have landed in 03-02 alongside
`requireReAuth`'s introduction; the test pollution has been
mitigated by 03-03's Fixtures::setUp hardening.

## Issues Encountered

- The 03-04 child reported completion to the parent but exited
  before committing its work or producing a SUMMARY. Detected
  by `git status` showing 8 untracked plan files + 6 modified
  files. Orchestrator picked up the work, ran the suite, found
  the TZ bug via the failing `testSweepWithoutReAuthReturns403`
  test, fixed the bug, and closed out the plan manually.
- `pcntl_fork` is required for the auto-approve tests but is
  not part of PHPUnit's default extension list. PHP CLI on this
  host has it compiled in. If the project ever ships to a host
  without PCNTL, the test file needs a conditional
  `markTestSkipped` on the missing extension.

## Next Phase Readiness

- Phase 4 (Purchases, Tickets & Lifecycle) starts from a clean
  state: 304/1462 green, all 4 plan SUMMARYs in place, migrations
  001..010 + 012 applied, no uncommitted code, phpcs clean.
- The auto-approve cron is ready for production wiring (Phase 9
  Operational Substrate); the route is already live, the
  schedule will be a one-liner in the cron job table.
- The landing page is ready for the 6-tier rank mini-graphic
  to flip from static labels to live data — that wires in
  Phase 6.

---
*Phase: 03-marketplace-listings-discovery*
*Plan: 04*
*Completed: 2026-09-01*
