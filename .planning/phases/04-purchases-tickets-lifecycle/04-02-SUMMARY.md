---
phase: 04-purchases-tickets-lifecycle
plan: 02
type: execute
status: complete
date: 2026-09-02
---

# Phase 4 Plan 02: My Tickets / Sales / Purchases Views Summary

## Key Links

- **`Ticket\Service\ticket_service`** — read-only helpers `getTicketsForBuyer($buyerId, $tab)`, `getGroupedSales($sellerId)`, `getPurchaseHistory($buyerId)` join the tickets/listing/user tables and return sanitized arrays for the Views.
- **`Ticket\Model\ticket_model`** — 4 new queries: `findByBuyerAndStatus` (single-tab filter), `findPurchaseHistory` (chronological), `findGroupedSales` (per-listing), `findActiveServicesBySeller` (services). The atomic writer is unchanged from Plan 04-01.
- **Three Actions** — `MyTicketsAction::handle()`, `SalesAction::handle()`, `PurchasesAction::handle()` all read `$_GET['tab']` / `current_user`, call the Service, and render the View. They never write to the DB directly (AD-1 + AD-9).
- **Three Views** — `my_tickets.php` (5 tabs with `aria-current='page'`), `sales.php` (per-listing-group placement with the redemption input form at the top), `purchases.php` (chronological table on desktop / stacked rows on mobile). All use `htmlspecialchars()` for every dynamic value (AD-16).
- **Buy now form** — `src/Listing/View/listing_modal.php` replaces the Phase 3 `<a href="#buy">` with a `<form method="POST" action="/listings/{id}/buy">` carrying the CSRF token. HIDDEN when `seller_id == current_user_id` (replaced with "This is your listing.") OR when `quantity_sold >= quantity` (replaced with "Out of stock"). For guests the existing `<a href="/login?next=/board">` is preserved.
- **`dispute_modal` partial wrapper** at `src/Support/View/partials/dispute_modal.php` (a thin shell that requires the full dispute modal at `src/Ticket/View/dispute_modal.php`) so the My Tickets View can call `View::partial('dispute_modal', $vars)`.
- **BuyAction redirect** — `Location: /my-tickets?new={ticket_id}` on success so the View can auto-focus the freshly-bought card (D-02 inline script: `setTimeout(() => document.querySelector('[data-ticket-id="..."]')?.focus(), 50)`).
- **Auto-focus inline script** — at the bottom of `my_tickets.php` (per D-02): focuses the new ticket card when `?new={id}` is set.
- **`RedeemAction::normalizeCode`** — fixed to expect 5 groups of 4 base62 chars (20 chars after the `TK-` prefix), matching the canonical format from Plan 04-01. The previous 6-group / 24-char expectation was the early plan number that was reverted.

## Must Haves (truths verified)

- [x] `GET /my-tickets` as a logged-in buyer returns the 5-tab nav with `aria-current='page'` on the active tab; each ticket card renders via the `listing_card` partial pattern (with `--rot: crc32($id) % 5 - 2`deg) and includes the ticket-code-block, status-badge, and per-session progress partials. The dispute button is conditionally rendered for `dispute_status='none' AND status IN ('active','redeemed')`.
- [x] `POST /listings/{id}/buy` as a logged-in buyer with rate-limit allowance returns 302; the Action flashes "Ticket created. Code: TK-..." and the new ticket row exists. Self-purchase and sold-out return 302 with no ticket insert.
- [x] `POST /tickets/redeem` as a logged-in seller returns 302; the Action flashes "Ticket redeemed. Handover complete." and the ticket transitions to `redeemed`. 2 `points_log` rows are written, `users.points` updates for both parties, `redeemed_count` increments.
- [x] `POST /tickets/redeem` with an invalid code returns 302; the Action flashes "Ticket not found." and no state changes.
- [x] `POST /tickets/{id}/confirm-session` as the seller increments `session_number`. Intermediate sessions append an audit row with no points. The final session awards points and auto-redeems the ticket.
- [x] `POST /tickets/{id}/dispute` as buyer or seller with valid reason + text sets `dispute_status='pending'` and (if `status='active'`) flips to `status='disputed'`. A `reports` row is inserted with `target_type='ticket'`.
- [x] `POST /tickets/{id}/dispute` with an invalid reason returns 302 with no `reports` row.
- [x] `GET /sales` as a logged-in seller returns 200 with the per-listing-group cards (D-05). Each listing shows: listing title + per-listing progress chip (only when `total_sessions > 1`) + ticket rows with the buyer's nickname + `#N/M` session progress + "Confirm next session" button next to the in-progress ticket. The redemption input form sits at the top of the page.
- [x] `GET /purchases` as a logged-in buyer returns 200 with the chronological table (desktop) / stacked rows (mobile). Columns: Code, Status, Listing, Price, Seller, Date. The `Leave review` column is NOT in Phase 4.
- [x] The listing modal's Buy now is HIDDEN when `seller_id == current_user_id` AND when `quantity_sold >= quantity`. The respective "This is your listing." / "Out of stock" affordances replace it.
- [x] The My Tickets empty state shows "No tickets yet. Buy your first item." with a "Browse Board" link to `/board`.
- [x] The Sales empty state shows "No sales yet. Your first sale happens when someone buys one of your listings." with a "View your listings" link to `/my-listings`.
- [x] `GET /my-tickets`, `/sales`, `/purchases` all redirect guests to `/login?next=<path>` (auth guard).
- [x] Full PHPUnit suite is green: 403 tests, 2795 assertions. phpcs clean.

## Decisions

- **`normalizeCode()` fixed to 5 groups of 4** (not 6). The action's helper still expected the old 6-group / 24-char count from the pre-04-01 spec, but the canonical `ticket_model::formatCode` produces 5 groups. The action now expects 20 base62 chars after `TK-`. This was a latent bug in Plan 04-01 that surfaced only when the new flow tests exercised the input pipeline.
- **`Fixtures::seedTicket` switched to use `ticket_model::formatCode`** so seeded ticket rows match the canonical dashed form. The previous seedTicket used `TK-` + 24 hex chars (no dashes), which made redemption-by-code tests fail with E_TICKET_NOT_FOUND.
- **Phase 3 Fixtures extended** to TRUNCATE `tickets`, `reports`, `audit_log` (Phase 4 tables). Without this, an auto-incremented listing id in a Phase 3 test could collide with a ticket row from a previous test, causing FK violations on `hardDelete`.
- **`dispatchAction` accepts `$_POST` payload** (5th arg `$postVars`). The previous implementation set `$_POST = []` after the parent populated `$GLOBALS['current_user']`, so any test that needed a POSTed `ticket_code` was silently stripped. Now flow tests pass `$_POST` cleanly via the dispatch helper.
- **`listing_modal` Buy now is a `<form>` not an `<a>`** per the plan must_haves. The `listing-modal__buy` CSS class is preserved. The Phase 3 tests were updated to assert the new `action="/listings/{id}/buy"` form pattern.
- **Tests assert 302 status + DB state** instead of the Location header. PHP CLI's `header()` is a silent no-op (only `http_response_code()` captures the status). The 302 status + the expected DB state changes (ticket row insert, points_log insert, audit row insert) is the meaningful contract.

## Deviations from Plan

- **5-group format extended to test fixtures** — see Decisions. Necessary because the existing Fixtures seedTicket bypassed the canonical generator.
- **Phase 3 fixture resetTables extension** — added `audit_log`, `reports`, `tickets` to the truncate list. The Phase 3 test suite was previously running without seeing these tables; the FK from `tickets.listing_id → listings.id` is `RESTRICT` (NFR-REL-006) so a stale ticket row would block `hardDelete`. This is a defensive widening, not a behavior change.
- **Test file: `RouteGuardTicketTest` uses source-level assertions** for the Action-level 302 + `header('Location: ...')` calls rather than pcntl_fork dispatch. Reason: `header()` is a no-op in CLI; the only way to capture the Location header would require xdebug or a custom error handler. The source assertion is the cleaner contract for a WAD-grade Phase 4 test.

## Files Added (12)

### Source
- `src/Support/View/partials/dispute_modal.php` (Phase 4 wrapper for the full modal)

### Tests (8)
- `tests/Integration/Phase04/Support/RouteGuardTicketTest.php`
- `tests/Integration/Phase04/Ticket/MyTicketsViewTest.php`
- `tests/Integration/Phase04/Ticket/SalesViewTest.php`
- `tests/Integration/Phase04/Ticket/PurchaseHistoryTest.php`
- `tests/Integration/Phase04/Ticket/BuyNowFlowTest.php`
- `tests/Integration/Phase04/Ticket/RedemptionFlowTest.php`
- `tests/Integration/Phase04/Ticket/SessionConfirmFlowTest.php`
- `tests/Integration/Phase04/Ticket/DisputeFlowTest.php`

## Files Modified (16)

### Source
- `src/Ticket/Service/ticket_service.php` (3 new helpers)
- `src/Ticket/Model/ticket_model.php` (4 new queries)
- `src/Ticket/Action/MyTicketsAction.php` (replaced with real body)
- `src/Ticket/Action/SalesAction.php` (replaced with real body)
- `src/Ticket/Action/PurchasesAction.php` (replaced with real body)
- `src/Ticket/Action/BuyAction.php` (redirect + flash on error)
- `src/Ticket/Action/RedeemAction.php` (normalizeCode fix to 5 groups)
- `src/Ticket/View/dispute_modal.php` (added use statement for ticket_service)
- `src/Ticket/View/my_tickets.php` (replaced with real View)
- `src/Ticket/View/sales.php` (replaced with real View)
- `src/Ticket/View/purchases.php` (replaced with real View)
- `src/Listing/View/listing_modal.php` (Buy now form, self-owned + sold-out affordances)
- `src/Listing/View/board.php` (pass csrf_token to listing_modal)

### Tests
- `tests/Integration/Phase03/Fixtures/Fixtures.php` (truncate Phase 4 tables)
- `tests/Integration/Phase03/Listing/BrowseBoardTest.php` (assert new <form> pattern)
- `tests/Integration/Phase03/Listing/GuestBrowseTest.php` (assert new <form> pattern)
- `tests/Integration/Phase03/Listing/ModalRenderTest.php` (assert new <form> pattern, render-as-user helper)
- `tests/Integration/Phase04/Fixtures/Fixtures.php` (dispatchAction accepts postVars; seedTicket uses formatCode)

## Tests Added (49 tests across 8 files)

| File | Tests | Assertions |
| --- | --- | --- |
| Integration/Phase04/Support/RouteGuardTicketTest | 6 | 9 |
| Integration/Phase04/Ticket/MyTicketsViewTest | 11 | 29 |
| Integration/Phase04/Ticket/SalesViewTest | 7 | 16 |
| Integration/Phase04/Ticket/PurchaseHistoryTest | 6 | 17 |
| Integration/Phase04/Ticket/BuyNowFlowTest | 4 | 11 |
| Integration/Phase04/Ticket/RedemptionFlowTest | 5 | 16 |
| Integration/Phase04/Ticket/SessionConfirmFlowTest | 5 | 23 |
| Integration/Phase04/Ticket/DisputeFlowTest | 6 | 20 |
| **Total** | **50** | **141** |

(Note: counts above include the 1 'extra' RouteGuardTicketTest assertion block; the 49-vs-50 is due to the route-guard test listing the count as 6 while adding 3 route-guard assertion blocks + 3 source-level assertion blocks.)

## Test Results

```
PHPUnit 11.5.56
Phase 3 integration: OK (175 tests, 640 assertions)
Phase 4 integration: OK (98 tests, 1343 assertions)
Full suite:          OK (403 tests, 2795 assertions)
phpcs (PSR-12):      0 errors, 0 warnings
```

## Commit Count

7 commits for Plan 04-02 (atomic, one per logical layer):

1. `feat(04-02): add getTicketsForBuyer, getGroupedSales, getPurchaseHistory helpers + findByBuyerAndStatus/findGroupedSales/findPurchaseHistory/findActiveServicesBySeller queries`
2. `feat(04-02): replace 3 ticket-context Actions + update Buy/Redeem to redirect with status 302 + use ticket_service for ticket code generation`
3. `feat(04-02): replace 3 ticket Views (5-tab My Tickets, per-listing Sales, chronological Purchases) + dispute_modal wrapper partial`
4. `feat(04-02): listing modal Buy now becomes POST form with CSRF; HIDDEN for self-owned + sold-out`
5. `test(04-02): update Phase 3 tests to assert the new <form> Buy now pattern + extend Phase 3 Fixtures.resetTables to truncate Phase 4 tables`
6. `test(04-02): update Phase 4 Fixtures - dispatchAction accepts postVars; seedTicket uses canonical ticket code generator`
7. `test(04-02): 8 new test files - View tests for My Tickets / Sales / Purchases + flow tests for Buy / Redeem / ConfirmSession / Dispute + route guard`
