<?php
/**
 * Phase 2 — VerifyTokenTest
 *
 * Verifies auth_service::verifyEmail() end-to-end:
 *  - happy path marks the verification row used, sets is_verified=TRUE
 *  - calls points_service::awardVerificationBonus which writes a
 *    points_log row with delta=50 and a UUID v7 event_uuid
 *  - re-using the same token returns E_TOKEN_INVALID
 *  - unknown token returns E_TOKEN_INVALID
 *  - expired token returns E_TOKEN_INVALID
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase02\Auth;

use App\Auth\Service\auth_service;
use App\Tests\Integration\Phase02\Fixtures\Fixtures;
use Ramsey\Uuid\Uuid;

class VerifyTokenTest extends Fixtures
{
    public function test_verify_awards_50_points_and_sets_verified(): void
    {
        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        $expires = (new \DateTime('+24 hours', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $uid = $this->seedUser(['email' => 'verify@students.nsbm.ac.lk', 'student_id' => 'NSBM/2024/V01', 'nickname' => 'verifyuser']);
        $this->pdo->prepare(
            'INSERT INTO email_verifications (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, NOW())'
        )->execute([$uid, $hash, $expires]);

        $r = auth_service::verifyEmail($raw);
        $this->assertTrue($r['ok'], 'verify should succeed: ' . json_encode($r));
        $this->assertSame($uid, $r['user_id']);

        // email_verifications.used_at is set
        $stmt = $this->pdo->prepare('SELECT used_at FROM email_verifications WHERE user_id = ?');
        $stmt->execute([$uid]);
        $v = $stmt->fetch();
        $this->assertNotNull($v['used_at']);

        // users.is_verified is TRUE, points = 50, tier = 'D'
        $stmt = $this->pdo->prepare('SELECT is_verified, points, tier FROM users WHERE user_id = ?');
        $stmt->execute([$uid]);
        $u = $stmt->fetch();
        $this->assertSame(1, (int) $u['is_verified']);
        $this->assertSame(50, (int) $u['points']);
        $this->assertSame('D', $u['tier']);

        // points_log has a row with delta=50, reference_type='email_verification', event_uuid is UUID v7
        $stmt = $this->pdo->prepare('SELECT * FROM points_log WHERE user_id = ?');
        $stmt->execute([$uid]);
        $pl = $stmt->fetch();
        $this->assertNotNull($pl, 'points_log row should exist');
        $this->assertSame(50, (int) $pl['delta']);
        $this->assertSame('email_verification', $pl['reference_type']);
        $this->assertSame(50, (int) $pl['balance_after']);
        // UUID v7 check
        $uuid = Uuid::fromString($pl['event_uuid']);
        $this->assertSame(\Ramsey\Uuid\Uuid::UUID_TYPE_UNIX_TIME, $uuid->getVersion());
    }

    public function test_verify_token_used_twice_returns_error(): void
    {
        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        $expires = (new \DateTime('+24 hours', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $uid = $this->seedUser(['email' => 'twice@students.nsbm.ac.lk', 'student_id' => 'NSBM/2024/V02', 'nickname' => 'twiceuser']);
        $this->pdo->prepare(
            'INSERT INTO email_verifications (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, NOW())'
        )->execute([$uid, $hash, $expires]);

        $r1 = auth_service::verifyEmail($raw);
        $this->assertTrue($r1['ok']);
        $r2 = auth_service::verifyEmail($raw);
        $this->assertFalse($r2['ok']);
        $this->assertSame('E_TOKEN_INVALID', $r2['error']['code']);
    }

    public function test_verify_unknown_token_returns_error(): void
    {
        $r = auth_service::verifyEmail('not-a-real-token-12345');
        $this->assertFalse($r['ok']);
        $this->assertSame('E_TOKEN_INVALID', $r['error']['code']);
    }

    public function test_verify_expired_token_returns_error(): void
    {
        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        $expired = (new \DateTime('-1 hour', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $uid = $this->seedUser(['email' => 'exp@students.nsbm.ac.lk', 'student_id' => 'NSBM/2024/V03', 'nickname' => 'expuser']);
        $this->pdo->prepare(
            'INSERT INTO email_verifications (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, NOW())'
        )->execute([$uid, $hash, $expired]);

        $r = auth_service::verifyEmail($raw);
        $this->assertFalse($r['ok']);
        $this->assertSame('E_TOKEN_INVALID', $r['error']['code']);
    }
}
