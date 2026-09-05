<?php
/**
 * Phase 2 — MigrateRunnerTest
 *
 * Runs `php migrate.php` against the test DB and asserts:
 *  - First run creates all 7 expected tables.
 *  - Second run is a no-op.
 *  - migrations/.applied has the expected line count (dynamically
 *    computed from glob('migrations/*.sql') minus dotfiles, since
 *    Phase 3 added three more migrations).
 *
 * The table-list assertion stays at the Phase 2 contract (7 tables);
 * new Phase 3 tables are not asserted here.
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
        // Clear .applied.test (per WR-07 the runner uses per-surface state files)
        @unlink(APP_ROOT . '/migrations/.applied.test');
        @unlink(APP_ROOT . '/migrations/.applied.test.lock');

        // Run the migrations
        $output = $this->runMigrations();
        $expectedCount = $this->countMigrationFiles();
        $this->assertStringContainsString('Applied ' . $expectedCount . ' files in', $output);

        // Verify expected tables exist (Phase 2 contract; new tables are
        // asserted in their respective test classes).
        $rows = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
        $expected = ['cache_rate', 'email_verifications', 'password_resets', 'points_log', 'sessions', 'student_id_allowlist', 'users'];
        foreach ($expected as $t) {
            $this->assertContains($t, $rows, "Missing table: $t");
        }
    }

    public function test_second_run_is_noop(): void
    {
        // Re-run; .applied.test already has all files from the previous test
        $output = $this->runMigrations();
        $this->assertStringContainsString('Already up-to-date (0 files to apply)', $output);
    }

    public function test_applied_file_has_expected_lines(): void
    {
        $applied = file_get_contents(APP_ROOT . '/migrations/.applied.test');
        $lines = array_filter(explode("\n", $applied), function ($l) {
            return trim($l) !== '';
        });
        $expected = $this->countMigrationFiles();
        $this->assertCount($expected, $lines);
    }

    /**
     * Count migration files matching the runner's filename filter:
     * *.sql starting with '0' and not starting with '.'.
     */
    private function countMigrationFiles(): int
    {
        $files = glob(APP_ROOT . '/migrations/*.sql') ?: [];
        $files = array_filter($files, function ($f) {
            $base = basename($f);
            return str_starts_with($base, '0') && substr($base, 0, 1) !== '.';
        });
        return count($files);
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
