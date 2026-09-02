<?php

/**
 * TicketTrade — Points\Service\points_service
 *
 * Per AD-10: this Service is the SOLE writer of points_log and the
 * sole updater of users.points and users.tier outside of Phase 6.
 * Every other context that adjusts points MUST go through this
 * Service.
 *
 * Phase 2 Plan 02-02 ships awardVerificationBonus().
 * Phase 4 Plan 04-01 ADDS awardTransaction() per D-06 — the Phase 6
 * contract. Phase 6 swaps the implementation without changing callers.
 */

declare(strict_types=1);

namespace App\Points\Service;

use App\Auth\Service\auth_service;
use App\Points\Model\points_log_model;
use App\Support\Db;
use Ramsey\Uuid\Uuid;

class points_service
{
    /**
     * Award the +50 email-verification bonus.
     *
     * Phase 2 simplification: the bonus is always +50 and always moves
     * a new user from E -> D (the only tier transition that fits within
     * 50..49). Phase 6 will generalize via auth_service::tierFromPoints().
     *
     * @return array{ok:bool,event_uuid?:string,error?:array}
     */
    public static function awardVerificationBonus(int $userId): array
    {
        $pdo = Db::pdo();
        $uuid = Uuid::uuid7()->toString();
        $newPoints = 50;
        $newTier = auth_service::tierFromPoints($newPoints);
        try {
            $pdo->beginTransaction();
            points_log_model::insert(
                $pdo,
                $userId,
                50,
                'email_verification',
                $userId,
                $newPoints,
                $uuid,
                null
            );
            $stmt = $pdo->prepare('UPDATE users SET points = ?, tier = ?, updated_at = NOW() WHERE user_id = ?');
            $stmt->execute([$newPoints, $newTier, $userId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_POINTS_WRITE',
                    'message' => 'Could not award the verification bonus.',
                ],
            ];
        }
        return [
            'ok' => true,
            'event_uuid' => $uuid,
        ];
    }

    /**
     * Award a per-transaction points delta to BOTH buyer and seller.
     *
     * Per AD-10 + D-06: sole writer of points_log + users.points/tier.
     * Phase 6 will swap the implementation without changing callers.
     *
     * Honors:
     *   - FR-PTS-007: 50% halving for the FIRST 5 counted redemptions
     *     per user (each party independently, via users.redeemed_count).
     *   - FR-PTS-010: if EITHER party's users.points_frozen is TRUE,
     *     the entire transaction is short-circuited (returns
     *     ok=true with data.skipped='points_frozen') — the ticket
     *     creation / redemption succeeds even when points are frozen.
     *
     * Does NOT honor (TODO Phase 6):
     *   - FR-PTS-005 velocity cap
     *   - FR-PTS-006 same-pair 2/day cap
     *
     * Increments users.redeemed_count by 1 for both parties ONLY on
     * the FINAL session path (referenceType='final_session').
     *
     * @return array{ok:bool,data?:array,error?:array}
     */
    public static function awardTransaction(
        int $buyerId,
        int $sellerId,
        int $ticketId,
        int $deltaBuyer,
        int $deltaSeller,
        string $referenceType
    ): array {
        $pdo = Db::pdo();
        try {
            $pdo->beginTransaction();

            // Lock both user rows.
            $lockStmt = $pdo->prepare(
                'SELECT user_id, points, points_frozen, redeemed_count '
                . 'FROM users WHERE user_id IN (?, ?) FOR UPDATE'
            );
            $lockStmt->execute([$buyerId, $sellerId]);
            $rows = $lockStmt->fetchAll();
            if (count($rows) < 2) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'error' => [
                        'code' => 'E_POINTS_WRITE',
                        'message' => 'Could not find both parties.',
                    ],
                ];
            }

            $buyerRow = null;
            $sellerRow = null;
            foreach ($rows as $r) {
                if ((int) $r['user_id'] === $buyerId) {
                    $buyerRow = $r;
                } elseif ((int) $r['user_id'] === $sellerId) {
                    $sellerRow = $r;
                }
            }
            if ($buyerRow === null || $sellerRow === null) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'error' => [
                        'code' => 'E_POINTS_WRITE',
                        'message' => 'Could not find both parties.',
                    ],
                ];
            }

            // FR-PTS-010: skip if either party has points_frozen=TRUE.
            if (!empty($buyerRow['points_frozen']) || !empty($sellerRow['points_frozen'])) {
                $pdo->commit();
                return [
                    'ok' => true,
                    'data' => ['skipped' => 'points_frozen'],
                ];
            }

            $buyerRedeemedCount = (int) $buyerRow['redeemed_count'];
            $sellerRedeemedCount = (int) $sellerRow['redeemed_count'];

            // FR-PTS-007: 50% halving for the first 5 counted redemptions.
            $effectiveBuyer = ($buyerRedeemedCount < 5)
                ? (int) floor($deltaBuyer * 0.5)
                : $deltaBuyer;
            $effectiveSeller = ($sellerRedeemedCount < 5)
                ? (int) floor($deltaSeller * 0.5)
                : $deltaSeller;

            // TODO: Phase 6 — apply FR-PTS-005 velocity cap
            //   (>300 pts/day or >150 pts/hour per FR-ADM-009) here.

            // TODO: Phase 6 — apply FR-PTS-006 same-pair 2/day cap.
            //   Count counted-transaction rows in points_log for the
            //   same (actor_id, counterparty_id, DATE(event_at)) tuple.
            //   If >= 2, set metadata.pair_cap_hit=TRUE and the row is
            //   logged but does NOT contribute to users.points.

            $uuidBuyer = Uuid::uuid7()->toString();
            $uuidSeller = Uuid::uuid7()->toString();

            $newBuyerPoints = (int) $buyerRow['points'] + $effectiveBuyer;
            $newSellerPoints = (int) $sellerRow['points'] + $effectiveSeller;
            $newBuyerTier = auth_service::tierFromPoints($newBuyerPoints);
            $newSellerTier = auth_service::tierFromPoints($newSellerPoints);

            points_log_model::insert(
                $pdo,
                $buyerId,
                $effectiveBuyer,
                $referenceType,
                $ticketId,
                $newBuyerPoints,
                $uuidBuyer,
                null
            );
            points_log_model::insert(
                $pdo,
                $sellerId,
                $effectiveSeller,
                $referenceType,
                $ticketId,
                $newSellerPoints,
                $uuidSeller,
                null
            );

            $upd = $pdo->prepare(
                'UPDATE users SET points = ?, tier = ?, updated_at = NOW() WHERE user_id = ?'
            );
            $upd->execute([$newBuyerPoints, $newBuyerTier, $buyerId]);
            $upd->execute([$newSellerPoints, $newSellerTier, $sellerId]);

            if ($referenceType === 'final_session') {
                $inc = $pdo->prepare(
                    'UPDATE users SET redeemed_count = redeemed_count + 1 WHERE user_id IN (?, ?)'
                );
                $inc->execute([$buyerId, $sellerId]);
            }

            $pdo->commit();
            return [
                'ok' => true,
                'data' => [
                    'event_uuid_buyer' => $uuidBuyer,
                    'event_uuid_seller' => $uuidSeller,
                    'delta_buyer' => $effectiveBuyer,
                    'delta_seller' => $effectiveSeller,
                    'redeemed_count_buyer' => $buyerRedeemedCount,
                    'redeemed_count_seller' => $sellerRedeemedCount,
                ],
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_POINTS_WRITE',
                    'message' => 'Could not award points.',
                ],
            ];
        }
    }
}
