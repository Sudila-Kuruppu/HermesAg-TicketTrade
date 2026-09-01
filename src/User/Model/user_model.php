<?php

/**
 * TicketTrade — User\Model\user_model
 *
 * Profile-edit operations on an existing user. The whitelist in
 * updateProfile() prevents is_admin / is_banned / points / tier
 * tampering (Pitfall 11 + D-15).
 */

declare(strict_types=1);

namespace App\User\Model;

use PDO;

class user_model
{
    public static function findByNickname(PDO $pdo, string $nickname): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE nickname = ? LIMIT 1');
        $stmt->execute([$nickname]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    public static function findById(PDO $pdo, int $userId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    /**
     * Update a user profile with a strict field whitelist.
     *
     * @param int $userId
     * @param array<string,mixed> $fields Allowed: full_name, bio, whatsapp, avatar_id
     * @return bool true if the UPDATE matched a row
     */
    public static function updateProfile(PDO $pdo, int $userId, array $fields): bool
    {
        $allowed = ['full_name', 'bio', 'whatsapp', 'avatar_id'];
        $setParts = [];
        $vals = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $fields)) {
                $setParts[] = "$k = ?";
                if ($k === 'avatar_id') {
                    $vals[] = max(1, min(12, (int) $fields[$k]));
                } else {
                    $vals[] = $fields[$k];
                }
            }
        }
        if (empty($setParts)) {
            return false;
        }
        $now = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $setParts[] = 'updated_at = ?';
        $vals[] = $now;
        // WHERE user_id = ? comes last; the placeholder order is
        // [set parts...] + updated_at + user_id.
        $vals[] = $userId;
        $sql = 'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE user_id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($vals);
        return $stmt->rowCount() > 0;
    }
}
