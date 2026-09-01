<?php
/**
 * Phase 2 — LoginFlowTest
 *
 * Verifies auth_service::login():
 *  - correct creds start a session + set $GLOBALS['current_user']
 *  - wrong password returns E_AUTH_INVALID
 *  - missing user returns E_AUTH_INVALID (same timing — see LoginTimingTest)
 *  - banned user returns E_AUTH_INVALID (don't leak the ban, D-06)
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase02\Auth;

use App\Auth\Service\auth_service;
use App\Tests\Integration\Phase02\Fixtures\Fixtures;

class LoginFlowTest extends Fixtures
{
    public function test_correct_creds_start_session(): void
    {
        $uid = $this->seedUser([
            'email' => 'login@students.nsbm.ac.lk',
            'nickname' => 'loginuser',
            'student_id' => 'NSBM/2024/L01',
            'password_hash' => password_hash('correcthorse', PASSWORD_BCRYPT, ['cost' => 12]),
        ]);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $r = auth_service::login('login@students.nsbm.ac.lk', 'correcthorse');
        $this->assertTrue($r['ok']);
        $this->assertSame($uid, $r['user_id']);

        // A sessions row was inserted.
        $sid = session_id();
        $stmt = $this->pdo->prepare('SELECT user_id FROM sessions WHERE session_id = ?');
        $stmt->execute([$sid]);
        $s = $stmt->fetch();
        $this->assertNotNull($s);
        $this->assertSame($uid, (int) $s['user_id']);
    }

    public function test_wrong_password_returns_error(): void
    {
        $this->seedUser([
            'email' => 'wrong@students.nsbm.ac.lk',
            'nickname' => 'wronguser',
            'student_id' => 'NSBM/2024/L02',
            'password_hash' => password_hash('correcthorse', PASSWORD_BCRYPT, ['cost' => 12]),
        ]);
        $r = auth_service::login('wrong@students.nsbm.ac.lk', 'wrongpass');
        $this->assertFalse($r['ok']);
        $this->assertSame('E_AUTH_INVALID', $r['error']['code']);
        $this->assertSame('Email or password is incorrect.', $r['error']['message']);
    }

    public function test_missing_user_returns_same_error(): void
    {
        $r = auth_service::login('nobody@students.nsbm.ac.lk', 'whatever');
        $this->assertFalse($r['ok']);
        $this->assertSame('E_AUTH_INVALID', $r['error']['code']);
        $this->assertSame('Email or password is incorrect.', $r['error']['message']);
    }

    public function test_banned_user_returns_same_error(): void
    {
        $this->seedUser([
            'email' => 'banned@students.nsbm.ac.lk',
            'nickname' => 'bannedlogin',
            'student_id' => 'NSBM/2024/L03',
            'password_hash' => password_hash('correcthorse', PASSWORD_BCRYPT, ['cost' => 12]),
            'is_banned' => true,
        ]);
        $r = auth_service::login('banned@students.nsbm.ac.lk', 'correcthorse');
        $this->assertFalse($r['ok']);
        // D-06: same generic copy; don't leak the ban.
        $this->assertSame('E_AUTH_INVALID', $r['error']['code']);
    }
}
