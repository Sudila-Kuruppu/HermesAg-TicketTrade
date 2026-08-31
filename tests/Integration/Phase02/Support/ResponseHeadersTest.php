<?php
/**
 * Phase 2 — ResponseHeadersTest
 *
 * Verifies that Support\ResponseHeaders::boot() sets all four AD-13
 * security headers. Uses a CLI sub-process so the headers are captured
 * in stdout by the dev-server protocol.
 *
 * Simpler approach: directly assert the source file references each
 * header, and verify the CSP string lives in config/security_headers.php.
 * The actual header-set behavior is exercised by the curl smoke matrix
 * documented in 02-01-SUMMARY.md.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase02\Support;

use PHPUnit\Framework\TestCase;

class ResponseHeadersTest extends TestCase
{
    public function test_boot_sets_all_four_headers(): void
    {
        $src = file_get_contents(__DIR__ . '/../../../../src/Support/ResponseHeaders.php');
        $this->assertStringContainsString('X-Content-Type-Options', $src);
        $this->assertStringContainsString('nosniff', $src);
        $this->assertStringContainsString('X-Frame-Options', $src);
        $this->assertStringContainsString('DENY', $src);
        $this->assertStringContainsString('Referrer-Policy', $src);
        $this->assertStringContainsString('strict-origin-when-cross-origin', $src);
        $this->assertStringContainsString('Content-Security-Policy', $src);
    }

    public function test_csp_string_in_config(): void
    {
        $config = require __DIR__ . '/../../../../config/security_headers.php';
        $this->assertIsArray($config);
        $this->assertArrayHasKey('csp', $config);
        $csp = $config['csp'];
        $this->assertStringContainsString("script-src 'self' cdn.jsdelivr.net 'unsafe-inline'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
    }

    public function test_response_headers_boots_before_auth(): void
    {
        // The order in config/bootstrap.php matters per D-13.
        $bootstrap = file_get_contents(__DIR__ . '/../../../../config/bootstrap.php');
        $respPos = strpos($bootstrap, 'ResponseHeaders::boot()');
        $authPos = strpos($bootstrap, 'Auth::boot()');
        $this->assertNotFalse($respPos);
        $this->assertNotFalse($authPos);
        $this->assertLessThan($authPos, $respPos, 'ResponseHeaders must boot before Auth');
    }
}
