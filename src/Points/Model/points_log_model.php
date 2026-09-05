<?php

/**
 * TicketTrade — Points\Model\points_log_model
 *
 * Phase 2 stub. The sole writer of points_log (AD-10) is
 * Points/Service/points_service.php; this Model exposes the
 * read helpers the Service needs plus the insert() helper.
 *
 * Phase 6 ADDS:
 *   - sumForUserInWindow()  — velocity cap reads
 *   - countPairInDay()      — same-pair cap reads
 *   - recentForUser()       — Profile recent-activity section
 *
 * The cap-hit metadata flag on insert() rows is the channel the
 * Service uses to mark "this row counted as 0 due to a cap" — see
 * D-08 in 06-CONTEXT.md. The sum/count helpers exclude those rows
 * by default so the velocity calculation reflects only counted
 * deltas.
 */

declare(strict_types=1);

namespace App\Points\Model;

use PDO;

class points_log_model
{
    /**
     * Insert a points_log row. Returns the new id.
     *
     * @param int    $userId
     * @param int    $delta       Signed delta (e.g. +50 for verify bonus)
     * @param string $referenceType e.g. 'email_verification'
     * @param int|null $referenceId e.g. $userId for verify
     * @param int    $balanceAfter  e.g. 50
     * @param string $eventUuid     UUID v7 hex string
     * @param string|null $metadataJson Optional JSON-encoded metadata.
     *                                  Cap-hit rows set `pair_cap_hit=true`
     *                                  or `velocity_cap_hit=true` so the
     *                                  velocity/pair-cap reads can exclude
     *                                  them.
     */
    public static function insert(
        PDO $pdo,
        int $userId,
        int $delta,
        string $referenceType,
        ?int $referenceId,
        int $balanceAfter,
        string $eventUuid,
        ?string $metadataJson = null
    ): int {
        $now = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'INSERT INTO points_log (user_id, delta, reference_type, reference_id, balance_after, '
            . 'event_uuid, metadata, event_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $delta,
            $referenceType,
            $referenceId,
            $balanceAfter,
            $eventUuid,
            $metadataJson,
            $now,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Sum of deltas for a user in the given INTERVAL window, excluding
     * cap-hit rows (the audit rows from velocity_cap_hit / pair_cap_hit
     * that were inserted with delta=0 per D-08).
     *
     * Used by the velocity cap check in points_service.
     *
     * @param PDO    $pdo
     * @param int    $userId
     * @param string $interval   MySQL INTERVAL literal: '1 DAY', '1 HOUR', etc.
     * @param bool   $excludePairCap When true, also excludes rows with
     *                               metadata.pair_cap_hit = TRUE.
     * @return int   Total counted delta in the window (>= 0).
     */
    public static function sumForUserInWindow(
        PDO $pdo,
        int $userId,
        string $interval,
        bool $excludePairCap = true
    ): int {
        // Whitelist the interval so it can't be injected; the Service
        // is the only caller and always passes a fixed literal.
        $allowed = ['1 DAY', '1 HOUR'];
        if (!in_array($interval, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid interval');
        }
        // The COALESCE handles the no-rows case explicitly; SUM() over
        // an empty set returns NULL which PHP would coerce to 0 with
        // `?? 0` anyway, but the explicit COALESCE matches the DDL.
        $pairClause = $excludePairCap
            ? 'AND (JSON_EXTRACT(metadata, "$.pair_cap_hit") IS NULL '
                . 'OR JSON_EXTRACT(metadata, "$.pair_cap_hit") = false)'
            : '';
        $sql = "SELECT COALESCE(SUM(delta), 0) FROM points_log "
            . "WHERE user_id = ? AND event_at >= NOW() - INTERVAL {$interval} "
            . "AND (JSON_EXTRACT(metadata, \"$.velocity_cap_hit\") IS NULL "
            . "OR JSON_EXTRACT(metadata, \"$.velocity_cap_hit\") = false) "
            . $pairClause;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Count of distinct ticket (reference_id) rows for the given pair
     * (userA, userB) today, excluding cap-hit rows and excluding
     * non-transaction reference_types. The pair-cap check in
     * points_service uses the value to decide whether to insert a counted
     * row or a cap-hit audit row.
     *
     * Per D-08 + FR-PTS-006: the cap is "2 counted transactions/day
     * per buyer-seller pair". The ticket_id parameter is the candidate
     * ticket; the count is the number of OTHER distinct tickets for
     * the same pair that have already counted today. When the count
     * is >= 2 the candidate ticket triggers the pair cap (this is
     * the 3rd counted transaction of the pair today).
     *
     * @param PDO $pdo
     * @param int $userA
     * @param int $userB
     * @param int $ticketId  The candidate ticket for the upcoming award.
     * @return int Count of distinct counted tickets for this pair today
     *              that ARE NOT the candidate ticket.
     */
    public static function countPairInDay(
        PDO $pdo,
        int $userA,
        int $userB,
        int $ticketId
    ): int {
        // D-08 + FR-PTS-006: the cap is 2 counted transactions/day
        // per (buyer, seller) pair. We count distinct reference_ids
        // (one per ticket) for the pair today, EXCLUDING the candidate
        // ticket. When the count is >= 2, the candidate ticket is the
        // 3rd counted transaction today → pair cap fires.
        //
        // The earlier 06-01 shape (WHERE reference_id = ?) was wrong —
        // it filtered to a single ticket so the count could never
        // reach 2 and the cap could never fire. This rewrite uses
        // DISTINCT reference_id without a ticket filter, then subtracts
        // 1 for the candidate if it already counted today.
        $sql = "SELECT COUNT(DISTINCT reference_id) FROM points_log "
            . "WHERE user_id IN (?, ?) "
            . "AND DATE(event_at) = CURDATE() "
            . "AND (JSON_EXTRACT(metadata, \"$.pair_cap_hit\") IS NULL "
            . "OR JSON_EXTRACT(metadata, \"$.pair_cap_hit\") = false) "
            . "AND reference_type IN ('final_session', 'transaction')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userA, $userB]);
        $total = (int) $stmt->fetchColumn();
        // If the candidate ticket has a counted row today, subtract 1 —
        // it's the row we're about to write (via the normal path) or
        // it's an already-counted row that should be allowed (the cap
        // is about OTHER tickets, not the current one).
        if ($ticketId > 0) {
            $sqlCand = "SELECT COUNT(*) FROM points_log "
                . "WHERE user_id IN (?, ?) AND reference_id = ? "
                . "AND DATE(event_at) = CURDATE() "
                . "AND (JSON_EXTRACT(metadata, \"$.pair_cap_hit\") IS NULL "
                . "OR JSON_EXTRACT(metadata, \"$.pair_cap_hit\") = false) "
                . "AND reference_type IN ('final_session', 'transaction')";
            $stmtCand = $pdo->prepare($sqlCand);
            $stmtCand->execute([$userA, $userB, $ticketId]);
            $candidateCount = (int) $stmtCand->fetchColumn();
            $total = max(0, $total - $candidateCount);
        }
        return $total;
    }

    /**
     * Recent rows for a user, newest first. Used by the Profile
     * recent-activity section (D-07 in 06-CONTEXT.md).
     *
     * @param PDO $pdo
     * @param int $userId
     * @param int $limit  Default 5; max 100.
     * @return array<int, array{delta:int,reference_type:string,event_at:string,metadata:?string}>
     */
    public static function recentForUser(PDO $pdo, int $userId, int $limit = 5): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $pdo->prepare(
            'SELECT delta, reference_type, event_at, metadata '
            . 'FROM points_log WHERE user_id = ? '
            . 'ORDER BY event_at DESC, id DESC LIMIT ' . $limit
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'delta' => (int) $r['delta'],
                'reference_type' => (string) $r['reference_type'],
                'event_at' => (string) $r['event_at'],
                'metadata' => $r['metadata'] !== null ? (string) $r['metadata'] : null,
            ];
        }
        return $out;
    }
}
