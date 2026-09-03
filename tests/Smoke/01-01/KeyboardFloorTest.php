<?php

declare(strict_types=1);

namespace App\Tests\Smoke\Smoke_01_01;

use PHPUnit\Framework\TestCase;

/**
 * Keyboard navigation floor smoke test for Phase 1 / Plan 01-01 Task 1 (UX-08).
 *
 * Phase 1 surface (static / file-based, no browser):
 *   - Every mockup declares <a class="skip-link" href="#main"> as the
 *     first focusable element in <body>, before <main>
 *   - Every mockup declares <main id="main" tabindex="-1"> as the
 *     skip-link target
 *   - tickettrade.css declares a *:focus-visible rule with a 2px outline
 *     in the primary color
 *   - tickettrade.css declares a.skip-link:focus { ... } (visible-on-focus)
 *
 * Modal focus-trap, ESC, and focus-return-to-trigger behaviors are
 * Phase 3+ concerns; out of scope for Phase 1 verification.
 */
final class KeyboardFloorTest extends TestCase
{
    private string $cssPath;
    private string $cssContent;
    private array $mockups = [
        'board-mobile.html',
        'my-tickets.html',
        'admin-dashboard.html',
    ];

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 3);
        $this->cssPath = $root . '/public/assets/css/tickettrade.css';
        $this->cssContent = (string) file_get_contents($this->cssPath);
        $this->assertNotEmpty($this->cssContent, 'tickettrade.css must be readable');
    }

    /**
     * Every mockup must declare <a class="skip-link" href="#main"> as the
     * first focusable element in <body> (before <main>).
     */
    public function test_each_mockup_has_skip_link_first_focusable(): void
    {
        $root = dirname(__DIR__, 3);

        foreach ($this->mockups as $file) {
            $path = $root . '/public/mockups/' . $file;
            $html = (string) file_get_contents($path);
            $this->assertNotEmpty($html, $file . ' must be readable');

            $this->assertMatchesRegularExpression(
                '/<a\s+class="skip-link"\s+href="#main">/i',
                $html,
                $file . ' must declare a <a class="skip-link" href="#main">'
            );

            $bodyPos = strpos($html, '<body');
            $this->assertNotFalse($bodyPos, $file . ' must contain a <body> tag');

            $bodySection = substr($html, $bodyPos);
            $skipLinkPos = strpos($bodySection, '<a class="skip-link"');
            $this->assertNotFalse(
                $skipLinkPos,
                $file . ' must declare the skip link inside <body>'
            );

            $mainPos = strpos($bodySection, '<main');
            $this->assertNotFalse($mainPos, $file . ' must declare a <main> tag');

            $this->assertLessThan(
                $mainPos,
                $skipLinkPos,
                $file . ' skip link must appear before <main> in document order'
            );
        }
    }

    /**
     * Every mockup must declare <main id="main" tabindex="-1"> as the
     * skip-link target.
     */
    public function test_each_mockup_has_main_target(): void
    {
        $root = dirname(__DIR__, 3);

        foreach ($this->mockups as $file) {
            $path = $root . '/public/mockups/' . $file;
            $html = (string) file_get_contents($path);
            $this->assertNotEmpty($html, $file . ' must be readable');

            $this->assertMatchesRegularExpression(
                '/<main\s+id="main"\s+tabindex="-1">/',
                $html,
                $file . ' must declare <main id="main" tabindex="-1"> as the skip-link target'
            );
        }
    }

    /**
     * tickettrade.css must declare a *:focus-visible rule with a 2px outline
     * in the primary color token.
     */
    public function test_focus_visible_outline_declared(): void
    {
        $this->assertMatchesRegularExpression(
            '/\*:focus-visible\s*\{[^}]*outline\s*:\s*2px\s+solid\s+var\(--color-primary\)/s',
            $this->cssContent,
            'tickettrade.css must declare *:focus-visible { outline: 2px solid var(--color-primary); ... }'
        );
    }

    /**
     * tickettrade.css must declare a.skip-link:focus { ... } so the
     * skip link becomes visible when focused (otherwise the off-screen
     * positioning hides it from sighted keyboard users).
     */
    public function test_skip_link_focus_visible_declared(): void
    {
        $this->assertMatchesRegularExpression(
            '/a\.skip-link:focus\s*\{/',
            $this->cssContent,
            'tickettrade.css must declare a.skip-link:focus { ... } so the skip link becomes visible on focus'
        );
    }
}