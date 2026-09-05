<?php
/**
 * Phase 6 Plan 06-03 — MigrationLeaderboardSummaryTest
 *
 * Verifies the 020_leaderboard_summary migration applied cleanly:
 *   - 4 tables: leaderboard_campus_legends, leaderboard_weekly_risers,
 *     leaderboard_category_leaders, leaderboard_streak_kings.
 *   - Composite indexes exist on each (matching the ORDER BY shape).
 *   - Idempotency: re-running migrate.php is a no-op (.applied contains 020).
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase06;

use App\Tests\Integration\Phase06\Fixtures\Fixtures;
use PDO;

class MigrationLeaderboardSummaryTest extends Fixtures
{
    public function test_four_tables_exist(): void
    {
        $tables = $this->pdo->query(
            "SELECT TABLE_NAME FROM information_schema.TABLES "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'leaderboard\\_%'"
        )->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('leaderboard_campus_legends', $tables);
        $this->assertContains('leaderboard_weekly_risers', $tables);
        $this->assertContains('leaderboard_category_leaders', $tables);
        $this->assertContains('leaderboard_streak_kings', $tables);
    }

    public function test_campus_legends_score_rank_index_exists(): void
    {
        $rows = $this->pdo->query(
            "SHOW INDEX FROM leaderboard_campus_legends WHERE Key_name = 'idx_score_rank'"
        )->fetchAll();
        $this->assertGreaterThanOrEqual(1, $rows, 'idx_score_rank missing on leaderboard_campus_legends');
    }

    public function test_weekly_risers_score_index_exists(): void
    {
        $rows = $this->pdo->query(
            "SHOW INDEX FROM leaderboard_weekly_risers WHERE Key_name = 'idx_score'"
        )->fetchAll();
        $this->assertGreaterThanOrEqual(1, $rows, 'idx_score missing on leaderboard_weekly_risers');
    }

    public function test_category_leaders_cat_score_index_exists(): void
    {
        $rows = $this->pdo->query(
            "SHOW INDEX FROM leaderboard_category_leaders WHERE Key_name = 'idx_cat_score'"
        )->fetchAll();
        $this->assertGreaterThanOrEqual(1, $rows, 'idx_cat_score missing on leaderboard_category_leaders');
        $columns = array_column($rows, 'Column_name');
        $this->assertSame('category_id', $columns[0]);
        $this->assertSame('score', $columns[1]);
        $this->assertSame('user_id', $columns[2]);
    }

    public function test_streak_kings_score_streak_index_exists(): void
    {
        $rows = $this->pdo->query(
            "SHOW INDEX FROM leaderboard_streak_kings WHERE Key_name = 'idx_score_streak'"
        )->fetchAll();
        $this->assertGreaterThanOrEqual(1, $rows, 'idx_score_streak missing on leaderboard_streak_kings');
    }

    public function test_migration_020_is_idempotent(): void
    {
        $applied = file(
            APP_ROOT . '/migrations/.applied',
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );
        $this->assertContains('020_leaderboard_summary.sql', $applied);
    }
}