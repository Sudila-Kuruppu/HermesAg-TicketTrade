# Phase 5: Reviews & Ratings - Context

**Gathered:** 2026-09-02
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 5 closes the trust loop on the marketplace: after a ticket reaches a terminal state (`redeemed` or `expired` with `dispute_status='none'`), both buyer and seller can leave a 1-5 star review with optional text within a 14-day window. The reviews surface is two places — the seller's public profile (aggregate row: average, count, 1-5 distribution, public dispute count) and the listing modal (compact "★ 4.8 (23 reviews)" inline next to the seller's rank badge). Concretely:

1. **Review write surface** — A new bounded context `App\Review\` ships `Review/Service/review_service.php` (sole writer of `reviews` per AD-2) and `Review/Model/review_model.php`. The Service enforces the AD-15 gate at the only place that should — `tickets.status IN ('redeemed','expired') AND tickets.dispute_status='none'`. The Service maps errors via the AD-16 envelope (`E_REVIEW_NOT_ELIGIBLE`, `E_REVIEW_ALREADY_LEFT`, `E_REVIEW_INVALID_RATING`, `E_REVIEW_WINDOW_CLOSED`, `E_REVIEW_FORBIDDEN`). The `reviews UNIQUE (ticket_id, reviewer_role)` constraint is the database-level double-entry guard; the Service attempts INSERT and maps the 2300 SQLSTATE to `E_REVIEW_ALREADY_LEFT`.

2. **Review Actions + Views** — `POST /tickets/{id}/review` and `GET /tickets/{id}/review` (or single `POST` route if we collapse the GET into the Purchase History row). The "Leave review" button is rendered on the Purchase History (`/purchases`) row of a `redeemed` ticket whose `redeemed_at` is within 14 days (Asia/Colombo). The form is the same modal reused across the two entry points; the modal opens full-screen with a fieldset of 5 named radio inputs (1-5) per EXPERIENCE.md L155, optional text field (50+ chars for detailed-review points), and a live char counter.

3. **Points wiring** — A new method `Points\Service\points_service::awardReviewPoints(int $revieweeId, int $reviewerId, int $ticketId, int $commentLength)` is added to the sole writer per AD-10. It writes ONE `points_log` row (reviewee side, `reference_type='review'`, delta=+10 only when `commentLength >= 50`, else delta=0 and no row), honoring FR-PTS-010 (skip if `points_frozen=TRUE`) AND FR-PTS-007 (the +10 is a transaction-derived point and counts toward the first-5 halving — `redeemed_count` is the read source). The reviewer (the one who left the review) gets NO points; only the reviewee (subject of the review) is awarded. The ticket's reviewee = seller when reviewer_role='buyer', and reviewee = buyer when reviewer_role='seller'. The reviewer side is NOT awarded anything — only the reviewee gets the +10 when eligible.

4. **Public profile aggregation** — Extend `User\Service\user_service::getByNicknameForPublicProfile()` (or add a sibling `Review\Service\review_service::getSummaryForUser(int $userId)` and call it from `PublicProfileAction`) to populate four new fields: `rating_avg` (0.0..5.0 rounded to 1 decimal), `rating_count` (total reviews received), `rating_distribution` (`[5=>N, 4=>N, 3=>N, 2=>N, 1=>N]`), `dispute_count` (count of tickets where this user is seller AND `dispute_status='upheld'`; returns 0 until Phase 7 lands). The `public_profile.php` View's stats row replaces the placeholder "No reviews yet" + "0 disputes" with the aggregated values. The listing modal's `listing-modal__seller` row gets a compact `★ 4.8 (23)` row inline next to the tier badge.

5. **Profile Reviews tab** — The Phase 2 public profile deferred the Reviews tab to Phase 5 (per `public_profile.php` L108 comment). Phase 5 ships the Reviews tab content (list of reviews received, reviewer nickname + rating + comment + relative date "2 days ago") for the public profile, and the same list view for the owner-facing `/profile` page (reviews received, plus reviews left by the owner). Tab navigation wires the existing 5-tab structure (My Listings / My Tickets / Purchase History / Sales History / Reviews).

The phase does NOT add: admin moderation queue for reviews (Phase 8), buyer-side review moderation tools, review reply/thread features, helpful/unhelpful votes, photo attachments on reviews.

</domain>

<decisions>
## Implementation Decisions

### Star input a11y
- **D-01:** The star rating input is a native `<fieldset>` containing 5 `<input type="radio" name="rating" value="1..5">` elements. The visible label for each is a 24px Bootstrap icon (`bi-star` empty, `bi-star-fill` filled). Radios are visually hidden with the existing `visually-hidden` Bootstrap class — focus and keyboard arrow-key cycling is browser-native (no JS arrow handler needed). The fieldset's `<legend>` is `class="visually-hidden">Rating</legend>` so screen readers announce the group. A small JS helper (~20 LOC in `public/assets/js/tickettrade.js`) toggles `.bi-star-fill` / `.bi-star` on the icon `<label>` siblings based on the checked value (using the `:checked + label` CSS sibling selector, with the JS just adding the visual swap on hover/focus before the click commits). The same widget is reused on the review compose modal AND on the future Phase 8 admin "review moderation" surface (no buyer-side review rating widget exists yet — RAT-04 stores a row but the buyer profile does NOT render ratings in Phase 5). — **Reversibility:** reversible — the widget is a self-contained component; swapping to a custom JS widget later is a JS + CSS change.

- **D-02:** RAT-04 (seller rates buyer) writes a `reviews` row with `reviewer_role='seller'` and `reviewee_id=$buyerId`. The row is stored and the buyer's `users.rating_avg` (via the Phase 5 aggregation read) is updated, but Phase 5 does NOT surface a buyer-rating row on the public profile View. The profile Review tab shows reviews RECEIVED by the owner; a buyer sees other sellers' reviews OF THEM in that list (which is fine — they don't know the reviewer's identity beyond nickname, per FR-RAT-003). Surfacing a separate "Buyers rate this seller" + "Sellers rate this buyer" split is deferred to v2 per EXPERIENCE.md L177 (PLAT-03 "narrative detail on profile — currently count only per RAT-05"). — **Reversibility:** reversible — the aggregation row stores both `reviewer_role='buyer'` and `reviewer_role='seller'` rows; surfacing them differently later is a View change.

### Review entry points
- **D-03:** "Leave review" button is rendered ONLY on `/purchases` (Purchase History) rows where the ticket is `redeemed` AND `redeemed_at` is within 14 days (Asia/Colombo). It is NOT rendered on `/my-tickets` (the My Tickets page surfaces ticket state for buyer follow-up; the review is a historical record best on Purchase History per EXPERIENCE.md L45). It is NOT rendered on `/tickets/{id}` (Ticket Detail) — Phase 4's ticket detail page links out to Purchase History instead. The 14-day window check uses a single SQL condition `redeemed_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)` evaluated at render time; the Service layer re-checks on POST. — **Reversibility:** reversible — adding a button on My Tickets later is a View change; the Service logic is the same.

- **D-04:** The Profile Reviews tab shows reviews RECEIVED by the owner. The tab is the same content view on both `/profile` (owner sees own reviews received, plus a "Reviews you left" section below) and `/profile/{nickname}` (public, shows reviews received only). The tab is the secondary way to discover reviews a seller received — the primary is the listing modal's inline "★ 4.8 (23)" row. There is NO "Leave review" button on the Profile Reviews tab; the only review-write entry is the Purchase History button. — **Reversibility:** reversible — the tab is a read view, can be split or expanded later.

### Review points wiring
- **D-05:** `Points\Service\points_service::awardReviewPoints(int $revieweeId, int $reviewerId, int $ticketId, int $commentLength): array` is the SOLE writer of the review-side points_log row. Signature matches `awardTransaction`'s shape (returns the AD-16 envelope). Honors:
  - `commentLength >= 50` → delta = +10 (detailed review earns points per FR-RAT-001)
  - `commentLength < 50` → delta = 0, NO points_log row written, returns `{ok: true, data: {skipped: 'no_points'}}`
  - FR-PTS-007 (50% halving on first-5 redemptions) DOES apply: the effective delta is `floor(10 * 0.5) = 5` when `users.redeemed_count < 5`. Read source is `users.redeemed_count` (the same column the Phase 4 stub uses for the buyer/seller pair). The `redeemed_count` is incremented by 1 for the REVIEWE only (not the reviewer — only the reviewee got points). This matches the PRD §5.4 #4 wording: "review may be left any time after redemption within 14 days; detailed-review points count as transaction-derived and ARE halved under FR-PTS-007 until the first-5 allowance is consumed."
  - FR-PTS-010: skip if `reviewee.points_frozen=TRUE`. Returns `{ok: true, data: {skipped: 'points_frozen'}}`.
  - The reviewer (the one leaving the review) is NOT awarded anything.
  - Writes ONE points_log row with `reference_type='review'` and `reference_id=$ticketId`. Distinct UUID v7 `event_uuid`.
  - Updates `users.points` and `users.tier` (via `auth_service::tierFromPoints()`).
  - Increments `redeemed_count` by 1 ONLY when the +10 was actually awarded (i.e., `commentLength >= 50` and not frozen).

  — **Reversibility:** reversible — the method signature is the Phase 6 contract; velocity + pair caps (Phase 6) slot into the same Service without changing callers.

- **D-06:** The +10 award is called from inside `Review\Service\review_service::submitReview()` inside the same DB transaction as the `reviews` INSERT. If the points award fails (returns `ok=false`), the entire transaction rolls back (no review row, no points row). The same `audit_log` row written for the review creation also covers the points side (single `Audit::log('review.created', ...)` call with `metadata.points_awarded=10` or `metadata.points_awarded=0`).

### Profile aggregation read path
- **D-07:** A new `Review\Service\review_service::getSummaryForUser(int $userId): array` returns `{rating_avg: float, rating_count: int, rating_distribution: array{1:int, 2:int, 3:int, 4:int, 5:int}, dispute_count: int}`. The query is two SQL statements inside a read-only transaction:
  ```sql
  -- ratings aggregation
  SELECT COUNT(*) AS rating_count,
         ROUND(AVG(rating), 1) AS rating_avg,
         SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) AS r5,
         SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) AS r4,
         SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) AS r3,
         SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) AS r2,
         SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) AS r1
  FROM reviews WHERE reviewee_id = ?;

  -- upheld dispute count (Phase 5 ships returning 0; Phase 7 admin Force Redeem/Expire
  -- sets dispute_status='upheld' which then makes this non-zero)
  SELECT COUNT(*) AS dispute_count
  FROM tickets
  WHERE seller_id = ? AND dispute_status = 'upheld';
  ```
  Called by `PublicProfileAction` (which already calls `user_service::getByNicknameForPublicProfile`) and `PublicProfileAction` populates the result into the View. The `Listing\Action\BrowseAction` calls it for the listing modal's compact row (one query per modal render — N+1 acceptable for the WAD scope since each modal render is one seller; per-board caching is a Phase 9 perf concern, not Phase 5).

- **D-08:** The Reviews tab data (a paginated list of reviews received) is read by `Review\Service\review_service::listReviewsForUser(int $userId, int $limit, int $offset): array` returning rows with `{review_id, reviewer_nickname, reviewer_role, rating, comment, created_at}`. Pagination is offset-based with 10 per page; the View renders "Prev / Next" if the offset > 0 or `total > offset+limit`.

### Dispute count now vs later
- **D-09:** Ship the dispute count column NOW (returns 0 forever until Phase 7 lands the admin Force Expire / Force Redeem / Dismiss Actions that set `dispute_status='upheld'`). The count is rendered on both the public profile (next to the rating row) AND the listing modal (compact "0 disputes" — the EXPERIENCE.md L47 + PLAT-03 wording allows a count-only display). The count is NOT rendered when it equals 0 on the listing modal (saves space; the public profile always shows it as "0 disputes" so the column's existence is signalled). When the count > 0, the column shows "N disputes on record" verbatim per FR-RAT-005. The column recomputes on every render (no caching; Phase 5 is a WAD-scoped MVP, not a high-traffic surface). — **Reversibility:** reversible — the column is a query; tightening visibility later is a View change.

### the agent's Discretion

These items follow from locked requirements or are routine implementation choices appropriate for a WAD-assignment scope:

- **Migration** — `migrations/017_reviews.sql` creates the `reviews` table per PRD §7 schema (`review_id, ticket_id, reviewer_id, reviewee_id, rating TINYINT UNSIGNED, comment TEXT NULL, reviewer_role ENUM('buyer','seller'), created_at, FK to tickets + users, UNIQUE KEY uq_review_per_role (ticket_id, reviewer_role), INDEX idx_reviewee (reviewee_id, created_at DESC), INDEX idx_reviewer (reviewer_id, created_at DESC)`). The Phase 4 tickets table already has the `dispute_status` and `redeemed_at` columns the Service gates on; no ticket-table changes. Per D-23 of Phase 2, migrations continue from the highest existing number — Phase 4 ends at `016_audit_log_stub.sql`, so Phase 5 migration is `017_*`.
- **Service + Model layer** — `App\Review\Service\review_service` is the SOLE writer of `reviews` (per AD-2 bounded context). `App\Review\Model\review_model` is the single-table data access. The Service's `submitReview()` and `getSummaryForUser()` and `listReviewsForUser()` methods; the Model's `insert()`, `findByTicketAndRole()`, `aggregateForReviewee()`, `disputeCountForSeller()` methods. The Action layer (`App\Review\Action\ReviewAction`) is thin: validate CSRF + ticket eligibility (status + dispute + 14-day window + reviewer is buyer or seller of the ticket), call Service, render View.
- **Routes** — `POST /tickets/{id}/review` (auth=true, admin=false, csrf=true, rate_limit='review'). The 14-day window check happens at render time (View button visibility) and again at POST time (Service gate). NO GET route for the form (it's a modal launched from the row button, not a dedicated page). The "Reviews" tab on `/profile` and `/profile/{nickname}` is a sub-section of the existing public_profile View; no new route.
- **Rate limit** — `config/rate_limits.php` ADDS `review => ['limit' => 10, 'window_seconds' => 3600, 'per' => 'user']` (matches the existing `listing_create` 20/hr pattern; 10 reviews/hour is plenty since the underlying 14-day ticket window is the real cap). Wired in `config/routes.php` as `rate_limit => 'review'`.
- **Audit logging** — `Support\Audit::log($actorUserId, 'review.created', 'ticket', $ticketId, ['reviewer_role' => ..., 'reviewee_id' => ..., 'rating' => ..., 'comment_length' => ..., 'points_awarded' => 10 or 0])` per review submission. Hash chain lands in Phase 8; the stub's signature is unchanged.
- **Points + reviews transaction** — `points_service::awardReviewPoints()` is called INSIDE the `review_service::submitReview()` transaction (the points award participates in the outer transaction, same as the Phase 4 `awardTransaction` is called inside the ticket redemption transaction). The two Services collaborate via the outer transaction; both honor `Db::pdo()->inTransaction()` semantics.
- **Reviews tab UX** — Empty state (no reviews yet) per EXPERIENCE.md pattern: "No reviews yet. Reviews appear after transactions complete." with named copy. Reviews list paginated 10/page with Prev/Next. Reviewer nickname only (never full name) per FR-RAT-003; relative timestamp "2 days ago" computed via `DateTime` + `DateTimeZone('Asia/Colombo')` and formatted via `DateTime::createFromFormat` + `diff()`.
- **Listing modal compact rating row** — Inline `★ 4.8 (23 reviews)` between the seller nickname and the tier badge, using the secondary-color fill from DESIGN.md's contrast ledger. When `rating_count === 0`, hide the row (no "No reviews" placeholder in the modal — the listing modal is information-dense; absence is signal). When `dispute_count > 0`, append "· N disputes" to the same row in the muted color.
- **Toast copy on review submitted** — "Review submitted." (success) and "Couldn't submit review. Try again." (failure). Phase 4's pattern via `View::flash('success', ...)`.
- **14-day window timezone** — `redeemed_at` is stored in UTC (MySQL `DATETIME` defaults to the server TZ which `config/bootstrap.php` sets to `Asia/Colombo`). The 14-day check uses `redeemed_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)` where NOW() is the Asia/Colombo server time. The View computes the relative "X days ago" via PHP `DateTime` with explicit `Asia/Colombo` timezone.
- **Self-review prevention** — The Service guards `reviewer_id != reviewee_id` (a user cannot review themselves on the same ticket; the FK + ticket ownership already prevents this for a 2-party ticket, but the explicit guard is defense in depth).
- **Comment length cap** — Comment is `TEXT NULL` (no length cap at the schema level), but the View's `<textarea>` has `maxlength="2000"` (generous cap to allow detailed reviews; the +10 points require `>=50` chars, and the PRD says no upper bound — the 2000 cap is a View-level UX guard against accidental paste-of-novel, not a Service enforcement). Empty comment is allowed (rating-only reviews are legal per FR-RAT-001).
- **Modal scrim-guard** — Reuse the existing `data-scrim-guard="2"` pattern from the Phase 4 dispute modal.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing Phase 5.**

### PRD and Topic 4 brief
- `prd.md` §3.2.1 Seller Ratings & Reviews (BookBridge Parity) — Authoritative FR-RAT-001..005 + the 14-day post-redemption window rationale.
- `prd.md` §4.2 Ticket State Machine — confirms `redeemed` and `expired` are the terminal states reviews can be left on (AD-15 gate).
- `prd.md` §4.3 Dispute State Machine — confirms `dispute_status='upheld'` is the only dispute status that counts toward the public dispute count (FR-RAT-005); rejected and auto-dismissed do not count.
- `prd.md` §5 Points Earning — Detailed review left (50+ chars) = +10 points; same-row note "detailed-review points count as transaction-derived and ARE halved under FR-PTS-007 until the first-5 allowance is consumed".
- `prd.md` §7 Schema — `reviews` table authoritative definition (reproduced in D-05 above).
- `prd.md` FR-BUY-003 — "Leave review" button eligibility (redeemed within 14 days, star rating required, comment optional 50+ chars).
- `prd.md` FR-PRO-002..003 — Profile tab structure (Reviews is one of the 5 tabs) and stats row.
- `prd.md` NFR-SEC-007 — Rate limit envelope (review 10/hr/user fits the existing pattern).
- `.planning/WAD-CONTEXT.md` — Topic 4 scope reminder; the review surface is one of the "additional innovative features beyond the minimum requirements" the brief encourages.

### Architecture and ADs
- `ARCHITECTURE-SPINE.md` AD-1..AD-20 — The binding layer rules. Critical for Phase 5:
  - AD-1: Action → Service → Model dependency arrow.
  - AD-2: `Review` is a new bounded context. `Ticket`, `Points`, `User` are existing bounded contexts. Cross-context work goes through Services only — `Review\Service` reads via `Ticket\Model\ticket_model::findByIdForReviewerGate()` (a thin read method that returns the gate-relevant ticket fields) and writes points via `Points\Service\points_service::awardReviewPoints()`.
  - AD-10: `Points\Service\points_service` is the SOLE writer of `points_log` and `users.points`/`users.tier`. Phase 5 ADDS `awardReviewPoints()` to this Service.
  - AD-15: Review gate `tickets.status IN ('redeemed','expired') AND tickets.dispute_status='none'`. Enforced at the Service layer (single check, single error code).
  - AD-16: Failure envelope on every Action exit. New error codes for Phase 5: `E_REVIEW_NOT_ELIGIBLE`, `E_REVIEW_ALREADY_LEFT`, `E_REVIEW_INVALID_RATING`, `E_REVIEW_WINDOW_CLOSED`, `E_REVIEW_FORBIDDEN`, `E_REVIEW_NOT_FOUND`.
  - AD-19: Not applicable (no admin destructive Actions in Phase 5).
- `ARCHITECTURE-SPINE.md` — Sole-writer table: `Review/Service/review_service.php` is the sole writer of `reviews` (mirroring AD-2's pattern).

### Visual identity and experience
- `DESIGN.md` — Star input widget (24px Bootstrap icons, secondary-color fill, outline-variant for empty). Contrast ledger row for the star color (already exists from Phase 1's design token system).
- `EXPERIENCE.md` L155 — Star rating input spec: fieldset of 5 named radios (1-5), radios hidden, visible label is 24px star icon, hover and focus preview, keyboard arrow keys cycle, screen reader announces "Rating: N of 5", Clear link resets to 0.
- `EXPERIENCE.md` L233-234 — "Leave review" eligible / window closed states on Purchase History rows.
- `EXPERIENCE.md` L239-245 — Review compose modal cold-load / star preview / comment length / success states.
- `EXPERIENCE.md` L45 — Purchase History row includes "Leave review" button on redeemed tickets within 14 days.
- `EXPERIENCE.md` L47 — Profile page shows rank badge, stars + rating breakdown + review count, points, join date, transaction counts, dispute count.

### Existing code
- `config/routes.php` — Phase 5 ADDS: `POST /tickets/{id}/review` (auth=true, admin=false, csrf=true, rate_limit='review'). The existing `GET /purchases` and `GET /profile/{nickname}` route entries stay (their Action classes get extended with the review button + profile aggregation row).
- `config/rate_limits.php` — Phase 5 ADDS `review => ['limit' => 10, 'window_seconds' => 3600, 'per' => 'user']`. `Support\RateLimit::hit('review', $userId)` enforces.
- `config/contexts.php` — Phase 5 ADDS `Review` to the bounded contexts list (mirrors the Phase 4 addition of `Ticket`).
- `config/bootstrap.php` — No structural change; PSR-4 autoload picks up `App\Review\*`.
- `src/Support/View/partials/star_rating_input.php` (NEW) — Renders the fieldset + 5 radios + 24px icons. Reused on the review modal.
- `src/Support/View/partials/review_summary.php` (NEW) — Renders the compact "★ 4.8 (23 reviews)" row (used on listing modal) and the full stats row (used on profile).
- `src/Support/View/partials/review_card.php` (NEW) — Renders a single review row (reviewer nickname + rating stars + relative date + comment) for the Profile Reviews tab.
- `src/Review/Action/ReviewAction.php` (NEW) — `handlePost()` for `POST /tickets/{id}/review`. Thin: CSRF + rate limit + input parse + call Service + redirect with toast.
- `src/Review/View/review_modal.php` (NEW) — Full-screen modal body with the star rating input + comment textarea + Submit / Cancel. The modal's `data-scrim-guard="2"` attribute follows the Phase 4 dispute modal pattern.
- `src/Ticket/View/purchases.php` — Phase 4 ships the row structure. Phase 5 ADDS the "Leave review" button column on `redeemed` rows where `redeemed_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)`. The button is a `<button>` that triggers the review modal via `data-bs-toggle="modal" data-bs-target="#reviewModal-{$ticket_id}"`.
- `src/Listing/View/listing_modal.php` — Phase 3 ships the seller info row (nickname + verified + tier badge). Phase 5 ADDS the compact `review_summary` partial inline (only renders when `rating_count > 0`).
- `src/User/View/public_profile.php` — Phase 2 ships the stats row placeholder. Phase 5 REPLACES the placeholder rows with the real `review_summary` data (avg, count, distribution, dispute count). ADDS the Reviews tab content section (paginated list of reviews received).
- `src/User/Action/PublicProfileAction.php` — Phase 2 ships. Phase 5 EXTENDS the `handle()` body to call `Review\Service\review_service::getSummaryForUser($userId)` and `listReviewsForUser($userId, $limit, $offset)` and inject both into the View.
- `src/Listing/Action/BrowseAction.php` — Phase 3 ships. Phase 5 EXTENDS the listing fetch to also fetch the seller's `rating_avg` and `rating_count` for the listing modal's compact row (single additional column on the existing `getBoardListings` query, or a per-modal separate fetch — the per-modal path is acceptable for WAD scope).
- `src/Points/Service/points_service.php` — Phase 4 ships `awardVerificationBonus()` and `awardTransaction()`. Phase 5 ADDS `awardReviewPoints(int $revieweeId, int $reviewerId, int $ticketId, int $commentLength): array`. The new method follows the `awardTransaction()` template (read users, lock, FR-PTS-010 check, FR-PTS-007 halving, write points_log, update users).
- `src/Ticket/Model/ticket_model.php` — Phase 4 ships the find methods. Phase 5 ADDS `findByIdForReviewerGate(int $ticketId): ?array` (a thin read returning `ticket_id, listing_id, buyer_id, seller_id, status, dispute_status, redeemed_at` for the Service gate check). The existing `findById` returns more fields than needed and includes joins that hurt perf for the hot path.
- `src/Ticket/Service/ticket_service.php` — Phase 4 ships. No Phase 5 changes needed (the reviews Service reads via `ticket_model`, not via `ticket_service` — per AD-2 boundary discipline).
- `src/User/Service/user_service.php` — Phase 2 ships. No Phase 5 changes (the reviews Service does the aggregation read, not user_service — per AD-2).
- `src/Support/Audit.php` — Phase 4 ships the stub. Phase 5 CALLS `Audit::log()` from the review Service for each review submission.
- `public/assets/css/tickettrade.components.css` — Phase 1 ships. Phase 5 ADDS `.star-rating-input`, `.star-rating-input__icon`, `.review-summary`, `.review-summary__row`, `.review-summary__distribution`, `.review-card`, `.review-card__rating`, `.review-card__comment`, `.review-card__meta`. The colors map to existing tokens (`--color-secondary` for filled stars, `--color-outline-variant` for empty, `--color-on-surface-variant` for comment text).
- `public/assets/js/tickettrade.js` — Phase 1 ships the component bundle. Phase 5 ADDS a small `starRatingInput` component (~30 LOC) for hover/focus preview; registers via `data-component="star-rating-input"`. Reuses `data-flash-toast` + `toast.show()`. The Bootstrap Icons are CDN-loaded already (Phase 1).
- `migrations/001_initial.sql` through `016_audit_log_stub.sql` — Phase 4 ships. Phase 5 ADDS `017_reviews.sql` (the `reviews` table only — no changes to `tickets` or `users`).

### Cross-phase lock-ins
- `.planning/REQUIREMENTS.md` RAT-01..06 — All implemented by Phase 5.
- `.planning/REQUIREMENTS.md` PROF-03 — Profile Reviews tab (the Phase 5 implementation is the explicit answer to the deferred Phase 2 placeholder at `public_profile.php` L108).
- `.planning/REQUIREMENTS.md` PTS-04 (transaction-derived halving applies to detailed-review points per PRD §5.4 #4) — Phase 5's `awardReviewPoints()` honors this.
- `.planning/REQUIREMENTS.md` SEC-06 — Rate limits (Phase 5 ADDS `review` named limit).

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **Bootstrap Icons (CDN, Phase 1)** — `bi-star`, `bi-star-fill`, `bi-star-half` are already available. The star rating input reuses these.
- **Toast container** (`data-component="toast"` + `window.TicketTrade.toast.show(...)`) — Phase 5 emits toasts for: review submitted (success), review submission failed (error), and "Could not submit review" on validation failure.
- **Server-set flash messages** (`data-flash-toast="..."` from Phase 2) — Phase 5 uses this for the "Review submitted." message that survives the modal-close → page-stay redirect.
- **Bootstrap 5 modal component** (`data-bs-toggle="modal" data-bs-target="..."`) — Phase 1 ships. The review modal is a standard Bootstrap modal with `data-scrim-guard="2"` (Phase 4 wiring).
- **`Support\Csrf::check()`** — Phase 2 ships; Phase 5's Review Action calls it before any state change.
- **`Support\RateLimit::hit('review', $userId)`** — Phase 5 ADDS the named limit; uses the same `Support\RateLimit` class as Phase 2's `login`/`register` and Phase 4's `purchase`/`redemption` named limits.
- **`points_service::tierFromPoints(int $points): string`** — Phase 2 ships; Phase 5's `awardReviewPoints()` calls it to recompute the tier after the +10 award.
- **`points_log_model::insert(PDO, int $userId, int $delta, string $referenceType, ?int $referenceId, int $balanceAfter, string $eventUuid, ?string $metadataJson): int`** — Phase 2 ships; Phase 5's `awardReviewPoints()` calls it once per awarded review (only when `commentLength >= 50` and not frozen).
- **`auth_service::sanitizeUser(array $row): array`** — Phase 2 ships; Phase 5's `PublicProfileAction` reuses the existing sanitization on the `user_service::getByNicknameForPublicProfile` return value.
- **`Support\Audit::log(?int $actorUserId, string $action, string $targetType, int $targetId, ?array $metadata): int`** — Phase 4 ships the stub; Phase 5 calls it from `review_service::submitReview()` with `action='review.created'`.
- **`User\Model\user_model::findById(int $userId): ?array`** — Phase 2 ships; Phase 5's `awardReviewPoints()` reads `users.redeemed_count` and `users.points_frozen` from this.
- **Listing-modal seller info slot** — Phase 3's `src/Listing/View/listing_modal.php` lines 89-101 already render the seller nickname + verified + tier badge. Phase 5 inserts the `review_summary` partial between nickname and tier badge (compact row, only when `rating_count > 0`).
- **Public profile stats row** — Phase 2's `src/User/View/public_profile.php` lines 90-107 render the 4-column stats (Sales / Purchases / Reviews / Disputes) with placeholder "0" / "—" / "No reviews yet" / "0 disputes". Phase 5 REPLACES the Reviews and Disputes columns with real values; Sales and Purchases remain at "0" until Phase 4's count queries are wired (NOT a Phase 5 concern — Phase 4 owns those columns per D-04 of Phase 4; Phase 5 only touches Reviews and Disputes).
- **Review-flag inline pattern** — Phase 3's `src/Support/View/partials/listing_status_pill.php` renders a `bg-warning text-dark` inline badge for the listing `review_flag`. Phase 5's listing-modal rating row uses the same pattern (compact, inline, single-line) — visual continuity.
- **`Mockups/my-tickets.html` and the existing ticket-card markup** — Phase 1 ships the ticket-card visual treatment. The review modal's background and padding match the ticket-card's surface treatment (`surface-raised`).

### Established Patterns
- **Layered Modular Monolith** (AD-1) — Bootstrap → FrontController → Action → Service → Model → PDO. Phase 5's `Review/Action/review_action.php` is thin: validate CSRF + rate limit + reviewer identity (buyer or seller of the ticket) + ticket eligibility, call `Review/Service/review_service.php::submitReview()`, render View. The Service handles the transaction, points delegation, and audit logging.
- **Failure envelope** (AD-16) — Every Action returns `['ok' => bool, 'data' => mixed, 'error' => ['code' => string, 'message' => string, 'fields' => array|null]]`. New error codes for Phase 5: `E_REVIEW_NOT_ELIGIBLE` (ticket status/dispute gate), `E_REVIEW_ALREADY_LEFT` (UNIQUE violation), `E_REVIEW_INVALID_RATING` (rating not in 1..5), `E_REVIEW_WINDOW_CLOSED` (redeemed_at > 14 days), `E_REVIEW_FORBIDDEN` (reviewer is not buyer or seller of the ticket), `E_REVIEW_NOT_FOUND` (ticket not found).
- **Sole-writer pattern** (AD-2 + AD-10) — `Review/Service/review_service.php` is the sole writer of `reviews`; `Points/Service/points_service.php` is the sole writer of the review-side `points_log` row. No Action writes to either table.
- **Atomic UPDATE for state mutation** (AD-9) — Not directly applicable to reviews (the INSERT is the only mutation), but the Service's `submitReview()` runs the INSERT + the points award + the audit log in a single transaction with `Db::pdo()->beginTransaction()` / `commit()` / `rollBack()`. The `awardReviewPoints()` method participates in the outer transaction (same pattern as `awardTransaction`).
- **Tokens-as-contracts** (Phase 1) — Every color/spacing/typography/elevation token in `tickettrade.tokens.css` traces to a row in `DESIGN.md`'s contrast ledger. Phase 5 inherits this; no new token additions (the `--color-secondary` for filled stars and `--color-outline-variant` for empty stars already ship from Phase 1).
- **Self-registering JS components** (Phase 1) — `data-component="..."` attributes register behavior. Phase 5 ADDS `data-component="star-rating-input"` (~30 LOC) for the hover/focus preview + visual fill swap. Reuses `toast`, `bottomNav`, `prefersReducedMotion`. The Bootstrap Icons library is CDN-loaded already.
- **Migrations runner** (Phase 2 D-22..D-28) — Each `.sql` migration runs in a single transaction, `IF NOT EXISTS`/`IF EXISTS` discipline, `.applied` file tracks progress. Phase 5 adds one migration following the same pattern.
- **Pagination** — Phase 3's board view already paginates at 50/page. Phase 5's Reviews tab paginates at 10/page using the same offset/limit + Prev/Next pattern.
- **Single-pass aggregation queries** — Phase 4's `ticket_service::runTicketExpirySweep` uses single-pass SQL with `SUM(CASE WHEN ...)` for distribution aggregation. Phase 5's `getSummaryForUser` follows the same pattern (one query for both `COUNT`, `AVG`, and the 5-bucket distribution).

### Integration Points
- **`config/routes.php`** — Phase 5 ADDS `POST /tickets/{id}/review`. The existing `/purchases` and `/profile/{nickname}` route entries stay.
- **`config/rate_limits.php` (MODIFIED)** — Phase 5 ADDS `review => ['limit' => 10, 'window_seconds' => 3600, 'per' => 'user']`. `Support\RateLimit::hit($name, $key)` reads this map.
- **`config/contexts.php` (MODIFIED)** — Phase 5 ADDS `Review` to the bounded contexts list.
- **`config/bootstrap.php`** — No structural change; PSR-4 autoload picks up `App\Review\*`.
- **Purchase History row markup** — Phase 4's `src/Ticket/View/purchases.php` renders each row as a `<tr>` (desktop) or stacked `<div class="card">` (mobile). Phase 5 ADDS a "Leave review" button to `redeemed` rows where `redeemed_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)`. The button triggers the modal via `data-bs-toggle="modal" data-bs-target="#reviewModal-{$ticket_id}"`.
- **Listing modal markup** — Phase 3's `src/Listing/View/listing_modal.php` lines 89-101. Phase 5 INSERTS the `review_summary` partial between the seller nickname and tier badge.
- **Public profile markup** — Phase 2's `src/User/View/public_profile.php`. Phase 5 REPLACES the Reviews column with the real `review_summary` partial, REPLACES the Disputes column with the real count, ADDS a new section below the stats row for the Reviews tab (paginated list of reviews received).
- **Points Service** — Phase 4's `awardTransaction()` is the template for `awardReviewPoints()`. The new method reads users, locks, honors FR-PTS-010 (skip if frozen) and FR-PTS-007 (halving on first-5), writes ONE points_log row with `reference_type='review'`, updates `users.points`/`users.tier`, and increments `redeemed_count` by 1 (only for the reviewee, only when +10 was awarded).
- **Audit log** — Phase 4's `Support\Audit::log()` is called from `review_service::submitReview()` after the commit (so audit logs don't roll back if the review insert fails). The Phase 8 hash chain wraps the same signature.

</code_context>

<specifics>
## Specific Ideas

- The 14-day review window check on the Purchase History row uses a single SQL expression evaluated server-side: `$purchases['is_reviewable'] = ($row['status'] === 'redeemed' && $row['redeemed_at'] >= date('Y-m-d H:i:s', strtotime('-14 days')))`. The View renders the "Leave review" button ONLY when `is_reviewable` is true; the modal's URL `data-bs-target` is `reviewModal-{ticket_id}` so each row has its own modal instance (the modal's hidden form input carries the ticket_id).
- The review modal's star input fieldset has 5 radios named `rating` (values 1..5). The form's hidden input `ticket_id` carries the ticket being reviewed. The comment textarea has `maxlength="2000"` and a live char counter "N chars" below. The Submit button is disabled until a rating is selected (rating radios all unchecked → disabled); the comment is optional. Server-side: the Action re-validates rating ∈ 1..5 and ticket eligibility regardless of client-side state.
- The public profile's Reviews tab content lists reviews received, with reviewer nickname (NEVER full name per FR-RAT-003), reviewer role badge ("Buyer" / "Seller"), star count (★★★★☆ rendered as filled + empty Bootstrap icons), the comment text (or "Rating only — no comment" when comment is NULL), and relative timestamp ("2 days ago", "3 weeks ago", etc.) computed via PHP `DateTime` + `diff()` with Asia/Colombo timezone. Each row is wrapped in `<article class="review-card">` matching the Phase 3 listing-card visual treatment (slight rotation via `--rot` for visual continuity).
- The listing modal's compact rating row format: `<span class="review-summary review-summary--compact">★ <strong>4.8</strong> <span class="caption">(23 reviews)</span></span>` — only renders when `rating_count > 0`. The `★` is a Bootstrap icon (`bi-star-fill`). When `dispute_count > 0`, append `<span class="review-summary__dispute">· 2 disputes</span>` in muted color.
- The reviews Service's `submitReview()` flow:
  1. Begin DB transaction
  2. Lookup ticket via `ticket_model::findByIdForReviewerGate($ticketId)` — returns `null` if not found → `E_REVIEW_NOT_FOUND`
  3. Check ticket status: `status IN ('redeemed','expired') AND dispute_status='none'` → otherwise `E_REVIEW_NOT_ELIGIBLE`
  4. Check 14-day window: `redeemed_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)` → otherwise `E_REVIEW_WINDOW_CLOSED`
  5. Check reviewer identity: `current_user.user_id === ticket.buyer_id` → reviewer_role='buyer'; `=== ticket.seller_id` → reviewer_role='seller'; otherwise `E_REVIEW_FORBIDDEN`
  6. Check reviewee ≠ reviewer: `ticket.buyer_id !== ticket.seller_id` (always true for a 2-party ticket; defensive check) → otherwise `E_REVIEW_FORBIDDEN`
  7. Validate rating ∈ 1..5 → otherwise `E_REVIEW_INVALID_RATING`
  8. Validate comment length ≤ 2000 (View-level guard; Service just truncates if longer)
  9. INSERT into `reviews` with the computed `reviewer_id`, `reviewee_id`, `reviewer_role`, `rating`, `comment`
  10. Map 2300 SQLSTATE (UNIQUE violation) to `E_REVIEW_ALREADY_LEFT`
  11. Call `points_service::awardReviewPoints($revieweeId, $reviewerId, $ticketId, $commentLength)` inside the same transaction
  12. Commit, then call `Audit::log(...)` AFTER commit (audit logs are not transactional with the business write; they go in even if the commit fails, for forensics)
  13. Return `{ok: true, data: {review_id, points_awarded}}`; the Action emits the toast and closes the modal
- The WAD-friendly demo path: register buyer + seller → seller creates + admin approves a textbook listing → buyer buys (Phase 4) → seller redeems the code (Phase 4) → buyer visits `/purchases`, sees "Leave review" on the redeemed row → opens modal → selects 5 stars + types "Great transaction, fast pickup" (60 chars) → submits → toast "Review submitted" → modal closes → `/purchases` row updates to "Reviewed" badge → buyer visits seller's `/profile/{nickname}` → sees "★ 5.0 (1 review)" + "0 disputes" + the review listed in the Reviews tab.
- The Phase 5 plan's WAD-friendly demo path also exercises the seller-reviews-buyer flow: from the Sales page, after redeeming a ticket, the seller sees a "Rate buyer" button on the same redeemed row (mirror of the buyer's "Leave review") → opens modal → selects 4 stars + optional comment → submits → buyer's profile now has one review received (from a seller).
- Audit log action names: `review.created`. The metadata includes `reviewer_role`, `reviewee_id`, `rating`, `comment_length`, `points_awarded`. Phase 8's hash chain wraps the same signature.

</specifics>

<deferred>
## Deferred Ideas

- **Admin moderation of reviews** — Phase 8 (admin console). Out of Phase 5 scope per RAT-01..05 (the user-side surface only).
- **Buyer-side reviews-of-buyer public profile page** — A buyer profile currently shows the buyer-as-reviewee reviews in the Reviews tab (per FR-RAT-004 + D-02). A dedicated "Buyers rate this buyer" split view is deferred to v2 per PLAT-03.
- **Review reply / thread** — Out of scope per PRD scope table.
- **Helpful / unhelpful votes on reviews** — Out of scope per PRD scope table.
- **Review photo attachments** — Out of scope per PRD scope table.
- **Real-time review notifications** — Polling on `/purchases` and `/profile` is sufficient for the WAD scope.
- **Review dispute flow** — A user disputing a review they received is v2. The current model only counts UPHELD ticket disputes as the public dispute count.
- **Edit / delete own review** — Reviews are write-once for the WAD scope. An admin moderation Action in Phase 8 can remove a review; the user cannot.
- **Review-flag (listing review_flag column re-used)** — Phase 3's `listings.review_flag` is a listing-recheck flag, unrelated to reviews. The naming collision is intentional but disambiguated in code comments.
- **Buyer-side rating row on listing modal** — Listing modal shows the seller's aggregate rating only; it does NOT show the buyer's (the buyer's rating isn't surfaced in Phase 5 per D-02).
- **Review surface on `/tickets/{id}` detail page** — Phase 4's ticket detail page links out to `/purchases` for the review button. Adding a "Leave review" button directly on the detail page is a View change.
- **Scheduled admin moderation queue refresh** — Phase 9 cron scope.
- **Review language / sentiment analysis** — Out of scope.
- **Cohort isolation (AD-20)** — Single-cohort MVP. Reviews are filtered through the same gate; a future cohort_id column would slot into the WHERE clauses without changing the API surface.

### Reviewed Todos (not folded)

No todos were folded from `cross_reference_todos` (the step returned `todo_count: 0`).

</deferred>

---

*Phase: 5-Reviews & Ratings*
*Context gathered: 2026-09-02*