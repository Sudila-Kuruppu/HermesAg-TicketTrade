<?php

declare(strict_types=1);

namespace App\Tests\Smoke\Smoke_01_02;

use PHPUnit\Framework\TestCase;

/**
 * Bottom-nav smoke test for Phase 1 / Plan 01-02 Task 3.
 *
 * Reads the bottom-nav partial and the three mockups. Asserts:
 *   - the partial renders exactly 5 nav items
 *   - board-mobile and my-tickets mark exactly 1 item with aria-current="page"
 *   - admin-dashboard marks NO item with aria-current="page"
 */
final class BottomNavTest extends TestCase
{
    private string $partialPath;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 3);
        $this->partialPath = $root . '/public/mockups/_partials/bottom-nav.html';
    }

    /**
     * Helper: extract every <a ...> tag with class="bottom-nav__item", assert exactly one
     * carries aria-current="page", and its href basename matches $expectedBasename.
     */
    private function assertActiveItemMatchesBasename(string $html, string $expectedBasename, string $label): void
    {
        // Count aria-current="page" occurrences in the whole HTML (should be exactly 1 in mockups)
        $allMatches = preg_match_all('/aria-current="page"/', $html);
        $this->assertSame(1, $allMatches, $label . ' must mark exactly 1 item with aria-current="page"');

        // Extract every bottom-nav__item anchor
        preg_match_all('/<a\b[^>]*class="bottom-nav__item"[^>]*>/', $html, $anchors);
        $activeFound = false;
        foreach ($anchors[0] as $anchor) {
            if (strpos($anchor, 'aria-current="page"') !== false) {
                $activeFound = true;
                // Extract the href attribute
                preg_match('/href="([^"]*)"/', $anchor, $hrefMatch);
                $href = $hrefMatch[1] ?? '';
                // Compare basename
                $hrefBasename = basename($href);
                $this->assertSame(
                    $expectedBasename,
                    $hrefBasename,
                    $label . ' active item href basename must match ' . $expectedBasename . ', got ' . $hrefBasename
                );
            }
        }
        $this->assertTrue($activeFound, $label . ' must contain at least one active bottom-nav__item');
    }

    /**
     * The partial declares exactly 5 nav items.
     */
    public function test_five_items_rendered(): void
    {
        $partial = (string) file_get_contents($this->partialPath);
        $this->assertNotEmpty($partial, 'bottom-nav.html must be readable');

        // Count occurrences of <a class="bottom-nav__item"
        preg_match_all('/<a\s+class="bottom-nav__item"/', $partial, $matches);
        $count = count($matches[0]);
        $this->assertSame(5, $count, sprintf('bottom-nav.html must render 5 items, got %d', $count));
    }

    /**
     * Active item contract:
     *   - board-mobile: 1 item with aria-current="page", href matches basename
     *   - my-tickets:   1 item with aria-current="page", href matches basename
     *   - admin-dashboard: 0 items with aria-current="page"
     */
    public function test_active_item_aria_current_contract(): void
    {
        $root = dirname(__DIR__, 3);

        // board-mobile: Board should be active
        $boardContent = (string) file_get_contents($root . '/public/mockups/board-mobile.html');
        $this->assertActiveItemMatchesBasename($boardContent, 'board-mobile.html', 'board-mobile.html');

        // my-tickets: My Tickets should be active
        $ticketsContent = (string) file_get_contents($root . '/public/mockups/my-tickets.html');
        $this->assertActiveItemMatchesBasename($ticketsContent, 'my-tickets.html', 'my-tickets.html');

        // admin-dashboard: NO item active (admin has no student bottom nav active)
        // We extract only the bottom-nav block and assert no aria-current inside it.
        $adminContent = (string) file_get_contents($root . '/public/mockups/admin-dashboard.html');
        preg_match_all('/<nav[^>]*data-component="bottom-nav"[\s\S]*?<\/nav>/', $adminContent, $navMatches);
        $navBlock = $navMatches[0][0] ?? '';
        preg_match_all('/aria-current="page"/', $navBlock, $adminNavMatches);
        $this->assertSame(0, count($adminNavMatches[0]), 'admin-dashboard bottom-nav must not mark any item active');
    }
}
