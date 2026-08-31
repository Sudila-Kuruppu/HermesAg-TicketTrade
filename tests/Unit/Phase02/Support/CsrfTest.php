<?php
/**
 * Phase 2 — CsrfTest
 *
 * Locks in:
 *  - token() returns a 64-char lowercase hex string.
 *  - token() in the same session returns the SAME token.
 *  - verify() uses hash_equals for constant-time comparison.
 *  - verify() rejects POST with wrong token (400 + E_CSRF envelope).
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase02\Support;

use PHPUnit\Framework\TestCase;

class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    public function test_token_is_64_hex_chars(): void
    {
        $token = \App\Support\Csrf::token();
        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function test_token_is_stable_in_same_session(): void
    {
        $a = \App\Support\Csrf::token();
        $b = \App\Support\Csrf::token();
        $this->assertSame($a, $b);
    }

    public function test_hash_equals_used_in_csrf_source(): void
    {
        $src = file_get_contents(APP_ROOT . '/src/Support/Csrf.php');
        $this->assertStringContainsString('hash_equals(', $src);
    }

    public function test_verify_rejects_wrong_token(): void
    {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_POST['csrf_token'] = str_repeat('0', 64);
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // We can't easily intercept the exit(); use process isolation.
        $php = PHP_BINARY;
        $script = <<<'PHP'
<?php
declare(strict_types=1);
require 'tests/bootstrap.php';
$_SESSION['csrf_token'] = 'abc123';
$_POST['csrf_token'] = 'wrong';
$_SERVER['REQUEST_METHOD'] = 'POST';
@session_start();
register_shutdown_function(function() {
    // Output whatever the script printed
});
ob_start();
try {
    \App\Support\Csrf::verify();
    echo "no_exit";
} catch (\Throwable $e) {
    echo "threw: " . $e->getMessage();
}
echo ob_get_clean();
PHP;

        $tmp = tempnam(sys_get_temp_dir(), 'csrf_test_');
        file_put_contents($tmp, $script);
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($tmp) . ' 2>&1';
        $output = shell_exec($cmd);
        @unlink($tmp);

        $this->assertStringContainsString('E_CSRF', $output ?: '');
        $this->assertStringContainsString('CSRF token mismatch', $output ?: '');

        unset($_POST['csrf_token'], $_SERVER['REQUEST_METHOD']);
        $_SESSION['csrf_token'] = '';
    }
}
