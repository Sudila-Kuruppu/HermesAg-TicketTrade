<?php
/**
 * Phase 2 — ErrorEnvelopeTest
 *
 * WR-001: Error::server_error must always log the internal message,
 * but only echo it to the client when APP_ENV is explicitly set to
 * 'development'. When APP_ENV is unset or 'production', the client
 * must see a generic page (no table names / paths / class names leak).
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase02\Support;

use PHPUnit\Framework\TestCase;

class ErrorEnvelopeTest extends TestCase
{
    public function test_envelope_shape(): void
    {
        $env = \App\Support\Error::envelope(true, ['x' => 1], null);
        $this->assertTrue($env['ok']);
        $this->assertSame(['x' => 1], $env['data']);
        $this->assertNull($env['error']);
    }

    public function test_envelope_error_shape(): void
    {
        $env = \App\Support\Error::envelope(false, null, [
            'code' => 'E_TEST',
            'message' => 'oops',
            'fields' => ['a' => 'required'],
        ]);
        $this->assertFalse($env['ok']);
        $this->assertNull($env['data']);
        $this->assertSame('E_TEST', $env['error']['code']);
        $this->assertSame('required', $env['error']['fields']['a']);
    }

    /**
     * WR-001: Error::server_error must log on every invocation and
     * default to a safe (generic) page when APP_ENV is unset.
     */
    public function test_server_error_source_logs_always_and_generic_default(): void
    {
        $src = file_get_contents(__DIR__ . '/../../../../src/Support/Error.php');
        // Always log server-side
        $this->assertStringContainsString("error_log('[server_error] '", $src);
        // Safe-by-default: explicit APP_ENV=development gates the verbose page
        $this->assertStringContainsString(
            "getenv('APP_ENV') !== false && getenv('APP_ENV') === 'development'",
            $src,
            'Error::server_error must require explicit APP_ENV=development to echo internals'
        );
    }
}