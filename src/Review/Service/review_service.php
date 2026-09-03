<?php

/**
 * TicketTrade — Review\Service\review_service
 *
 * Per AD-1 + AD-2: the sole writer of `reviews`. Per AD-15: the
 * review gate is enforced here (single check, single error code):
 *
 *   tickets.status IN ('redeemed','expired')
 *   AND tickets.dispute_status='none'
 *   AND redeemed_at >= NOW() - INTERVAL 14 DAY
 *
 * Per D-05 + D-06: calls Points\Service\points_service::awardReviewPoints()
 * INSIDE the same DB transaction as the reviews INSERT. If the
 * points award fails, the entire transaction rolls back (no review
 * row, no points row).
 *
 * Phase 5 Plan 05-01 ships submitReview() end-to-end plus the
 * read-path placeholders (getSummaryForUser, listReviewsForUser) that
 * Plan 05-02 wires into PublicProfileAction.
 */

declare(strict_types=1);

namespace App\Review\Service;

use App\Points\Service\points_service;
use App\Review\Model\review_model;
use App\Support\Audit;
use App\Support\Db;
use App\Support\Error;
use App\Ticket\Model\ticket_model;
use PDO;
use PDOException;
use Throwable;

class review_service
{
    /**
     * 14-day review window per FR-RAT-001 + D-03.
     */
    public const REVIEW_WINDOW_DAYS = 14;

    /**
     * Comment length threshold for the +10 detailed-review points
     * per FR-RAT-001 + D-05.
     */
    public const DETAILED_REVIEW_CHARS = 50;

    /**
     * Comment max length enforced at the View level (D-05).
     * The Service truncates anything longer to keep the gate
     * lenient; the View's maxlength prevents paste-of-novel.
     */
    public const COMMENT_MAX_CHARS = 2000;

    /**
     * Submit a review atomically.
     *
     * Flow:
     *   1. Lookup the ticket via ticket_model::findByIdForReviewerGate().
     *   2. Validate ticket status IN (redeemed,expired) AND
     *      dispute_status='none' (AD-15).
     *   3. Validate 14-day window: redeemed_at >= NOW() - INTERVAL 14 DAY.
     *   4. Validate reviewer is ticket.buyer_id (reviewer_role='buyer')
     *      or ticket.seller_id (reviewer_role='seller'); else forbidden.
     *   5. Validate rating in 1..5.
     *   6. Truncate comment at COMMENT_MAX_CHARS.
     *   7. INSERT reviews row; map SQLSTATE 23000 to E_REVIEW_ALREADY_LEFT.
     *   8. Call points_service::awardReviewPoints() in the same transaction.
     *   9. Audit::log('review.created', ...) AFTER commit (D-06).
     *
     * @param int $ticketId
     * @param int $reviewerId
     * @param int $rating 1..5
     * @param string|null $comment
     * @return array AD-16 failure envelope. On success:
     *   ['ok'=>true, 'data'=>['review_id'=>int, 'points_awarded'=>int, 'points_event_uuid'=>?string]]
     */
    public static function submitReview(
        int $ticketId,
        int $reviewerId,
        int $rating,
        ?string $comment
    ): array {
        // Step 0: pre-validate rating + comment length BEFORE opening a transaction.
        if ($rating < 1 || $rating > 5) {
            return Error::envelope(false, null, [
                'code' => 'E_REVIEW_INVALID_RATING',
                'message' => 'Rating must be between 1 and 5.',
            ]);
        }
        $normalizedComment = null;
        if ($comment !== null && $comment !== '') {
            $len = mb_strlen($comment);
            if ($len > self::COMMENT_MAX_CHARS) {
                $comment = mb_substr($comment, 0, self::COMMENT_MAX_CHARS);
            }
            $normalizedComment = $comment;
        }

        $pdo = Db::pdo();
        try {
            $pdo->beginTransaction();

            // Step 1: ticket lookup.
            $ticket = ticket_model::findByIdForReviewerGate($pdo, $ticketId);
            if ($ticket === null) {
                $pdo->rollBack();
                return Error::envelope(false, null, [
                    'code' => 'E_REVIEW_NOT_FOUND',
                    'message' => 'Ticket not found.',
                ]);
            }

            // Step 2: AD-15 gate.
            $status = (string) $ticket['status'];
            $dispute = (string) $ticket['dispute_status'];
            if (!in_array($status, ['redeemed', 'expired'], true) || $dispute !== 'none') {
                $pdo->rollBack();
                return Error::envelope(false, null, [
                    'code' => 'E_REVIEW_NOT_ELIGIBLE',
                    'message' => 'This ticket is not eligible for review.',
                ]);
            }

            // Step 3: 14-day window.
            $redeemedAt = $ticket['redeemed_at'] ?? null;
            if ($redeemedAt === null || $redeemedAt === '') {
                $pdo->rollBack();
                return Error::envelope(false, null, [
                    'code' => 'E_REVIEW_WINDOW_CLOSED',
                    'message' => 'Review window has closed.',
                ]);
            }
            $windowOk = (bool) $pdo->query(
                "SELECT (CAST('{$redeemedAt}' AS DATETIME) >= DATE_SUB(NOW(), INTERVAL "
                . self::REVIEW_WINDOW_DAYS . " DAY)) AS w"
            )->fetchColumn();
            if (!$windowOk) {
                $pdo->rollBack();
                return Error::envelope(false, null, [
                    'code' => 'E_REVIEW_WINDOW_CLOSED',
                    'message' => 'Review window has closed.',
                ]);
            }

            // Step 4: reviewer identity + role.
            $buyerId = (int) $ticket['buyer_id'];
            $sellerId = (int) $ticket['seller_id'];
            if ($reviewerId === $buyerId) {
                $reviewerRole = 'buyer';
                $revieweeId = $sellerId;
            } elseif ($reviewerId === $sellerId) {
                $reviewerRole = 'seller';
                $revieweeId = $buyerId;
            } else {
                $pdo->rollBack();
                return Error::envelope(false, null, [
                    'code' => 'E_REVIEW_FORBIDDEN',
                    'message' => 'You are not a party to this ticket.',
                ]);
            }
            // Defense in depth: never let a user review themselves.
            if ($reviewerId === $revieweeId) {
                $pdo->rollBack();
                return Error::envelope(false, null, [
                    'code' => 'E_REVIEW_FORBIDDEN',
                    'message' => 'You cannot review yourself.',
                ]);
            }

            // Step 5: rating range (re-checked defensively).
            if ($rating < 1 || $rating > 5) {
                $pdo->rollBack();
                return Error::envelope(false, null, [
                    'code' => 'E_REVIEW_INVALID_RATING',
                    'message' => 'Rating must be between 1 and 5.',
                ]);
            }

            // Step 6: INSERT the review row. SQLSTATE 23000 on UNIQUE
            // violation maps to E_REVIEW_ALREADY_LEFT.
            try {
                $reviewId = review_model::insert(
                    $pdo,
                    $ticketId,
                    $reviewerId,
                    $revieweeId,
                    $rating,
                    $normalizedComment,
                    $reviewerRole
                );
            } catch (PDOException $e) {
                $pdo->rollBack();
                if ((string) $e->getCode() === '23000') {
                    return Error::envelope(false, null, [
                        'code' => 'E_REVIEW_ALREADY_LEFT',
                        'message' => 'You have already reviewed this ticket.',
                    ]);
                }
                throw $e;
            }

            // Step 7: award points INSIDE the same transaction.
            $commentLength = $normalizedComment === null ? 0 : mb_strlen($normalizedComment);
            $pointsRes = points_service::awardReviewPoints(
                $revieweeId,
                $reviewerId,
                $ticketId,
                $commentLength
            );
            if ($pointsRes['ok'] === false) {
                $pdo->rollBack();
                return Error::envelope(false, null, $pointsRes['error']);
            }

            $pdo->commit();

            // Step 8: audit AFTER commit (D-06).
            Audit::log($reviewerId, 'review.created', 'ticket', $ticketId, [
                'review_id' => $reviewId,
                'reviewer_role' => $reviewerRole,
                'reviewee_id' => $revieweeId,
                'rating' => $rating,
                'comment_length' => $commentLength,
                'points_awarded' => (int) ($pointsRes['data']['delta'] ?? 0),
            ]);

            return Error::envelope(true, [
                'review_id' => $reviewId,
                'points_awarded' => (int) ($pointsRes['data']['delta'] ?? 0),
                'points_event_uuid' => $pointsRes['data']['event_uuid'] ?? null,
                'reviewer_role' => $reviewerRole,
            ], null);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[review_service::submitReview] ' . $e->getMessage());
            return Error::envelope(false, null, [
                'code' => 'E_INTERNAL',
                'message' => 'Could not submit review.',
            ]);
        }
    }

    /**
     * Placeholder for Plan 05-02's read path. Returns the rating
     * summary for a user (avg, count, distribution) + dispute count.
     * Plan 05-02 wires this into PublicProfileAction.
     *
     * @return array{rating_avg:float, rating_count:int, rating_distribution:array<int,int>, dispute_count:int}
     */
    public static function getSummaryForUser(int $userId): array
    {
        $pdo = Db::pdo();
        $agg = review_model::aggregateForReviewee($pdo, $userId);
        $disputeCount = review_model::disputeCountForSeller($pdo, $userId);
        return [
            'rating_avg' => $agg['rating_avg'],
            'rating_count' => $agg['rating_count'],
            'rating_distribution' => $agg['distribution'],
            'dispute_count' => $disputeCount,
        ];
    }

    /**
     * Placeholder for Plan 05-02's Reviews tab. Returns the
     * paginated list of reviews received by the user.
     *
     * @return array<int, array<string,mixed>>
     */
    public static function listReviewsForUser(int $userId, int $limit, int $offset): array
    {
        $pdo = Db::pdo();
        return review_model::listForReviewee($pdo, $userId, $limit, $offset);
    }
}
