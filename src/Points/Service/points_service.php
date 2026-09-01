<?php

/**
 * TicketTrade — Points\Service\points_service
 *
 * Phase 2 stub (D-23). The signature is the Phase 6 contract; this
 * file lands in Plan 02-02 with the +50 verify-bonus logic.
 *
 * AD-10: points_service is the SOLE writer of points_log + the sole
 * updater of users.points and users.tier outside of Phase 6. Every
 * other context that adjusts points MUST go through this Service.
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
     * a new user from E → D (the only tier transition that fits within
     * 50..49). Phase 6 will generalize via auth_service::tierFromPoints().
     *
     * The transaction holds three statements:
     *   1. INSERT points_log row with event_uuid (UUID v7)
     *   2. UPDATE users SET points = 50, tier = 'D'
     *
     * @return array{ok:bool,event_uuid?:string,error?:array}
     */
    public static function awardVerificationBonus(int $userId): array
    {
        $pdo = Db::pdo();
        $uuid = Uuid::uuid7()->toString();
        $newPoints = 50;
        // Compute the tier from points so the stub honors the rank ladder
        // (E 0-49, D 50-149, ...). Per AD-10, auth_service::tierFromPoints
        // is the single source of truth for the tier computation.
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
}
