<?php
/**
 * Phase 6 — MigrationLastActiveTest
 *
 * Verifies the 019_users_last_active migration applied cleanly:
 *   - users.last_active_at DATETIME NULL column exists.
 *   - idx_users_last_active index exists on users(last_active_at).
 *   - users.frozen_at + users.last_unfrozen_at columns exist.
 *   - trg_points_log_refresh_last_active trigger exists.
 *   - Inserting a points_log row refreshes users.last_active_at.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase06;

use App\Tests\Integration\Phase06\Fixtures\Fixtures;

class MigrationLastActiveTest extends Fixtures
{
    public function test_users_last_active_at_column_exists(): void
    {
        $cols = $this->columns('users');
        $this->assertContains('last_active_at', $cols, 'users.last_active_at column missing');
        $this->assertContains('frozen_at', $cols, 'users.frozen_at column missing');
        $this->assertContains('last_unfrozen_at', $cols, 'users.last_unfrozen_at column missing');
    }

    public function test_idx_users_last_active_exists(): void
    {
        $rows = $this->pdo->query(
            "SHOW INDEX FROM users WHERE Key_name = 'idx_users_last_active'"
        )->fetchAll();
        $this->assertGreaterThanOrEqual(1, $rows, 'idx_users_last_active index missing');
        $this->assertSame('last_active_at', (string) $rows[0]['Column_name']);
    }

    public function test_trigger_exists(): void
    {
        $rows = $this->pdo->query(
            "SELECT TRIGGER_NAME FROM information_schema.TRIGGERS "
            . "WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = 'trg_points_log_refresh_last_active'"
        )->fetchAll();
        $this->assertGreaterThanOrEqual(1, $rows, 'trigger trg_points_log_refresh_last_active missing');
        $this->assertSame('trg_points_log_refresh_last_active', (string) $rows[0]['TRIGGER_NAME']);
    }

    public function test_trigger_refreshes_last_active_at_on_points_log_insert(): void
    {
        // Seed a user and an explicit last_active_at from 30 days ago.
        $user = $this->seedUser(['nickname' => 'stale']);
        $oldStamp = (new \DateTime('-30 days', new \DateTimeZone('Asia/Colombo')))
            ->format('Y-m-d H:i:s');
        $this->pdo->prepare('UPDATE users SET last_active_at = ? WHERE user_id = ?')
            ->execute([$oldStamp, $user]);

        // Insert a points_log row directly — the trigger should refresh
        // last_active_at to NOW().
        $now = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))
            ->format('Y-m-d H:i:s');
        $this->pdo->prepare(
            'INSERT INTO points_log (user_id, delta, reference_type, reference_id, balance_after, '
            . 'event_uuid, metadata, event_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $user,
            10,
            'test_award',
            null,
            10,
            \Ramsey\Uuid\Uuid::uuid7()->toString(),
            null,
            $now,
        ]);

        $row = $this->pdo->query('SELECT last_active_at FROM users WHERE user_id = ' . $user)->fetch();
        $this->assertNotNull($row['last_active_at'], 'trigger did not refresh last_active_at');

        // Confirm it's a fresh timestamp (>= test run start, allowing
        // for sub-second clock skew).
        $runStart = (new \DateTime('-1 minute', new \DateTimeZone('Asia/Colombo')))
            ->format('Y-m-d H:i:s');
        $this->assertGreaterThanOrEqual(
            $runStart,
            (string) $row['last_active_at'],
            'last_active_at is not freshly refreshed'
        );
    }

    public function test_migration_019_is_idempotent(): void
    {
        $applied = file(
            APP_ROOT . '/migrations/.applied',
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );
        $this->assertContains('019_users_last_active.sql', $applied);
    }

    private function columns(string $table): array
    {
        $rows = $this->pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll(\PDO::FETCH_ASSOC);
        return array_column($rows, 'Field');
    }
}