<?php

/**
 * TicketTrade — User\Model\user_model
 *
 * Profile-edit operations on an existing user. The whitelist in
 * updateProfile() prevents is_admin / is_banned / points / tier
 * tampering (Pitfall 11 + D-15).
 *
 * Phase 6 ADDS:
 *   - updateLastActive()  — direct writer of users.last_active_at for
 *                           the auth login path. The gamification path
 *                           uses the points_log trigger (migration 019)
 *                           per D-03.
 *   - findForLeaderboard($criteria) — read helper for the
 *                           Phase 6 Plan 06-03 leaderboard Service.
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

    /**
     * Refresh users.last_active_at on the auth login path.
     *
     * Per D-03 (06-CONTEXT.md), the gamification path (points_log
     * INSERT) refreshes last_active_at via the trg_points_log_refresh_last_active
     * trigger — application code MUST NOT update the column on that
     * path. The login path (auth_service::recordLogin) calls this
     * helper to do the same.
     *
     * Phase 6 Plan 06-02 layers the auth-service::recordLogin
     * caller on top. Plan 06-01 ships the canonical writer.
     *
     * @return bool true if the UPDATE matched a row
     */
    public static function updateLastActive(PDO $pdo, int $userId): bool
    {
        $stmt = $pdo->prepare(
            'UPDATE users SET last_active_at = NOW(), updated_at = NOW() WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Find users matching the leaderboard criteria.
     *
     * Phase 6 Plan 06-03 reads via this to the leaderboard rows. For
     * Phase 6 Plan 06-01 we ship the helper with the basic shape
     * (top-N by points within optional tier filter); the full
     * leaderboard service builds on top in Plan 06-03.
     *
     * @param PDO $pdo
     * @param array{
     *     tier?: string|null,
     *     min_points?: int,
     *     limit?: int,
     *     exclude_banned?: bool
     * } $criteria
     * @return array<int, array{
     *   user_id:int, nickname:string, points:int, tier:string,
     *   full_name:string
     * }>
     */
    public static function findForLeaderboard(PDO $pdo, array $criteria): array
    {
        $tier = $criteria['tier'] ?? null;
        $minPoints = (int) ($criteria['min_points'] ?? 0);
        $limit = max(1, min(100, (int) ($criteria['limit'] ?? 20)));
        $excludeBanned = (bool) ($criteria['exclude_banned'] ?? true);
        $where = ['points >= ?'];
        $vals = [$minPoints];
        if ($tier !== null) {
            $where[] = 'tier = ?';
            $vals[] = $tier;
        }
        if ($excludeBanned) {
            $where[] = 'is_banned = FALSE';
        }
        // Tiebreaker: ascending user_id (D-05).
        $sql = 'SELECT user_id, nickname, points, tier, full_name '
            . 'FROM users WHERE ' . implode(' AND ', $where) . ' '
            . 'ORDER BY points DESC, user_id ASC LIMIT ' . $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($vals);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'user_id' => (int) $r['user_id'],
                'nickname' => (string) $r['nickname'],
                'points' => (int) $r['points'],
                'tier' => (string) $r['tier'],
                'full_name' => (string) ($r['full_name'] ?? ''),
            ];
        }
        return $out;
    }
}
