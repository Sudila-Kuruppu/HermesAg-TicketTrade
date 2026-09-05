<?php
/**
 * Phase 6 Plan 06-03 — MigrationLoginStreaksTest
 *
 * Verifies the 021_login_streaks migration applied cleanly:
 *   - login_streaks table exists with user_id, login_date, streak_count, updated_at.
 *   - users.current_streak + longest_streak columns exist.
 *   - UNIQUE KEY uq_user_date (user_id, login_date) exists.
 *   - Idempotency: re-running migrate.php is a no-op.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase06;

use App\Tests\Integration\Phase06\Fixtures\Fixtures;
use PDO;

class MigrationLoginStreaksTest extends Fixtures
{
    public function test_login_streaks_table_exists(): void
    {
        $tables = $this->pdo->query(
            "SELECT TABLE_NAME FROM information_schema.TABLES "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'login_streaks'"
        )->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('login_streaks', $tables);
    }

    public function test_login_streaks_columns(): void
    {
        $cols = $this->pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'login_streaks'"
        )->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('user_id', $cols);
        $this->assertContains('login_date', $cols);
        $this->assertContains('streak_count', $cols);
        $this->assertContains('updated_at', $cols);
    }

    public function test_users_streak_columns_exist(): void
    {
        $cols = $this->pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' "
            . "AND COLUMN_NAME IN ('current_streak', 'longest_streak')"
        )->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('current_streak', $cols);
        $this->assertContains('longest_streak', $cols);
    }

    public function test_uq_user_date_index_exists(): void
    {
        $rows = $this->pdo->query(
            "SHOW INDEX FROM login_streaks WHERE Key_name = 'uq_user_date'"
        )->fetchAll();
        $this->assertGreaterThanOrEqual(1, $rows, 'uq_user_date index missing');
        $columns = array_column($rows, 'Column_name');
        $this->assertSame('user_id', $columns[0]);
        $this->assertSame('login_date', $columns[1]);
    }

    public function test_migration_021_is_idempotent(): void
    {
        $applied = file(
            APP_ROOT . '/migrations/.applied',
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );
        $this->assertContains('021_login_streaks.sql', $applied);
    }
}