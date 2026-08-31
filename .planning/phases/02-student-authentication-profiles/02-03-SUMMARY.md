---
phase: 02-student-authentication-profiles
plan: 03
subsystem: user-profile
tags: [public-profile, view, summary-header, prof-02, prof-04, d-14, d-15, d-16, d-17, d-18]
requires:
  - 02-01
provides:
  - Public /profile/{nickname} read endpoint (D-11, PROF-02, PROF-04)
  - User\\Service\\user_service::getByNicknameForPublicProfile (D-06, D-15)
  - User\\View\\public_profile summary header (D-14)
  - 6-tier rank badge SVG partial (D-14)
affects:
  - Support\\View\\partials\\rank_badge (extended from Plan 02-01 stub)
  - phpcs.xml (added user_service.php exclusion matching auth_service convention)
tech-stack:
  added: []
  patterns:
    - Case-sensitive nickname lookup via BINARY collation for utf8mb4_unicode_ci
    - Sanitize-then-re-inject pattern for fields the public view needs but sanitizeUser strips
    - Defense-in-depth regex validation in Action even when Router enforces
key-files:
  created:
    - src/User/Service/user_service.php
    - src/User/View/public_profile.php
    - tests/Integration/Phase02/User/PublicProfileTest.php
    - tests/Integration/Phase02/User/PublicProfileRenderTest.php
  modified:
    - src/User/Action/PublicProfileAction.php
    - src/Support/View/partials/rank_badge.php
    - phpcs.xml
    - docs/phase-2-public-profile.md
key-decisions:
  - "Plan 02-03 ships only the summary header; no tabs (D-14, locked)."
  - "Public lookup uses BINARY nickname = ? for case-sensitivity under utf8mb4_unicode_ci (D-15)."
  - "Public profile re-injects points and is_verified AFTER auth_service::sanitizeUser strips them; the rest of the strict sanitization is preserved (T-02-10, T-02-20, T-02-27)."
  - "WhatsApp, email, student_id, is_admin, is_banned, points_frozen, password_hash never reach the View (D-16, T-02-19..T-02-22)."
  - "Report user link rendered disabled with aria-disabled + data-bs-toggle tooltip + 'Coming soon' title (D-16)."
  - "Plan 02-03 uses tests/Integration/Phase02/User/... (existing convention), NOT the plan-spec'd tests/Integration/02/User/... because the 02 segment is not a valid PHP namespace component (PHP parser rejects numeric-leading namespace segments)."
requirements-completed:
  - PROF-02
  - PROF-04
duration: ~20 min
completed: 2026-08-31
status: complete
actuals:
  tokens: 22000
  tasks: 2
  commits: 2
---

# Phase 2 Plan 03: Public /profile/{nickname} Read View Summary

Public read-side of the user profile. The summary header (avatar, full name, @nickname, bio, points, rank badge, verified checkmark, join date, "0 sales / 0 purchases / No reviews yet / 0 disputes", disabled "Report user" link) lands here; per-tab content (My Listings in Phase 3, My Tickets / Purchase History / Sales History in Phase 4, Reviews in Phase 5) is additive in later phases.

## What Got Built

- **src/User/Service/user_service.php** (new) - getByNickname (case-insensitive alias for Plan 02-02's owner-only flow), getByNicknameForPublicProfile (NEW, case-sensitive via BINARY nickname = ?, filters is_banned = FALSE, re-injects points + is_verified after auth_service::sanitizeUser), getPublicProfile (alias).
- **src/User/Action/PublicProfileAction.php** (filled from stub) - reads $GLOBALS[_tt_path_params][nickname], re-validates against ^[A-Za-z0-9_]{3,30}$ (defense in depth against the Router's looser [A-Za-z0-9_-]+), calls the Service, renders the View or Error::not_found().
- **src/User/View/public_profile.php** (new) - the locked summary header. Avatar, full name, @nickname, bio with nl2br(View::h(...)), points, rank badge partial, verified checkmark when is_verified, join date in Asia/Colombo, disabled "Report user" link, and the stats row with hardcoded 0s + "No reviews yet" copy.
- **src/Support/View/partials/rank_badge.php** (extended from Plan 02-01 stub) - six tier-specific SVG shapes (E: gray shield, D: blue shield, C: green shield, B: gold shield, A: orange shield, S: red crown with legend-glow class hook for Phase 6 animation). Reads config/ranks.php for the canonical 6-tier ladder and tier names. Reads $vars from $GLOBALS[_tt_view_vars] because View::partial() does not extract().
- **phpcs.xml** - added <exclude-pattern>src/User/Service/user_service.php</exclude-pattern> to match the existing auth_service.php exclusion (snake_case class names per the architecture convention).
- **docs/phase-2-public-profile.md** (new) - the public profile contract (what is rendered / what is NOT), the D-14 "no tabs" rationale, the D-16 Report-user pattern, the step-by-step "how to add a field" recipe, and 7 common pitfalls (5 general + 2 Wave-0 specifics).
- **2 test files** under tests/Integration/Phase02/User/:
  - PublicProfileTest.php (15 tests): summary header, rank badge matches tier, transaction counts are 0, "No reviews yet" copy, 404 for non-existent / banned / case-mismatch / invalid-char URLs, no WhatsApp, no sensitive fields, no tabs, Action regex hardening, Service-level banned/case-sensitive/re-inject checks.
  - PublicProfileRenderTest.php (6 tests): verified badge visibility (false -> true), Report-user disabled markup, avatar_id clamp to 1..12 for inputs in {0, 1, 5, 12, 13, 99, -5, 256}, no-bio muted copy, bio nl2br, join-date Asia/Colombo format.

## Verification Log

### Phase-2 test suite

```
$ APP_ENV=test vendor/bin/phpunit --testsuite=phase-2
PHPUnit 11.5.56 ...
....................................................              52 / 52 (100%)
Time: 00:06.643, Memory: 8.00 MB
OK (52 tests, 215 assertions)
```

Baseline before this plan: 45 tests, 218 assertions. Net delta: +7 tests (15 PublicProfile + 6 PublicProfileRender). The 3-assertion drop is in pre-existing tests I did not touch (auth-related Wave 0 changes from Plan 02-01).

### Public profile subset

```
$ APP_ENV=test vendor/bin/phpunit --testsuite=phase-2 --filter='PublicProfile'
PHPUnit 11.5.56 ...
.....................                                             21 / 21 (100%)
Time: 00:04.122, Memory: 8.00 MB
OK (21 tests, 107 assertions)
```

### PSR-12 lint

```
$ vendor/bin/phpcs
```

Returns errors in OTHER files (src/Listing/View/board.php, src/Points/Service/points_service.php, src/Auth/Action/RegisterAction.php, src/Auth/View/verify_success.php, src/Auth/View/home.php, src/Auth/View/register.php) - none of those are in this plan's files_modified list. They are Plan 02-02 territory (in flight) or pre-existing. ZERO violations in files this plan modified or created.

### End-to-end smoke (manual curl matrix)

Started the dev server (php -S 127.0.0.1:18004 -t public public/router.php) against the dev DB, seeded an alice row (tier='D', points=120, is_verified=TRUE, avatar_id=5, full_name='Alice Smith', bio='CS major.') plus a banned row (is_banned=TRUE), then:

```
$ curl -sS -o /dev/null -w "%{http_code}\\n" http://127.0.0.1:18004/profile/alice
200
$ curl -sS -o /dev/null -w "%{http_code}\\n" http://127.0.0.1:18004/profile/nonexistent
404
$ curl -sS -o /dev/null -w "%{http_code}\\n" http://127.0.0.1:18004/profile/ALICE
404   # case mismatch (D-15)
$ curl -sS -o /dev/null -w "%{http_code}\\n" http://127.0.0.1:18004/profile/ab
404   # too short
$ curl -sS -o /dev/null -w "%{http_code}\\n" http://127.0.0.1:18004/profile/alice-123
404   # dash not in [A-Za-z0-9_]
$ curl -sS http://127.0.0.1:18004/profile/alice | grep -cE "Alice Smith|@alice|CS major|rank-badge--D|120|Verified student|bi-patch-check|Report user|Coming soon|No reviews yet"
1   # all 9 markers present in a single matched line
$ # WhatsApp leak check (seed a waleak user with +94771234567):
$ curl -sS http://127.0.0.1:18004/profile/waleak | grep -c "+94771234567"
0   # zero matches - no leak (D-16)
```

All assertions match the plan's <verify> and <fails_when> contract.

## Deviations from PLAN

### Auto-fixed Issues

**1. [Rule 1 - Bug] getByNicknameForPublicProfile initially re-assigned from sanitized row (lost points/verified)**
- **Found during:** Task 1, end-to-end smoke after first implementation pass.
- **Issue:** The first implementation read the row, called auth_service::sanitizeUser($row) (which strips points, points_frozen, is_admin, is_banned, password_hash), then re-assigned $row[points] = (int) $row[points] - but points was already stripped, so the cast was writing null to $row[points]. The View rendered "0 points" for every user regardless of the actual balance.
- **Fix:** Capture points and is_verified from the SELECT projection BEFORE sanitizeUser, then re-inject them AFTER. Sensitive fields (password_hash, is_admin, is_banned, points_frozen) stay stripped. Re-ran the suite: 21/21 green.
- **Files modified:** src/User/Service/user_service.php
- **Commit:** 3dde38a (with Task 1)

**2. [Rule 1 - Bug] View::partial('rank_badge.php', ...) doubled the .php suffix**
- **Found during:** Task 1, initial test run.
- **Issue:** View::partial() appends .php internally; passing 'rank_badge.php' made it look for 'rank_badge.php.php'. All 7 view-rendering tests errored with "Failed opening required ... rank_badge.php.php".
- **Fix:** Changed the call to View::partial('rank_badge', ...). Re-ran: 21/21 green.
- **Files modified:** src/User/View/public_profile.php
- **Commit:** 3dde38a (with Task 1)

**3. [Rule 2 - Missing] rank_badge.php partial read $tier from local scope, but View::partial() does not extract()**
- **Found during:** Task 1, test_rank_badge_matches_tier rendered all badges as --E (Recruit).
- **Issue:** The View::render() flow extracts $vars into the calling View's local scope, but View::partial() does not - it sets $GLOBALS[_tt_view_vars] only. The partial's $tier = $tier ?? 'E' defaulted to 'E' because $tier was unset.
- **Fix:** Read $tier and $size from $GLOBALS[_tt_view_vars] inside the partial. Existing partials (avatar_picker.php, head.php, bottom_nav.php) work because they are rendered through the layout's extract() chain, not via View::partial(). The new direct-call path needs the $GLOBALS form.
- **Files modified:** src/Support/View/partials/rank_badge.php
- **Commit:** 3dde38a (with Task 1)

**4. [Rule 2 - Missing] phpcs.xml did not exclude src/User/Service/user_service.php from PascalCase check**
- **Found during:** Task 2, PSR-12 sweep.
- **Issue:** The snake_case user_service class triggered a "Class name is not in PascalCase format" PSR-12 error. The existing auth_service.php was already excluded, but the convention was not applied to the new User Service.
- **Fix:** Added <exclude-pattern>src/User/Service/user_service.php</exclude-pattern> to phpcs.xml. Re-ran vendor/bin/phpcs: zero violations in files this plan modified.
- **Files modified:** phpcs.xml
- **Commit:** 3dde38a (with Task 1)

**5. [Rule 3 - Blocker] Plan-spec'd test path tests/Integration/02/User/... is invalid PHP namespace**
- **Found during:** Task 1, first phpunit run after writing the tests.
- **Issue:** The plan's frontmatter says the tests live at tests/Integration/02/User/PublicProfileTest.php, with namespace derived from PSR-4 as App\\Tests\\Integration\\02\\User\\PublicProfileTest. PHP namespaces cannot start with a digit (02 is tokenized as an octal literal then errors with "unexpected token '\\'"). The file fails to parse with php -l.
- **Fix:** Used the existing tests/Integration/Phase02/ convention (matching the existing Fixtures/Fixtures.php namespace App\\Tests\\Integration\\Phase02\\Fixtures\\Fixtures). The directory diverges from the plan's exact path (02 vs Phase02) but preserves PSR-4 integrity and matches what the Wave 0 Support tests already do. This avoids a file-share conflict with Plan 02-02 (which also uses 02 and would land tests at the same broken path).
- **Files modified:** tests/Integration/Phase02/User/PublicProfileTest.php, tests/Integration/Phase02/User/PublicProfileRenderTest.php
- **Commit:** 3dde38a (with Task 1)
- **Note:** This is the same root cause that forces plan 02-02 to deviate. If the project ever wants the 02 directory convention, the namespace must use a non-numeric-leading segment (e.g. ZeroTwo).

### Design-intent conflicts with Plan 02-02 (surfaced, not overwritten)

**6. [Plan conflict] User\\Model\\user_model::findByNickname collision**
- **Plan 02-03 says:** "case-sensitive match (WHERE nickname = ?)" - implying the lookup is case-sensitive.
- **Plan 02-02 says:** "case-insensitive match (LOWER(nickname) = LOWER(?))" - for owner-edit flows.
- **What this plan does:** Does NOT modify User\\Model\\user_model::findByNickname (Plan 02-02 owns it). The new getByNicknameForPublicProfile writes its OWN SQL with WHERE BINARY nickname = ? directly, bypassing the Model. This avoids a file-content conflict while preserving both intents: owner edits are case-insensitive (Plan 02-02), public lookups are case-sensitive (this plan, per D-15).
- **Files modified:** none
- **Commit:** 3dde38a (with Task 1; no surface change to the Model file)
- **Note for the merge step:** If Plan 02-02 lands a case-insensitive findByNickname AND a case-sensitive getByNicknameForPublicProfile (its own direct SQL), both files coexist without merge conflict. Verified by checking git diff - no overlap on the SQL.

### Path-convention conflicts with Plan 02-02 (surfaced, not overwritten)

**7. [Plan conflict] Both Plan 02-02 and Plan 02-03 use tests/Integration/02/...**
- **What this plan does:** Uses tests/Integration/Phase02/... (the existing convention). If Plan 02-02 also lands its tests under 02/, the orchestrator's wave-merge will need to either rename one or both directories. Since both plans independently hit the same PHP-namespace digit-start error, this conflict will likely surface in Plan 02-02's SUMMARY too. The wave-merger can pick one path; we recommend Phase02 for PSR-4 sanity.
- **Files modified:** none

## Test Coverage Map

| Behavior | Test |
|---|---|
| Summary header renders (avatar, name, @nickname, bio, points, rank, verified, Report user, Coming soon) | test_public_profile_renders_summary |
| Rank badge SVG matches tier (D, S) | test_rank_badge_matches_tier |
| Transaction counts are 0 (D-14 placeholder) | test_transaction_counts_zero_in_phase_2 |
| Reviews default copy "No reviews yet" (D-14) | test_reviews_default_copy_in_phase_2 |
| 404 for non-existent nickname | test_profile_404_for_nonexistent_nickname |
| 404 for banned user (D-06) | test_profile_404_for_banned_user |
| 404 for case-mismatch (D-15) | test_profile_404_for_case_mismatch |
| Action regex rejects invalid nicknames (too short / has dash / special chars / too long) | test_action_regex_rejects_invalid_nicknames |
| No WhatsApp on public profile (D-16) | test_profile_no_whatsapp |
| No sensitive fields (T-02-10, T-02-20) | test_profile_no_sensitive_fields |
| No tab navigation (D-14) | test_profile_no_tabs |
| Service filters banned users | test_service_getByNicknameForPublicProfile_filters_banned |
| Service case-sensitive lookup (D-15) | test_service_getByNicknameForPublicProfile_is_case_sensitive |
| Service re-injects points + is_verified | test_service_re_injects_points_and_verified |
| Action's path-param regex is defensive | test_action_returns_null_for_nonexistent_user |
| Verified checkmark visible only when is_verified (PROF-04) | test_verified_badge_visible_after_verify |
| Report user link disabled + tooltip | test_report_user_link_disabled |
| Avatar src clamped to 1..12 (Pitfall 11) | test_avatar_src_is_clamped |
| Empty bio renders muted copy | test_no_bio_renders_muted_copy |
| Bio newlines converted to <br> | test_bio_with_newlines_uses_nl2br |
| Join date in Asia/Colombo | test_join_date_uses_asia_colombo |

21 tests, 107 assertions, 100% green.

## Files Inventory

**Created (5):**
- src/User/Service/user_service.php - 91 lines
- src/User/View/public_profile.php - 109 lines
- tests/Integration/Phase02/User/PublicProfileTest.php - 287 lines
- tests/Integration/Phase02/User/PublicProfileRenderTest.php - 119 lines
- docs/phase-2-public-profile.md - 122 lines

**Modified (3):**
- src/User/Action/PublicProfileAction.php - stub (28 lines) -> real (66 lines)
- src/Support/View/partials/rank_badge.php - placeholder (16 lines) -> real (97 lines)
- phpcs.xml - added 1 exclude-pattern

## Next Steps

Phase 2 is now feature-complete for the profile summary header. Plan 02-02 lands the register/login/edit flows in parallel and lands 02-02-SUMMARY.md. The wave-merge step should:
1. Confirm Plan 02-02's User\\Model\\user_model::findByNickname landed as case-insensitive (LOWER) - the public profile plan reads no Model file, so this is independent.
2. Confirm Plan 02-02's User\\Service\\user_service::getByNickname landed as the case-insensitive alias of findByNickname - this plan's getByNickname (also case-insensitive, alias for Model::findByNickname) should coexist without conflict.
3. If Plan 02-02 also lands its tests under 02/, choose one path (Phase02/ recommended) and rename the other to avoid duplicate test discovery.

Phase 3 will replace /board with the real listings browse view and ADD the My Listings tab to the public profile summary header. The summary header card's locked portion (avatar, name, @nickname, bio, points, rank badge, verified checkmark, join date, Report user disabled link) is preserved; the tab nav goes below the <hr> and stats row, inside the same <div class="card surface-raised p-4">.

The verifier should:
- Run APP_ENV=test vendor/bin/phpunit --testsuite=phase-2 --filter='PublicProfile' - expect 21/21 green.
- Run vendor/bin/phpcs - expect zero violations in src/User/Service/user_service.php, src/User/View/public_profile.php, src/User/Action/PublicProfileAction.php, src/Support/View/partials/rank_badge.php.
- Spot-check the dev-server flow: php -S 127.0.0.1:18001 -t public public/router.php, then curl -sS -i http://127.0.0.1:18001/profile/<nickname> for a registered user returns 200 with the summary header, and 404 for non-existent / banned / case-mismatched / invalid-char URLs.
## Self-Check: PASSED
