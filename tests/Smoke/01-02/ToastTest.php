<?php

declare(strict_types=1);

namespace App\Tests\Smoke\Smoke_01_02;

use PHPUnit\Framework\TestCase;

/**
 * Toast smoke test for Phase 1 / Plan 01-02 Task 3.
 *
 * Reads tickettrade.js and asserts the toast module exposes the
 * documented contract:
 *   - show(message, type) returns a numeric id
 *   - container role upgrades to alert on error/warning
 *   - queue is capped at 3
 */
final class ToastTest extends TestCase
{
    private string $jsContent;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 3);
        $path = $root . '/public/assets/js/tickettrade.js';
        $this->jsContent = (string) file_get_contents($path);
        $this->assertNotEmpty($this->jsContent, 'tickettrade.js must be readable');
    }

    /**
     * Show() returns a numeric id; data-toast-id attribute is set with that id.
     */
    public function test_show_returns_numeric_id(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+show\s*\(/',
            $this->jsContent,
            'tickettrade.js must declare a show() function'
        );

        $this->assertMatchesRegularExpression(
            '/var\s+id\s*=\s*_nextId\+\+/',
            $this->jsContent,
            'show() must increment _nextId'
        );

        $this->assertMatchesRegularExpression(
            '/return\s+id\s*;/',
            $this->jsContent,
            'show() must return id'
        );

        // Pattern: setAttribute('data-toast-id', String(_nextId))
        $pattern = '/setAttribute\(\s*[\'"]data-toast-id[\'"]\s*,\s*String\(_nextId\)/';
        $this->assertMatchesRegularExpression(
            $pattern,
            $this->jsContent,
            'show() must attach data-toast-id with String(_nextId)'
        );
    }

    /**
     * Container role attribute upgrades to 'alert' on error/warning and
     * downgrades to 'status' when no alert toast remains.
     */
    public function test_role_upgrades_on_error_or_warning(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+syncContainerRole\s*\(/',
            $this->jsContent,
            'toast module must declare syncContainerRole()'
        );

        // Container: hasAlert ? 'alert' : 'status'
        $patternContainerTernary = '/hasAlert\s*\?\s*[\'"]alert[\'"]\s*:\s*[\'"]status[\'"]/';
        $this->assertMatchesRegularExpression(
            $patternContainerTernary,
            $this->jsContent,
            'container role must toggle between alert and status via hasAlert'
        );

        // Toast element: isAlert ? 'alert' : 'status'
        $patternToastTernary = '/isAlert\s*\?\s*[\'"]alert[\'"]\s*:\s*[\'"]status[\'"]/';
        $this->assertMatchesRegularExpression(
            $patternToastTernary,
            $this->jsContent,
            'each toast must carry role=alert or role=status based on isAlert'
        );

        // setAttribute('role', ...) is called for both container and each toast
        $patternRoleSet = '/setAttribute\(\s*[\'"]role[\'"]/';
        $matches = preg_match_all($patternRoleSet, $this->jsContent);
        $this->assertGreaterThanOrEqual(
            2,
            $matches,
            'role attribute must be set on the container and on each toast'
        );
    }

    /**
     * The queue is capped at 3.
     */
    public function test_queue_capped_at_three(): void
    {
        $this->assertMatchesRegularExpression(
            '/var\s+QUEUE_CAP\s*=\s*3\s*;/',
            $this->jsContent,
            'toast module must define QUEUE_CAP = 3'
        );

        $this->assertMatchesRegularExpression(
            '/while\s*\(\s*_queue\.length\s*>=\s*QUEUE_CAP\s*\)\s*\{\s*removeEntry\s*\(\s*_queue\[\s*0\s*\]\s*\)/s',
            $this->jsContent,
            'toast queue must be capped at QUEUE_CAP by removing the oldest entry'
        );
    }

    /**
     * WR-004: clearTimer() must clamp remainingMs to a small minimum so
     * a toast whose timer already expired while paused does not re-arm
     * with 0ms (which fires synchronously and races the click handler).
     */
    public function test_clear_timer_clamps_remaining_ms(): void
    {
        $this->assertMatchesRegularExpression(
            '/Math\.max\(\s*50\s*,\s*entry\.expiresAt\s*-\s*Date\.now\(\)\s*\)/',
            $this->jsContent,
            'clearTimer() must clamp remainingMs to Math.max(50, ...) so a paused-then-expired toast does not re-arm with 0ms'
        );
    }
}
