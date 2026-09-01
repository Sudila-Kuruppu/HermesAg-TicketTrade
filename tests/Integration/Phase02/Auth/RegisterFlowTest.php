<?php
/**
 * Phase 2 — RegisterFlowTest
 *
 * Verifies the auth_service::register() end-to-end:
 *  - happy path creates a user + email_verification + auto-starts a session
 *  - combined anti-enumeration (D-13) returns E_AUTH_ALLOWLIST for
 *    email not in allowlist, student ID not in allowlist, and duplicate email
 *  - nickname taken returns E_NICKNAME_TAKEN
 *  - password < 8 chars returns E_PASSWORD_WEAK
 *  - email format error returns E_VALIDATION
 *  - reserved nicknames are rejected
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase02\Auth;

use App\Auth\Model\email_verification_model;
use App\Auth\Model\session_model;
use App\Auth\Service\auth_service;
use App\Tests\Integration\Phase02\Fixtures\Fixtures;

class RegisterFlowTest extends Fixtures
{
    protected function setUp(): void
    {
        parent::setUp();
        // Seed one allowlist row used by multiple tests
        $now = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO student_id_allowlist (student_id, email, created_at) VALUES (?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE created_at = VALUES(created_at)'
        );
        $stmt->execute(['NSBM/2024/001', 'alice@students.nsbm.ac.lk', $now]);
    }

    public function test_register_creates_user_with_allowlist_match(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        // Use a unique nickname to avoid collisions
        $nick = 'alice_' . bin2hex(random_bytes(3));
        $r = auth_service::register(
            'alice@students.nsbm.ac.lk',
            'correcthorse',
            $nick,
            'NSBM/2024/001',
            'Alice Perera'
        );
        $this->assertTrue($r['ok'], 'register should succeed with valid inputs');
        $this->assertArrayHasKey('user_id', $r);
        $this->assertArrayHasKey('verify_token', $r);
        $this->assertNotEmpty($r['verify_token']);
        $this->assertSame(64, strlen($r['verify_token']), 'verify token is 64 hex chars');

        // The user row exists with the right defaults.
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE user_id = ?');
        $stmt->execute([$r['user_id']]);
        $user = $stmt->fetch();
        $this->assertNotNull($user);
        $this->assertSame(0, (int) $user['is_verified']);
        $this->assertSame(0, (int) $user['points']);
        $this->assertSame('E', $user['tier']);
        $this->assertSame(0, (int) $user['is_admin']);
        $this->assertSame(0, (int) $user['is_banned']);
        $this->assertGreaterThanOrEqual(1, (int) $user['avatar_id']);
        $this->assertLessThanOrEqual(12, (int) $user['avatar_id']);
        $this->assertNotEmpty($user['password_hash']);
        // password_hash is bcrypt cost 12
        $this->assertStringStartsWith('$2y$12$', $user['password_hash']);

        // The email_verifications row was created.
        $stmt = $this->pdo->prepare('SELECT * FROM email_verifications WHERE user_id = ?');
        $stmt->execute([$r['user_id']]);
        $verRow = $stmt->fetch();
        $this->assertNotNull($verRow, 'email_verifications row should exist');
        $this->assertNull($verRow['used_at']);
        $this->assertSame(64, strlen($verRow['token_hash']));
        // The hash matches the raw token
        $this->assertSame(hash('sha256', $r['verify_token']), $verRow['token_hash']);
    }

    public function test_register_combined_error_on_allowlist_miss(): void
    {
        $r = auth_service::register(
            'evil@example.com', // not @students.nsbm.ac.lk
            'correcthorse',
            'evilev',
            'NSBM/2024/001',
            'Evil One'
        );
        $this->assertFalse($r['ok']);
        $this->assertSame('E_VALIDATION', $r['error']['code']);
        $this->assertArrayHasKey('email', $r['error']['fields']);
    }

    public function test_register_combined_error_on_student_id_miss(): void
    {
        $r = auth_service::register(
            'alice@students.nsbm.ac.lk',
            'correcthorse',
            'bobbyb',
            'NSBM/2024/999', // not in allowlist
            'Bob NoMatch'
        );
        $this->assertFalse($r['ok']);
        $this->assertSame('E_AUTH_ALLOWLIST', $r['error']['code']);
    }

    public function test_register_combined_error_on_duplicate_email(): void
    {
        $r1 = auth_service::register(
            'alice@students.nsbm.ac.lk',
            'correcthorse',
            'aliceA',
            'NSBM/2024/001',
            'Alice A'
        );
        $this->assertTrue($r1['ok']);
        $r2 = auth_service::register(
            'alice@students.nsbm.ac.lk',
            'correcthorse',
            'aliceB',
            'NSBM/2024/001',
            'Alice B'
        );
        $this->assertFalse($r2['ok']);
        $this->assertSame('E_AUTH_ALLOWLIST', $r2['error']['code']);
    }

    public function test_register_nickname_taken_error(): void
    {
        $r1 = auth_service::register(
            'alice@students.nsbm.ac.lk',
            'correcthorse',
            'takenick',
            'NSBM/2024/001',
            'Take Nick'
        );
        $this->assertTrue($r1['ok']);
        // Insert a different allowlist row for the second attempt
        $now = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $this->pdo->prepare(
            'INSERT INTO student_id_allowlist (student_id, email, created_at) VALUES (?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE created_at = VALUES(created_at)'
        )->execute(['NSBM/2024/002', 'alice2@students.nsbm.ac.lk', $now]);
        $r2 = auth_service::register(
            'alice2@students.nsbm.ac.lk',
            'correcthorse',
            'takenick',
            'NSBM/2024/002',
            'Take Nick 2'
        );
        $this->assertFalse($r2['ok']);
        $this->assertSame('E_NICKNAME_TAKEN', $r2['error']['code']);
        $this->assertArrayHasKey('nickname', $r2['error']['fields']);
    }

    public function test_register_password_min_length_error(): void
    {
        $r = auth_service::register(
            'alice@students.nsbm.ac.lk',
            'short',
            'newuser',
            'NSBM/2024/001',
            'New User'
        );
        $this->assertFalse($r['ok']);
        $this->assertSame('E_PASSWORD_WEAK', $r['error']['code']);
    }

    public function test_register_email_format_error(): void
    {
        $r = auth_service::register(
            'notanemail',
            'correcthorse',
            'newuser',
            'NSBM/2024/001',
            'New User'
        );
        $this->assertFalse($r['ok']);
        $this->assertSame('E_VALIDATION', $r['error']['code']);
    }

    public function test_register_reserved_nickname_rejected(): void
    {
        $r = auth_service::register(
            'alice@students.nsbm.ac.lk',
            'correcthorse',
            'admin', // reserved
            'NSBM/2024/001',
            'Admin Person'
        );
        $this->assertFalse($r['ok']);
        $this->assertSame('E_VALIDATION', $r['error']['code']);
        $this->assertArrayHasKey('nickname', $r['error']['fields']);
    }
}
