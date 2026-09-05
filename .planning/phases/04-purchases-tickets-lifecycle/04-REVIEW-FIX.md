---
phase: 04-purchases-tickets-lifecycle
fixed_at: 2026-09-05T00:00:00Z
review_path: .planning/phases/04-purchases-tickets-lifecycle/04-REVIEW.md
iteration: 1
findings_in_scope: 10
fixed: 10
skipped: 0
status: all_fixed
---

# Phase 04 — Code Review Fix Report

**Fixed at:** 2026-09-05T00:00:00Z
**Source review:** `.planning/phases/04-purchases-tickets-lifecycle/04-REVIEW.md`
**Iteration:** 1

**Summary:**
- Findings in scope: 10
- Fixed: 10 (1 by this run, 9 already fixed in prior commits)
- Skipped: 0

## Scope note

9 of 10 findings (CR-01, CR-03, CR-04, CR-05, WR-01, WR-02, WR-03, WR-04, WR-05) were already fixed in earlier `fix(04):` commits before this fixer ran. The current source tree was inspected and each prior fix was verified as present and correctly implemented:

- CR-01 fixed at `907c9b5` — `findByCodeForUpdate` in `redeemTicket`
- CR-03 fixed at `ad9cc0b` — `price_cents` in the createTicket FOR UPDATE projection
- CR-04 fixed at `5a93c06` — FOR UPDATE + `quantity_sold >= ?` guard in `decrementListingStockForExpiredTicket`
- CR-05 fixed (Audit.php) — explicit `PDO::PARAM_NULL`/`PDO::PARAM_INT` binding + boundary normalization
- WR-01 fixed at `5e932f7` — state check before seller check in `redeemTicket`
- WR-02 fixed at `f645c7d` — `isFinal` derived from `session_number >= total_sessions`
- WR-03 fixed at `6933978` — unconditional `redeemed_count` increment on `final_session`
- WR-04 fixed at `12f9413` — `$auditOk` capture + `error_log` on `Audit::log` failure at 4 call sites
- WR-05 fixed at `9d933eb` — `$decrement > 0` + `quantity_sold >= ?` guards + test coverage

The only finding still outstanding was CR-02, which the prior commit `5a93c06` had mis-applied: the FOR UPDATE lock was placed in the wrong branch of `fileDispute` (after rollback, where it was a no-op) and was missing from `confirmSession` entirely. The current run is the proper CR-02 fix.

## Fixed Issues

### CR-02: `confirmSession` + `fileDispute` pre-flight now use FOR UPDATE row lock

**Files modified:** `004/tickettrade/src/Ticket/Service/ticket_service.php`
**Commit:** `c8919d7`
**Applied fix:**

1. `confirmSession` (line 351): replaced the non-locking `ticket_model::findById` with `ticket_model::findByIdForUpdate`. The row is now X-locked before the `session_number < total_sessions` pre-flight check, so the subsequent `incrementSession` UPDATE observes the same snapshot and cannot race against a concurrent `confirmSession` / `redeemTicket` / `fileDispute` on the same ticket.

2. `fileDispute` (lines 478–520): restructured the pre-flight + atomic UPDATE sequence. The previous commit (5a93c06) had placed `findByIdForUpdate` inside the post-rollback error-mapping branch — where the transaction was already gone and the FOR UPDATE lock was meaningless. Now: `findByIdForUpdate` runs first (inside the active transaction), error codes `E_TICKET_NOT_FOUND` / `E_TICKET_FORBIDDEN` are mapped from the locked read, then `ticket_model::fileDispute` runs the atomic UPDATE under the same X-lock.

**Verification:** syntax check `php -l` clean; `SessionConfirmTest`, `DisputeFlowTest`, `RedemptionFlowTest`, `TicketRedemptionTest` (22 tests, 82 assertions) pass.

## Verified-as-already-fixed

The remaining 9 findings were already addressed in earlier commits on `NSBM-EventHub`. Each was re-verified by inspecting the current source tree; the fixes described in the review's "Suggested fix" sections are present and correct. Re-applying them would duplicate the commits.

### CR-01: `redeemTicket` pre-flight `findByCode` is not locked

**File:** `004/tickettrade/src/Ticket/Service/ticket_service.php:228-262`
**Reason:** Already fixed at `907c9b5` (`fix(04): CR-01 use FOR UPDATE row lock on redeemTicket pre-flight`). The Service now calls `ticket_model::findByCodeForUpdate($pdo, $code)` on line 238. Verified in current source.
**Original issue:** Pre-flight SELECT was non-locking; subsequent UPDATE could race.

### CR-03: `createTicket` re-reads `listings.price_cents` after the lock with a non-locking SELECT

**File:** `004/tickettrade/src/Ticket/Service/ticket_service.php:144-147`
**Reason:** Already fixed at `ad9cc0b` (`fix(04): CR-03 include price_cents in FOR UPDATE listing lock`). The line-94 `SELECT ... FOR UPDATE` now projects `price_cents` directly; the separate non-locking re-read on line 144 has been removed. Verified in current source.
**Original issue:** price_cents was read in a separate non-locking SELECT after the lock.

### CR-04: `decrementListingStockForExpiredTicket` blindly UPDATEs without re-reading under lock

**File:** `004/tickettrade/src/Ticket/Model/ticket_model.php:551-574`
**Reason:** Already fixed at `5a93c06` (and earlier). The function now locks the listing under `SELECT ... FOR UPDATE` first, guards the decrement on `quantity_sold >= ?` AND `status IN ('active','sold')`, and the status-restore runs under the same lock. Verified in current source.
**Original issue:** 5-second-window re-run could re-decrement stock.

### CR-05: `audit_log.metadata_json` nullable actor binding

**File:** `004/tickettrade/src/Support/Audit.php:42-74`
**Reason:** Already fixed. `Audit::log()` now uses explicit `bindValue()` with `PDO::PARAM_NULL` when `$actorUserId` is null or `<= 0`, otherwise `PDO::PARAM_INT`. Boundary normalization `$bindActor = ($actorUserId !== null && $actorUserId > 0) ? $actorUserId : null` protects callers that forget the cast. Verified in current source.
**Original issue:** Nullable int could bind as empty string for BIGINT UNSIGNED FK.

### WR-01: `redeemTicket` pre-flight error-code ordering (state before seller)

**File:** `004/tickettrade/src/Ticket/Service/ticket_service.php:228-262`
**Reason:** Already fixed at `5e932f7` (`fix(04): WR-01 reorder pre-flight: state before seller-identity`). State check (`status!='active' || dispute_status='pending'`) runs at lines 250–259 BEFORE the seller-identity check at lines 260–266. Verified in current source.
**Original issue:** Wrong seller on an inactive ticket got E_TICKET_FORBIDDEN instead of E_TICKET_INVALID_STATE.

### WR-02: `redeemTicket` hardcoded `isFinal = true`

**File:** `004/tickettrade/src/Ticket/Service/ticket_service.php:269-276`
**Reason:** Already fixed at `f645c7d` (`fix(04): WR-02 derive isFinal from session_number >= total_sessions`). Line 286 now reads `$isFinal = ((int) $redeemed['session_number'] >= (int) $redeemed['total_sessions'])`. Verified in current source.
**Original issue:** Hardcoded isFinal would mis-mark service tickets if they ever hit /tickets/redeem.

### WR-03: `redeemed_count` not incremented when points skip (frozen)

**File:** `004/tickettrade/src/Points/Service/points_service.php:384-396`
**Reason:** Already fixed at `6933978` (`fix(04): WR-03 increment redeemed_count on final_session even when points skip`). The `redeemed_count` UPDATE at lines 395–409 runs unconditionally after the velocity / pair-cap short-circuit logic, with a comment explaining the semantic (per-redemption, not per-points-credited). Verified in current source.
**Original issue:** A frozen user redeeming a ticket never advanced FR-PTS-007 halving counter.

### WR-04: `Audit::log` return value not checked at call sites

**File:** `004/tickettrade/src/Support/Audit.php:42-74` + 4 callers in `ticket_service.php`
**Reason:** Already fixed at `12f9413` (`fix(04): WR-04 capture Audit::log return value in 4 ticket call sites`). `createTicket` (line 175), `redeemTicket` (line 301), `confirmSession` (line 424), `fileDispute` (line 530) all capture `$auditOk = Audit::log(...)` and `error_log` when it returns 0. Verified in current source.
**Original issue:** Silent audit failures were invisible in the response.

### WR-05: `decrementListingStockForExpiredTicket` runs even when `quantity_sold = 0`

**File:** `004/tickettrade/src/Ticket/Model/ticket_model.php:551-574`
**Reason:** Already fixed (commit `9d933eb` added a test; the function guard is in place). The function now early-returns when `$decrement <= 0` (line 616) and the guarded UPDATE on line 627 requires `quantity_sold >= ?`. Both prevent wasted / under-counting work. Verified in current source.
**Original issue:** Decrement could run on already-zero stock.

---

_Fixed: 2026-09-05T00:00:00Z_
_Fixer: the agent (gsd-code-fixer)_
_Iteration: 1_
_Commit: c8919d7_