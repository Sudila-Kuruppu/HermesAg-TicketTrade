<?php

/**
 * TicketTrade — Points\Model\points_log_model
 *
 * Phase 2 stub. The sole writer of points_log (AD-10) is
 * Points/Service/points_service.php; this Model exposes the minimal
 * insert() helper the Service needs.
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
     * @param string|null $metadataJson Optional JSON-encoded metadata
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
}
