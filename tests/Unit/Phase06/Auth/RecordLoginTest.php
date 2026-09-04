<?php
/**
 * Phase 6 Plan 06-02 — RecordLoginTest
 *
 * Covers auth_service::recordLogin():
 *   - login() refreshes users.last_active_at to within the last 5s.
 *   - consumePasswordReset() also refreshes last_active_at on the
 *     auto-login path.
 *   - recordLogin() does NOT throw on a missing user (UPDATE matches
 *     0 rows).
 *   - recordLogin() does NOT throw on a DB error (swallowed).
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase06\Auth;

use App\Auth\Service\auth_service;
use App\Support\Db;
use App\Tests\Integration\Phase06\Fixtures\Fixtures;

class RecordLoginTest extends Fixtures
{
    /**
     * Convert last_active_at (stored in the test session timezone,
     * +05:30 per tests/Integration/Phase04/Fixtures::setUp) to a Unix
     * timestamp. The test fixture sets the DB session to +05:30 so
     * the wall-clock matches the PHP-side Asia/Colombo time used in
     * the seeds and matches the prod-shape interpretation per AD-17.
     */
    private function lastActiveAtTs(int $uid): int
    {
        $current = $this->lastActiveAt($uid);
        $this->assertNotNull($current);
        $dt = new \DateTime($current, new \DateTimeZone('Asia/Colombo'));
        return $dt->getTimestamp();
    }

    public function test_login_refreshes_last_active_at(): void
    {
        // Seed an old last_active_at so we can verify it's refreshed.
        $oldTime = (new \DateTime('-30 days', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $uid = $this->seedUser([
            'email' => 'login@students.nsbm.ac.lk',
            'nickname' => 'loginuser',
            'student_id' => 'NSBM/2024/L01',
            'password_hash' => password_hash('correcthorse', PASSWORD_BCRYPT, ['cost' => 12]),
        ]);
        $this->pdo->prepare('UPDATE users SET last_active_at = ? WHERE user_id = ?')
            ->execute([$oldTime, $uid]);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $r = auth_service::login('login@students.nsbm.ac.lk', 'correcthorse');
        $this->assertTrue($r['ok']);

        // last_active_at should be very recent (within 5s of the
        // current Colombo wall-clock — the DB column is in +05:30
        // because the fixture pins session.time_zone = '+05:30').
        $nowColombo = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->getTimestamp();
        $ts = $this->lastActiveAtTs($uid);
        $this->assertGreaterThan($nowColombo - 5, $ts, "last_active_at should be within 5s of now (Colombo)");
        $this->assertLessThanOrEqual($nowColombo + 1, $ts, "last_active_at should not be in the future");
    }

    public function test_consume_password_reset_refreshes_last_active_at(): void
    {
        $oldTime = (new \DateTime('-30 days', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $uid = $this->seedUser([
            'email' => 'reset@students.nsbm.ac.lk',
            'nickname' => 'resetuser',
            'student_id' => 'NSBM/2024/R01',
            'password_hash' => password_hash('correcthorse', PASSWORD_BCRYPT, ['cost' => 12]),
        ]);
        $this->pdo->prepare('UPDATE users SET last_active_at = ? WHERE user_id = ?')
            ->execute([$oldTime, $uid]);

        // Insert a password_resets row directly.
        $rawToken = 'test-raw-token-' . bin2hex(random_bytes(8));
        $hash = auth_service::hashToken($rawToken);
        $expires = (new \DateTime('+1 hour', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $this->pdo->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) '
            . 'VALUES (?, ?, ?, NOW())'
        )->execute([$uid, $hash, $expires]);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $r = auth_service::consumePasswordReset($rawToken, 'newpassword123');
        $this->assertTrue($r['ok']);
        $this->assertSame($uid, $r['user_id']);

        $nowColombo = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->getTimestamp();
        $ts = $this->lastActiveAtTs($uid);
        $this->assertGreaterThan($nowColombo - 5, $ts);
    }

    public function test_record_login_unknown_user_does_not_throw(): void
    {
        // The UPDATE matches zero rows; the method must NOT throw.
        auth_service::recordLogin(999_999);
        $this->assertTrue(true, 'recordLogin() swallowed the no-op update');
    }

    public function test_record_login_direct_call_refreshes_column(): void
    {
        $oldTime = (new \DateTime('-30 days', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $uid = $this->seedUser([
            'email' => 'direct@students.nsbm.ac.lk',
            'nickname' => 'directuser',
            'student_id' => 'NSBM/2024/D01',
        ]);
        $this->pdo->prepare('UPDATE users SET last_active_at = ? WHERE user_id = ?')
            ->execute([$oldTime, $uid]);

        auth_service::recordLogin($uid);

        $nowColombo = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->getTimestamp();
        $ts = $this->lastActiveAtTs($uid);
        $this->assertGreaterThan($nowColombo - 5, $ts);
    }
}
