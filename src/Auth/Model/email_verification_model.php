<?php
/**
 * TicketTrade — Auth\Model\email_verification_model
 *
 * Insert + look up + mark-used for the simulated email-verification
 * tokens (D-03). The findActiveByHash() call joins users so the verify
 * Action can flip is_verified in one round trip.
 */

declare(strict_types=1);

namespace App\Auth\Model;

use PDO;

class email_verification_model
{
    public static function insert(PDO $pdo, int $userId, string $tokenHash, string $expiresAt): int
    {
        $now = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'INSERT INTO email_verifications (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $tokenHash, $expiresAt, $now]);
        return (int) $pdo->lastInsertId();
    }

    public static function findActiveByHash(PDO $pdo, string $tokenHash): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT v.*, u.email, u.user_id FROM email_verifications v '
            . 'JOIN users u ON u.user_id = v.user_id '
            . 'WHERE v.token_hash = ? AND v.used_at IS NULL AND v.expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    public static function markUsed(PDO $pdo, int $id): bool
    {
        $now = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare('UPDATE email_verifications SET used_at = ? WHERE id = ? AND used_at IS NULL');
        $stmt->execute([$now, $id]);
        return $stmt->rowCount() === 1;
    }
}
