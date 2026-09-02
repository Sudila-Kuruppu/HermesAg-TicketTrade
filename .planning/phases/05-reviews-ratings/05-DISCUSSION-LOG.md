# Phase 5: Reviews & Ratings - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-09-02
**Phase:** 5-Reviews & Ratings
**Areas discussed:** Star input a11y + JS scope, Where Leave review lives, Review points wiring scope, Profile aggregation read path, Dispute count now vs later

---

## Star input a11y + JS scope

| Option | Description | Selected |
|--------|-------------|----------|
| Native fieldset/radio | `<fieldset>` of 5 `<input type="radio">` with visually-hidden radios + 24px Bootstrap icon labels; browser-native keyboard arrows; ~20 LOC JS for hover preview | ✓ |
| Custom widget | Full custom JS star widget with hover/focus preview | |
| Buyer-side review rating widget | Same widget reused on a buyer profile to display reviews-of-buyer | |

**User's choice:** "no need to ask me... since this is WAD topic 4, students use suitable" — agent discretion to pick conventional, native, minimal option per area.
**Notes:** D-01 native fieldset (zero deps, browser handles a11y, ~20 LOC helper); D-02 RAT-04 stores the seller-rates-buyer row but the buyer profile does NOT render a separate ratings split in Phase 5 (deferred to v2 per PLAT-03).

---

## Where Leave review lives

| Option | Description | Selected |
|--------|-------------|----------|
| Purchase History only | Button on `/purchases` rows where ticket is `redeemed` AND `redeemed_at >= 14 days` | ✓ |
| My Tickets too | Button on `/my-tickets` redeemed rows | |
| Ticket Detail too | Button on `/tickets/{id}` detail page | |
| Profile Reviews tab | Compose button on the Profile Reviews tab | |

**User's choice:** agent discretion.
**Notes:** D-03 Purchase History only (matches EXPERIENCE.md L45); D-04 Profile Reviews tab is READ-only, not a compose surface; entry is single — `/purchases`.

---

## Review points wiring scope

| Option | Description | Selected |
|--------|-------------|----------|
| New `awardReviewPoints()` method | Add to `points_service` sole writer; honors FR-PTS-007 + FR-PTS-010; writes 1 row when 50+ chars | ✓ |
| Fold into `awardTransaction()` | Same method, new param for review delta | |
| Skip points wiring in Phase 5 | Defer +10 to Phase 6 points engine | |

**User's choice:** agent discretion.
**Notes:** D-05 new method (cleaner AD-10 boundary, follows `awardTransaction()` template); D-06 called from inside `review_service::submitReview()` transaction. PRD §5.4 #4 says halving applies — locked.

---

## Profile aggregation read path

| Option | Description | Selected |
|--------|-------------|----------|
| New `Review/Service::getSummaryForUser()` | Two SQL queries (ratings agg + dispute count) via the new bounded-context Service | ✓ |
| Inline in `user_service` | Aggregation lives in user_service | |
| Listing modal fetch per render | N+1 acceptable for WAD | ✓ |

**User's choice:** agent discretion.
**Notes:** D-07 new Service method per AD-2; per-modal fetch acceptable (Phase 9 perf concern, not Phase 5); D-08 listReviewsForUser() with offset pagination 10/page.

---

## Dispute count now vs later

| Option | Description | Selected |
|--------|-------------|----------|
| Ship upheld-count query now | Column renders 0 until Phase 7 lands `dispute_status='upheld'` writes | ✓ |
| Defer dispute count to Phase 7 | Wait for admin Force Expire/Redeem/Dismiss to land | |
| Show on listing modal only | Listing modal gets the count, profile does not | |
| Show on profile only | Profile gets the count, listing modal does not | |

**User's choice:** agent discretion.
**Notes:** D-09 ship now (forward-compatible; query is cheap); render on BOTH listing modal (compact "N disputes" only when > 0) AND profile ("N disputes on record" always).

---

## the agent's Discretion

- Star input: native fieldset (D-01).
- RAT-04 buyer-side split view: deferred to v2 (D-02).
- Leave review entry: Purchase History only (D-03).
- Profile Reviews tab: read-only (D-04).
- Points: new `awardReviewPoints()` method (D-05).
- FR-PTS-007 halving on +10 review points: applies (D-05, per PRD §5.4 #4).
- Aggregation: new `Review/Service::getSummaryForUser()` (D-07).
- Reviews list pagination: 10/page offset (D-08).
- Dispute count: ship now on both surfaces (D-09).
- Migration: `017_reviews.sql`.
- Service/Model namespacing: `App\Review\Service\review_service`, `App\Review\Model\review_model`.
- Action: `POST /tickets/{id}/review` only (no GET form route — modal launched from row).
- Rate limit: `review` named limit at 10/hr/user.
- Audit action name: `review.created`.
- Comment cap: 2000 chars at View level, no Service enforcement.
- Self-review prevention: Service-level guard (`reviewer_id != reviewee_id`).
- 14-day window: SQL `redeemed_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)` evaluated server-side.

## Deferred Ideas

- Admin moderation of reviews → Phase 8.
- Buyer-side reviews-of-buyer public profile split → v2 (PLAT-03).
- Review reply / thread → v2.
- Helpful / unhelpful votes → v2.
- Review photo attachments → v2.
- Real-time review notifications → v2.
- Review dispute flow → v2.
- Edit / delete own review → out of scope (write-once for WAD).
- Buyer-side rating row on listing modal → deferred (Phase 5 shows seller aggregate only per D-02).
- Review surface on `/tickets/{id}` detail page → deferred (link to `/purchases`).
- Scheduled admin moderation queue refresh → Phase 9.
- Review language / sentiment analysis → out of scope.