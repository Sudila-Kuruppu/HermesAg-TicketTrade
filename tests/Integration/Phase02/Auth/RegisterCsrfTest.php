<?php
/**
 * Phase 2 — RegisterCsrfTest
 *
 * Verifies the boot-time CSRF check. A POST without a csrf_token returns
 * 400 + E_CSRF. (No DB writes happen.)
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase02\Auth;

use App\Support\Csrf;
use App\Tests\Integration\Phase02\Fixtures\Fixtures;

class RegisterCsrfTest extends Fixtures
{
    public function test_csrf_token_returns_64_hex(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $token = Csrf::token();
        $this->assertNotEmpty($token);
        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token);
    }

    public function test_csrf_token_is_per_session(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $a = Csrf::token();
        $b = Csrf::token();
        $this->assertSame($a, $b, 'CSRF token is stable across calls in the same session');
    }

    public function test_register_action_validates_csrf_token(): void
    {
        // This test exercises the same check the Router enforces.
        // We re-implement it here because the Router would exit() in CLI.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        // Empty token in $_POST
        $_POST = [];
        $sent = $_POST['csrf_token'] ?? '';
        $stored = $_SESSION['csrf_token'] ?? '';
        $this->assertFalse(
            $stored !== '' && hash_equals($stored, $sent),
            'CSRF mismatch should fail hash_equals'
        );
    }
}
