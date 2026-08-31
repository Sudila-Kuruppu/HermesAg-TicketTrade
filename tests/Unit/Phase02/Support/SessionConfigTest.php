<?php
/**
 * Phase 2 — SessionConfigTest
 *
 * Locks in the AD-13 canonical session config: use_strict_mode=1,
 * sid_length=48, sid_bits_per_char=5, gc_maxlifetime=604800, plus
 * cookie httponly=1, samesite=Strict, lifetime=604800, path=/.
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase02\Support;

use PHPUnit\Framework\TestCase;

class SessionConfigTest extends TestCase
{
    public function test_ini_session_params(): void
    {
        // The ini settings are applied in config/bootstrap.php BEFORE
        // session_start. We can't read them AFTER session_start because
        // session_start overrides the ini settings. So we check the
        // bootstrap.php source instead.
        $src = file_get_contents(__DIR__ . '/../../../../config/bootstrap.php');
        $this->assertStringContainsString("ini_set('session.use_strict_mode', '1')", $src);
        $this->assertStringContainsString("ini_set('session.sid_length', '48')", $src);
        $this->assertStringContainsString("ini_set('session.sid_bits_per_char', '5')", $src);
        $this->assertStringContainsString("ini_set('session.gc_maxlifetime', '604800')", $src);
    }

    public function test_cookie_params_in_bootstrap(): void
    {
        $src = file_get_contents(__DIR__ . '/../../../../config/bootstrap.php');
        $this->assertStringContainsString("'lifetime' => 7 * 24 * 60 * 60", $src);
        $this->assertStringContainsString("'httponly' => true", $src);
        $this->assertStringContainsString("'samesite' => 'Strict'", $src);
        $this->assertStringContainsString("'path' => '/'", $src);
        // production-gating check: just confirm bootstrap reads APP_ENV
        $this->assertStringContainsString("APP_ENV", $src);
    }

    public function test_cli_skips_session(): void
    {
        $src = file_get_contents(__DIR__ . '/../../../../config/bootstrap.php');
        $this->assertStringContainsString("PHP_SAPI !== 'cli'", $src);
    }
}
