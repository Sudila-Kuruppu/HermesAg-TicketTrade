<?php
/**
 * Phase 2 — MigrateRunnerTest
 *
 * Runs `php migrate.php` against the test DB and asserts:
 *  - First run creates all 7 expected tables.
 *  - Second run is a no-op.
 *  - migrations/.applied has 7 lines.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase02\Support;

use PHPUnit\Framework\TestCase;
use App\Support\Db;

class MigrateRunnerTest extends TestCase
{
    public function test_first_run_creates_all_tables(): void
    {
        // Ensure a fresh schema state
        Db::reset();
        $pdo = Db::pdo();
        $this->dropAllTables($pdo);
        // Also clear .applied so the runner reapplies
        @unlink(APP_ROOT . '/migrations/.applied');

        // Run the migrations
        $output = $this->runMigrations();
        $this->assertStringContainsString('Applied 7 files in', $output);

        // Verify expected tables exist
        $rows = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
        $expected = ['cache_rate', 'email_verifications', 'password_resets', 'points_log', 'sessions', 'student_id_allowlist', 'users'];
        foreach ($expected as $t) {
            $this->assertContains($t, $rows, "Missing table: $t");
        }
    }

    public function test_second_run_is_noop(): void
    {
        // Re-run; .applied already has all 7 from the previous test
        $output = $this->runMigrations();
        $this->assertStringContainsString('Already up-to-date (0 files to apply)', $output);
    }

    public function test_applied_file_has_seven_lines(): void
    {
        $applied = file_get_contents(APP_ROOT . '/migrations/.applied');
        $lines = array_filter(explode("\n", $applied), function ($l) {
            return trim($l) !== '';
        });
        $this->assertCount(7, $lines);
    }

    private function runMigrations(): string
    {
        $cmd = 'APP_ENV=test php ' . escapeshellarg(APP_ROOT . '/migrate.php') . ' 2>&1';
        return shell_exec($cmd) ?: '';
    }

    private function dropAllTables(\PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN) as $t) {
            try {
                $pdo->exec("DROP TABLE IF EXISTS `$t`");
            } catch (\Throwable $e) {
                // ignore
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
