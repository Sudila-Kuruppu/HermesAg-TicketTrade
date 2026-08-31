<?php
/**
 * TicketTrade — Auth\Model\password_reset_model
 *
 * Mirrors email_verification_model.php for the password-reset flow
 * (D-07). Same CHAR(64) token_hash column shape.
 */

declare(strict_types=1);

namespace App\Auth\Model;

use PDO;

class password_reset_model
{
    public static function insert(PDO $pdo, int $userId, string $tokenHash, string $expiresAt): int
    {
        $now = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $tokenHash, $expiresAt, $now]);
        return (int) $pdo->lastInsertId();
    }

    public static function findActiveByHash(PDO $pdo, string $tokenHash): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT r.*, u.email, u.user_id FROM password_resets r '
            . 'JOIN users u ON u.user_id = r.user_id '
            . 'WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    public static function markUsed(PDO $pdo, int $id): bool
    {
        $now = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare('UPDATE password_resets SET used_at = ? WHERE id = ? AND used_at IS NULL');
        $stmt->execute([$now, $id]);
        return $stmt->rowCount() === 1;
    }
}
