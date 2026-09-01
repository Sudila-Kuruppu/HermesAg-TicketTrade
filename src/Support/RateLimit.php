<?php

/**
 * TicketTrade — Support\RateLimit Fixed-Window Limiter
 *
 * Per D-12 + AD-13:
 *   - Fixed window per route (e.g. login: 5 per 5 minutes per IP).
 *   - Atomic check-and-increment via INSERT ... ON DUPLICATE KEY UPDATE.
 *   - Bucket key format: route:ip:YYYY-MM-DD-HH-MM-bucketed.
 *   - Returns ['allowed', 'count', 'retry_after'].
 */

declare(strict_types=1);

namespace App\Support;

use DateTime;
use DateTimeZone;

class RateLimit
{
    /**
     * Increment and check the rate-limit counter for the given route + IP.
     *
     * @param string $route Route key in config/rate_limits.php
     * @param string $ip    Client IP (REMOTE_ADDR or 0.0.0.0)
     * @param string $userId Optional user ID (unused in Phase 2)
     * @return array{allowed:bool,count:int,retry_after:int}
     */
    public static function hit(string $route, string $ip, string $userId = ''): array
    {
        $limits = require APP_ROOT . '/config/rate_limits.php';
        if (!isset($limits[$route])) {
            return ['allowed' => true, 'count' => 0, 'retry_after' => 0];
        }
        $limit = $limits[$route];
        $minutes = (int) $limit['window_minutes'];
        $bucket = (int) floor(((int) date('i')) / $minutes) * $minutes;
        $key = sprintf(
            '%s:ip:%s:%s-%s',
            $route,
            $ip,
            date('Y-m-d-H'),
            str_pad((string) $bucket, 2, '0', STR_PAD_LEFT)
        );

        $now = (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $expires = (new DateTime('+' . $minutes . ' minutes'))->format('Y-m-d H:i:s');

        try {
            $sql = 'INSERT INTO cache_rate (rate_key, count, window_start, expires_at) '
                . 'VALUES (?, 1, ?, ?) '
                . 'ON DUPLICATE KEY UPDATE count = count + 1, expires_at = VALUES(expires_at)';
            $stmt = Db::pdo()->prepare($sql);
            $stmt->execute([$key, $now, $expires]);
        } catch (\Throwable $e) {
            // cache_rate table may be missing (e.g. before migrations run); allow the request.
            return ['allowed' => true, 'count' => 0, 'retry_after' => 0];
        }

        try {
            $row = Db::pdo()->prepare('SELECT count, expires_at FROM cache_rate WHERE rate_key = ?');
            $row->execute([$key]);
            $r = $row->fetch();
        } catch (\Throwable $e) {
            return ['allowed' => true, 'count' => 0, 'retry_after' => 0];
        }
        $count = (int) ($r['count'] ?? 0);
        $allowed = $count <= (int) $limit['max'];
        $retry = $allowed ? 0 : max(0, (int) ((strtotime($r['expires_at'] ?? $expires) - time())));
        return ['allowed' => $allowed, 'count' => $count, 'retry_after' => $retry];
    }
}
