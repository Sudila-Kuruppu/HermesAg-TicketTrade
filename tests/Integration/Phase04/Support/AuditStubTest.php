<?php
/**
 * Phase 4 — AuditStubTest (integration)
 *
 * Covers Support\Audit::log() integration:
 *   - log() writes a row with the right shape
 *   - log() returns the new audit_id
 *   - log() NEVER throws (a failed INSERT returns 0)
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase04\Support;

use App\Support\Audit;
use App\Support\Db;
use App\Tests\Integration\Phase04\Fixtures\Fixtures;

class AuditStubTest extends Fixtures
{
    public function test_log_writes_row_with_correct_shape(): void
    {
        $userId = $this->seedUser();
        $id = Audit::log($userId, 'ticket.created', 'ticket', 42, ['price_cents' => 1000]);
        $this->assertGreaterThan(0, $id);

        $row = $this->pdo->query('SELECT * FROM audit_log WHERE id = ' . (int) $id)->fetch();
        $this->assertNotEmpty($row);
        $this->assertSame('ticket.created', $row['action']);
        $this->assertSame('ticket', $row['target_type']);
        $this->assertSame(42, (int) $row['target_id']);
        $this->assertSame((int) $userId, (int) $row['actor_user_id']);
        $this->assertNotNull($row['metadata_json']);
        // MySQL JSON column returns as a string in PDO; compare JSON-decoded form.
        $decoded = json_decode((string) $row['metadata_json'], true);
        $this->assertSame(['price_cents' => 1000], $decoded);
    }

    public function test_log_returns_audit_id(): void
    {
        $userId = $this->seedUser();
        $id = Audit::log($userId, 'ticket.redeemed', 'ticket', 99, null);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function test_log_with_null_actor(): void
    {
        $id = Audit::log(null, 'cron.expiry', 'ticket', 1, null);
        $this->assertGreaterThan(0, $id);
        $row = $this->pdo->query('SELECT actor_user_id FROM audit_log WHERE id = ' . (int) $id)->fetch();
        $this->assertNull($row['actor_user_id']);
    }

    public function test_log_handles_metadata_null(): void
    {
        $id = Audit::log(null, 'system.boot', 'system', 0, null);
        $this->assertGreaterThan(0, $id);
    }

    public function test_log_never_throws_on_db_failure(): void
    {
        // Simulate a DB failure by closing the singleton and resetting
        // it to a stub PDO that throws. Use a closure-captured flag to
        // confirm error_log was called.
        $captured = null;
        $previousHandler = set_error_handler(function ($errno, $errstr) use (&$captured) {
            $captured = $errstr;
            return true;
        }, E_ALL);
        // Force a failure: pass a non-existent column shape by
        // temporarily renaming the table. That's heavy; easier:
        // inject a PDO that throws on prepare.
        // We use a Test override: set up a PDO subclass that throws.
        // For unit-level coverage we have AuditStubUnitTest which
        // uses reflection. For this integration test, we test that
        // a constrained log call (action too long) is captured.
        $longAction = str_repeat('x', 70); // VARCHAR(60) cap
        // This will throw at the DB level; Audit::log must catch.
        $id = Audit::log(null, $longAction, 'ticket', 1, null);
        // The function returns 0 (failed write) and does NOT throw.
        $this->assertSame(0, $id);
        restore_error_handler();
    }
}
