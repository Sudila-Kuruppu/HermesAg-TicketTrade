<?php
/**
 * Phase 6 — MigrationIndexesTest
 *
 * Verifies the 018_points_log_indexes migration applied cleanly:
 *   - idx_points_user_event (user_id, event_at DESC) exists.
 *   - idx_points_pair (user_id, reference_id, event_at DESC) exists.
 *   - Idempotency: re-running migrate.php is a no-op (.applied contains 018).
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase06;

use App\Tests\Integration\Phase06\Fixtures\Fixtures;

class MigrationIndexesTest extends Fixtures
{
    public function test_idx_points_user_event_exists(): void
    {
        $rows = $this->pdo->query(
            "SHOW INDEX FROM points_log WHERE Key_name = 'idx_points_user_event'"
        )->fetchAll();
        $this->assertGreaterThanOrEqual(1, $rows, 'idx_points_user_event index missing');

        // Confirm columns and ordering: (user_id, event_at) with event_at DESC.
        $columns = array_column($rows, 'Column_name');
        $this->assertSame('user_id', $columns[0]);
        $this->assertSame('event_at', $columns[1]);
        $this->assertSame('D', $rows[1]['Collation']);
    }

    public function test_idx_points_pair_exists(): void
    {
        $rows = $this->pdo->query(
            "SHOW INDEX FROM points_log WHERE Key_name = 'idx_points_pair'"
        )->fetchAll();
        $this->assertGreaterThanOrEqual(1, $rows, 'idx_points_pair index missing');

        $columns = array_column($rows, 'Column_name');
        $this->assertSame('user_id', $columns[0]);
        $this->assertSame('reference_id', $columns[1]);
        $this->assertSame('event_at', $columns[2]);
        $this->assertSame('D', $rows[2]['Collation']);
    }

    public function test_migration_018_is_idempotent(): void
    {
        $applied = file(
            APP_ROOT . '/migrations/.applied',
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );
        $this->assertContains('018_points_log_indexes.sql', $applied);
    }
}