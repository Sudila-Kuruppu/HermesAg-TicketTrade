---
phase: "05"
slug: "reviews-ratings"
status: validated
nyquist_compliant: true
wave_0_complete: true
created: "2026-09-04"
---

# Phase 5 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution. Reconstructed from `05-01-SUMMARY.md` + `05-02-SUMMARY.md` + `05-VERIFICATION.md` (State B — no prior `*-VALIDATION.md`).

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11.5.56 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `APP_ENV=test vendor/bin/phpunit --testsuite=phase-5-unit` (~7s) |
| **Full suite command** | `APP_ENV=test vendor/bin/phpunit --testsuite=phase-5` (~50s) |
| **Estimated runtime** | ~50 seconds (combined unit + integration) |

Notes:
- Test DB bootstrap: `bin/dev-setup.sh` creates `tickettrade_test`; `bin/test` rebuilds schema when `data/.test-schema-fingerprint` changes.
- `tests/Integration/Phase04/Fixtures/Fixtures.php` adds `'reviews'` to the TRUNCATE list (Rule 2 fix from 05-01) to prevent flaky UNIQUE collisions on `(ticket_id, reviewer_role)`.
- PHPUnit runs against MariaDB on `/tmp/mysql.sock`; `config/db.test.php` is the DSN source.

---

## Sampling Rate

- **After every task commit:** `APP_ENV=test vendor/bin/phpunit --testsuite=phase-5-unit`
- **After every plan wave:** `APP_ENV=test vendor/bin/phpunit --testsuite=phase-5`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** ~50 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 05-01-T1 | 01 | 1 | RAT-01..04, AD-15, AD-16 | T-05-01 | AD-15 gate (status ∈ {redeemed,expired} AND dispute_status='none') inside one transaction | integration | `vendor/bin/phpunit --testsuite=phase-5-integration --filter=MigrationTest` | ✅ | ✅ |
| 05-01-T1 | 01 | 1 | RAT-03, AD-2 | — | sole-writer of `reviews` per AD-2; SQLSTATE 23000 → E_REVIEW_ALREADY_LEFT | integration | `vendor/bin/phpunit --filter=ReviewGate` | ✅ | ✅ |
| 05-01-T2 | 01 | 1 | PTS-04, FR-PTS-007, FR-PTS-010, FR-RAT-001 | T-05-02 | +10 only on commentLength ≥ 50; first-5 halving; points_frozen skip; participates in outer txn | unit | `vendor/bin/phpunit --testsuite=phase-5-unit --filter=AwardReviewPoints` | ✅ | ✅ |
| 05-01-T3 | 01 | 1 | RAT-01..06, SEC-06, NFR-SEC-007 | — | 10/hr/user rate limit; CSRF on POST; 6 error codes flash-toast + 302 | integration | `vendor/bin/phpunit --filter=ReviewAction` | ✅ | ✅ |
| 05-01-T4 | 01 | 1 | RAT-01, RAT-06, D-01, D-03 | — | fieldset of 5 hidden radios + 24px bi-star labels + visually-hidden legend; Clear link resets | integration | `vendor/bin/phpunit --filter=StarRatingInput` | ✅ | ✅ |
| 05-02-T1 | 02 | 2 | RAT-02, RAT-05, D-07, D-09 | T-05-03 | aggregation read in two prepared statements; `dispute_status='upheld'` filter only | integration | `vendor/bin/phpunit --filter=ProfileAggregation` | ✅ | ✅ |
| 05-02-T2 | 02 | 2 | RAT-02, RAT-03, FR-RAT-003, PROF-02, PROF-03, D-08 | T-05-04 (privacy_pii) | reviewer nickname only (NEVER full_name); pagination offset 0..1000 clamped; empty state copy | integration | `vendor/bin/phpunit --filter=ReviewsTab` | ✅ | ✅ |
| 05-02-T3 | 02 | 2 | RAT-03, RAT-05, D-09 | — | compact rating + compact dispute fragments gated INDEPENDENTLY (0-reviews-but-N-disputes path) | integration | `vendor/bin/phpunit --filter=ListingModalRating` | ✅ | ✅ |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] `phpunit.xml` — testsuites `phase-5-integration`, `phase-5-unit`, `phase-5` registered
- [x] `tests/Integration/Phase04/Fixtures/Fixtures.php` — `'reviews'` added to TRUNCATE list
- [x] `bin/dev-setup.sh` + `bin/test` — DB bootstrap + suite entry point
- [x] PHP framework: `phpunit/phpunit` (in `require-dev` per `composer.json`); no further install needed

*Existing infrastructure covers all phase requirements.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Star-rating hover preview + focus preview + Clear button visual state | RAT-06, D-01 | JS-driven CSS `:checked + label` icon swap requires browser eye to confirm | Open `/purchases`; click `Leave review` on a redeemed ticket; hover across 5 stars; confirm fill from 1..N; Tab to focus a radio; press ←/→; confirm cycle; click Clear; confirm all stars empty |
| Review modal `:has()` CSS-only submit-enable when rating is checked | RAT-01 | CSS-only state binding requires visual confirmation | Open review modal without rating → Submit disabled. Select 1 star → Submit enabled. Clear → Submit disabled again |
| 60-char comment textarea live char counter ("N chars" updating on each keystroke) | RAT-01, D-03 | JS-driven character counter requires browser interaction | Open review modal; type into comment; confirm counter updates per keystroke; reach 2000 char limit; confirm stop |
| Bootstrap Modal `data-scrim-guard="2"` 2-second backdrop suppression | D-04 (Phase 4 carryover) | JS backdrop-click timing — markup asserted only | Open review modal; click backdrop within 2s; modal stays open; click after 2s; modal closes |
| Per-row review modal renders inside `/purchases` mobile card layout | RAT-01, D-03 | View rendering at small breakpoint requires visual check | Resize browser to ≤480px; navigate `/purchases`; confirm modal opens from mobile-card `Leave review` button |

*Core business logic (AD-15 gate, 14-day window, points halving, aggregation, pagination, dispute-only-when-no-reviews edge case) is fully covered by integration tests. Visual/JS behaviors are the only manual-only verifications and follow from the markup contracts verified by View tests.*

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references (existing infra: phpunit + dev-setup + Fixtures)
- [x] No watch-mode flags
- [x] Feedback latency ~50s for full phase-5 suite (under reasonable threshold)
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved 2026-09-04

---

## Validation Audit 2026-09-04

| Metric | Count |
|--------|-------|
| Test files | 7 (1 migration + 1 debug + 3 review + 2 profile + 1 listing + 1 points unit) |
| Tests | 56 (Plan 05-01: 31; Plan 05-02: 25) |
| Assertions | 204 |
| Gaps found | 0 |
| Resolved | n/a |
| Escalated to manual-only | 5 (visual/JS UX — see table) |
| Phase 5 full-suite result | OK (56 tests, 204 assertions) |

State B reconstruction from SUMMARY artifacts + live green run confirmed all 8 must-have tasks map to existing automated tests; no new test files generated.

Coverage cross-check:
- RAT-01..06 + PROF-02 + PROF-03 + PTS-04 + SEC-06 — all 10 requirement IDs satisfied.
- All 21 PLAN must-have artifacts verified present (see `05-VERIFICATION.md` §Required Artifacts).
- All 9 key-link wirings verified.
- All 7 data-flow traces verified FLOWING (no hardcoded data in render paths).
