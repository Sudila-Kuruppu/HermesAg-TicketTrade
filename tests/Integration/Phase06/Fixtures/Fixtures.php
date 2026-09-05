<?php

/**
 * Phase 6 — Integration Test Fixtures
 *
 * Extends the Phase 4 fixtures to additionally TRUNCATE the Phase 6
 * tables added in Plan 06-01+ (none yet from this plan; the truncate
 * patterns from Phase 04 use try/catch to ignore missing tables so
 * the leaderboard_* + login_streaks tables from Plan 06-03+ will
 * just work when added later).
 *
 * Mirrors the Phase 05 Fixtures shape.
 *
 * Plan 06-02 ADDS:
 *   - seedPointsLog(): inserts a raw points_log row (used to seed
 *     pre-cap day/hour totals for velocity/pair-cap tests).
 *   - lastActiveAt() / setPointsFrozen(): thin helpers around
 *     users.last_active_at / points_frozen.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase06\Fixtures;

use App\Tests\Integration\Phase04\Fixtures\Fixtures as Phase04Fixtures;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__ . '/../../../..'));
}

abstract class Fixtures extends Phase04Fixtures
{
    /**
     * Plan 06-03 override: also clear the leaderboard_* + login_streaks
     * tables so suite order is deterministic.
     */
    protected function resetTables(): void
    {
        parent::resetTables();
        $this->truncateLeaderboards();
    }

    /**
     * Insert a raw points_log row with the given values. Bypasses
     * the points_service so tests can seed pre-cap deltas precisely.
     *
     * @param int $userId
     * @param int $delta
     * @param string $referenceType  e.g. 'final_session', 'review'
     * @param int|null $referenceId
     * @param string|null $metadataJson  JSON-encoded metadata or null
     * @param string|null $eventAt  Y-m-d H:i:s in Asia/Colombo; default = NOW()
     * @return int The new points_log id.
     */
    protected function seedPointsLog(
        int $userId,
        int $delta,
        string $referenceType = 'final_session',
        ?int $referenceId = null,
        ?string $metadataJson = null,
        ?string $eventAt = null
    ): int {
        $eventAt ??= (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $uuid = \Ramsey\Uuid\Uuid::uuid7()->toString();
        $stmt = $this->pdo->prepare(
            'INSERT INTO points_log (user_id, delta, reference_type, reference_id, balance_after, '
            . 'event_uuid, metadata, event_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $delta,
            $referenceType,
            $referenceId,
            0,
            $uuid,
            $metadataJson,
            $eventAt,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Read users.last_active_at (raw DB value, Asia/Colombo string or NULL).
     */
    protected function lastActiveAt(int $userId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT last_active_at FROM users WHERE user_id = ?');
        $stmt->execute([$userId]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : ($v !== null ? (string) $v : null);
    }

    /**
     * Insert a sessions row directly for tests that need to seed
     * "logged in today" users (Plan 06-03 streak recompute).
     */
    protected function seedSessionFor(int $userId, ?string $lastSeen = null): string
    {
        $sid = 'test-sid-' . bin2hex(random_bytes(4));
        $now = $lastSeen ?? (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))
            ->format('Y-m-d H:i:s');
        $this->pdo->prepare(
            'INSERT INTO sessions (session_id, user_id, last_seen, ip, user_agent, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$sid, $userId, $now, '127.0.0.1', 'phpunit', $now]);
        return $sid;
    }

    /**
     * Insert a login_streaks row directly (bypasses the daily cron).
     */
    protected function seedLoginStreak(int $userId, string $loginDate, int $streakCount = 1): void
    {
        $now = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))
            ->format('Y-m-d H:i:s');
        $this->pdo->prepare(
            'INSERT INTO login_streaks (user_id, login_date, streak_count, updated_at) '
            . 'VALUES (?, ?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE streak_count = VALUES(streak_count), updated_at = VALUES(updated_at)'
        )->execute([$userId, $loginDate, $streakCount, $now]);
    }

    /**
     * Truncate the Phase 6 leaderboard tables + login_streaks between
     * tests so suite order is deterministic.
     */
    protected function truncateLeaderboards(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (
            [
            'leaderboard_campus_legends',
            'leaderboard_weekly_risers',
            'leaderboard_category_leaders',
            'leaderboard_streak_kings',
            'login_streaks',
            ] as $t
        ) {
            try {
                $this->pdo->exec('TRUNCATE TABLE ' . $t);
            } catch (\Throwable $e) {
                // ignore
            }
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
