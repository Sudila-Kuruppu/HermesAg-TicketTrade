<?php
/**
 * TicketTrade — Auth\Model\session_model
 *
 * DB-backed sessions (D-05 + AD-13). Used by the login/logout Actions
 * in Plan 02-02 and by Support\Auth::boot()'s last_seen bump.
 */

declare(strict_types=1);

namespace App\Auth\Model;

use PDO;

class session_model
{
    public static function insert(PDO $pdo, string $sessionId, int $userId, ?string $ip, ?string $userAgent): int
    {
        $now = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $ipBin = $ip === null ? null : inet_pton($ip);
        $stmt = $pdo->prepare(
            'INSERT INTO sessions (session_id, user_id, last_seen, ip, user_agent, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$sessionId, $userId, $now, $ipBin, $userAgent, $now]);
        return (int) $pdo->lastInsertId();
    }

    public static function findById(PDO $pdo, string $sessionId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM sessions WHERE session_id = ? LIMIT 1');
        $stmt->execute([$sessionId]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    public static function touch(PDO $pdo, string $sessionId): bool
    {
        $now = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $stmt = $pdo->prepare('UPDATE sessions SET last_seen = ? WHERE session_id = ?');
        $stmt->execute([$now, $sessionId]);
        return $stmt->rowCount() > 0;
    }

    public static function delete(PDO $pdo, string $sessionId, int $userId): bool
    {
        $stmt = $pdo->prepare('DELETE FROM sessions WHERE session_id = ? AND user_id = ?');
        $stmt->execute([$sessionId, $userId]);
        return $stmt->rowCount() > 0;
    }
}
