<?php

declare(strict_types=1);

namespace App\Tests\Smoke\Smoke_01_02;

use PHPUnit\Framework\TestCase;

/**
 * Skeleton loading smoke test for Phase 1 / Plan 01-02 Task 1 (UX-02).
 *
 * Asserts the static contract for the skeleton shimmer surface:
 *   - .skeleton class declared in components.css
 *   - @keyframes skeleton-shimmer declared (1s shimmer per UX-02)
 *   - prefers-reduced-motion: reduce disables the animation
 *   - .reduce-motion class gate also disables the animation
 *   - tickettrade.js registers the skeleton module on [data-skeleton]
 *   - _partials/skeleton-card.html declares 3 shimmer rows inside .skeleton-card
 */
final class SkeletonTest extends TestCase
{
    private string $componentsPath;
    private string $jsPath;
    private string $partialPath;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 3);
        $this->componentsPath = $root . '/public/assets/css/tickettrade.components.css';
        $this->jsPath = $root . '/public/assets/js/tickettrade.js';
        $this->partialPath = $root . '/public/mockups/_partials/skeleton-card.html';
    }

    /**
     * The .skeleton class and @keyframes skeleton-shimmer must be declared.
     */
    public function test_skeleton_class_and_keyframes_declared(): void
    {
        $css = (string) file_get_contents($this->componentsPath);
        $this->assertNotEmpty($css, 'tickettrade.components.css must be readable');

        $this->assertMatchesRegularExpression(
            '/\.skeleton\s*,?\s*\n?\s*\[data-skeleton\]/s',
            $css,
            'components.css must declare a .skeleton / [data-skeleton] selector'
        );

        $this->assertMatchesRegularExpression(
            '/@keyframes\s+skeleton-shimmer\s*\{/',
            $css,
            'components.css must declare the @keyframes skeleton-shimmer rule'
        );
    }

    /**
     * The shimmer animation must be disabled under prefers-reduced-motion.
     */
    public function test_reduced_motion_disables_animation(): void
    {
        $css = (string) file_get_contents($this->componentsPath);
        $this->assertNotEmpty($css);

        $this->assertMatchesRegularExpression(
            '/@media\s*\(\s*prefers-reduced-motion\s*:\s*reduce\s*\)\s*\{[^}]*\.skeleton[^{]*\{[^}]*animation\s*:\s*none/s',
            $css,
            'components.css must declare animation: none inside @media (prefers-reduced-motion: reduce) for .skeleton'
        );

        $this->assertStringContainsString(
            '.reduce-motion',
            $css,
            'components.css must also gate the animation behind the runtime .reduce-motion class'
        );
    }

    /**
     * The JS bundle must register the skeleton module via [data-skeleton] selector.
     */
    public function test_js_registers_skeleton_on_data_skeleton(): void
    {
        $js = (string) file_get_contents($this->jsPath);
        $this->assertNotEmpty($js, 'tickettrade.js must be readable');

        $this->assertMatchesRegularExpression(
            '/ComponentRegistry\.register\s*\(\s*[\'"]skeleton[\'"]/',
            $js,
            'tickettrade.js must register the skeleton module via ComponentRegistry'
        );

        $this->assertStringContainsString(
            '[data-skeleton]',
            $js,
            'tickettrade.js must query the [data-skeleton] selector when registering the skeleton module'
        );
    }

    /**
     * _partials/skeleton-card.html must declare 3 shimmer rows inside .skeleton-card.
     */
    public function test_skeleton_card_partial_structure(): void
    {
        $partial = (string) file_get_contents($this->partialPath);
        $this->assertNotEmpty($partial, 'skeleton-card.html must be readable');

        $this->assertStringContainsString(
            'skeleton-card',
            $partial,
            'skeleton-card.html must declare the .skeleton-card wrapper class'
        );

        preg_match_all('/<div\s+class="skeleton"/', $partial, $matches);
        $this->assertGreaterThanOrEqual(
            3,
            count($matches[0]),
            sprintf('skeleton-card.html must contain at least 3 .skeleton shimmer rows, got %d', count($matches[0]))
        );
    }
}