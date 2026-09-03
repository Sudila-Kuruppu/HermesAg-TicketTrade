---
phase: 05-reviews-ratings
plan: 02
subsystem: reviews
tags: [reviews, profile, listing-modal, aggregation, pagination, public-read-path, RAT-02..RAT-05, PROF-02, PROF-03]
requires:
  - review_service::getSummaryForUser (placeholder filled in Task 1)
  - review_service::listReviewsForUser (placeholder filled in Task 1)
  - review_summary partials (4 variants, Task 2)
  - review_card partial (Task 2)
  - PublicProfileAction (Task 2)
  - public_profile.php (Task 2)
provides:
  - public-profile-rating-aggregation
  - profile-reviews-tab-paginated
  - listing-modal-compact-rating-row
  - listing-modal-dispute-suffix
affects:
  - listing-modal-view
  - board-view
  - browse-action
tech-stack:
  added: []
  patterns:
    - View::partial() indirection with two independently-gated compact fragments
    - offset/limit pagination with Prev/Next link templating
    - N+1 acceptable per CONTEXT D-07 (one extra query per board render for first-listing modal)
key-files:
  created:
    - tests/Integration/Phase05/Listing/ListingModalRatingTest.php
  modified:
    - src/Listing/View/listing_modal.php
    - src/Listing/View/board.php
    - src/Listing/Action/BrowseAction.php
key-decisions:
  - decision: "Two compact fragments (review_summary_compact_rating + review_summary_compact_dispute) gate INDEPENDENTLY in the listing modal, not a single conditional block."
    rationale: "Per D-09 + the BLOCKER review note: a seller with 0 reviews but 2 upheld disputes must still show '· 2 disputes' even when the rating row is hidden. Splitting into two fragments with their own if/return early-exits makes the independence explicit and prevents the dual-condition bug the BLOCKER caught."
  - decision: "Profile Reviews tab paginates at 10 per page using offset-based pagination (D-08); same Prev/Next shape as the board."
    rationale: "Matches existing convention (Phase 3 board pagination). The Service's listReviewsForUser returns [rows, total] so the View can render Prev/Next when offset > 0 or total > offset + limit."
  - decision: "BrowseAction fetches getSummaryForUser only for the FIRST board row's seller (the one the JS pre-loads into the modal). N+1 acceptable per CONTEXT D-07."
    rationale: "WAD scope — at <=50 listings per board, the first-listing modal pre-render is the only mandatory case. Per-board seller caching deferred to Phase 9 perf scope."
  - decision: "dispute_count on the listing modal renders the '· N disputes' suffix only when > 0 (D-09 hides empty dispute count on listing modal to keep the row compact)."
    rationale: "Listing modal is information-dense; absence is signal. The public profile's stats row ALWAYS renders '0 disputes' so the column's existence is signalled even when zero (FR-RAT-005)."
coverage:
  - deliverable: "getSummaryForUser aggregation read (avg + count + distribution + dispute_count)"
    kind: integration-test
    ref: tests/Integration/Phase05/Profile/ProfileAggregationTest.php
    status: pass
    human_judgment: false
  - deliverable: "listReviewsForUser paginated rows + total count"
    kind: integration-test
    ref: tests/Integration/Phase05/Profile/ReviewsTabTest.php
    status: pass
    human_judgment: false
  - deliverable: "Listing modal compact rating + dispute fragments render via View::partial"
    kind: integration-test
    ref: tests/Integration/Phase05/Listing/ListingModalRatingTest.php
    status: pass
    human_judgment: false
  - deliverable: "Profile stats row + Reviews tab content + Prev/Next pagination + empty state + FR-RAT-003 (no full_name)"
    kind: integration-test
    ref: tests/Integration/Phase05/Profile/ReviewsTabTest.php
    status: pass
    human_judgment: false
requirements-completed:
  - RAT-02
  - RAT-03
  - RAT-04
  - RAT-05
  - PROF-02
  - PROF-03
duration: ~25 min
status: complete
actuals:
  tokens: 70000
  tasks: 3
  commits: 3
---

# Phase 5 Plan 02: Public Profile Aggregation + Listing Modal Rating Row

Plan 05-02 ships the **reviews READ path** end-to-end: when a student
views a seller's public profile, the profile shows real rating
aggregation, a 1..5 distribution, the public dispute count, and the
paginated list of reviews received. The listing modal also surfaces a
compact `★ 4.8 (23 reviews)` row inline next to the seller's tier badge.

## Accomplishments

### Task 1 — review_service read methods

1. **`getSummaryForUser(int $userId): array`** — Two SQL statements (one
   ratings aggregation with `SUM(CASE WHEN rating = N THEN 1 ELSE 0 END)`
   for the 1..5 distribution + `ROUND(AVG(rating), 1)` + `COUNT(*)`, one
   dispute count via `tickets.seller_id = ? AND dispute_status = 'upheld'`).
   Read-only — no transaction. Returns zeros when the user has no
   reviews / no upheld disputes. Honors D-07.
2. **`listReviewsForUser(int $userId, int $limit, int $offset): array`** —
   Returns `[rows, total]` tuple. Rows are reviews RECEIVED by the user
   (per D-02, regardless of reviewer_role), joined with `users.nickname`
   AS `reviewer_nickname` (never full_name per FR-RAT-003), ordered
   `created_at DESC`. Pagination clamps at limit=10..50, offset >= 0
   (defense-in-depth; View also clamps).

### Task 2 — PublicProfileAction + public_profile View + Reviews tab

3. **`PublicProfileAction::handle()`** — Reads `$offset` from
   `$_GET['offset'] ?? 0` (clamped to 0..1000 per D-08). After fetching
   the user, calls `review_service::getSummaryForUser()` +
   `listReviewsForUser($userId, 10, $offset)`. Injects `$summary`,
   `$reviews`, `$reviews_total`, `$reviews_offset`, `$reviews_per_page`
   into the View alongside the existing `$profile` / `$is_owner`.
4. **`public_profile.php`** — Replaces the Phase 2 placeholder Reviews
   and Disputes cells with the real `review_summary` partial (full
   variant: avg + count + 1..5 distribution + dispute count). ADDS a new
   Reviews tab content section (paginated list of reviews received,
   Prev/Next links, empty state per EXPERIENCE.md:
   `No reviews yet. Reviews appear after transactions complete.`).
5. **`review_summary` partial** — Full variant for the profile stats
   row. Two compact fragments (`review_summary_compact_rating` +
   `review_summary_compact_dispute`) so the listing modal can gate
   them INDEPENDENTLY (D-09 + BLOCKER review note).
6. **`review_card` partial** — `<article class="review-card">` with
   reviewer nickname, role badge, 5 Bootstrap star icons, relative
   timestamp ("2 days ago"), and comment text (or "Rating only —
   no comment." placeholder when NULL). Uses reviewer_nickname only
   (FR-RAT-003).
7. **CSS additions** in `tickettrade.components.css` — `.review-summary`,
   `.review-summary__avg`, `.review-summary__count`,
   `.review-summary__distribution`, `.review-summary--compact`,
   `.review-summary__dispute`, `.review-card`, `.review-card__header`,
   `.review-card__reviewer`, `.review-card__rating`, `.review-card__time`,
   `.review-card__comment` using existing `--color-secondary` (filled
   stars) + `--color-on-surface-variant` (muted text) tokens.

### Task 3 — Listing modal compact row + BrowseAction seller fetch

8. **`listing_modal.php`** — Inserts the two compact fragments in the
   seller info row between the seller nickname and the tier badge. The
   rating fragment renders `<span class="review-summary review-summary--compact"
   data-testid="listing-modal-rating">★ <strong>{avg}</strong>
   ({N} reviews)</span>` and returns early when `rating_count === 0`.
   The dispute fragment renders `<span class="review-summary__dispute
   caption text-on-surface-variant" data-testid="listing-modal-dispute">·
   {N} disputes</span>` and returns early when `dispute_count === 0`.
9. **`board.php`** — Forwards `$seller_summary` into the listing modal
   partial via the local view-vars array.
10. **`BrowseAction::handle()`** — Calls `review_service::getSummaryForUser()`
    on the first board row's seller. Defaults to zeros when the board is
    empty. N+1 acceptable per CONTEXT D-07 (one extra query per board
    render); per-board caching is a Phase 9 perf concern.
11. **`ListingModalRatingTest`** — 8 cases covering the partials directly:
    - Zero reviews: no rating row, no dispute suffix
    - 5 reviews (avg 4.8): "★ 4.8 (5 reviews)" + singular/plural
    - Singular "1 review" vs plural "1 reviews" label edge case
    - Singular "1 dispute" vs plural "1 disputes" label edge case
    - 0 reviews + 2 disputes: dispute suffix rendered independently
    - 5 reviews + 2 disputes: both fragments back-to-back (rating first)
    - Zero-state empty render (no fragments, empty output)
    - BrowseAction contract: `getSummaryForUser` returns the shape the
      Action forwards; partials render that shape correctly

## Verification Results

| Task | Command | Result |
|------|---------|--------|
| 1 | `DB_DSN=... php migrate.php` | `Already up-to-date (0 files to apply). EXIT: 0` |
| 1 | `vendor/bin/phpunit --testsuite=phase-5 --filter=ProfileAggregation` | `OK` |
| 2 | `vendor/bin/phpunit --testsuite=phase-5 --filter=ReviewsTab` | `OK (9 tests, 41 assertions)` |
| 3 | `vendor/bin/phpunit --testsuite=phase-5 --filter=ListingModalRating` | `OK (8 tests, 29 assertions)` |
| 3 | `vendor/bin/phpunit --testsuite=phase-5` (full regression) | `OK (56 tests, 204 assertions)` |
| 3 | `vendor/bin/phpcs --standard=PSR12 src/Listing/View/listing_modal.php src/Listing/Action/BrowseAction.php` | 0 errors (6 pre-existing line-length warnings unchanged) |

## Files Changed

11 files / +1330 / -14 lines across 3 commits:

```
2f3da26 feat(05-02): review_service getSummaryForUser + listReviewsForUser with total count
5ee0799 feat(05-02): public profile Reviews tab + stats row + 4 view partials + CSS
93924b3 feat(05-02): listing modal compact rating row + BrowseAction seller fetch + 8-case test
```

All paths inside `/004/tickettrade/` (no parent-repo contamination).

## Deviations from Plan

### Auto-fixed Issues

None — plan executed exactly as written. Tasks 1 and 2 commits (`2f3da26`,
`5ee0799`) preceded this session; Task 3 completed the plan via commit
`93924b3`.

### Pre-existing PSR-12 warnings (not fixed)

- 6 line-length warnings in `listing_modal.php` lines 64, 98, 106, 125,
  126, 149 (Phase 3 markup): cosmetic, no behavior impact. Out of scope
  per Phase 5 conventions (template strings authored in earlier phases).

## Test Coverage Summary

| Suite | Tests | Assertions |
|-------|-------|------------|
| `tests/Integration/Phase05/Profile/ProfileAggregationTest.php` | (covers avg/count/distribution/dispute_count shapes) | — |
| `tests/Integration/Phase05/Profile/ReviewsTabTest.php` | 9 | 41 (avg/count, dispute copy, empty state, comment placeholder, FR-RAT-003, pagination Prev/Next, single-page no-pagination, distribution buckets) |
| `tests/Integration/Phase05/Listing/ListingModalRatingTest.php` | 8 | 29 (zero reviews, 5-review avg, singular labels, dispute-only-when-no-reviews, both fragments, empty state, BrowseAction contract) |

Phase 5 combined: **56 tests, ~204 assertions** (covers Plans 05-01 + 05-02).

## Known Stubs

None — all delivered surfaces have wired data sources and end-to-end paths.
The dispute count column returns 0 until Phase 7 lands the admin Force
Redeem/Expire Actions that set `dispute_status='upheld'` (per CONTEXT D-09).
The listing modal and public profile render the count column correctly
when Phase 7 ships — no follow-up wiring needed in this plan.

## Threat Flags

| Flag | File | Description |
|------|------|-------------|
| `threat_flag:privacy_pii` | `src/Support/View/partials/review_card.php` | Renders reviewer nickname only (NEVER full_name) per FR-RAT-003. Verified by `ReviewsTabTest::test_does_not_render_reviewer_full_name_per_FR_RAT_003` which asserts "Kasun Perera" never leaks into the View. |
| `threat_flag:sql_injection_prevention` | `src/Review/Service/review_service.php` | All aggregation reads (Task 1) use PDO prepared statements via `review_model::aggregateForReviewee`, `disputeCountForSeller`, `listForReviewee`, `countForReviewee`. No string interpolation. |

## Next Steps

- **Phase 6 (Points + Ranks)**: no integration with this plan.
- **Phase 7 (Reports + Disputes)**: the `dispute_count` column on
  PublicProfileAction + listing modal becomes live once Phase 7 ships
  admin Force Redeem/Expire Actions that set `dispute_status='upheld'`.
  No Phase 5 follow-up needed — the read path already renders non-zero
  counts when they exist.
- **Phase 9 (Operational Substrate)**: per-board seller rating cache
  addresses the N+1 documented in this plan (CONTEXT D-07 perf note).
- **Verify-Work**: run `/gsd-verify-work 05` to validate RAT-01..RAT-06
  + PROF-02/03 against the implemented UI.