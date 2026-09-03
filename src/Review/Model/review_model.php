<?php

/**
 * TicketTrade — Review\Model\review_model
 *
 * Per AD-1 + AD-2: the sole writer of `reviews` is
 * Review/Service/review_service. This Model exposes single-table
 * data-access helpers only. All queries use prepared statements
 * (AD-5).
 *
 * Per AD-15 + D-02: reviews are gated on
 *   tickets.status IN ('redeemed','expired')
 *   AND tickets.dispute_status='none'
 * The Service is the gate; this Model just reads/writes rows.
 */

declare(strict_types=1);

namespace App\Review\Model;

use PDO;

class review_model
{
    /**
     * Insert a reviews row. Returns the new id.
     *
     * @param string $reviewerRole 'buyer' | 'seller'
     */
    public static function insert(
        PDO $pdo,
        int $ticketId,
        int $reviewerId,
        int $revieweeId,
        int $rating,
        ?string $comment,
        string $reviewerRole
    ): int {
        $stmt = $pdo->prepare(
            'INSERT INTO reviews (ticket_id, reviewer_id, reviewee_id, rating, comment, '
            . 'reviewer_role, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $ticketId,
            $reviewerId,
            $revieweeId,
            $rating,
            $comment,
            $reviewerRole,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Find a review row by (ticket_id, reviewer_role). Returns null if absent.
     */
    public static function findByTicketAndRole(PDO $pdo, int $ticketId, string $reviewerRole): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT id, ticket_id, reviewer_id, reviewee_id, rating, comment, '
            . 'reviewer_role, created_at FROM reviews '
            . 'WHERE ticket_id = ? AND reviewer_role = ? LIMIT 1'
        );
        $stmt->execute([$ticketId, $reviewerRole]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    /**
     * Aggregate rating stats for a user (the reviewee).
     *
     * @return array{rating_count:int, rating_avg:float, distribution:array<int,int>}
     */
    public static function aggregateForReviewee(PDO $pdo, int $userId): array
    {
        $sql = 'SELECT COUNT(*) AS rating_count, '
            . 'ROUND(AVG(rating), 1) AS rating_avg, '
            . 'SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) AS r5, '
            . 'SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) AS r4, '
            . 'SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) AS r3, '
            . 'SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) AS r2, '
            . 'SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) AS r1 '
            . 'FROM reviews WHERE reviewee_id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $r = $stmt->fetch();
        if ($r === false || (int) $r['rating_count'] === 0) {
            return [
                'rating_count' => 0,
                'rating_avg' => 0.0,
                'distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            ];
        }
        return [
            'rating_count' => (int) $r['rating_count'],
            'rating_avg' => (float) $r['rating_avg'],
            'distribution' => [
                1 => (int) $r['r1'],
                2 => (int) $r['r2'],
                3 => (int) $r['r3'],
                4 => (int) $r['r4'],
                5 => (int) $r['r5'],
            ],
        ];
    }

    /**
     * List reviews received by a user (newest first). Uses offset pagination.
     *
     * @return array<int, array<string,mixed>>
     */
    public static function listForReviewee(PDO $pdo, int $userId, int $limit, int $offset): array
    {
        $sql = 'SELECT r.id, r.ticket_id, r.reviewer_id, r.reviewee_id, r.rating, '
            . 'r.comment, r.reviewer_role, r.created_at, u.nickname AS reviewer_nickname '
            . 'FROM reviews r JOIN users u ON u.user_id = r.reviewer_id '
            . 'WHERE r.reviewee_id = ? '
            . 'ORDER BY r.created_at DESC LIMIT ? OFFSET ?';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Total count of reviews received by a user. Used by the Reviews
     * tab for pagination (Prev/Next rendering, D-08).
     */
    public static function countForReviewee(PDO $pdo, int $userId): int
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM reviews WHERE reviewee_id = ?');
        $stmt->execute([$userId]);
        $r = $stmt->fetch();
        return $r === false ? 0 : (int) $r['c'];
    }

    /**
     * Count of tickets where the given user was the seller and the
     * dispute was resolved as UPHELD. Phase 5 ships the function
     * returning 0; Phase 7's admin Force Expire/Redeem sets
     * dispute_status='upheld' which populates this count.
     */
    public static function disputeCountForSeller(PDO $pdo, int $userId): int
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS c FROM tickets '
            . 'WHERE seller_id = ? AND dispute_status = \'upheld\''
        );
        $stmt->execute([$userId]);
        $r = $stmt->fetch();
        return $r === false ? 0 : (int) $r['c'];
    }
}
