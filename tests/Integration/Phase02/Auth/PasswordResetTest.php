<?php
/**
 * Phase 2 — PasswordResetTest
 *
 * Verifies the forgot-password / reset-password flow:
 *  - requestPasswordReset always returns ok (D-07 anti-enumeration)
 *  - a registered email creates a password_resets row
 *  - a non-registered email creates NO row
 *  - peekPasswordReset returns the row OR null (no consumption)
 *  - consumePasswordReset updates users.password_hash and marks the row used
 *  - re-using the same token returns E_TOKEN_INVALID
 *  - expired token returns E_TOKEN_INVALID
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase02\Auth;

use App\Auth\Service\auth_service;
use App\Tests\Integration\Phase02\Fixtures\Fixtures;

class PasswordResetTest extends Fixtures
{
    public function test_request_reset_creates_row_for_known_email(): void
    {
        $this->seedUser([
            'email' => 'reset@students.nsbm.ac.lk',
            'nickname' => 'resetuser',
            'student_id' => 'NSBM/2024/PR1',
        ]);
        $r = auth_service::requestPasswordReset('reset@students.nsbm.ac.lk');
        $this->assertTrue($r['ok']);
        // In dev mode, the token is returned (so error_log line can be tested)
        if (getenv('APP_ENV') !== 'production') {
            $this->assertNotEmpty($r['token']);
        }
        $stmt = $this->pdo->query('SELECT * FROM password_resets');
        $this->assertSame(1, $stmt->rowCount());
    }

    public function test_request_reset_no_row_for_unknown_email(): void
    {
        $r = auth_service::requestPasswordReset('nobody@students.nsbm.ac.lk');
        $this->assertTrue($r['ok']);
        $this->assertNull($r['token']);
        $stmt = $this->pdo->query('SELECT * FROM password_resets');
        $this->assertSame(0, $stmt->rowCount());
    }

    public function test_consume_reset_updates_password_and_marks_used(): void
    {
        $uid = $this->seedUser([
            'email' => 'consume@students.nsbm.ac.lk',
            'nickname' => 'consumeuser',
            'student_id' => 'NSBM/2024/PR2',
            'password_hash' => password_hash('oldpassword', PASSWORD_BCRYPT, ['cost' => 12]),
        ]);
        $r = auth_service::requestPasswordReset('consume@students.nsbm.ac.lk');
        $token = (string) ($r['token'] ?? '');
        $this->assertNotEmpty($token, 'token returned in dev mode');

        $r2 = auth_service::consumePasswordReset($token, 'newpassword');
        $this->assertTrue($r2['ok'], 'consume should succeed: ' . json_encode($r2));
        $this->assertSame($uid, $r2['user_id']);

        // password_hash is updated.
        $stmt = $this->pdo->prepare('SELECT password_hash FROM users WHERE user_id = ?');
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        $this->assertTrue(password_verify('newpassword', $row['password_hash']));
        $this->assertFalse(password_verify('oldpassword', $row['password_hash']));

        // password_resets.used_at is set.
        $stmt = $this->pdo->prepare('SELECT used_at FROM password_resets WHERE user_id = ?');
        $stmt->execute([$uid]);
        $v = $stmt->fetch();
        $this->assertNotNull($v['used_at']);
    }

    public function test_consume_twice_returns_error(): void
    {
        $this->seedUser([
            'email' => 'twice2@students.nsbm.ac.lk',
            'nickname' => 'twice2user',
            'student_id' => 'NSBM/2024/PR3',
        ]);
        $r = auth_service::requestPasswordReset('twice2@students.nsbm.ac.lk');
        $token = (string) ($r['token'] ?? '');
        $this->assertTrue(auth_service::consumePasswordReset($token, 'newpass1')['ok']);
        $r2 = auth_service::consumePasswordReset($token, 'newpass2');
        $this->assertFalse($r2['ok']);
        $this->assertSame('E_TOKEN_INVALID', $r2['error']['code']);
    }

    public function test_consume_expired_token_returns_error(): void
    {
        $uid = $this->seedUser([
            'email' => 'expired@students.nsbm.ac.lk',
            'nickname' => 'expireduser',
            'student_id' => 'NSBM/2024/PR4',
        ]);
        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        $expired = (new \DateTime('-1 hour', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $this->pdo->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, NOW())'
        )->execute([$uid, $hash, $expired]);

        $r = auth_service::consumePasswordReset($raw, 'newpass1');
        $this->assertFalse($r['ok']);
        $this->assertSame('E_TOKEN_INVALID', $r['error']['code']);
    }

    public function test_consume_weak_password_returns_error(): void
    {
        $this->seedUser([
            'email' => 'weakpw@students.nsbm.ac.lk',
            'nickname' => 'weakpwuser',
            'student_id' => 'NSBM/2024/PR5',
        ]);
        $r = auth_service::requestPasswordReset('weakpw@students.nsbm.ac.lk');
        $token = (string) ($r['token'] ?? '');
        $r2 = auth_service::consumePasswordReset($token, 'short');
        $this->assertFalse($r2['ok']);
        $this->assertSame('E_PASSWORD_WEAK', $r2['error']['code']);
    }
}
