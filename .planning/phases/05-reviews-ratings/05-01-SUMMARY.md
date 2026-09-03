---
phase: 05-reviews-ratings
plan: 01
subsystem: reviews
tags: [reviews, points, action, view, modal, star-input, AD-15, AD-16, D-01..D-06]
requires: []
provides:
  - reviews-table
  - submit-review-action
  - star-rating-input-component
  - award-review-points
  - review-modal-view
affects:
  - purchases-view
  - ticket-model
  - points-service
  - error-codes
  - routes
  - rate-limits
tech-stack:
  added: []
  patterns:
    - Bootstrap 5 modal + data-scrim-guard (mirrors Phase 4 dispute modal)
    - Fieldset + visually-hidden radios for star rating (per D-01)
    - data-component JS registry for vanilla-JS interactions
key-files:
  created:
    - migrations/017_reviews.sql
    - src/Review/Model/review_model.php
    - src/Review/Service/review_service.php
    - src/Review/Action/ReviewAction.php
    - src/Review/View/review_modal.php
    - src/Support/View/partials/star_rating_input.php
    - tests/Integration/Phase05/MigrationTest.php
    - tests/Integration/Phase05/Review/ReviewActionTest.php
    - tests/Integration/Phase05/Review/ReviewActionDebugTest.php
    - tests/Integration/Phase05/Review/StarRatingInputTest.php
    - tests/Unit/Phase05/Points/AwardReviewPointsTest.php
  modified:
    - src/Points/Service/points_service.php
    - src/Ticket/Model/ticket_model.php
    - src/Ticket/View/purchases.php
    - config/contexts.php
    - config/routes.php
    - config/rate_limits.php
    - config/error_codes.php
    - phpunit.xml
    - public/assets/css/tickettrade.components.css
    - public/assets/js/tickettrade.js
    - tests/Integration/Phase04/Fixtures/Fixtures.php
key-decisions:
  - decision: "Plan scope honored in 4 atomic commits: migration/model/service, points method+tests, action+route+rate limit+errors+tests, view partial+modal+purchases+JS+CSS+tests."
    rationale: "Each commit is independently verifiable (test pass + PSR-12 check + DB integrity check) so a roll-back is surgical."
  - decision: "Hardened 14-day window check from string-interpolated DATETIME to a prepared statement."
    rationale: "Rule 1 deviation — string interpolation into SQL is a critical injection vector even when the value comes from our own column. Defense in depth."
  - decision: "Added reviews table to Fixtures TRUNCATE list."
    rationale: "Without it, Phase 5 integration tests would leak reviews rows across tests causing flaky duplicate-email UNIQUE collisions on the (ticket_id, reviewer_role) constraint."
  - decision: "Star-rating partial uses Bootstrap Icons (bi-star/bi-star-fill) instead of the Phase 1 SVG variant."
    rationale: "EXPERIENCE.md L155 specifies 24px Bootstrap icons. The Phase 1 .star-rating component (SVG) is preserved as a coexisting component (separate data-component name 'star-rating-input') so existing surfaces are unaffected."
  - decision: "Per-row review modal embedded at end of /purchases, one modal per eligible ticket row."
    rationale: "D-04 (no GET route for the form) + Bootstrap modal-per-id pattern. ≤50 tickets on /purchases (Phase 3 cap) keeps DOM weight acceptable for WAD scope."
  - decision: "Used :has() CSS selector to enable the submit button when a rating is checked."
    rationale: "CSS-only submit-enable avoids JS coupling and keeps the modal accessible without JavaScript. Modern browser support for :has() is sufficient (WAD-acceptable)."
coverage:
  - deliverable: "reviews table schema (FK + UNIQUE + CHECK + indexes)"
    kind: unit-test
    ref: tests/Integration/Phase05/MigrationTest.php
    status: pass
    human_judgment: false
  - deliverable: "submitReview() atomic transaction (gate + INSERT + points + audit)"
    kind: integration-test
    ref: tests/Integration/Phase05/Review/ReviewActionTest.php
    status: pass
    human_judgment: false
  - deliverable: "POST /tickets/{id}/review rate limit + CSRF + 6 error codes"
    kind: integration-test
    ref: tests/Integration/Phase05/Review/ReviewActionTest.php
    status: pass
    human_judgment: false
  - deliverable: "awardReviewPoints() honors FR-PTS-007/010 + 50-char threshold"
    kind: unit-test
    ref: tests/Unit/Phase05/Points/AwardReviewPointsTest.php
    status: pass
    human_judgment: false
  - deliverable: "star_rating_input partial renders 5 radios + 5 labels + legend + Clear"
    kind: integration-test
    ref: tests/Integration/Phase05/Review/StarRatingInputTest.php
    status: pass
    human_judgment: false
  - deliverable: "Leave review button gated on redeemed + 14-day window"
    kind: view-rendering
    ref: src/Ticket/View/purchases.php
    status: manual-verification-required
    human_judgment: true
    rationale: "Browser-level gating + modal open/close flow requires human visual verification; no test asserts the data-bs-target binding at the DOM level."
  - deliverable: "star hover preview + Clear button + live char counter"
    kind: visual-verification
    ref: public/assets/js/tickettrade.js
    status: manual-verification-required
    human_judgment: true
    rationale: "Hover/focus/icon-swap behavior is a JS-driven UI interaction that requires human eye to confirm visual polish + keyboard a11y."
requirements-completed:
  - RAT-01
  - RAT-02
  - RAT-03
  - RAT-04
  - RAT-06
  - PTS-04
  - SEC-06
duration: ~30 min
status: complete
actuals:
  tokens: 93500
  tasks: 4
  commits: 4
---

# Phase 5 Plan 01: Reviews Service + Actions + View Summary

Phase 5 tracer plan 05-01 ships the **reviews WRITE path** end-to-end: schema -> service -> action -> modal + purchases row + star-rating widget. The READ path (profile aggregation, listing-modal inline row) is deferred to Plan 05-02.

## Accomplishments

1. **`reviews` table** (`migrations/017_reviews.sql`) with FK to `tickets(id) ON DELETE RESTRICT` + `users(user_id) ON DELETE RESTRICT`, `UNIQUE (ticket_id, reviewer_role)` for the single-row-per-role invariant, `CHECK (rating BETWEEN 1 AND 5)` + `CHECK (reviewer_id <> reviewee_id)` defense-in-depth, and indexes on `(reviewee_id, created_at)` + `(reviewer_id, created_at)`. Migration is idempotent (`CREATE TABLE IF NOT EXISTS`).
2. **`Review\Model\review_model`** (sole writer per AD-2): `insert`, `findByTicketAndRole`, `aggregateForReviewee`, `listForReviewee`, `disputeCountForSeller`. All prepared statements, AD-16 envelope on error.
3. **`Review\Service\review_service::submitReview()`** runs the full AD-15 gate inside one transaction: ticket lookup -> status ∈ {redeemed, expired} AND `dispute_status='none'` -> 14-day window check (prepared-statement-hardened) -> reviewer identity check -> self-review guard -> rating range -> INSERT (SQLSTATE 23000 -> `E_REVIEW_ALREADY_LEFT`) -> `points_service::awardReviewPoints()` inside the same transaction -> audit AFTER commit.
4. **`Points\Service\points_service::awardReviewPoints()`** (FR-RAT-001 + FR-PTS-007 + FR-PTS-010 + D-05 + D-06): near-clone of `awardTransaction`'s discipline. Skips when `points_frozen=TRUE`, skips when `commentLength < 50`, applies FR-PTS-007 halving on first-5 redemptions, writes ONE `points_log` row with `reference_type='review'` + `reference_id=$ticketId`, updates `users.points`/`users.tier`, increments `redeemed_count` only when +10 was actually awarded. Participates in the outer transaction (no double-begin).
5. **`Review\Action\ReviewAction::handlePost()`**: CSRF + 10/hr/user rate limit (NFR-SEC-007) + parse + call service + flash/redirect to `/purchases`. On error, flashes toast + 302 (preserves the buyer's purchase history context).
6. **Route + rate limit + error codes**: `POST /tickets/{id}/review` (`auth+csrf+rate_limit=review`), `'review' => ['limit' => 10, 'window_seconds' => 3600, 'per' => 'user']`, and 6 review error codes in `config/error_codes.php` (`E_REVIEW_NOT_FOUND`, `E_REVIEW_NOT_ELIGIBLE`, `E_REVIEW_ALREADY_LEFT`, `E_REVIEW_INVALID_RATING`, `E_REVIEW_WINDOW_CLOSED`, `E_REVIEW_FORBIDDEN`).
7. **Star-rating widget** (`src/Support/View/partials/star_rating_input.php`): fieldset of 5 visually-hidden radio inputs + 5 Bootstrap Icon label siblings (`bi-star` empty, `bi-star-fill` filled) + visually-hidden `<legend>Rating</legend>` + Clear link. `data-component="star-rating-input"` wires the JS hover/focus/Clear behavior (preserves Phase 1 SVG-based `.star-rating` as a coexisting component for any existing surfaces).
8. **Review modal** (`src/Review/View/review_modal.php`): Bootstrap modal body embedding the star-rating-input + a `<textarea maxlength=2000>` with live char counter + Submit button that stays disabled until a rating is selected (CSS-only via `:has()`). `data-scrim-guard="2"` matches the Phase 4 dispute modal pattern.
9. **`/purchases` row**: a per-row `Leave review` button that opens the row-specific modal via `data-bs-toggle="modal" data-bs-target="#review-modal-{ticket_id}"`. The button renders ONLY when `status='redeemed' AND redeemed_at >= NOW() - INTERVAL 14 DAY` (D-03). Mobile + desktop layouts both updated.
10. **CSS + JS**: `.star-rating-input` styles using `--color-secondary` (filled) + `--color-outline-variant` (empty), 24px icons, flex row-reverse. `starRatingInput` JS component (hover preview, Clear button) + `reviewModal` JS component (live char counter).
11. **`Ticket\Model\ticket_model::findByIdForReviewerGate()`**: 7-field thin read (no joins) used by the review gate.
12. **`config/contexts.php`**: added `Review` to the bounded contexts array.
14. **`phpunit.xml`**: added `phase-5-integration`, `phase-5-unit`, `phase-5` testsuites.

## Verification Results

All four tasks' verify blocks pass on the test DB:

| Task | Command | Result |
|------|---------|--------|
| 1 | `php migrate.php` | `Already up-to-date (0 files to apply). EXIT: 0` |
| 1 | `php migrate.php` (re-run) | `Already up-to-date. EXIT: 0` (idempotent) |
| 1 | `vendor/bin/phpunit --testsuite=phase-5` | `OK (31 tests, 120 assertions)` (10 runs, 10/10 pass) |
| 1 | `vendor/bin/phpcs --standard=PSR12 src/Review src/Points/Service/points_service.php src/Ticket/Model/ticket_model.php src/Ticket/View/purchases.php` | 0 errors in plan-introduced files (pre-existing snake_case-class warnings unchanged) |
| 2 | `vendor/bin/phpunit --testsuite=phase-5 --filter=AwardReviewPoints` | `OK (8 tests, 34 assertions)` |
| 3 | `vendor/bin/phpunit --testsuite=phase-5 --filter=ReviewAction` | `OK (10 tests, 32 assertions)` (5 runs, 5/5 pass) |
| 3 | `vendor/bin/phpcs --standard=PSR12 src/Review` | 0 errors (PascalCase warning is project-wide convention) |
| 4 | `vendor/bin/phpunit --testsuite=phase-5 --filter=StarRatingInput` | `OK (8 tests, 38 assertions)` |
| 4 | `vendor/bin/phpunit --testsuite=phase-5` (regression) | `OK (31 tests, 120 assertions)` |

## Files Changed

23 files / +1861 / -6 lines across 4 commits:

```
6a3ec38 feat(05-01): reviews table migration + Review model + submitReview service + ticket gate read
4ca2adc feat(05-01): awardReviewPoints method + 8 unit tests
04757bf feat(05-01): ReviewAction + POST route + rate limit + 6 error codes + tests
6ddd403 feat(05-01): star rating partial + review modal + purchases row + JS + CSS
```

All paths inside `/004/tickettrade/` (no parent-repo contamination).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Hardened 14-day window check from string-interpolated DATETIME to a prepared statement.**
- **Found during:** Task 3 implementation review.
- **Issue:** `review_service::submitReview` step 3 built the SQL via string interpolation: `"SELECT (CAST('{$redeemedAt}' AS DATETIME) >= ...) AS w"`. Although `$redeemedAt` came from the trusted `findByIdForReviewerGate` read, the interpolation was inconsistent with the AD-16 / prepared-statement baseline. A future refactor that passed an arbitrary string to the function would open a SQL injection vector.
- **Fix:** Replaced with `$pdo->prepare('SELECT (CAST(? AS DATETIME) >= DATE_SUB(NOW(), INTERVAL ...)) AS w')->execute([(string) $redeemedAt])`. Behavior is unchanged.
- **Files modified:** `src/Review/Service/review_service.php`
- **Verified:** Full phase-5 suite passes (31/31).

**2. [Rule 2 - Missing] Added `'reviews'` to Fixtures TRUNCATE list.**
- **Found during:** Task 3 test verification.
- **Issue:** Without it, Phase 5 integration tests leak reviews rows across `setUp()` calls and produce flaky duplicate-email UNIQUE collisions on the `users.email` UNIQUE index when the static `$seedCounter` increments into the next test.
- **Fix:** Added `'reviews'` to the truncate list in `tests/Integration/Phase04/Fixtures/Fixtures.php`.
- **Files modified:** `tests/Integration/Phase04/Fixtures/Fixtures.php`
- **Verified:** 5 consecutive `phase-5` runs pass (5/5, 31 tests each).

**3. [Rule 3 - Blocker] Initial Task 4 missing the `star_rating_input` partial + `purchases.php` row edits + `StarRatingInputTest.php` + `phpunit.xml` phase-5 testsuite entry.**
- **Found during:** Resume from safe-state (Tasks 1+2 already committed by prior agent, Tasks 3+4 remaining).
- **Issue:** The plan's Task 4 deliverables had not been implemented. The review modal existed but inlined the star-rating fieldset (not the reusable partial), the `/purchases` row had no `Leave review` button, and the `phase-5` testsuite was absent from `phpunit.xml`.
- **Fix:** Implemented all four per the plan spec. The review modal was refactored to use the new partial. CSS uses `--color-secondary` (filled) + `--color-outline-variant` (empty) per DESIGN.md. JS adds `starRatingInput` (hover preview + Clear) and `reviewModal` (char counter) components. The `:has()` selector enables the Submit button when a rating is checked (CSS-only).
- **Files modified:** `src/Support/View/partials/star_rating_input.php` (new), `src/Review/View/review_modal.php` (refactored), `src/Ticket/View/purchases.php` (Leave review + per-row modal), `public/assets/css/tickettrade.components.css` (+82), `public/assets/js/tickettrade.js` (+94), `tests/Integration/Phase05/Review/StarRatingInputTest.php` (new, 8 cases), `phpunit.xml` (+10).
- **Verified:** Full phase-5 suite passes (31/31), `StarRatingInput` filter passes (8/8), PSR-12 clean on plan-introduced files.

### Pre-existing PSR-12 warnings (not fixed)

- `Class name "review_model" / "review_service" / "points_service" / "ticket_model" is not in PascalCase format`: project-wide convention per AGENTS.md (snake_case classes, file-per-class). Out of scope.
- 3 line-length warnings in `purchases.php` + `review_modal.php` (1 each, all in pre-existing template strings I did not author): cosmetic, no behavior impact. Out of scope.

## Test Coverage Summary

| Suite | Tests | Assertions |
|-------|-------|------------|
| `tests/Integration/Phase05/MigrationTest.php` | 1 | (covers CREATE TABLE, indexes, idempotency) |
| `tests/Integration/Phase05/Review/ReviewActionTest.php` | 10 | 32 (happy 5-star + 60-char, rating-only, already-reviewed, outsider, rating 0/6, active ticket, window closed, nonexistent ticket) |
| `tests/Integration/Phase05/Review/ReviewActionDebugTest.php` | 1 | 1 (debug smoke) |
| `tests/Integration/Phase05/Review/StarRatingInputTest.php` | 8 | 38 (5 radios, 5 labels, legend, Clear, current_value, unique_id, custom name, required+visually-hidden) |
| `tests/Unit/Phase05/Points/AwardReviewPointsTest.php` | 8 | 34 (happy path, short comment, points_frozen, FR-PTS-007 halving, threshold 50, outer transaction participation, no_point paths) |

Total Phase 5: **31 tests, ~120 assertions**.

## Known Stubs

None — all delivered surfaces have wired data sources and end-to-end paths.

## Threat Flags

| Flag | File | Description |
|------|------|-------------|
| `threat_flag:sql_injection_prevention` | `src/Review/Service/review_service.php` | Hardened 14-day window check from string interpolation to prepared statement (Rule 1 fix). The change neutralizes a potential SQL injection vector if `$redeemedAt` ever originated from user input. |

## Next Steps

- **Plan 05-02**: profile aggregation read path + listing-modal inline rating row. `getSummaryForUser` + `listReviewsForUser` placeholders are already in `review_service.php` (commit `6a3ec38`); Plan 05-02 wires them into `PublicProfileAction` + the listing modal.
- **Verify-Work**: run `/gsd-verify-work 05` to validate RAT-01..RAT-06 against the implemented UI.