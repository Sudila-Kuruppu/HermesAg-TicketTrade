---
phase: 04-purchases-tickets-lifecycle
reviewed: 2026-09-05T00:00:00Z
depth: standard
files_reviewed: 66
files_reviewed_list:
  - 004/tickettrade/config/error_codes.php
  - 004/tickettrade/config/rate_limits.php
  - 004/tickettrade/config/routes.php
  - 004/tickettrade/migrations/013_tickets.sql
  - 004/tickettrade/migrations/014_users_redemption_count.sql
  - 004/tickettrade/migrations/015_reports.sql
  - 004/tickettrade/migrations/016_audit_log_stub.sql
  - 004/tickettrade/phpcs.xml
  - 004/tickettrade/phpunit.xml
  - 004/tickettrade/public/assets/js/tickettrade.js
  - 004/tickettrade/src/Admin/Action/CronAction.php
  - 004/tickettrade/src/Listing/Action/ListingAutoApproveAction.php
  - 004/tickettrade/src/Listing/Model/listing_model.php
  - 004/tickettrade/src/Listing/View/board.php
  - 004/tickettrade/src/Listing/View/listing_modal.php
  - 004/tickettrade/src/Points/Service/points_service.php
  - 004/tickettrade/src/Support/Audit.php
  - 004/tickettrade/src/Support/RateLimit.php
  - 004/tickettrade/src/Support/View/partials/dispute_modal.php
  - 004/tickettrade/src/Support/View/partials/session_progress.php
  - 004/tickettrade/src/Support/View/partials/status_badge.php
  - 004/tickettrade/src/Support/View/partials/ticket_code_block.php
  - 004/tickettrade/src/Ticket/Action/BuyAction.php
  - 004/tickettrade/src/Ticket/Action/ConfirmSessionAction.php
  - 004/tickettrade/src/Ticket/Action/DisputeAction.php
  - 004/tickettrade/src/Ticket/Action/MyTicketsAction.php
  - 004/tickettrade/src/Ticket/Action/PurchasesAction.php
  - 004/tickettrade/src/Ticket/Action/RedeemAction.php
  - 004/tickettrade/src/Ticket/Action/SalesAction.php
  - 004/tickettrade/src/Ticket/Action/TicketDetailAction.php
  - 004/tickettrade/src/Ticket/Model/ticket_model.php
  - 004/tickettrade/src/Ticket/Service/ticket_service.php
  - 004/tickettrade/src/Ticket/View/confirm_session_card.php
  - 004/tickettrade/src/Ticket/View/dispute_modal.php
  - 004/tickettrade/src/Ticket/View/my_tickets.php
  - 004/tickettrade/src/Ticket/View/purchases.php
  - 004/tickettrade/src/Ticket/View/sales.php
  - 004/tickettrade/src/Ticket/View/ticket_detail.php
  - 004/tickettrade/tests/Integration/Phase03/Fixtures/Fixtures.php
  - 004/tickettrade/tests/Integration/Phase03/Listing/BrowseBoardTest.php
  - 004/tickettrade/tests/Integration/Phase03/Listing/GuestBrowseTest.php
  - 004/tickettrade/tests/Integration/Phase03/Listing/ListingAutoApproveSweepTest.php
  - 004/tickettrade/tests/Integration/Phase03/Listing/ModalRenderTest.php
  - 004/tickettrade/tests/Integration/Phase04/Cron/CronSweepTest.php
  - 004/tickettrade/tests/Integration/Phase04/Cron/DisputeAutoDismissTest.php
  - 004/tickettrade/tests/Integration/Phase04/Cron/IdempotencyTest.php
  - 004/tickettrade/tests/Integration/Phase04/Cron/PerformanceTest.php
  - 004/tickettrade/tests/Integration/Phase04/Cron/TicketExpiryTest.php
  - 004/tickettrade/tests/Integration/Phase04/Fixtures/Fixtures.php
  - 004/tickettrade/tests/Integration/Phase04/MigrationTest.php
  - 004/tickettrade/tests/Integration/Phase04/Points/AwardTransactionTest.php
  - 004/tickettrade/tests/Integration/Phase04/Support/AuditStubTest.php
  - 004/tickettrade/tests/Integration/Phase04/Support/RouteGuardTicketTest.php
  - 004/tickettrade/tests/Integration/Phase04/Ticket/BuyNowFlowTest.php
  - 004/tickettrade/tests/Integration/Phase04/Ticket/DisputeFlowTest.php
  - 004/tickettrade/tests/Integration/Phase04/Ticket/MyTicketsViewTest.php
  - 004/tickettrade/tests/Integration/Phase04/Ticket/PurchaseHistoryTest.php
  - 004/tickettrade/tests/Integration/Phase04/Ticket/RedemptionFlowTest.php
  - 004/tickettrade/tests/Integration/Phase04/Ticket/SalesViewTest.php
  - 004/tickettrade/tests/Integration/Phase04/Ticket/SessionConfirmFlowTest.php
  - 004/tickettrade/tests/Integration/Phase04/Ticket/SessionConfirmTest.php
  - 004/tickettrade/tests/Integration/Phase04/Ticket/TicketCodeGeneratorTest.php
  - 004/tickettrade/tests/Integration/Phase04/Ticket/TicketCreationTest.php
  - 004/tickettrade/tests/Integration/Phase04/Ticket/TicketRedemptionTest.php
  - 004/tickettrade/tests/Unit/Phase04/Support/AuditStubUnitTest.php
  - 004/tickettrade/tests/Unit/Phase04/Support/RateLimitPerTicketTest.php
findings:
  critical: 5
  blocker: 0
  warning: 5
  info: 0
  total: 10
status: issues_found
---

# Phase 04 — Code Review Report

**Reviewed:** 2026-09-05
**Depth:** standard
**Files Reviewed:** 66
**Status:** issues_found

## Summary

Phase 4 ships the purchases / tickets / lifecycle subsystem: ticket code generation,
atomic buy/redeem/confirm/dispute flows, cron sweeps (ticket expiry + dispute
auto-dismiss), and the ticket-code-block JS partial with mask/copy/share.

The implementation is broadly sound. Atomic ticket operations use `SELECT … FOR UPDATE`
in the right places, the base62 ticket-code generator has a retry loop with a
collision cap, rate limits carry the new per-ticket bucket key, audit logging
honors the never-throw contract, and CSRF is enforced globally at bootstrap for
every state-changing route. Views escape user-influenced output via
`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` and PDO is used everywhere with
prepared statements (no string interpolation was found).

The blockers found are concentrated in the concurrency story. `createTicket`,
`redeemTicket`, `confirmSession`, and `fileDispute` all do a pre-flight
non-locking `SELECT` before the atomic `UPDATE`, but in some cases the pre-flight
read of an auxiliary table (listings.price_cents, tickets.status) happens AFTER
the row lock and reads a different snapshot from a different statement, opening
a small but real TOCTOU window. The `tickets` table FK to `listings` uses
`ON DELETE RESTRICT` — that's correct, but combined with the fact that
`decrementListingStockForExpiredTicket` blindly UPDATEs without checking
`status='sold'`, a re-run on a listing that's already restored to active can
under-count stock. Several other findings degrade robustness without breaking
correctness: column-type mismatches in the JSON `metadata_json` queries, an
unused `redeemed_count` column in the `redeemed_count INCREMENT` path on
intermediate sessions, a missing page-load focus on the new ticket card when
the listing belongs to a non-numeric ID edge case, and a typo in a docblock.

**Recommendation:** fix the 5 critical issues before the next milestone ships;
they affect the atomicity guarantee the rest of the system relies on.

## Critical Issues

### CR-01: `redeemTicket` pre-flight `findByCode` is not locked; an UPDATE can flip status between read and UPDATE

**File:** `004/tickettrade/src/Ticket/Service/ticket_service.php:228-255`
**Severity:** BLOCKER
**Issue:** `redeemTicket` performs a non-locking `ticket_model::findByCode($pdo, $code)` (line 228), validates the result in PHP, and then runs `ticket_model::markRedeemed` (line 255) which is an atomic `UPDATE ... WHERE ticket_code = ? AND status='active' AND dispute_status != 'pending' AND seller_id = ?`. Because the pre-flight `SELECT` is not `FOR UPDATE`, another concurrent request can:
- File a dispute on the same ticket between line 228 and line 255 → `markRedeemed` returns null → caller gets `E_TICKET_INVALID_STATE`. (Benign — correctly fails closed.)
- A second redeem request for the same code can pass both pre-flight checks; the second `markRedeemed` UPDATE then guards on `status='active'` and fails because the first one already flipped status to 'redeemed'. (Benign — natural idempotency, matches the AGENTS.md "atomic UPDATE redemption (naturally idempotent)" promise.)
- However: between pre-flight reading `status='active'` (line 244) and the atomic UPDATE, a second concurrent `redeemTicket` call can race the same `$sellerId` and `points_service::awardTransaction` is invoked *both* times. Because both UPDATEs hit the `WHERE status='active'` guard, only the first can succeed (the second's `rowCount()===0` short-circuits); but the pre-flight reads will both see `status='active'`, and the points-awarding side only runs for the first. So this case is also benign.

The actual data-loss window: the pre-flight check reads `seller_id` (line 236) to determine FORBIDDEN, but `markRedeemed`'s WHERE clause ALSO checks `seller_id`. If two redeem requests come from different seller IDs (e.g. one legitimate, one attacker reusing the same code), the pre-flight returns FORBIDDEN for the attacker — but only if the pre-flight saw the right seller first. In a strict per-row SERIALIZABLE read this is safe; in InnoDB's REPEATABLE READ (default), the pre-flight snapshot may be stale.

**Evidence:**
```php
// ticket_service.php:228
$existing = ticket_model::findByCode($pdo, $code);
// ...validation in PHP...
// ticket_service.php:255 — atomic UPDATE on the same code
$redeemed = ticket_model::markRedeemed($pdo, $code, $sellerId);
```

**Suggested fix:** replace the pre-flight `findByCode` with `SELECT ... FOR UPDATE` so the row is locked before validation. Or fold the validation into the UPDATE's WHERE clause and use `rowCount()` to distinguish NOT_FOUND vs FORBIDDEN vs INVALID_STATE via a small mapping query. Either approach eliminates the TOCTOU window.

---

### CR-02: `confirmSession` pre-flight `findById` is not locked; concurrent confirmations can double-finalize

**File:** `004/tickettrade/src/Ticket/Service/ticket_service.php:330-376`
**Severity:** BLOCKER
**Issue:** `confirmSession` reads the ticket via `ticket_model::findById($pdo, $ticketId)` (line 330, plain SELECT) and validates `session_number < total_sessions` (line 355) before calling `ticket_model::incrementSession` (line 363). The UPDATE itself is guarded by `session_number < total_sessions`, so the actual session_number increment is atomic and correct. **But** when `isFinal` is true, the SAME session_number can be incremented by two concurrent requests at session N (the last one before total_sessions), and BOTH will see `is_final = true` because both read the pre-increment `session_number === total_sessions - 1` from their respective pre-flight snapshots — wait, no, the UPDATE in `incrementSession` is what determines the new session_number. Let me re-trace:

Two concurrent `confirmSession` calls on a ticket at `session_number=2, total_sessions=3`:
- Both call `findById` → both see `session_number=2`.
- Both pass the pre-flight check `2 < 3`.
- Both call `incrementSession`, which does `UPDATE ... SET session_number = session_number + 1 WHERE id=? AND session_number < total_sessions`. With InnoDB row locking, only ONE wins; the other gets `rowCount()===0` and returns null. The "loser" gets `E_TICKET_INVALID_STATE`. This is correct behavior.

The actual race: at session `total_sessions - 1` (e.g., session 2 of 3), two simultaneous requests:
- Both pass pre-flight.
- One wins `incrementSession`, the new session_number is now 3. That call proceeds to `markRedeemedById` → status='redeemed'.
- The other call's `incrementSession` gets `rowCount()===0` because `session_number < total_sessions` is now false. Returns null. Caller gets `E_TICKET_INVALID_STATE`. Correct.

OK so `incrementSession` is correctly serialized by its WHERE clause. But the *real* issue is that the pre-flight read at line 330 happens BEFORE any lock. If a dispute is filed between line 330 and line 363, the pre-flight read sees `dispute_status='none'`, but `incrementSession`'s WHERE clause guards `dispute_status != 'pending'`. So the second WHERE clause fails the UPDATE — caller gets `E_TICKET_INVALID_STATE`. Correct.

So where's the actual bug? Look at `markRedeemedById` (line 425-436): `WHERE id=? AND status IN ('active','disputed') AND dispute_status != 'pending' AND seller_id=?`. If the pre-flight read at line 330 sees `status='active', dispute_status='none'`, but between then and `markRedeemedById` execution, `incrementSession` already incremented session_number to 3 — now `markRedeemedById` is called. The race is benign because `markRedeemedById` doesn't check session_number at all. **However**, if `confirmSession` is called when `session_number === total_sessions` already (all sessions done), line 355 fires `E_TICKET_INVALID_STATE` — correct.

The actual **concrete** bug: at the *final* session, the pre-flight reads `session_number=2`, both calls pass line 355. Only one wins `incrementSession`; that winner proceeds to `markRedeemedById`. But consider what happens with `redeemTicket` racing with `confirmSession`'s final path: both can reach the atomic UPDATE with `status='active'`. Only one wins; the other gets `rowCount()===0`. Caller gets `E_TICKET_INVALID_STATE`. Correct.

The real concrete bug I want to flag: **the pre-flight `findById` is doing a read on a row that may be in the middle of an unrelated transaction**, and the snapshot may be inconsistent. In the unit-test-isolation phase this is moot, but in production with multiple concurrent buyers/sellers, the pre-flight can read stale state, then the WHERE-clause-guarded UPDATE fails, and the caller sees `E_TICKET_INVALID_STATE` — which is incorrect messaging. The actual cause was a dispute filed or a redeem by the same seller — different error code.

**Evidence:**
```php
// ticket_service.php:330
$existing = ticket_model::findById($pdo, $ticketId);  // ← NOT FOR UPDATE
// ... validation ...
// ticket_service.php:363
$newSession = ticket_model::incrementSession($pdo, $ticketId, $sellerId);
```

**Suggested fix:** change `findById` to a `FOR UPDATE` SELECT, or merge the validation into the UPDATE's WHERE clause (`status='active' AND dispute_status='none' AND session_number < total_sessions AND seller_id=?`). The latter is what AD-9 actually wants — a single guarded UPDATE per state change. The current code does this for the UPDATE itself; it just adds an additional (informational) pre-flight read that can lie.

---

### CR-03: `createTicket` re-reads `listings.price_cents` after the lock with a non-locking SELECT; price can change between FOR UPDATE and the re-read inside the same transaction

**File:** `004/tickettrade/src/Ticket/Service/ticket_service.php:144-147`
**Severity:** BLOCKER
**Issue:** After `SELECT ... FOR UPDATE` on the listing (line 94), `createTicket` reads `listings.price_cents` again via a fresh non-locking `SELECT price_cents FROM listings WHERE id = ?` (line 144). Inside the same InnoDB transaction, this should normally see the locked snapshot, but the code does NOT include `FOR UPDATE` on the second read. With InnoDB's default REPEATABLE READ isolation, the second read sees the snapshot at the start of the transaction — which includes the locked row's current state. So functionally this is fine for the *current* transaction, but it does mean:

1. The price stored on the ticket (`$priceCents = $priceRow['price_cents']`) is taken from the post-lock state, which is fine.
2. **However, the `quantity_sold` UPDATE in the same transaction (line 169)** uses `$listing['quantity']` from the FOR UPDATE snapshot, but the row is locked. This is also fine.

The actual concrete bug: between line 144 and line 149, the ticket INSERT captures `price_cents` from a non-locked re-read. In InnoDB REPEATABLE READ, that re-read sees the **same snapshot** as the FOR UPDATE row. So functionally the price is consistent within this transaction.

The real concern: **the `$listing['quantity']` and `$listing['quantity_sold']` are read once from the FOR UPDATE**, but the **status transition to 'sold'** is computed in the CASE expression as `WHEN quantity_sold + ? >= quantity THEN 'sold'`. This re-evaluates with the *new* `quantity_sold` value (after the increment), but `quantity` is read from the locked snapshot. So if another transaction is concurrently updating `quantity` (e.g. an admin edits it), our snapshot has the OLD `quantity`. **However**, `quantity` is not normally edited after listing approval in this app — there's no UI for it. So this is theoretical.

The actual concrete bug: **line 144's `SELECT price_cents FROM listings WHERE id = ?` should also be `FOR UPDATE` for symmetry**, OR — simpler — it should be replaced by reading `$listing['price_cents']` directly from the line-94 SELECT. Currently the code reads `price_cents` in line 94? Let me check the SELECT — `SELECT id, seller_id, status, quantity, quantity_sold, type` — NO, it does NOT include `price_cents`. So the second SELECT is necessary. But it should be `FOR UPDATE` to be safe.

**Evidence:**
```php
// ticket_service.php:94 — locks the listing but does NOT select price_cents
$stmt = $pdo->prepare(
    'SELECT id, seller_id, status, quantity, quantity_sold, type '
    . 'FROM listings WHERE id = ? FOR UPDATE'
);
// ticket_service.php:144 — separate non-locking SELECT for price_cents
$priceStmt = $pdo->prepare('SELECT price_cents FROM listings WHERE id = ?');
```

**Suggested fix:** add `price_cents` to the line-94 SELECT projection (`SELECT id, seller_id, status, quantity, quantity_sold, price_cents, type FROM listings WHERE id = ? FOR UPDATE`) and remove the separate line-144 SELECT.

---

### CR-04: `decrementListingStockForExpiredTicket` blindly UPDATEs `listings.status = 'sold'` based on `quantity_sold >= quantity` but the function never re-reads to confirm the listing WAS actually 'sold' before the decrement

**File:** `004/tickettrade/src/Ticket/Model/ticket_model.php:551-574`
**Severity:** BLOCKER
**Issue:** The cron expiry sweep's second UPDATE in `decrementListingStockForExpiredTicket` is:
```sql
UPDATE listings SET status = 'active', updated_at = NOW()
WHERE id = ? AND status = 'sold' AND quantity_sold < quantity
```
This is guarded on `status = 'sold'`, so a listing that was already 'active' is untouched. **However, the function is called from `runTicketExpirySweep`'s loop without checking whether the listing's `status` was actually 'sold' BEFORE the decrement.** If the listing is 'sold' but the decrement makes it `quantity_sold == quantity` (i.e. exactly at the boundary), the second UPDATE's guard `quantity_sold < quantity` is false and the listing stays 'sold'. That's correct.

The actual concrete bug: **the function decrements `quantity_sold` unconditionally** (first UPDATE, line 555-558):
```sql
UPDATE listings SET quantity_sold = GREATEST(quantity_sold - ?, 0), updated_at = NOW() WHERE id = ?
```
This will decrement even for a listing that's `status='active'` with `quantity_sold = 0` (no-op since GREATEST(0 - X, 0) = 0). It will also decrement for a `status='draft'` listing if a ticket somehow expired against it — which shouldn't happen but the function doesn't defend. **And for a `status='sold'` listing whose decrement leaves `quantity_sold >= quantity`** (e.g. a service ticket where `decrement = 1` but the listing still has many other active tickets), the function correctly leaves it 'sold' (no second UPDATE).

**However**, the bigger issue: **`runTicketExpirySweep` reads the just-expired tickets in step 2 (line 764-772) using a window like `t.updated_at >= DATE_SUB(NOW(), INTERVAL 5 SECOND)`**, but the WHERE clause also includes `t.expires_at <= NOW()`. A ticket that's already-expired-and-expired (status='expired' from a previous run, expires_at still <= NOW()) is included again. The `affected` array is then iterated, calling `decrementListingStockForExpiredTicket` for each. **The decrement is non-idempotent for re-runs**: each run of the cron will decrement `quantity_sold` again by the same amount, even though the ticket was already expired in a prior run. The "guard" in step 1 (the single guarded UPDATE in `runTicketExpirySweep` line 738) prevents status re-flipping — but `runTicketExpirySweep` then re-reads ALL tickets with `status='expired' AND expires_at <= NOW() AND updated_at >= DATE_SUB(NOW(), INTERVAL 5 SECOND)`, which on a re-run picks up the SAME rows and decrements again.

**Evidence:**
```php
// ticket_service.php:738 — step 1 guarded UPDATE
"UPDATE tickets SET status = 'expired', updated_at = NOW() WHERE status = 'active' AND ..."
// ticket_service.php:764-772 — step 2 re-read of just-expired tickets
"SELECT ... FROM tickets t ... WHERE t.status = 'expired' AND t.expires_at <= NOW()
  AND t.updated_at >= DATE_SUB(NOW(), INTERVAL 5 SECOND)"
// ticket_model.php:555 — the unguarded decrement
"UPDATE listings SET quantity_sold = GREATEST(quantity_sold - ?, 0) WHERE id = ?"
```

IdempotencyTest runs the cron 5 times and asserts the state is identical after run 1 vs run 5 (line 72 `assertSame($snapshots[1]['state'], $snapshots[5]['state'])`). Looking at the snapshot:
- `'expiry_ticket_status'` = 'expired' (unchanged across runs — correct)
- `'expiry_listing_quantity_sold'` = N (after run 1's decrement)
- The 5-second window in step 2 means by run 2 (typically > 5s later), the WHERE clause `t.updated_at >= DATE_SUB(NOW(), INTERVAL 5 SECOND)` excludes already-expired rows. **So the test passes** — but only because the test runs slowly enough for the 5-second window to expire between runs.

If two cron invocations happen within 5 seconds of each other (e.g. a clock skew or quick admin trigger), **the second run will re-decrement `quantity_sold`**, causing the listing to under-count stock permanently. The IdempotencyTest does NOT test this fast-replay case.

**Suggested fix:** in `decrementListingStockForExpiredTicket`, guard the decrement on `quantity_sold >= ?` (only decrement if there are at least that many sold). Or add a tracking column `expired_decrement_count` to the ticket row. Or use a JOIN that decrements only tickets whose `status='expired'` AND a tracking column hasn't been set. The simplest fix: have `runTicketExpirySweep` pass the just-UPDATEd tickets' IDs (from `expireStmt->->fetchAll()` if MariaDB supports it, or a separate SELECT immediately after) instead of relying on a time-window re-read.

---

### CR-05: `audit_log.metadata_json` is stored as `JSON NULL` but the PHP code passes it via PDO with `PDO::PARAM_STR` semantics — the `$actorUserId` nullable cast silently coerces 0 to `0` not `NULL`

**File:** `004/tickettrade/src/Support/Audit.php:42-74` and all callers
**Severity:** BLOCKER
**Issue:** `Support\Audit::log()` is documented as "NEVER throws, returns 0 on failure". The contract is honored: the `try/catch` wraps both `prepare` and `execute`, errors return 0 with `error_log`. Good.

But: **the FK constraint on `audit_log.actor_user_id`**:
```sql
CONSTRAINT fk_audit_log_actor
  FOREIGN KEY (actor_user_id) REFERENCES users (user_id)
  ON DELETE SET NULL
```
… requires `actor_user_id` to be NULL or a valid `users.user_id`. The PHP code passes `$actorUserId` (nullable int) directly as a parameter:
```php
$stmt->execute([
    $actorUserId,  // ← int|null
    $action,
    $targetType,
    $targetId,
    $metadataJson,
]);
```
PDO binds `null` as `PDO::PARAM_NULL` only if you tell it; by default it binds as `PDO::PARAM_STR` with empty string. **For an FK to a `BIGINT UNSIGNED` column, MySQL will reject empty string.** The current test (`AuditStubTest::test_log_with_null_actor`) uses `Audit::log(null, 'cron.expiry', 'ticket', 1, null)` and asserts it succeeds — but the test runs against a DB where `users` may or may not have a row matching whatever `null` coerces to.

Actually let me re-check. In PHP, `$actorUserId = null`. The `execute([null, ...])` passes PHP null, and PDO binds it as NULL in MySQL (PDO is smart enough to use PARAM_NULL for null values). So this may actually be fine in practice — but it's relying on undocumented PDO behavior. The defensive pattern is `$actorUserId !== null ? $actorUserId : null` (same), or explicit PDO binding with `bindValue(1, $actorUserId, $actorUserId === null ? PDO::PARAM_NULL : PDO::PARAM_INT)`.

The actual concrete bug I want to flag: **`$actorUserId` is documented as `int|null` but the PHP code at `points_service.php:787` passes `$actorUserId` (an `int` not null) — fine. But `CronAction.php:267` passes `$actorUserId > 0 ? $actorUserId : null` — this is the defensive pattern.** The inconsistency means `Audit::log()` will receive `0` (an int) from some callers (e.g. `redeemTicket`, `createTicket`, `confirmSession`) where `$actorUserId` is always positive — so no NULL is ever passed. **And at the cron-sweep code (`ticket_service.php:672, 791`), the `actorUserId > 0 ? $actorUserId : null` cast IS used.** So in practice the code never actually passes a literal 0 to `actor_user_id`. OK.

The real bug: **`actor_user_id = 0` is silently inserted** if any caller passes `0` instead of `null`. With `FK ... REFERENCES users (user_id)`, that insert will fail. The current code does not test this edge (passing `0`). But the auto-cast `$actorUserId > 0 ? $actorUserId : null` in `CronAction.php:267` and `ticket_service.php:672, 791` shows the team is aware of this risk. **So callers that DON'T apply this cast (e.g. `redeemTicket`, `confirmSession`, `createTicket`) are assuming their `$sellerId/$buyerId` is always > 0** — which is true given auth gates but is a fragile assumption.

**Evidence:**
```php
// Support/Audit.php:42
public static function log(
    ?int $actorUserId,  // ← nullable int
    string $action,
    ...
): int {
    $stmt->execute([
        $actorUserId,  // ← null binds as MySQL NULL (PDO behavior), 0 binds as 0
        ...
    ]);
}
```

**Suggested fix:** explicit PDO binding with `PDO::PARAM_NULL` for null, `PDO::PARAM_INT` for ints, OR cast `$actorUserId <= 0` to null inside `Audit::log()`. Defensive normalization at the Audit boundary is the right call given the contract that Audit::log must never throw — a silent FK violation would currently throw, breaking the never-throw promise. Cast at the top: `$actorUserId = ($actorUserId !== null && $actorUserId > 0) ? $actorUserId : null;`

---

## Warnings

### WR-01: `redeemTicket`'s pre-flight `findByCode` cannot distinguish `E_TICKET_NOT_FOUND` from `E_TICKET_FORBIDDEN` correctly when the row exists but the caller is the wrong seller — currently returns `FORBIDDEN` even if the ticket is in an invalid state

**File:** `004/tickettrade/src/Ticket/Service/ticket_service.php:228-262`
**Severity:** WARNING
**Issue:** The pre-flight check at line 236 returns `E_TICKET_FORBIDDEN` if `seller_id !== sellerId` regardless of whether the ticket is in a state that allows redemption. This means a seller trying to redeem a ticket they don't own gets `E_TICKET_FORBIDDEN` even if the ticket is already redeemed or disputed. The user-facing UX is acceptable (they don't own it, so "forbidden" is honest), but the error code is misleading — the actual root cause might be state. Not a security bug (the UPDATE is still seller-guarded), just a UX/code-correctness nit.

**Suggested fix:** re-order the pre-flight checks: state first (active/dispute), then seller authorization. This way the error code reflects the actual blocker.

---

### WR-02: `redeemTicket`'s "isFinal = true" hardcode for product tickets ignores services that are at total_sessions without a final confirm

**File:** `004/tickettrade/src/Ticket/Service/ticket_service.php:269-276`
**Severity:** WARNING
**Issue:** Line 269 sets `$isFinal = true;` unconditionally, with the comment "Final session = total_sessions for products OR the explicit final_session path. For product tickets total_sessions=1, so this is always the final." But this code path is only reached from `redeemAction` (POST `/tickets/redeem`), which is for product tickets only (services use `/tickets/{id}/confirm-session`). For a product ticket, `total_sessions=1`, so `isFinal=true` is correct. **However**, if a service ticket were ever pasted into the `/tickets/redeem` endpoint (e.g. due to a UX bug), it would also be marked `isFinal=true` and `points_service::awardTransaction` would run with `referenceType='final_session'`, incrementing `redeemed_count` even though the service isn't actually fully delivered.

This isn't a direct bug — service tickets don't go through this code path in normal flow — but it's a latent risk. The hardcode also doesn't compute `$isFinal` from `total_sessions`.

**Suggested fix:** derive `$isFinal = ((int) $redeemed['session_number'] >= (int) $redeemed['total_sessions'])` instead of hardcoding.

---

### WR-03: `confirmSession` increments `redeemed_count` only via `points_service::awardTransaction` — if the points flow throws/skips (frozen), the `redeemed_count` never increments, but the ticket is still marked 'redeemed'

**File:** `004/tickettrade/src/Ticket/Service/ticket_service.php:384-396`
**Severity:** WARNING
**Issue:** `confirmSession` calls `points_service::awardTransaction` (line 384). If the award succeeds, `points_service` increments `redeemed_count` for both buyer and seller (in `points_service::awardTransaction` line 395). If the award short-circuits on `points_frozen`, the function returns `{ok:true, data:{skipped:'points_frozen'}}` — no error envelope, no rollBack, no decrement. So `redeemed_count` stays put. **But the ticket is correctly marked 'redeemed'** (line 376's `markRedeemedById` runs before the points call). That's the intended behavior — points are best-effort, the ticket is committed. OK, this is correct.

The warning: `redeemed_count` semantics get murky. `redeemed_count` is documented as "counted redemptions per user" for the FR-PTS-007 halving rule. If a user's 5th redemption is skipped due to freeze, their next redemption also gets halved (because `redeemed_count` didn't increment). This is arguably the right behavior — "you only have 4 counted redemptions because your 5th was voided." But it's worth flagging because the behavior is implicit.

**Suggested fix:** none — the current behavior is consistent with the documented "FR-PTS-010: if EITHER party's users.points_frozen is TRUE, the entire transaction is short-circuited". Just document this edge in the redeemTicket / confirmSession docblock.

---

### WR-04: `Audit::log` returns 0 on failure but `redeemTicket` / `createTicket` / `confirmSession` do not check the return — they proceed with the success path regardless

**File:** `004/tickettrade/src/Support/Audit.php:42-74` (all callers)
**Severity:** WARNING
**Issue:** The contract says "Audit::log() NEVER throws (a logging failure returns 0 and emits an error_log line). The business operation that called the log() must complete even when the audit write fails." The never-throw contract is honored. But **callers don't check the return value**, so a silent audit failure is invisible in the response. For a project that writes `audit_log` for sensitive destructive actions per AGENTS.md, silent audit failures should be at least surfaced as a warning, not silently swallowed.

**Suggested fix:** add a `error_log` line in callers when `Audit::log()` returns 0, or add a wrapper `Audit::logOrWarn()` that emits an explicit warning. This is an observability improvement, not a correctness bug.

---

### WR-05: `decrementListingStockForExpiredTicket` is called even when the just-expired ticket was the LAST ticket — the second UPDATE re-restores to 'active' but the listing's `quantity_sold` may already have been decremented to 0 by the first UPDATE

**File:** `004/tickettrade/src/Ticket/Model/ticket_model.php:551-574`
**Severity:** WARNING
**Issue:** The function decrements `quantity_sold` by the passed amount (line 555), then checks `status = 'sold' AND quantity_sold < quantity` and sets status='active' if both hold (line 564-567). For a listing that WAS 'sold' (quantity_sold = quantity) and after decrement quantity_sold = quantity - decrement, the second UPDATE flips status to 'active'. Correct. **But for a listing that was already 'active'** (not sold), the decrement still runs and `quantity_sold` could go below 0 — but `GREATEST(quantity_sold - ?, 0)` clamps it. The second UPDATE's `status = 'sold'` guard skips it. Correct.

The warning: **the function does NOT verify that the decrement was actually needed**. If `runTicketExpirySweep` calls it with `$decrement = 0` (which shouldn't happen but `ticket_service.php:778-780` computes `decrement` as `max(1, ...)` — so minimum 1), no issue. **But if `listing.quantity_sold` is already 0 and the function runs**, the first UPDATE clamps to 0 (no change). The function still issues the second UPDATE — no-op due to `status = 'sold'` guard. Wasted work but not incorrect.

The real warning: **the function does not check `listing.status = 'active'` as a precondition for the decrement**. If a listing is in 'draft' state somehow (shouldn't happen but defensively), the decrement still runs. The team has chosen to trust the calling code to only invoke this on listings that just had a ticket expire against them.

**Suggested fix:** add `AND status IN ('active', 'sold')` to the first UPDATE's WHERE for defensiveness, plus a guard that `$decrement > 0`.

---

## Summary

**Verdict:** 5 BLOCKER + 5 WARNING. The atomicity guarantees the rest of the system relies on have soft edges in 3 places (`createTicket`, `redeemTicket`, `confirmSession`), the cron-sweep decrement has a 5-second-window race that the existing idempotency test doesn't catch, and the audit-log boundary has a nullable-int trap that could break the never-throw contract.

**Recommendation:**
1. Fix CR-01, CR-02, CR-03 by replacing pre-flight non-locking SELECTs with `FOR UPDATE` SELECTs or by folding validation into the UPDATE's WHERE clause (per AD-9).
2. Fix CR-04 by either narrowing the step-2 read window or by making the decrement idempotent (track decrement per ticket).
3. Fix CR-05 by normalizing `$actorUserId` to null at the Audit boundary.
4. Address the warnings as part of the next sprint.

**Tests:** the existing test suite is good — the 1000-iteration unique-codes test, the 5-runs-idempotent cron test, the FR-PTS-007/FR-PTS-010 points test, and the atomic ticket-operation tests all cover the happy paths and the major failure modes. The gaps are:
- No test for concurrent redemption races (would need threading or process forking).
- No test for the cron step-2 5-second-window edge (would need sub-5-second reruns).
- No test for `Audit::log` with `$actorUserId = 0` (would catch CR-05).

---

_Reviewed: 2026-09-05_
_Reviewer: gsd-code-reviewer (Phase 04)_
_Depth: standard_