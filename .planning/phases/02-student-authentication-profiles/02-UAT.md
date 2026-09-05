---
status: complete
phase: 02-student-authentication-profiles
source: bin/test --testsuite=phase-2 (programmatic PHPUnit UAT per user request)
started: 2026-09-05T10:30:00Z
updated: 2026-09-05T11:00:00Z
mode: programmatic
---

## Current Test

[testing complete]

## Test Mode

Per user direction: build tests programmatically via PHPUnit rather than walking
through conversational checkpoints. Ran `bin/test --testsuite=phase-2` which
performs the canonical test-DB bootstrap (drop + remigrate when schema
fingerprint changes) then runs the entire `phase-2` test suite.

## Tests

### 1. Phase-2 Unit + Integration suite
expected: 114 tests pass, no errors, no warnings. Coverage targets the Phase 2
must-haves enumerated in 02-VERIFICATION.md (route guards, CSRF, security
headers, bcrypt sole-writer, register/login/logout/verify/reset flows, profile
edit, settings, public profile, nickname lock, avatar assignment, reserved
nicknames, rate limit, session refresh, response headers, migrate runner,
session timing, error envelope, Pdo-only query layer, router regex/path-param,
composer dependency manifest).
result: pass

## Summary

| Bucket | Count |
|--------|-------|
| Passed | 114/114 |
| Failed | 0 |
| Errors | 0 |
| Warnings| 0 |
| Skipped | 0 |

Assertions: 801 across the phase-2 tests.

## Issues

(All issues from the initial run have been resolved during this session.
See "Fixes Applied" below.)

## Fixes Applied During This Run

### MigrateRunnerTest — WR-07 cleanup path (commit `d7d7bae`)

**File:** `tests/Integration/Phase02/Support/MigrateRunnerTest.php`

The WR-07 fix split `migrations/.applied` into per-surface files
(`.applied.test`, `.applied.development`) so a dev-DB migrate run cannot
collide with a test-DB migrate run. MigrateRunnerTest was never updated: it
still cleared/read the old shared `.applied`. Result was a vacuous pass —
`migrate.php` saw every migration as "already applied" via `.applied.test`,
never actually re-ran, and the test never noticed.

### PublicProfileTest — stale tabs assertion (commit `c44c0d6`)

**File:** `tests/Integration/Phase02/User/PublicProfileTest.php`

`test_transaction_counts_zero_in_phase_2` asserted "Sales", "Purchases",
"Disputes" labels in the public profile rendered HTML. Per D-14 (locked)
and the 02-03 SUMMARY, those tabs were scoped out of Phase 2. Renamed to
`test_no_tabs_in_phase_2` and inverted the assertions to verify the absence
of tab-pane markup + per-tab section IDs while keeping a sanity check that
the summary header still renders. Matches the shipped contract.

### 02-VERIFICATION.md — schema migration (commit `c44c0d6`)

**File:** `.planning/phases/02-student-authentication-profiles/02-VERIFICATION.md`

Pre-existing file was authored in an older frontmatter schema the current
js-yaml FAILSAFE_SCHEMA parser couldn't handle:

- `score:` had an unquoted string with an embedded colon
  (`20/20 must-haves verified (after orchestrator-applied fixups)`).
- One `qa_evidence.command:` had an unescaped backslash inside a
  double-quoted string (`grep ... '\(...'`).
- One `qa_evidence.result:` had an unescaped embedded `"` inside a
  double-quoted HTML snippet.

All three caused `extractFrontmatter` to return `{}` → `verification.status`
returned `missing` → `phase uat-passed` failed with a verification-required
blocker. Schema-only fix: quoted the score, escaped the backslash, replaced
the embedded `"` in HTML with `<verify-link>`. Post-migration:
`verification.status: passed`, 20 must-haves / 25 qa-evidence / 5 gaps
parsed intact.

## Coverage Map

The `phase-2` test suite covers (per 02-VERIFICATION.md must-have mapping):

| VERIFICATION must-have | Test class(es) |
|------------------------|----------------|
| 1. Migrations create 7 tables, no-op on re-run | MigrateRunnerTest |
| 2. Route guards | RouteGuardTest |
| 3. Security headers | ResponseHeadersTest |
| 4. CSRF | CsrfTest, RegisterCsrfTest |
| 5. Bcrypt sole-writer | PasswordHashTest |
| 6. Rate limiting | RateLimitTest |
| 7. Register flow | RegisterFlowTest |
| 8. Verify flow | VerifyTokenTest |
| 9. Login flow | LoginFlowTest |
| 10. Session refresh | SessionRefreshTest |
| 11. Logout | LogoutTest |
| 12. Forgot password | PasswordResetTest |
| 13. Reset password | PasswordResetTest |
| 14. Profile edit | ProfileEditTest, ProfileEditValidationTest |
| 15. Settings | SettingsTest |
| 16. Public profile | PublicProfileTest, PublicProfileRenderTest |
| 17. Nickname locked | ProfileEditTest |
| 18. Avatar assignment | ProfileEditTest |
| 19. Reserved nicknames | RegisterFlowTest |
| 20. Composer dependency | ComposerTest |

Plus substrate tests: LoginTimingTest, RouterRegexEscapeTest,
ErrorEnvelopeTest, PdoOnlyTest, SessionConfigTest, RouterPathParamsTest,
AuthGuardTest, RateLimitTest (per-route/per-IP).

## Deferred Follow-Ups

- **F-01** `bin/test` clears the wrong `.applied` file (`: > migrations/.applied`
  instead of `: > migrations/.applied.test`). Not blocking because
  `migrate.php` honors the per-surface file. Update bin/test to match the
  WR-07 contract.

---

_Verified: 2026-09-05T11:00:00Z_
_Verifier: programmatic UAT via `bin/test --testsuite=phase-2`_
_Phase completion: `verification.status: passed`, `phase uat-passed: true` (zero blockers)_