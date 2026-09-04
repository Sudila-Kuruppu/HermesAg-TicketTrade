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
 *
 * Phase 5 Plan 05-01 ADDS awardReviewPoints() per D-05.
 *
 * Phase 6 Plan 06-01 ADDS:
 *   - awardListingApproval()        — +5 for approved listing
 *   - awardReportValidated()        — +20 for valid report
 *   - awardStreakBonus()            — +15 (7-day) / +50 (30-day)
 *   - voidPoints()                  — admin void (Plan 08 caller; Plan 6 ships the method)
 *   - clearPointsFreeze()           — admin unfreeze (Plan 08 caller; Plan 6 ships the method)
 *
 * Every writer short-circuits cleanly when users.points_frozen=TRUE
 * (returns {ok:true, data:{skipped:'points_frozen'}} — no row, no audit
 * trail beyond the existing flag).
 *
 * Phase 6 Plan 06-02 layers the velocity and pair-cap enforcement onto
 * awardTransaction() + awardReviewPoints() per PTS-05 + FR-PTS-010 +
 * D-08. The cap enforcement is two INDEPENDENT checks at insert time:
 *
 *   (a) PTS-05 per-day transactional cap (150 pts/day from
 *       transactions: sale + purchase + review). When
 *       day_total + effective > 150 for a user, INSERT a zero-delta
 *       points_log row with metadata.velocity_cap_hit=TRUE and
 *       RETURN the velocity_cap envelope. Does NOT set users.points_frozen.
 *
 *   (b) FR-PTS-010 freeze-trigger (>300 pts/day OR >150 pts/hr from
 *       transactions). Evaluated against the same pre-cap totals
 *       (so the freeze is the ceiling of the cap). On first hit
 *       (users.points_frozen=FALSE), UPDATE users SET points_frozen=TRUE,
 *       frozen_at=NOW(); write audit row 'points.frozen'. Subsequent
 *       hits no-op the flag but still log the cap hit if the cap fires.
 *
 * The cap can fire many times in a day without the freeze ever
 * firing, and the freeze can fire (on its own day_total/hour_total
 * check) without an immediate cap hit.
 */

declare(strict_types=1);

namespace App\Points\Service;

use App\Auth\Service\auth_service;
use App\Points\Model\points_log_model;
use App\Support\Audit;
use App\Support\Db;
use Ramsey\Uuid\Uuid;

class points_service
{
    /**
     * Cap constants per REQUIREMENTS.md PTS-05 + FR-PTS-010.
     * 150/day is the per-day transactional cap; >300/day OR >150/hr
     * triggers the freeze flag.
     */
    private const PTS05_DAILY_CAP = 150;
    private const FREEZE_DAILY_THRESHOLD = 300;
    private const FREEZE_HOURLY_THRESHOLD = 150;

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
     * Does NOT honor (TODO Phase 6 Plan 06-02):
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
        // Support being called inside an outer transaction (e.g.
        // ticket_service::redeemTicket). If a transaction is already
        // active, we participate in it; the caller commits/rolls back.
        $ownsTransaction = !$pdo->inTransaction();
        try {
            if ($ownsTransaction) {
                $pdo->beginTransaction();
            }

            // Lock both user rows.
            $lockStmt = $pdo->prepare(
                'SELECT user_id, points, points_frozen, redeemed_count '
                . 'FROM users WHERE user_id IN (?, ?) FOR UPDATE'
            );
            $lockStmt->execute([$buyerId, $sellerId]);
            $rows = $lockStmt->fetchAll();
            if (count($rows) < 2) {
                if ($ownsTransaction) {
                    $pdo->rollBack();
                }
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
                if ($ownsTransaction) {
                    $pdo->rollBack();
                }
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
                if ($ownsTransaction) {
                    $pdo->commit();
                }
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

            // PTS-05 + FR-PTS-010 velocity + freeze-trigger checks
            // run BEFORE the pair-cap check, BEFORE the INSERT. The
            // freeze flips users.points_frozen on first hit; the cap
            // short-circuits the row insert. Both are evaluated for
            // buyer and seller independently (a cap hit on buyer
            // does NOT block seller, and vice versa).
            foreach (
                [
                    [$buyerId, $effectiveBuyer, 'buyer'],
                    [$sellerId, $effectiveSeller, 'seller'],
                ] as [$userId, $effective, $party]
            ) {
                $capResult = self::applyVelocityAndFreeze(
                    $pdo,
                    (int) $userId,
                    (int) $effective,
                    (string) $party,
                    $referenceType,
                    $ticketId,
                    $ownsTransaction
                );
                if ($capResult !== null) {
                    if ($ownsTransaction) {
                        $pdo->commit();
                    }
                    return [
                        'ok' => true,
                        'data' => $capResult,
                    ];
                }
            }

            // Pair-cap (FR-PTS-006): 2 counted transactions per
            // (buyer, seller, ticket) tuple per day. The pair-cap
            // check runs AFTER the velocity checks pass (so a frozen
            // or capped-out user doesn't accidentally hit the
            // pair-cap audit row). On hit: insert zero-delta row,
            // audit-log, return pair_cap envelope.
            $pairCount = points_log_model::countPairInDay(
                $pdo,
                $buyerId,
                $sellerId,
                $ticketId
            );
            if ($pairCount >= 2) {
                $uuidPair = Uuid::uuid7()->toString();
                $metadataPair = json_encode([
                    'pair_cap_hit' => true,
                    'pair_count_today' => $pairCount,
                    'effective_delta_buyer' => $effectiveBuyer,
                    'effective_delta_seller' => $effectiveSeller,
                    'cap' => 'pts05_pair',
                ], JSON_UNESCAPED_UNICODE);
                points_log_model::insert(
                    $pdo,
                    $buyerId,
                    0,
                    $referenceType,
                    $ticketId,
                    (int) $buyerRow['points'],
                    $uuidPair,
                    $metadataPair
                );
                Audit::log(null, 'points.pair_cap', 'user', $buyerId, [
                    'event_uuid' => $uuidPair,
                    'pair_count_today' => $pairCount,
                    'cap' => 'pts05_pair',
                    'reference_type' => $referenceType,
                    'reference_id' => $ticketId,
                ]);
                if ($ownsTransaction) {
                    $pdo->commit();
                }
                return [
                    'ok' => true,
                    'data' => [
                        'skipped' => 'pair_cap',
                        'event_uuid' => $uuidPair,
                        'pair_count_today' => $pairCount,
                    ],
                ];
            }

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

            if ($ownsTransaction) {
                $pdo->commit();
            }
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
            if ($ownsTransaction && $pdo->inTransaction()) {
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

    /**
     * Award the +10 detailed-review points to the reviewee.
     *
     * Per AD-10 + D-05 + D-06: sole writer of the review-side
     * points_log row + users.points/tier update. The reviewer gets
     * NO points (the $reviewerId parameter is for audit only).
     *
     * Honors:
     *   - FR-RAT-001 + D-05: commentLength >= 50 -> delta = +10.
     *     Else -> {ok:true, data.skipped='no_points'}, no row written.
     *   - FR-PTS-007: 50% halving on first-5 redemptions
     *     (reviewee.users.redeemed_count < 5 -> floor(10*0.5) = 5).
     *   - FR-PTS-010: reviewee.points_frozen=TRUE -> short-circuit,
     *     returns {ok:true, data.skipped='points_frozen'}, no row.
     *
     * Participates in an outer transaction if one is active
     * (review_service::submitReview owns the boundary). If no outer
     * transaction is active, opens one and commits on success.
     *
     * @return array AD-16 envelope. On success:
     *   ['ok'=>true, 'data'=>['delta'=>int, 'event_uuid'=>?string, 'redeemed_count_after'=>int]]
     * Skipped cases:
     *   ['ok'=>true, 'data'=>['skipped'=>'no_points'|'points_frozen']]
     */
    public static function awardReviewPoints(
        int $revieweeId,
        int $reviewerId,
        int $ticketId,
        int $commentLength
    ): array {
        $pdo = Db::pdo();
        $ownsTransaction = !$pdo->inTransaction();
        try {
            if ($ownsTransaction) {
                $pdo->beginTransaction();
            }

            // Lock the reviewee row.
            $lockStmt = $pdo->prepare(
                'SELECT user_id, points, points_frozen, redeemed_count '
                . 'FROM users WHERE user_id = ? FOR UPDATE'
            );
            $lockStmt->execute([$revieweeId]);
            $row = $lockStmt->fetch();
            if ($row === false) {
                if ($ownsTransaction) {
                    $pdo->rollBack();
                }
                return [
                    'ok' => false,
                    'error' => [
                        'code' => 'E_POINTS_WRITE',
                        'message' => 'Reviewee user not found.',
                    ],
                ];
            }

            // FR-PTS-010: skip if reviewee.points_frozen.
            if (!empty($row['points_frozen'])) {
                if ($ownsTransaction) {
                    $pdo->commit();
                }
                return [
                    'ok' => true,
                    'data' => ['skipped' => 'points_frozen'],
                ];
            }

            // FR-RAT-001: short-comment reviews do not earn points.
            if ($commentLength < 50) {
                if ($ownsTransaction) {
                    $pdo->commit();
                }
                return [
                    'ok' => true,
                    'data' => ['skipped' => 'no_points'],
                ];
            }

            $redeemedCount = (int) $row['redeemed_count'];

            // FR-PTS-007: halving for first-5 redemptions.
            $effectiveDelta = ($redeemedCount < 5)
                ? (int) floor(10 * 0.5)
                : 10;

            // PTS-05 + FR-PTS-010 velocity + freeze-trigger checks.
            // Review points count toward the per-day transactional cap
            // (per REQUIREMENTS.md PTS-05). Pair-cap is NOT applied
            // here — reviews are not transactions. On cap/freeze hit
            // we short-circuit with the velocity_cap envelope.
            $capResult = self::applyVelocityAndFreeze(
                $pdo,
                $revieweeId,
                $effectiveDelta,
                'reviewer',
                'review',
                $ticketId,
                $ownsTransaction
            );
            if ($capResult !== null) {
                if ($ownsTransaction) {
                    $pdo->commit();
                }
                return [
                    'ok' => true,
                    'data' => $capResult,
                ];
            }

            $uuid = Uuid::uuid7()->toString();
            $newPoints = (int) $row['points'] + $effectiveDelta;
            $newTier = auth_service::tierFromPoints($newPoints);

            $metadata = json_encode([
                'comment_length' => $commentLength,
                'reviewer_id' => $reviewerId,
            ], JSON_UNESCAPED_UNICODE);

            points_log_model::insert(
                $pdo,
                $revieweeId,
                $effectiveDelta,
                'review',
                $ticketId,
                $newPoints,
                $uuid,
                $metadata
            );

            $upd = $pdo->prepare(
                'UPDATE users SET points = ?, tier = ?, '
                . 'redeemed_count = redeemed_count + 1, updated_at = NOW() '
                . 'WHERE user_id = ?'
            );
            $upd->execute([$newPoints, $newTier, $revieweeId]);

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return [
                'ok' => true,
                'data' => [
                    'delta' => $effectiveDelta,
                    'event_uuid' => $uuid,
                    'redeemed_count_after' => $redeemedCount + 1,
                ],
            ];
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[points_service::awardReviewPoints] ' . $e->getMessage());
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_POINTS_WRITE',
                    'message' => 'Could not award review points.',
                ],
            ];
        }
    }

    // ====================================================================
    //  Phase 6 Plan 06-01 — new writers (no halving, frozen-gated)
    // ====================================================================

    /**
     * Award +5 for an approved listing (FR-PTS-001 row 2).
     *
     * No FR-PTS-007 halving — the multiplier is transaction-only per
     * D-15 (06-CONTEXT.md). Honors FR-PTS-010 frozen-gate.
     *
     * @return array AD-16 envelope.
     *   Success:  ['ok'=>true, 'data'=>['delta'=>5, 'event_uuid'=>string, 'balance_after'=>int]]
     *   Skipped:  ['ok'=>true, 'data'=>['skipped'=>'points_frozen']]
     */
    public static function awardListingApproval(int $userId, int $listingId): array
    {
        return self::simpleAward(
            $userId,
            5,
            'listing_approval',
            $listingId,
            ['listing_id' => $listingId, 'cap_hit' => false]
        );
    }

    /**
     * Award +20 for a validated report (FR-PTS-001 row 6).
     * No halving. Honors FR-PTS-010 frozen-gate.
     */
    public static function awardReportValidated(int $userId, int $reportId): array
    {
        return self::simpleAward(
            $userId,
            20,
            'report_validated',
            $reportId,
            ['report_id' => $reportId, 'cap_hit' => false]
        );
    }

    /**
     * Award the +15 (7-day) or +50 (30-day) streak bonus (FR-PTS-001
     * rows 7-8). streakDays must be exactly 7 or 30; any other value
     * is rejected with E_VALIDATION.
     *
     * No halving. Honors FR-PTS-010 frozen-gate.
     *
     * @return array AD-16 envelope. Success carries the typed
     *   reference_type 'streak_7day' or 'streak_30day'.
     */
    public static function awardStreakBonus(int $userId, int $streakDays): array
    {
        if ($streakDays === 7) {
            return self::simpleAward(
                $userId,
                15,
                'streak_7day',
                $streakDays,
                ['streak_days' => 7, 'cap_hit' => false]
            );
        }
        if ($streakDays === 30) {
            return self::simpleAward(
                $userId,
                50,
                'streak_30day',
                $streakDays,
                ['streak_days' => 30, 'cap_hit' => false]
            );
        }
        return [
            'ok' => false,
            'error' => [
                'code' => 'E_VALIDATION',
                'message' => 'streakDays must be 7 or 30.',
            ],
        ];
    }

    /**
     * Void points from a user (admin action, Phase 8 caller).
     *
     * Reads users.points FOR UPDATE, computes new_balance =
     * max(0, points - delta), inserts a negative-delta points_log row,
     * updates users.points + tier, writes an audit row 'points.void'.
     *
     * Phase 6 ships the method as part of the engine surface; the
     * admin UI / endpoint lands in Phase 8.
     *
     * @return array AD-16 envelope.
     *   Success:    ['ok'=>true, 'data'=>['voided'=>int, 'balance_after'=>int, 'event_uuid'=>string]]
     *   Insufficient: ['ok'=>false, 'error'=>['code'=>'E_VOID_INSUFFICIENT_BALANCE']]
     */
    public static function voidPoints(int $userId, int $delta, string $reason): array
    {
        $pdo = Db::pdo();
        $ownsTransaction = !$pdo->inTransaction();
        try {
            if ($ownsTransaction) {
                $pdo->beginTransaction();
            }
            if ($delta <= 0) {
                if ($ownsTransaction) {
                    $pdo->rollBack();
                }
                return [
                    'ok' => false,
                    'error' => [
                        'code' => 'E_VALIDATION',
                        'message' => 'delta must be positive.',
                    ],
                ];
            }
            $lock = $pdo->prepare(
                'SELECT user_id, points FROM users WHERE user_id = ? FOR UPDATE'
            );
            $lock->execute([$userId]);
            $row = $lock->fetch();
            if ($row === false) {
                if ($ownsTransaction) {
                    $pdo->rollBack();
                }
                return [
                    'ok' => false,
                    'error' => [
                        'code' => 'E_POINTS_WRITE',
                        'message' => 'User not found.',
                    ],
                ];
            }
            $current = (int) $row['points'];
            // Edge case: caller asked to void MORE than the user has AND
            // current is already 0 — return E_VOID_INSUFFICIENT_BALANCE
            // with no row written (audit stays clean).
            if ($delta > $current && $current === 0) {
                if ($ownsTransaction) {
                    $pdo->rollBack();
                }
                return [
                    'ok' => false,
                    'error' => [
                        'code' => 'E_VOID_INSUFFICIENT_BALANCE',
                        'message' => 'User has no points to void.',
                    ],
                ];
            }
            // Floored void — never goes below 0.
            $voided = min($delta, $current);
            $newBalance = $current - $voided;
            $newTier = auth_service::tierFromPoints($newBalance);
            $uuid = Uuid::uuid7()->toString();
            $metadata = json_encode(
                ['reason' => $reason, 'voided' => true, 'requested_delta' => $delta],
                JSON_UNESCAPED_UNICODE
            );
            points_log_model::insert(
                $pdo,
                $userId,
                -$voided,
                'void',
                $userId,
                $newBalance,
                $uuid,
                $metadata
            );
            $upd = $pdo->prepare(
                'UPDATE users SET points = ?, tier = ?, updated_at = NOW() WHERE user_id = ?'
            );
            $upd->execute([$newBalance, $newTier, $userId]);
            // Best-effort audit (Phase 8 wraps the hash chain).
            Audit::log(null, 'points.void', 'user', $userId, [
                'voided' => $voided,
                'balance_after' => $newBalance,
                'reason' => $reason,
                'event_uuid' => $uuid,
            ]);
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return [
                'ok' => true,
                'data' => [
                    'voided' => $voided,
                    'balance_after' => $newBalance,
                    'event_uuid' => $uuid,
                ],
            ];
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_POINTS_WRITE',
                    'message' => 'Could not void points.',
                ],
            ];
        }
    }

    /**
     * Clear the points_frozen flag (admin action, Phase 8 caller).
     *
     * UPDATEs users.points_frozen=FALSE, frozen_at=NULL,
     * last_unfrozen_at=NOW() and writes an audit row 'points.unfrozen'.
     *
     * Phase 6 ships the method as part of the engine surface; the
     * admin UI / endpoint lands in Phase 8.
     *
     * @return array AD-16 envelope.
     *   Success: ['ok'=>true, 'data'=>['unfrozen_user_id'=>int]]
     */
    public static function clearPointsFreeze(int $userId): array
    {
        $pdo = Db::pdo();
        $ownsTransaction = !$pdo->inTransaction();
        try {
            if ($ownsTransaction) {
                $pdo->beginTransaction();
            }
            $upd = $pdo->prepare(
                'UPDATE users SET points_frozen = FALSE, '
                . 'frozen_at = NULL, last_unfrozen_at = NOW(), updated_at = NOW() '
                . 'WHERE user_id = ?'
            );
            $upd->execute([$userId]);
            if ($upd->rowCount() === 0) {
                if ($ownsTransaction) {
                    $pdo->rollBack();
                }
                return [
                    'ok' => false,
                    'error' => [
                        'code' => 'E_POINTS_WRITE',
                        'message' => 'User not found.',
                    ],
                ];
            }
            Audit::log(null, 'points.unfrozen', 'user', $userId, [
                'unfrozen_user_id' => $userId,
            ]);
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return [
                'ok' => true,
                'data' => ['unfrozen_user_id' => $userId],
            ];
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_POINTS_WRITE',
                    'message' => 'Could not clear freeze.',
                ],
            ];
        }
    }

    /**
     * Shared writer for awardListingApproval / awardReportValidated /
     * awardStreakBonus — no halving, frozen-gated, single-row.
     *
     * Tier-up toast marker is queued in $GLOBALS['_tt_toast_queue']
     * (the View layer reads this on the next page load per D-15).
     */
    private static function simpleAward(
        int $userId,
        int $delta,
        string $referenceType,
        int $referenceId,
        array $metadataFields
    ): array {
        $pdo = Db::pdo();
        $ownsTransaction = !$pdo->inTransaction();
        try {
            if ($ownsTransaction) {
                $pdo->beginTransaction();
            }
            $lock = $pdo->prepare(
                'SELECT user_id, points, points_frozen, tier FROM users WHERE user_id = ? FOR UPDATE'
            );
            $lock->execute([$userId]);
            $row = $lock->fetch();
            if ($row === false) {
                if ($ownsTransaction) {
                    $pdo->rollBack();
                }
                return [
                    'ok' => false,
                    'error' => [
                        'code' => 'E_POINTS_WRITE',
                        'message' => 'User not found.',
                    ],
                ];
            }
            // FR-PTS-010: frozen short-circuit.
            if (!empty($row['points_frozen'])) {
                if ($ownsTransaction) {
                    $pdo->commit();
                }
                return [
                    'ok' => true,
                    'data' => ['skipped' => 'points_frozen'],
                ];
            }
            $prevTier = (string) $row['tier'];
            $newPoints = (int) $row['points'] + $delta;
            $newTier = auth_service::tierFromPoints($newPoints);
            $uuid = Uuid::uuid7()->toString();
            $metadata = json_encode($metadataFields, JSON_UNESCAPED_UNICODE);
            points_log_model::insert(
                $pdo,
                $userId,
                $delta,
                $referenceType,
                $referenceId,
                $newPoints,
                $uuid,
                $metadata
            );
            $upd = $pdo->prepare(
                'UPDATE users SET points = ?, tier = ?, updated_at = NOW() WHERE user_id = ?'
            );
            $upd->execute([$newPoints, $newTier, $userId]);
            // Tier-up toast marker (visible transitions only).
            if ($newTier !== $prevTier && self::isVisibleTierUp($prevTier, $newTier)) {
                $ladder = require APP_ROOT . '/config/ranks.php';
                $name = $ladder[$newTier]['name'] ?? $newTier;
                if (!isset($GLOBALS['_tt_toast_queue']) || !is_array($GLOBALS['_tt_toast_queue'])) {
                    $GLOBALS['_tt_toast_queue'] = [];
                }
                $GLOBALS['_tt_toast_queue'][] = [
                    'type' => 'success',
                    'message' => "Tier up! You're now {$name} ({$newTier}).",
                ];
            }
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return [
                'ok' => true,
                'data' => [
                    'delta' => $delta,
                    'event_uuid' => $uuid,
                    'balance_after' => $newPoints,
                ],
            ];
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
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

    /**
     * Visible-tier-up predicate — E->D, D->C, C->B, B->A, A->S only.
     * Same-tier re-renders are silent (no toast).
     */
    private static function isVisibleTierUp(string $from, string $to): bool
    {
        $ladder = ['E' => 0, 'D' => 1, 'C' => 2, 'B' => 3, 'A' => 4, 'S' => 5];
        if (!isset($ladder[$from]) || !isset($ladder[$to])) {
            return false;
        }
        return $ladder[$to] > $ladder[$from];
    }

    /**
     * Apply the PTS-05 + FR-PTS-010 velocity + freeze-trigger checks
     * for a single user inside the same DB transaction as the points
     * writer. Called by awardTransaction() (buyer + seller) and
     * awardReviewPoints() (reviewee) before the normal INSERT.
     *
     * Two INDEPENDENT checks per REQUIREMENTS.md PTS-05 + CONTEXT.md
     * FR-PTS-010 + D-08:
     *
     *   (a) PTS-05 per-day transactional cap. When
     *       day_total + effective > 150, INSERT a zero-delta row with
     *       metadata.velocity_cap_hit=TRUE and RETURN the velocity_cap
     *       envelope. Does NOT set users.points_frozen. Audit row
     *       'points.velocity_cap' written.
     *
     *   (b) FR-PTS-010 freeze-trigger (>300/day OR >150/hr from
     *       transactions). On first hit (users.points_frozen=FALSE),
     *       UPDATE users SET points_frozen=TRUE, frozen_at=NOW(); write
     *       audit row 'points.frozen'. Subsequent hits no-op the flag
     *       (the UPDATE is guarded by points_frozen=FALSE in the
     *       WHERE; the audit row only fires on the first hit).
     *
     * The cap can fire many times in a day without the freeze ever
     * firing (day_total+effective > 150 but day_total <= 300 and
     * hour_total <= 150). The freeze can fire without an immediate
     * cap hit (day_total > 300 alone triggers the freeze even when
     * day_total + effective is, e.g., exactly 310 — only the freeze
     * triggers, the cap row fires only when day_total+effective > 150
     * which is virtually always true on a freeze path).
     *
     * @param PDO    $pdo
     * @param int    $userId
     * @param int    $effective     The post-FR-PTS-007 halving delta for this user.
     * @param string $party         'buyer' | 'seller' | 'reviewer' — recorded in metadata for audit.
     * @param string $referenceType points_log.reference_type for the cap row (e.g. 'final_session', 'review').
     * @param ?int   $referenceId   Optional ticket id for the cap row (null for review-without-ticket).
     * @param bool   $ownsTransaction Outer transaction flag (unused here — caller commits/rolls back).
     * @return ?array Velocity-cap envelope on hit (caller returns
     *               {ok:true, data:$envelope}); null on no-hit
     *               (caller continues with the normal INSERT path).
     */
    private static function applyVelocityAndFreeze(
        \PDO $pdo,
        int $userId,
        int $effective,
        string $party,
        string $referenceType,
        ?int $referenceId,
        bool $ownsTransaction
    ): ?array {
        $dayTotal = points_log_model::sumForUserInWindow(
            $pdo,
            $userId,
            '1 DAY',
            true
        );
        $hourTotal = points_log_model::sumForUserInWindow(
            $pdo,
            $userId,
            '1 HOUR',
            true
        );

        // (b) FR-PTS-010 freeze-trigger. Checked FIRST so a freeze
        // flip is committed even when the cap also fires on the same
        // call (the cap short-circuits the row insert but does NOT
        // roll back the freeze flip). Freeze is one-shot: subsequent
        // hits do NOT re-UPDATE the flag or write a duplicate audit
        // row.
        $freezeTrigger = null;
        if ($dayTotal > self::FREEZE_DAILY_THRESHOLD) {
            $freezeTrigger = 'day_overflow';
        } elseif ($hourTotal > self::FREEZE_HOURLY_THRESHOLD) {
            $freezeTrigger = 'hour_overflow';
        }
        if ($freezeTrigger !== null) {
            $frz = $pdo->prepare(
                'UPDATE users SET points_frozen = TRUE, frozen_at = NOW(), updated_at = NOW() '
                . 'WHERE user_id = ? AND points_frozen = FALSE'
            );
            $frz->execute([$userId]);
            if ($frz->rowCount() > 0) {
                // First hit — write audit row. Subsequent hits are
                // a no-op on the UPDATE and skip the audit.
                Audit::log(null, 'points.frozen', 'user', $userId, [
                    'trigger' => $freezeTrigger,
                    'day_total' => $dayTotal,
                    'hour_total' => $hourTotal,
                    'effective' => $effective,
                    'party' => $party,
                ]);
            }
        }

        // (a) PTS-05 per-day transactional cap. Inserts a zero-delta
        // audit row and returns the velocity_cap envelope. The freeze
        // flip above is preserved (it lives in the same transaction).
        if ($dayTotal + $effective > self::PTS05_DAILY_CAP) {
            $uuid = Uuid::uuid7()->toString();
            $metadata = json_encode([
                'velocity_cap_hit' => true,
                'cap' => 'pts05_daily',
                'day_total_before' => $dayTotal,
                'effective_delta' => $effective,
                'party' => $party,
            ], JSON_UNESCAPED_UNICODE);
            points_log_model::insert(
                $pdo,
                $userId,
                0,
                $referenceType,
                $referenceId,
                $dayTotal,
                $uuid,
                $metadata
            );
            Audit::log(null, 'points.velocity_cap', 'user', $userId, [
                'event_uuid' => $uuid,
                'cap' => 'pts05_daily',
                'day_total_before' => $dayTotal,
                'effective_delta' => $effective,
                'party' => $party,
            ]);
            return [
                'skipped' => 'velocity_cap',
                'event_uuid' => $uuid,
                'day_total_before' => $dayTotal,
                'effective_delta' => $effective,
            ];
        }
        return null;
    }
}
