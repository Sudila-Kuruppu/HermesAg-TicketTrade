<?php
/**
 * Phase 3 — EdgeCasesTest
 *
 * Phase 3 Plan 03-03 Task 2: Search, pagination, and empty-state edge cases.
 *
 * Verifies:
 *   - XSS in q is escaped in the search input AND in the empty-state copy
 *   - ?page=999 out-of-range coerces to 1 (renders empty-state + no pagination)
 *   - ?page=0 coerces to 1
 *   - Empty q behaves as no filter
 *   - Empty cat behaves as no filter
 *   - Non-existent cat falls back to All
 *   - "No matches" empty state uses the named copy
 *   - "No listings" empty state uses the named copy
 *   - Input parsing coerces page, caps q at 100 chars
 *   - is_numeric is enforced for ?cat
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase03\Listing;

use App\Listing\Action\BrowseAction;
use App\Tests\Integration\Phase03\Fixtures\Fixtures;

class EdgeCasesTest extends Fixtures
{

    public function test_xss_in_q_is_escaped_in_input(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Safe item');

        $out = $this->renderBoard(['q' => '<script>alert(1)</script>']);
        // No <script>alert(1)</script> in output (it's escaped)
        $this->assertStringNotContainsString('<script>alert(1)</script>', $out);
        // The escaped form is in the search input
        $this->assertStringContainsString('&lt;script&gt;', $out);
    }

    public function test_xss_in_q_is_escaped_in_empty_state(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'A real item');

        $out = $this->renderBoard(['q' => '<img src=x onerror=alert(1)>']);
        // The empty-state copy (since no match) has the escaped form
        $this->assertStringContainsString('&lt;img', $out);
        $this->assertStringNotContainsString('<img src=x', $out);
    }

    public function test_empty_q_behaves_as_no_filter(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Item A');
        $this->seedListing($sellerId, $catId, 'Item B');

        $out = $this->renderBoard(['q' => '']);
        $this->assertStringContainsString('Item A', $out);
        $this->assertStringContainsString('Item B', $out);
    }

    public function test_empty_cat_behaves_as_no_filter(): void
    {
        $sellerId = $this->seedUser();
        $catA = $this->seedCategory('Books', 200);
        $catB = $this->seedCategory('Phones', 201);
        $this->seedListing($sellerId, $catA, 'Book item');
        $this->seedListing($sellerId, $catB, 'Phone item');

        $out = $this->renderBoard(['cat' => '']);
        $this->assertStringContainsString('Book item', $out);
        $this->assertStringContainsString('Phone item', $out);
    }

    public function test_page_999_coerces_to_first_page(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        for ($i = 0; $i < 60; $i++) {
            $this->seedListing($sellerId, $catId, 'Item ' . $i);
        }
        $out = $this->renderBoard(['page' => 999]);
        // page=999 coerces to 1, which shows the FIRST 50 items (created last).
        // The pagination control is rendered (60 items / 50 per page = 2 pages).
        $this->assertStringContainsString('aria-label="Page navigation"', $out);
        // The active page indicator is on "1", not "999"
        $this->assertMatchesRegularExpression('/page-item active[^>]*aria-current="page"[^>]*>\s*<a[^>]*>\s*1\s*</', $out);
        // The Prev link is disabled (we are on page 1)
        $this->assertMatchesRegularExpression('/page-item disabled[^>]*>\s*<a[^>]*>\s*Prev/', $out);
    }

    public function test_page_beyond_range_with_no_listings_renders_empty_state(): void
    {
        $out = $this->renderBoard(['page' => 999]);
        // No listings at all → empty state
        $this->assertStringContainsString('No listings yet', $out);
    }

    public function test_page_0_coerces_to_1(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Only item');

        $out = $this->renderBoard(['page' => 0]);
        $this->assertStringNotContainsString('>Next<', $out);
        $this->assertStringContainsString('Only item', $out);
    }

    public function test_page_negative_coerces_to_1(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Item');

        $out = $this->renderBoard(['page' => -5]);
        $this->assertStringContainsString('Item', $out);
        $this->assertStringNotContainsString('>Next<', $out);
    }

    public function test_non_numeric_cat_falls_back_to_all(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Item');

        $out = $this->renderBoard(['cat' => 'abc']);
        $this->assertStringContainsString('Item', $out);
        // The 'All' tab is active
        $this->assertMatchesRegularExpression(
            '/category-tab[^>]*aria-current="page"[^>]*>\s*All/',
            $out
        );
    }

    public function test_non_existent_cat_falls_back_to_all(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Item');

        $out = $this->renderBoard(['cat' => 99999]);
        $this->assertStringContainsString('Item', $out);
        $this->assertMatchesRegularExpression(
            '/category-tab[^>]*aria-current="page"[^>]*>\s*All/',
            $out
        );
    }

    public function test_no_listings_empty_state_uses_named_copy(): void
    {
        $out = $this->renderBoard([]);
        $this->assertStringContainsString('No listings yet - check back soon', $out);
        $this->assertStringContainsString('within 24 hours', $out);
    }

    public function test_no_matches_empty_state_uses_named_copy(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Real item');

        $out = $this->renderBoard(['q' => 'nonexistent_xyzzy']);
        $this->assertStringContainsString('No matches', $out);
    }

    public function test_q_capped_at_100_chars_in_input(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Item');

        $longQ = str_repeat('a', 200);
        $out = $this->renderBoard(['q' => $longQ]);
        // The input value should be at most 100 chars
        // Look for value="aaa...aaa" with no more than 100 a's
        if (preg_match('/name="q"[^>]*value="([^"]*)"/', $out, $m)) {
            $this->assertLessThanOrEqual(100, strlen($m[1]));
        }
    }

    public function test_q_capped_at_100_chars_in_empty_state(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Item');

        $longQ = str_repeat('z', 200);
        $out = $this->renderBoard(['q' => $longQ]);
        // The empty-state copy shows the truncated query
        $matches = [];
        $this->assertStringContainsString('No matches', $out);
        // The query in the empty-state copy is truncated to <=100 chars
        $this->assertSame(1, preg_match('/No matches for &quot;([^&]*)&quot;/', $out, $matches));
        $this->assertLessThanOrEqual(100, strlen($matches[1]));
        $this->assertGreaterThanOrEqual(50, strlen($matches[1]));
    }

    public function test_view_does_not_throw_on_invalid_input(): void
    {
        // Various invalid inputs should not error
        $invalidInputs = [
            ['q' => null],
            ['cat' => null],
            ['page' => null],
            ['q' => '', 'cat' => '', 'page' => ''],
            ['q' => str_repeat('x', 1000)],
            ['page' => 'abc'],
            ['cat' => 'abc'],
        ];
        foreach ($invalidInputs as $get) {
            $out = $this->renderBoard($get);
            // Just verify it doesn't error
            $this->assertIsString($out);
            $this->assertStringContainsString('<html', $out);
        }
    }

    public function test_search_input_preserves_user_typed_text(): void
    {
        $out = $this->renderBoard(['q' => 'hello world']);
        $this->assertStringContainsString('value="hello world"', $out);
    }

    public function test_empty_state_for_filtered_no_match_includes_q(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Item');

        $out = $this->renderBoard(['q' => 'unicorn']);
        $this->assertStringContainsString('No matches', $out);
        $this->assertStringContainsString('unicorn', $out);
    }

    public function test_empty_state_for_filtered_no_match_includes_cat(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory('Books', 200);
        $this->seedListing($sellerId, $catId, 'Item');

        $out = $this->renderBoard(['q' => 'unicorn', 'cat' => $catId]);
        $this->assertStringContainsString('No matches', $out);
        $this->assertStringContainsString('Books', $out);
    }

    public function test_pagination_hidden_when_only_one_page(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        for ($i = 0; $i < 5; $i++) {
            $this->seedListing($sellerId, $catId, 'Item ' . $i);
        }
        $out = $this->renderBoard([]);
        $this->assertStringNotContainsString('aria-label="Page navigation"', $out);
    }

    public function test_search_results_xss_prevented_in_carousel_alt(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $title = '<script>alert(1)</script>Safe';
        $this->seedListing($sellerId, $catId, $title);

        $out = $this->renderBoard([]);
        // The title is HTML-escaped in the listing card
        $this->assertStringNotContainsString('<script>alert(1)</script>Safe', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
    }

    private function renderBoard(array $get): string
    {
        $originalGet = $_GET ?? [];
        $originalUser = $GLOBALS['current_user'] ?? null;

        $_GET = $get;
        $GLOBALS['current_user'] = null;

        ob_start();
        try {
            $action = new BrowseAction();
            $action->handle();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        $out = (string) ob_get_clean();

        $_GET = $originalGet;
        $GLOBALS['current_user'] = $originalUser;

        return $out;
    }

    private function seedListing(int $sellerId, int $catId, string $title): int
    {
        $now = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO listings (seller_id, category_id, title, description, price_cents, type, '
            . '`condition`, quantity, quantity_sold, status, approved_at, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, \'active\', NOW(), ?, ?)'
        );
        $stmt->execute([$sellerId, $catId, $title, 'A test description.', 100000, 'product', 'like_new', 1, $now, $now]);
        return (int) $this->pdo->lastInsertId();
    }
}
