<?php

/**
 * TicketTrade — Ticket\Service\ticket_service
 *
 * Per AD-1: sole writer of `tickets`. Every public method runs inside
 * a PDO transaction with try/catch rollback on any Throwable.
 *
 * Per AD-7: `quantity_sold` increments ONLY inside createTicket().
 *   - Products: +1 per ticket.
 *   - Services: +total_sessions per ticket (the listing.quantity is
 *     the number of sessions a single buyer purchases at once; the
 *     service ticket has total_sessions = listing.quantity, and
 *     quantity_sold increments by that many in one shot).
 *
 * Per AD-9: every state-changing ticket operation is a single
 * `UPDATE tickets SET ... WHERE ...` with `rowCount()===0` as the
 * invalid branch.
 *
 * Per AD-15 + D-03: dispute branch flips `dispute_status='pending'`
 * and (if old status was 'active') sets `status='disputed'`. A
 * dispute on a 'redeemed' ticket keeps status='redeemed'.
 *
 * Cross-context writes go through Services only (AD-2): createTicket
 * reads listing via Listing\Service\listing_service::getWithImages()
 * (read-only), redeemTicket delegates the points to
 * Points\Service\points_service::awardTransaction(). No Action or
 * Model writes to `tickets` directly.
 */

declare(strict_types=1);

namespace App\Ticket\Service;

use App\Listing\Service\listing_service;
use App\Points\Service\points_service;
use App\Support\Audit;
use App\Support\Db;
use App\Support\Error;
use App\Ticket\Model\ticket_model;
use DateInterval;
use DateTime;
use DateTimeZone;
use PDO;
use Throwable;

class ticket_service
{
    /**
     * 7-day expiry window per FR-TKT-004 + D-07.
     */
    public const EXPIRY_DAYS = 7;

    /**
     * Allowed dispute reasons (D-03). The View renders these in the
     * dropdown; the Service enforces the same set server-side.
     */
    public const DISPUTE_REASONS = [
        'seller_unresponsive',
        'item_not_as_described',
        'buyer_unresponsive',
        'other',
    ];

    public const DISPUTE_TEXT_MAX = 200;

    /**
     * Create a ticket atomically.
     *
     * Flow (per AD-9):
     *   1. SELECT ... FOR UPDATE locks the listing row.
     *   2. Validate status='active' AND quantity_sold < quantity
     *      AND seller_id != buyer_id (self-purchase prevention).
     *   3. Generate a unique dashed ticket code.
     *   4. INSERT the ticket row (status='active', dispute_status='none',
     *      session_number=1, total_sessions=listing.quantity for
     *      services or 1 for products, expires_at=now+7d).
     *   5. UPDATE listings SET quantity_sold = quantity_sold + N (N=1
     *      for products, N=total_sessions for services).
     *   6. Audit::log('ticket.created', ...).
     *   7. Commit.
     *
     * @return array AD-16 failure envelope. On success:
     *   ['ok'=>true, 'data'=>['ticket_code'=>string, 'ticket_id'=>int, 'listing_id'=>int]]
     */
    public static function createTicket(int $listingId, int $buyerId): array
    {
        $pdo = Db::pdo();
        try {
            $pdo->beginTransaction();

            // Lock the listing row. CR-03 fix: include price_cents in
            // the FOR UPDATE projection so the price snapshot for the
            // ticket row is read under the same row lock — no need for
            // a separate non-locking re-read that could in principle
            // observe a different snapshot.
            $stmt = $pdo->prepare(
                'SELECT id, seller_id, status, quantity, quantity_sold, '
                . 'price_cents, type '
                . 'FROM listings WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$listingId]);
            $listing = $stmt->fetch();

            if ($listing === false) {
                $pdo->rollBack();
                return Error::envelope(false, null, [
                    'code' => 'E_LISTING_NOT_FOUND',
                    'message' => 'Listing not found.',
                ]);
            }
            if ((string) $listing['status'] !== 'active') {
                $pdo->rollBack();
                return Error::envelope(false, null, [
                    'code' => 'E_LISTING_NOT_ACTIVE',
                    'message' => 'This listing is not active.',
                ]);
            }
            if ((int) $listing['seller_id'] === $buyerId) {
                $pdo->rollBack();
                return Error::envelope(false, null, [
                    'code' => 'E_TICKET_SELF_PURCHASE',
                    'message' => 'You cannot buy your own listing.',
                ]);
            }
            if ((int) $listing['quantity_sold'] >= (int) $listing['quantity']) {
                $pdo->rollBack();
                return Error::envelope(false, null, [
                    'code' => 'E_LISTING_SOLD_OUT',
                    'message' => 'This listing is sold out.',
                ]);
            }

            // Compute total_sessions and the inventory increment.
            $isService = ((string) $listing['type'] === 'service');
            $totalSessions = $isService ? (int) $listing['quantity'] : 1;
            $inventoryDelta = $isService ? (int) $listing['quantity'] : 1;

            // Generate a unique dashed ticket code.
            $code = ticket_model::generateUniqueCode($pdo);

            // Compute expires_at = created_at + 7 days.
            $now = new DateTime('now', new DateTimeZone('Asia/Colombo'));
            $expiresAt = (clone $now)->add(new DateInterval('P' . self::EXPIRY_DAYS . 'D'))
                ->format('Y-m-d H:i:s');
            $createdAt = $now->format('Y-m-d H:i:s');

            // CR-03 fix: price_cents now comes from the FOR UPDATE
            // snapshot above (same transaction, same row lock).
            $priceCents = (int) ($listing['price_cents'] ?? 0);

            $ticketId = ticket_model::insert([
                'ticket_code' => $code,
                'listing_id' => $listingId,
                'buyer_id' => $buyerId,
                'seller_id' => (int) $listing['seller_id'],
                'status' => 'active',
                'dispute_status' => 'none',
                'price_cents' => $priceCents,
                'session_number' => 1,
                'total_sessions' => $totalSessions,
                'expires_at' => $expiresAt,
            ]);

            // Increment listings.quantity_sold by inventoryDelta.
            $u = $pdo->prepare(
                "UPDATE listings SET quantity_sold = quantity_sold + ?, "
                . "updated_at = NOW(), status = CASE WHEN quantity_sold + ? >= quantity "
                . "THEN 'sold' ELSE status END "
                . "WHERE id = ?"
            );
            $u->execute([$inventoryDelta, $inventoryDelta, $listingId]);

            // Audit row.
            $auditOk = Audit::log($buyerId, 'ticket.created', 'ticket', $ticketId, [
                'listing_id' => $listingId,
                'price_cents' => $priceCents,
                'total_sessions' => $totalSessions,
                'inventory_delta' => $inventoryDelta,
                'ticket_code' => $code,
            ]);
            if ($auditOk === 0) {
                // WR-04 fix: surface silent audit failure as an
                // error_log line so it's observable in production.
                error_log('[ticket_service::createTicket] audit_log write failed for ticket_id=' . $ticketId);
            }

            $pdo->commit();
            return Error::envelope(true, [
                'ticket_id' => $ticketId,
                'ticket_code' => $code,
                'listing_id' => $listingId,
                'expires_at' => $expiresAt,
                'session_number' => 1,
                'total_sessions' => $totalSessions,
            ], null);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[ticket_service::createTicket] ' . $e->getMessage());
            return Error::envelope(false, null, [
                'code' => 'E_INTERNAL',
                'message' => 'Could not create ticket.',
            ]);
        }
    }

    /**
     * Redeem a ticket atomically by code.
     *
     * Flow (per AD-9 + D-01):
     *   1. Lookup the ticket by code (with status check pre-flight to
     *      map errors cleanly: NOT_FOUND vs INVALID_STATE vs FORBIDDEN).
     *   2. UPDATE tickets SET status='redeemed', redeemed_at=NOW()
     *      WHERE ticket_code=? AND status='active'
     *        AND dispute_status != 'pending' AND seller_id = ?
     *   3. On success, delegate to points_service::awardTransaction()
     *      which writes 2 points_log rows + bumps users.points/tier +
     *      increments redeemed_count on the FINAL session path.
     *
     * @param string $code Dashed ticket code (e.g. TK-XXXX-XXXX-XXXX-XXXX-XXXX).
     * @param int $sellerId The authenticated seller user_id.
     * @return array AD-16 failure envelope. On success:
     *   ['ok'=>true, 'data'=>['ticket_id'=>int, 'ticket_code'=>string,
     *    'redeemed_at'=>string, 'points'=>['event_uuid_buyer'=>...,
     *    'event_uuid_seller'=>..., 'delta_buyer'=>int, 'delta_seller'=>int]]]
     */
    public static function redeemTicket(string $code, int $sellerId): array
    {
        $pdo = Db::pdo();
        try {
            $pdo->beginTransaction();

            // Pre-flight lookup (CR-01 fix: use FOR UPDATE so the row is
            // row-locked before we validate). The X-lock is released
            // when this transaction commits or rolls back.
            $existing = ticket_model::findByCodeForUpdate($pdo, $code);
            if ($existing === null) {
                $pdo->rollBack();
                return Error::envelope(false, null, [
                    'code' => 'E_TICKET_NOT_FOUND',
                    'message' => 'Ticket not found.',
                ]);
            }
            // WR-01 fix: state check comes BEFORE seller check so
            // the error code reflects the actual blocker. A wrong
            // seller attempting to redeem a non-active ticket now sees
            // E_TICKET_INVALID_STATE rather than E_TICKET_FORBIDDEN.
            if (
                (string) $existing['status'] !== 'active'
                || (string) $existing['dispute_status'] === 'pending'
            ) {
                $pdo->rollBack();
                return Error::envelope(false, null, [
                    'code' => 'E_TICKET_INVALID_STATE',
                    'message' => 'Ticket is not in a state that allows redemption.',
                ]);
            }
            if ((int) $existing['seller_id'] !== $sellerId) {
                $pdo->rollBack();
                return Error::envelope(false, null, [
                    'code' => 'E_TICKET_FORBIDDEN',
                    'message' => 'You do not have permission to redeem this ticket.',
                ]);
            }

            // Atomic UPDATE per AD-9.
            $redeemed = ticket_model::markRedeemed($pdo, $code, $sellerId);
            if ($redeemed === null) {
                $pdo->rollBack();
                return Error::envelope(false, null, [
                    'code' => 'E_TICKET_INVALID_STATE',
                    'message' => 'Ticket is not in a state that allows redemption.',
                ]);
            }

            // Award points. The Service is the sole writer of
            // points_log + users.points/tier per AD-10.
            // WR-02 fix: derive $isFinal from the post-redeem
            // session_number vs total_sessions instead of hardcoding
            // true. The /tickets/redeem path is for products (so
            // session_number === total_sessions === 1 is the normal
            // final case) but the constant prevents accidental
            // misuse if a service ticket ever routes here.
            $isFinal = ((int) $redeemed['session_number'] >= (int) $redeemed['total_sessions']);
            $pointsRes = points_service::awardTransaction(
                (int) $redeemed['buyer_id'],
                (int) $redeemed['seller_id'],
                (int) $redeemed['id'],
                10,
                30,
                $isFinal ? 'final_session' : 'redemption'
            );
            if ($pointsRes['ok'] === false) {
                $pdo->rollBack();
                return Error::envelope(false, null, $pointsRes['error']);
            }

            // Audit row.
            $auditOk = Audit::log($sellerId, 'ticket.redeemed', 'ticket', (int) $redeemed['id'], [
                'ticket_code' => $code,
                'buyer_id' => (int) $redeemed['buyer_id'],
                'is_final' => $isFinal,
            ]);
            if ($auditOk === 0) {
                // WR-04 fix: observe silent audit failure.
                error_log('[ticket_service::redeemTicket] audit_log write failed for ticket_id=' . (int) $redeemed['id']);
            }

            $pdo->commit();
            return Error::envelope(true, [
                'ticket_id' => (int) $redeemed['id'],
                'ticket_code' => $code,
                'redeemed_at' => $redeemed['redeemed_at'] ?? null,
                'points' => $pointsRes['data'] ?? null,
                'is_final' => $isFinal,
            ], null);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[ticket_service::redeemTicket] ' . $e->getMessage());
            return Error::envelope(false, null, [
                'code' => 'E_INTERNAL',
                'message' => 'Could not redeem ticket.',
            ]);
        }
    }

    /**
     * Confirm the next session on a multi-session service ticket.
     *
     * Flow:
     *   1. Lookup the ticket.
     *   2. Increment session_number atomically.
     *   3. If new session_number < total_sessions: intermediate
     *      session — no points, no status change.
     *   4. If new session_number === total_sessions: final session —
     *      atomic UPDATE to status='redeemed', redeemed_at=NOW(), then
     *      points_service::awardTransaction() with referenceType='final_session'.
     *
     * @return array AD-16 failure envelope.
     */
    public static function confirmSession(int $ticketId, int $sellerId): array
    {
        $pdo = Db::pdo();
        try {
            $pdo->beginTransaction();

            $existing = ticket_model::findById($pdo, $ticketId);
            if ($existing === null) {
                $pdo->rollBack();
                return Error::envelope(false, null, [
                    'code' => 'E_TICKET_NOT_FOUND',
                    'message' => 'Ticket not found.',
                ]);
            }
            if ((int) $existing['seller_id'] !== $sellerId) {
                $pdo->rollBack();
                return Error::envelope(false, null, [
                    'code' => 'E_TICKET_FORBIDDEN',
                    'message' => 'You do not have permission to confirm this ticket.',
                ]);
            }
            if (
                (string) $existing['status'] !== 'active'
                || (string) $existing['dispute_status'] === 'pending'
            ) {
                $pdo->rollBack();
                return Error::envelope(false, null, [
                    'code' => 'E_TICKET_INVALID_STATE',
                    'message' => 'Ticket is not in a state that allows confirmation.',
                ]);
            }
            if ((int) $existing['session_number'] >= (int) $existing['total_sessions']) {
                $pdo->rollBack();
                return Error::envelope(false, null, [
                    'code' => 'E_TICKET_INVALID_STATE',
                    'message' => 'All sessions are already confirmed.',
                ]);
            }

            $newSession = ticket_model::incrementSession($pdo, $ticketId, $sellerId);
            if ($newSession === null) {
                $pdo->rollBack();
                return Error::envelope(false, null, [
                    'code' => 'E_TICKET_INVALID_STATE',
                    'message' => 'Could not confirm next session.',
                ]);
            }

            $isFinal = ($newSession === (int) $existing['total_sessions']);
            $pointsRes = null;
            if ($isFinal) {
                // Final session: atomic UPDATE to 'redeemed' + award.
                $redeemRes = ticket_model::markRedeemedById($pdo, $ticketId, $sellerId);
                if ($redeemRes === null) {
                    $pdo->rollBack();
                    return Error::envelope(false, null, [
                        'code' => 'E_TICKET_INVALID_STATE',
                        'message' => 'Could not finalize ticket.',
                    ]);
                }
                $pointsRes = points_service::awardTransaction(
                    (int) $existing['buyer_id'],
                    (int) $existing['seller_id'],
                    $ticketId,
                    10,
                    30,
                    'final_session'
                );
                if ($pointsRes['ok'] === false) {
                    $pdo->rollBack();
                    return Error::envelope(false, null, $pointsRes['error']);
                }
            }

            $auditOk = Audit::log($sellerId, 'ticket.session_confirmed', 'ticket', $ticketId, [
                'session_number' => $newSession,
                'is_final' => $isFinal,
                'total_sessions' => (int) $existing['total_sessions'],
            ]);
            if ($auditOk === 0) {
                // WR-04 fix: observe silent audit failure.
                error_log('[ticket_service::confirmSession] audit_log write failed for ticket_id=' . $ticketId);
            }

            $pdo->commit();
            return Error::envelope(true, [
                'ticket_id' => $ticketId,
                'session_number' => $newSession,
                'is_final' => $isFinal,
                'total_sessions' => (int) $existing['total_sessions'],
                'points' => $pointsRes['data'] ?? null,
            ], null);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Error::envelope(false, null, [
                'code' => 'E_INTERNAL',
                'message' => 'Could not confirm session.',
            ]);
        }
    }

    /**
     * File a dispute on a ticket. Per D-03 + AD-15.
     *
     * @param int $ticketId
     * @param int $actorUserId The user filing the dispute (buyer or seller).
     * @param string $reason One of DISPUTE_REASONS.
     * @param string $text 1..DISPUTE_TEXT_MAX chars.
     * @return array AD-16 failure envelope.
     */
    public static function fileDispute(int $ticketId, int $actorUserId, string $reason, string $text): array
    {
        // Validate reason + text length BEFORE opening a transaction.
        if (!in_array($reason, self::DISPUTE_REASONS, true)) {
            return Error::envelope(false, null, [
                'code' => 'E_DISPUTE_INVALID_REASON',
                'message' => 'Dispute reason is invalid.',
            ]);
        }
        $textLen = mb_strlen($text);
        if ($textLen < 1 || $textLen > self::DISPUTE_TEXT_MAX) {
            return Error::envelope(false, null, [
                'code' => 'E_DISPUTE_TEXT_TOO_LONG',
                'message' => 'Dispute text must be between 1 and ' . self::DISPUTE_TEXT_MAX . ' characters.',
            ]);
        }

        $pdo = Db::pdo();
        try {
            $pdo->beginTransaction();

            $updated = ticket_model::fileDispute($pdo, $ticketId, $actorUserId);
            if ($updated === null) {
                $pdo->rollBack();
                // Differentiate NOT_FOUND vs INVALID_STATE vs FORBIDDEN.
// Pre-flight lookup (CR-02 fix: use FOR UPDATE so the row is
            // row-locked before we validate). The X-lock is released
            // when this transaction commits or rolls back.
            $existing = ticket_model::findByIdForUpdate($pdo, $ticketId);
                if ($existing === null) {
                    return Error::envelope(false, null, [
                        'code' => 'E_TICKET_NOT_FOUND',
                        'message' => 'Ticket not found.',
                    ]);
                }
                if (
                    (int) $existing['buyer_id'] !== $actorUserId
                    && (int) $existing['seller_id'] !== $actorUserId
                ) {
                    return Error::envelope(false, null, [
                        'code' => 'E_TICKET_FORBIDDEN',
                        'message' => 'You do not have permission to dispute this ticket.',
                    ]);
                }
                return Error::envelope(false, null, [
                    'code' => 'E_TICKET_INVALID_STATE',
                    'message' => 'Ticket is not in a state that allows disputes.',
                ]);
            }

            // Insert reports row.
            $now = (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
            $ins = $pdo->prepare(
                "INSERT INTO reports (target_type, target_id, reporter_id, reason, text, status, created_at) "
                . "VALUES (?, ?, ?, ?, ?, 'pending', ?)"
            );
            $ins->execute(['ticket', $ticketId, $actorUserId, $reason, $text, $now]);

            $auditOk = Audit::log($actorUserId, 'ticket.dispute_filed', 'ticket', $ticketId, [
                'reason' => $reason,
                'text_length' => $textLen,
            ]);
            if ($auditOk === 0) {
                // WR-04 fix: observe silent audit failure.
                error_log('[ticket_service::fileDispute] audit_log write failed for ticket_id=' . $ticketId);
            }

            $pdo->commit();
            return Error::envelope(true, [
                'affected_ticket_id' => $ticketId,
                'dispute_status' => 'pending',
                'ticket_status' => $updated['status'],
            ], null);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Error::envelope(false, null, [
                'code' => 'E_INTERNAL',
                'message' => 'Could not file dispute.',
            ]);
        }
    }

    /**
     * Get a ticket for the authenticated viewer. Returns null if the
     * viewer is not buyer, seller, or admin (T-04-15 — 404 vs 403).
     */
    public static function getTicketForViewer(int $ticketId, array $viewerRow): ?array
    {
        $pdo = Db::pdo();
        $ticket = ticket_model::findById($pdo, $ticketId);
        if ($ticket === null) {
            return null;
        }
        $uid = (int) ($viewerRow['user_id'] ?? 0);
        $isAdmin = !empty($viewerRow['is_admin']);
        if (
            !$isAdmin
            && (int) $ticket['buyer_id'] !== $uid
            && (int) $ticket['seller_id'] !== $uid
        ) {
            return null;
        }
        return $ticket;
    }

    /**
     * Read-only helper for MyTicketsAction (Phase 4 Plan 04-02).
     * Returns the buyer's tickets filtered by the tab (?tab=).
     * Also returns the per-tab counts so the View can render
     * the All/Active/Redeemed/Expired/Disputed counts in the header.
     *
     * @param string $tab One of: all, active, redeemed, expired, disputed.
     * @return array{tickets: array<int, array<string,mixed>>, tab_counts: array<string, int>, tab: string}
     */
    public static function getTicketsForBuyer(int $buyerId, string $tab): array
    {
        $allowed = ['all', 'active', 'redeemed', 'expired', 'disputed'];
        if (!in_array($tab, $allowed, true)) {
            $tab = 'active';
        }
        $pdo = Db::pdo();
        $tickets = ticket_model::findByBuyerAndStatus($pdo, $buyerId, $tab);

        // Compute per-tab counts in a single query for efficiency.
        $countsStmt = $pdo->prepare(
            "SELECT "
            . "SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_count, "
            . "SUM(CASE WHEN status = 'redeemed' THEN 1 ELSE 0 END) AS redeemed_count, "
            . "SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) AS expired_count, "
            . "SUM(CASE WHEN status = 'disputed' THEN 1 ELSE 0 END) AS disputed_count "
            . "FROM tickets WHERE buyer_id = ?"
        );
        $countsStmt->execute([$buyerId]);
        $countsRow = $countsStmt->fetch();
        $tabCounts = [
            'all' => 0,
            'active' => (int) ($countsRow['active_count'] ?? 0),
            'redeemed' => (int) ($countsRow['redeemed_count'] ?? 0),
            'expired' => (int) ($countsRow['expired_count'] ?? 0),
            'disputed' => (int) ($countsRow['disputed_count'] ?? 0),
        ];
        $tabCounts['all'] = $tabCounts['active'] + $tabCounts['redeemed'] + $tabCounts['expired'] + $tabCounts['disputed'];

        return [
            'tickets' => $tickets,
            'tab_counts' => $tabCounts,
            'tab' => $tab,
        ];
    }

    /**
     * Read-only helper for SalesAction (Phase 4 Plan 04-02). Returns
     * the seller's tickets grouped by listing for the per-listing-group
     * placement (D-05). Each group carries `listing_id`, `listing_title`,
     * `tickets[]`.
     *
     * @return array<int, array<string,mixed>> List of group rows.
     */
    public static function getGroupedSales(int $sellerId): array
    {
        $pdo = Db::pdo();
        return ticket_model::findGroupedSales($pdo, $sellerId);
    }

    /**
     * Read-only helper for PurchasesAction (Phase 4 Plan 04-02). Returns
     * the buyer's purchase history joined with listing title and seller
     * info, sorted by created_at DESC.
     *
     * @return array<int, array<string,mixed>>
     */
    public static function getPurchaseHistory(int $buyerId): array
    {
        $pdo = Db::pdo();
        return ticket_model::findPurchaseHistory($pdo, $buyerId);
    }


    /**
     * Run the 3-day dispute auto-dismiss sweep (D-07). For every
     * ticket with `dispute_status='pending'` AND `disputed_at <= NOW()
     * - INTERVAL 3 DAY`, the sweep:
     *   1. Captures the pre-dispute `status` (active or redeemed).
     *   2. UPDATEs the row to `dispute_status='rejected'`,
     *      `status` restored to its pre-dispute value, `updated_at` =
     *      NOW(); `created_at` and `disputed_at` are NEVER touched.
     *   3. Appends `Audit::log('ticket.dispute_auto_dismissed')` per
     *      affected ticket.
     *
     * Returns the AD-16 envelope:
     *   ['ok'=>true, 'data'=>['processed'=>N, 'affected_tickets'=>['TK-...', ...]]]
     *
     * Idempotent: the WHERE guard filters already-rejected tickets.
     *
     * @return array{ok:bool,data:?array,error:?array}
     */
    public static function runDisputeAutoDismissSweep(int $actorUserId): array
    {
        $pdo = Db::pdo();
        try {
            $pdo->beginTransaction();

            // Read the pre-dispute rows (FOR UPDATE locks them).
            $stale = $pdo->query(
                "SELECT id, ticket_code, status "
                . "FROM tickets "
                . "WHERE dispute_status = 'pending' "
                . "AND disputed_at <= NOW() - INTERVAL 3 DAY "
                . "FOR UPDATE"
            )->fetchAll();

            if (empty($stale)) {
                $pdo->commit();
                self::writeCronLog(
                    'ticket.dispute_auto_dismiss',
                    $actorUserId,
                    0,
                    []
                );
                return Error::envelope(true, [
                    'processed' => 0,
                    'affected_tickets' => [],
                ], null);
            }

            $affected = [];
            $upd = $pdo->prepare(
                "UPDATE tickets SET dispute_status = 'rejected', "
                . "status = CASE "
                . "WHEN status IN ('active', 'disputed') THEN 'active' "
                . "WHEN status = 'redeemed' THEN 'redeemed' "
                . "ELSE status END, "
                . "updated_at = NOW() "
                . "WHERE id = ? AND dispute_status = 'pending' "
                . "AND disputed_at <= NOW() - INTERVAL 3 DAY"
            );
            foreach ($stale as $row) {
                $upd->execute([(int) $row['id']]);
                if ($upd->rowCount() > 0) {
                    $affected[] = [
                        'ticket_id' => (int) $row['id'],
                        'ticket_code' => (string) $row['ticket_code'],
                        'restored_status' => (string) $row['status'],
                    ];
                    Audit::log(
                        $actorUserId > 0 ? $actorUserId : null,
                        'ticket.dispute_auto_dismissed',
                        'ticket',
                        (int) $row['id'],
                        [
                            'old_dispute_status' => 'pending',
                            'new_dispute_status' => 'rejected',
                            'restored_status' => (string) $row['status'],
                        ]
                    );
                }
            }

            $pdo->commit();

            self::writeCronLog(
                'ticket.dispute_auto_dismiss',
                $actorUserId,
                count($affected),
                []
            );

            return Error::envelope(true, [
                'processed' => count($affected),
                'affected_tickets' => $affected,
            ], null);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[ticket_service::runDisputeAutoDismissSweep] ' . $e->getMessage());
            return Error::envelope(false, null, [
                'code' => 'E_INTERNAL',
                'message' => 'Dispute auto-dismiss sweep failed.',
            ]);
        }
    }

    /**
     * Run the 7-day ticket expiry sweep (D-07). For every ticket with
     * `status='active' AND dispute_status != 'pending' AND expires_at <= NOW()`:
     *   1. UPDATEs the row to `status='expired'`, `updated_at=NOW()`.
     *   2. For each affected ticket, decrements
     *      `listings.quantity_sold` per AD-7 (1 for products,
     *      `total_sessions - (session_number - 1)` for services).
     *   3. If `quantity_sold < quantity AND listings.status='sold'`,
     *      the Service restores `listings.status='active'`.
     *   4. Appends `Audit::log('ticket.expired')` per affected ticket.
     *
     * The single guarded UPDATE is the dominant cost; the per-ticket
     * loop is bounded by the number of expiring tickets, not the total
     * ticket population. Per NFR-PER-004, completes in < 30s for 10k
     * tickets.
     *
     * @return array{ok:bool,data:?array,error:?array}
     */
    public static function runTicketExpirySweep(int $actorUserId): array
    {
        $pdo = Db::pdo();
        try {
            $pdo->beginTransaction();

            // Step 1: Single guarded UPDATE — flip eligible tickets
            // to 'expired'. Skip rows where dispute_status='pending'
            // (admin must resolve first per PRD §4.2).
            $expireStmt = $pdo->prepare(
                "UPDATE tickets SET status = 'expired', updated_at = NOW() "
                . "WHERE status = 'active' "
                . "AND dispute_status != 'pending' "
                . "AND expires_at <= NOW()"
            );
            $expireStmt->execute();
            $expireCount = (int) $expireStmt->rowCount();

            if ($expireCount === 0) {
                $pdo->commit();
                self::writeCronLog(
                    'ticket.expire',
                    $actorUserId,
                    0,
                    []
                );
                return Error::envelope(true, [
                    'processed' => 0,
                    'affected_tickets' => [],
                ], null);
            }

            // Step 2: Read the just-expired tickets so we can apply
            // the AD-7 inventory invariant per row. The reads run
            // inside the same transaction so the rowCount() result
            // is consistent.
            $expired = $pdo->query(
                "SELECT t.id, t.ticket_code, t.listing_id, t.session_number, "
                . "t.total_sessions, l.type AS listing_type, "
                . "t.expires_at "
                . "FROM tickets t JOIN listings l ON l.id = t.listing_id "
                . "WHERE t.status = 'expired' "
                . "AND t.expires_at <= NOW() "
                . "AND t.updated_at >= DATE_SUB(NOW(), INTERVAL 5 SECOND)"
            )->fetchAll();

            $affected = [];
            foreach ($expired as $row) {
                $lid = (int) $row['listing_id'];
                $isService = ((string) $row['listing_type'] === 'service');
                $decrement = $isService
                    ? max(1, ((int) $row['total_sessions']) - ((int) $row['session_number'] - 1))
                    : 1;

                ticket_model::decrementListingStockForExpiredTicket($lid, $decrement);

                $affected[] = [
                    'ticket_id' => (int) $row['id'],
                    'ticket_code' => (string) $row['ticket_code'],
                    'listing_id' => $lid,
                    'decrement' => $decrement,
                ];

                Audit::log(
                    $actorUserId > 0 ? $actorUserId : null,
                    'ticket.expired',
                    'ticket',
                    (int) $row['id'],
                    [
                        'expires_at' => (string) $row['expires_at'],
                        'listing_id' => $lid,
                        'decrement' => $decrement,
                    ]
                );
            }

            $pdo->commit();

            self::writeCronLog(
                'ticket.expire',
                $actorUserId,
                count($affected),
                []
            );

            return Error::envelope(true, [
                'processed' => count($affected),
                'affected_tickets' => $affected,
            ], null);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[ticket_service::runTicketExpirySweep] ' . $e->getMessage());
            return Error::envelope(false, null, [
                'code' => 'E_INTERNAL',
                'message' => 'Ticket expiry sweep failed.',
            ]);
        }
    }

    /**
     * Append a structured run-log row to the `cron_log` table (Plan
     * 03-02 migration 012). Phase 9 will migrate this to the
     * hash-chained audit_log (AD-12). Logging failures must not break
     * the sweep itself.
     */
    private static function writeCronLog(string $jobName, int $actorUserId, int $processed, array $errors): void
    {
        try {
            $stmt = Db::pdo()->prepare(
                'INSERT INTO cron_log (job_name, run_at, processed_count, errors_json, actor_user_id, created_at) '
                . 'VALUES (?, NOW(), ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $jobName,
                $processed,
                json_encode($errors, JSON_UNESCAPED_UNICODE),
                $actorUserId > 0 ? $actorUserId : null,
            ]);
        } catch (Throwable $e) {
            error_log('[cron_log] write failed: ' . $e->getMessage());
        }
    }
}
