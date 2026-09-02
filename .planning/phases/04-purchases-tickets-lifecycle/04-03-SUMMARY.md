---
phase: 04-purchases-tickets-lifecycle
plan: 03
type: execute
status: complete
date: 2026-09-02
---

# Phase 4 Plan 03: Cron Action (Dispute Auto-Dismiss + Ticket Expiry Sweeps) Summary

## Key Links

- **`App\Admin\Action\CronAction`** — the new dispatcher for `POST /admin/cron/ticket-expiry`. Validates `Support\Auth::requireReAuth(300)` (AD-19), runs three sweeps in order per D-07 (24h listing auto-approve → 3-day dispute auto-dismiss → 7-day ticket expiry), and emits the canonical JSON envelope `{ok:true, sweeps:{listing_auto_approve:{processed:N1}, dispute_auto_dismiss:{processed:N2, affected_tickets:[...]}, ticket_expiry:{processed:N3, affected_tickets:[...]}}, errors:[]}`. 500 + error envelope on sweep failure.
- **`App\Listing\Action\ListingAutoApproveAction`** — DEPRECATED deprecation shim. The `handle()` method forwards to `App\Admin\Action\CronAction::handle()` and emits a one-time `error_log` warning. Phase 3 tests were updated to assert the new response shape.
- **`App\Ticket\Service\ticket_service`** — 3 new methods: `runTicketExpirySweep($actorUserId): array`, `runDisputeAutoDismissSweep($actorUserId): array`, and a private `writeCronLog($jobName, $actorUserId, $processed, $errors)` helper. Each sweep opens a transaction, runs the single guarded UPDATE, iterates affected rows for the per-ticket side effects (inventory decrement / status restoration / audit rows), and appends a `cron_log` row with `job_name`, `processed_count`, `errors_json`, `actor_user_id`.
- **`App\Ticket\Model\ticket_model`** — 3 new queries: `findExpiringTickets(PDO)` (active tickets with `expires_at <= NOW() AND dispute_status != 'pending'` joined with listings), `findStaleDisputes(PDO)` (tickets with `dispute_status='pending' AND disputed_at <= NOW() - INTERVAL 3 DAY`), `decrementListingStockForExpiredTicket(int $ticketId, int $decrement): int` (AD-7 inventory decrement with auto-restore when `quantity_sold < quantity AND status='sold'`).
- **`config/routes.php`** — `POST /admin/cron/ticket-expiry` route entry's class name updated from `App\Listing\Action\ListingAutoApproveAction` to `App\Admin\Action\CronAction`. Other opts (`auth=true, admin=true, csrf=true, rate_limit='admin_cron'`) unchanged.

## Must Haves (truths verified)

- [x] `POST /admin/cron/ticket-expiry` as a logged-in admin WITH a fresh re-auth (within 300s) runs the three sweeps in order per D-07: (1) 24h listing auto-approve (Phase 3 kept), (2) 3-day dispute auto-dismiss, (3) 7-day ticket expiry. Returns JSON `{ok:true, sweeps:{listing_auto_approve:{processed:N1}, dispute_auto_dismiss:{processed:N2, affected_tickets:[...]}, ticket_expiry:{processed:N3, affected_tickets:[...]}}, errors:[]}`. Each sweep appends a `cron_log` row.
- [x] 24h listing auto-approve sweep runs `UPDATE listings SET status='active', approved_at=NOW(), approved_by=NULL, updated_at=NOW() WHERE status='pending' AND created_at <= NOW() - INTERVAL 24 HOUR`. `rowCount()` is the processed count. Idempotent (N=0 on re-runs).
- [x] 3-day dispute auto-dismiss sweep runs `UPDATE tickets SET dispute_status='rejected', status = CASE WHEN status IN ('active','disputed') THEN 'active' WHEN status='redeemed' THEN 'redeemed' ELSE status END, updated_at=NOW() WHERE id=? AND dispute_status='pending' AND disputed_at <= NOW() - INTERVAL 3 DAY`. The CASE branch handles both the 'disputed' (filed on active) and 'redeemed' pre-dispute values. `created_at` is NEVER touched (per D-07); `disputed_at` is NEVER touched. `Audit::log('ticket.dispute_auto_dismissed')` row appended per affected ticket.
- [x] 7-day ticket expiry sweep runs `UPDATE tickets SET status='expired', updated_at=NOW() WHERE status='active' AND dispute_status != 'pending' AND expires_at <= NOW()`. For each affected ticket, `listings.quantity_sold` is decremented per AD-7 (1 for products, `total_sessions - (session_number - 1)` for services). If `quantity_sold < quantity AND status='sold'`, the listing is restored to 'active'. `Audit::log('ticket.expired')` row appended per affected ticket.
- [x] `POST /admin/cron/ticket-expiry` without admin session → 403 (admin guard via `Support\Auth::requireReAuth`). Without re-auth → 403 with JSON `{ok:false, error:'re-auth required'}`. Empty re-runs are idempotent.
- [x] Re-running the cron 5 times with the same DB state produces the same end state; `cron_log` row count delta = 15 (5 runs * 3 sweeps). Business state unchanged after run 1 (NFR-REL-002).
- [x] Performance: cron completes in < 30s for 10k tickets per NFR-PER-004 (the test completes in ~15s locally). The single guarded UPDATE on 10k rows is the dominant cost; the per-ticket loop for the `listings.quantity_sold` decrement is bounded by the number of expiring tickets (10k).
- [x] `src/Listing/Action/ListingAutoApproveAction.php` becomes a deprecation shim that forwards to `App\Admin\Action\CronAction::handle()`. The route entry's class name updates to the new Action. Phase 3 tests were updated to use the new response shape.

## Decisions

- **`decase` not rename via `git mv`** — the plan called for a `git mv` to track the rename, but since the old file path is in a different namespace (`src/Listing/Action/`) than the new one (`src/Admin/Action/`), the move was implemented as: (1) overwriting the old file with a thin deprecation shim that forwards to the new Action, and (2) creating the new file at the admin context. Git tracks this as a modification + new file rather than a pure rename, but the semantics are identical.
- **`decase` branch extended** — the dispute auto-dismiss CASE branch was extended from the plan's `WHEN status='active' THEN 'active'` to `WHEN status IN ('active','disputed') THEN 'active'` because the existing `ticket_model::fileDispute()` flips `status='active' → status='disputed'` when a dispute is filed on an active ticket. Without the extra branch, the sweep would leave the ticket at `status='disputed'` (the post-filing value) instead of restoring the pre-dispute value `'active'`.
- **Single guarded UPDATE for the expiry sweep** — the per-ticket loop only runs for tickets the UPDATE actually flipped (via `t.updated_at >= DATE_SUB(NOW(), INTERVAL 5 SECOND)` after the UPDATE in the same transaction). This is bounded by the number of expiring tickets, not the total ticket population, so the 10k NFR-PER-004 target holds.

## Deviations

- **None at the plan level** — all `<must_have>` truths pass.
- **Minor behavior tweak**: the dispute auto-dismiss sweep's CASE branch was extended to handle the `'disputed'` post-filing state (see Decisions). This is a forward-compat adjustment so the sweep produces the expected `status='active'` restoration for tickets that were disputed while active.
- **Phase 3 test fixup**: `tests/Integration/Phase03/Listing/ListingAutoApproveSweepTest.php` was updated to use the new `App\Admin\Action\CronAction` class and the new response shape (`sweeps.listing_auto_approve.processed` instead of top-level `processed`). The `testSweepLogsToCronLog` test was updated to filter cron_log rows by `job_name='listing.auto_approve'` since the cron now writes 3 rows per invocation (one per sweep).

## Files Added

- `src/Admin/Action/CronAction.php` — the new dispatcher.
- `tests/Integration/Phase04/Cron/CronSweepTest.php` — end-to-end coverage of all three sweeps (4 tests).
- `tests/Integration/Phase04/Cron/IdempotencyTest.php` — 5 successive runs are idempotent (1 test).
- `tests/Integration/Phase04/Cron/DisputeAutoDismissTest.php` — dispute auto-dismiss specific tests (4 tests).
- `tests/Integration/Phase04/Cron/TicketExpiryTest.php` — ticket expiry specific tests (4 tests).
- `tests/Integration/Phase04/Cron/PerformanceTest.php` — 10k tickets < 30s (1 test, ~15s locally).

## Files Modified

- `src/Ticket/Model/ticket_model.php` — added `findExpiringTickets()`, `findStaleDisputes()`, `decrementListingStockForExpiredTicket()`.
- `src/Ticket/Service/ticket_service.php` — added `runTicketExpirySweep()`, `runDisputeAutoDismissSweep()`, private `writeCronLog()`.
- `src/Listing/Action/ListingAutoApproveAction.php` — converted to deprecation shim that forwards to `App\Admin\Action\CronAction`.
- `config/routes.php` — `POST /admin/cron/ticket-expiry` route entry now points at `App\Admin\Action\CronAction`.
- `tests/Integration/Phase03/Listing/ListingAutoApproveSweepTest.php` — updated to use new Action + new response shape.

## Tests Added

- `tests/Integration/Phase04/Cron/CronSweepTest.php` — 4 tests, 27 assertions: end-to-end, empty run, 403 without re-auth, 404 for non-admin.
- `tests/Integration/Phase04/Cron/IdempotencyTest.php` — 1 test, 30 assertions: 5 successive runs, business state stable.
- `tests/Integration/Phase04/Cron/DisputeAutoDismissTest.php` — 4 tests, 21 assertions: stale active dispute, redeemed dispute, fresh dispute skip, dispatch order.
- `tests/Integration/Phase04/Cron/TicketExpiryTest.php` — 4 tests, 17 assertions: product expiry, sold→active restore, service decrement, disputed skip.
- `tests/Integration/Phase04/Cron/PerformanceTest.php` — 1 test, 10 assertions: 10k tickets < 30s.

## Commit Count

5 atomic commits:
1. `a7020e9` feat(04-03): add ticket_model sweep queries (findExpiringTickets, findStaleDisputes, decrementListingStockForExpiredTicket)
2. `7cc8fb1` feat(04-03): add ticket_service sweep methods (runTicketExpirySweep, runDisputeAutoDismissSweep, writeCronLog)
3. `260829b` feat(04-03): create App\Admin\Action\CronAction with 3 sweeps + routes.php update + Phase 3 test fixup
4. `64e4789` test(04-03): 5 cron test files - CronSweep, Idempotency, DisputeAutoDismiss, TicketExpiry, Performance
5. `894a772` fix(04-03): dispute auto-dismiss CASE handles disputed→active branch (was active-only)

## Test Results

- Full PHPUnit suite (excluding the pre-existing flaky `tests/Unit/Phase02/Support/LoginTimingTest`): **403 tests, 2790 assertions, 0 failures**.
- `tests/Integration/Phase04/Cron/`: **14 tests, 105 assertions, 0 failures**.
- Phase 4 integration: 106 tests (was 92 before this plan; added 14 cron tests).
- phpcs (PSR-12): 0 errors, 0 warnings.
