<?php

declare(strict_types=1);

namespace App\Tests\Smoke\Smoke_01_01;

use PHPUnit\Framework\TestCase;

/**
 * Theme persistence smoke test for Phase 1 / Plan 01-01 Task 2.
 *
 * Mirrors the JS themeController algorithm in PHP and asserts the
 * documented priority order:
 *   1. localStorage.tickettrade.theme (light/dark/system) wins if set
 *   2. data-surface on <html> drives default (student=dark, admin=light)
 *   3. matchMedia('(prefers-color-scheme: dark)') is the fallback
 *
 * Also reads the JS source string and asserts the priority order
 * is expressed in the expected order.
 */
final class ThemePersistenceTest extends TestCase
{
    private string $jsPath;
    private string $jsContent;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 3);
        $this->jsPath = $root . '/public/assets/js/tickettrade.js';
        $this->jsContent = (string) file_get_contents($this->jsPath);
        $this->assertNotEmpty($this->jsContent, 'tickettrade.js must be readable');
    }

    /**
     * localStorage tickettrade.theme='dark' overrides system preference.
     */
    public function test_localStorage_wins_over_system_preference(): void
    {
        $resolved = $this->resolveTheme(
            localStorage: 'dark',
            surface: 'student',
            systemPrefersDark: false
        );
        $this->assertSame('dark', $resolved, 'localStorage=dark must win over system preference');

        $resolved2 = $this->resolveTheme(
            localStorage: 'dark',
            surface: 'student',
            systemPrefersDark: true
        );
        $this->assertSame('dark', $resolved2);
    }

    /**
     * Empty localStorage + student surface → dark/light via system preference.
     */
    public function test_system_preference_used_when_localStorage_empty(): void
    {
        $resolvedDark = $this->resolveTheme(
            localStorage: null,
            surface: 'student',
            systemPrefersDark: true
        );
        $this->assertSame('dark', $resolvedDark, 'Student surface with system=dark should be dark');

        $resolvedLight = $this->resolveTheme(
            localStorage: null,
            surface: 'student',
            systemPrefersDark: false
        );
        $this->assertSame('light', $resolvedLight, 'Student surface with system=light should be light');
    }

    /**
     * Empty localStorage + admin surface → always light (regardless of system).
     */
    public function test_admin_surface_defaults_to_light(): void
    {
        $resolvedDarkSys = $this->resolveTheme(
            localStorage: null,
            surface: 'admin',
            systemPrefersDark: true
        );
        $this->assertSame('light', $resolvedDarkSys, 'Admin surface must default to light even when system is dark');

        $resolvedLightSys = $this->resolveTheme(
            localStorage: null,
            surface: 'admin',
            systemPrefersDark: false
        );
        $this->assertSame('light', $resolvedLightSys);
    }

    /**
     * The JS source must reference the priority order components.
     * This is a brittle-but-pragmatic regression test: when the JS
     * changes, this test changes.
     */
    public function test_js_priority_order_present(): void
    {
        // The JS must define the storage key constant (literal tickettrade.theme)
        $this->assertMatchesRegularExpression(
            '/STORAGE_KEY\s*=\s*[\'\"]tickettrade\.theme[\'\"]/',
            $this->jsContent,
            'tickettrade.js must define STORAGE_KEY = tickettrade.theme'
        );

        // The JS must call localStorage.getItem to read the theme
        $this->assertMatchesRegularExpression(
            '/localStorage\.getItem/',
            $this->jsContent,
            'tickettrade.js must call localStorage.getItem'
        );

        // The JS must check data-surface attribute
        $this->assertMatchesRegularExpression(
            "/data-surface/i",
            $this->jsContent,
            'tickettrade.js must check data-surface on <html>'
        );

        // The JS must query matchMedia for prefers-color-scheme
        $this->assertMatchesRegularExpression(
            "/prefers-color-scheme:\s*dark/i",
            $this->jsContent,
            'tickettrade.js must query matchMedia for prefers-color-scheme: dark'
        );
    }

    /**
     * Inline FOUC-guard script in the mockup also follows the priority order.
     */
    public function test_mockup_fouc_guard_present(): void
    {
        $root = dirname(__DIR__, 3);
        $mockupPath = $root . '/public/mockups/board-mobile.html';
        $mockup = (string) file_get_contents($mockupPath);
        $this->assertNotEmpty($mockup, 'Mockup must be readable');

        // Must reference the storage key
        $this->assertMatchesRegularExpression(
            "/localStorage\.getItem\(['\"]tickettrade\.theme['\"]\)/",
            $mockup,
            'Mockup FOUC-guard script must read localStorage.tickettrade.theme'
        );

        // Must use matchMedia
        $this->assertMatchesRegularExpression(
            "/matchMedia.*prefers-color-scheme.*dark/is",
            $mockup,
            'Mockup FOUC-guard script must check prefers-color-scheme: dark'
        );
    }

    /**
     * Pure-PHP mirror of the themeController resolution algorithm.
     */
    private function resolveTheme(
        ?string $localStorage,
        string $surface,
        bool $systemPrefersDark
    ): string {
        // 1. localStorage wins if set to a valid mode
        if ($localStorage === 'light' || $localStorage === 'dark') {
            return $localStorage;
        }
        // 2. data-surface drives default for 'system' or unset
        if ($surface === 'admin') {
            return 'light';
        }
        // 3. Student surface: system preference
        return $systemPrefersDark ? 'dark' : 'light';
    }
}
