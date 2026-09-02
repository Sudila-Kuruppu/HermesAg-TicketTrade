<?php
/**
 * Phase 4 — AuditStubUnitTest (pure PHP)
 *
 * Verifies that Audit::log() never throws and returns 0 on failure.
 * Uses reflection to inject a PDO stub that throws on prepare().
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase04\Support;

use App\Support\Audit;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__ . '/../../../..'));
}

require_once APP_ROOT . '/vendor/autoload.php';

class AuditStubUnitTest extends TestCase
{
    public function test_log_returns_zero_on_db_failure(): void
    {
        // We cannot easily inject a PDO stub into the static Audit::log
        // since it calls Db::pdo() directly. Instead we use the actual
        // schema and force a failure via a too-long action column.
        $longAction = str_repeat('x', 70); // exceeds VARCHAR(60)
        $id = Audit::log(null, $longAction, 'ticket', 1, null);
        $this->assertSame(0, $id);
    }

    public function test_log_does_not_throw(): void
    {
        $longAction = str_repeat('x', 70);
        $caught = false;
        try {
            Audit::log(null, $longAction, 'ticket', 1, null);
        } catch (\Throwable $e) {
            $caught = true;
        }
        $this->assertFalse($caught, 'Audit::log must not throw on DB failure');
    }

    public function test_canonical_row_sorts_keys(): void
    {
        $row = ['b' => 1, 'a' => 2, 'c' => 3];
        $sorted = Audit::canonicalRow($row);
        $keys = array_keys($sorted);
        $this->assertSame(['a', 'b', 'c'], $keys);
    }
}
