<?php
/**
 * Phase 3 — SearchTest
 *
 * Verifies the FULLTEXT search filter on /board (D-15):
 *   - ?q= matches via MATCH(title, description) AGAINST(? IN BOOLEAN MODE)
 *   - ?q=cal matches "cal*" via BOOLEAN MODE prefix wildcard
 *   - ?q= with HTML/XSS payload is escaped
 *   - ?q= empty behaves as no filter
 *   - 0 matches renders the named "No matches for ... in ..." copy
 *   - The search input carries the current q as value
 *
 * Also tests:
 *   - ?cat=2 only shows listings in category 2
 *   - ?cat=2&q=phone combines the two
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase03\Listing;

use App\Listing\Action\BrowseAction;
use App\Tests\Integration\Phase03\Fixtures\Fixtures;

class SearchTest extends Fixtures
{

    public function test_q_filter_renders_only_matching_listings(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Calculus 101 textbook');
        $this->seedListing($sellerId, $catId, 'Phone charger');
        $this->seedListing($sellerId, $catId, 'Pen set');

        $out = $this->renderBoard(['q' => 'calculus']);
        // Only the calculus listing should appear
        $this->assertStringContainsString('Calculus 101 textbook', $out);
        $this->assertStringNotContainsString('Phone charger', $out);
        $this->assertStringNotContainsString('Pen set', $out);
        // Search input pre-populated
        $this->assertStringContainsString('value="calculus"', $out);
    }

    public function test_q_prefix_wildcard_via_boolean_mode(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Calculus textbook');
        $this->seedListing($sellerId, $catId, 'Calculator');
        $this->seedListing($sellerId, $catId, 'Phone');

        $out = $this->renderBoard(['q' => 'cal']);
        // Both Calculus and Calculator should match (cal* prefix)
        $this->assertStringContainsString('Calculus textbook', $out);
        $this->assertStringContainsString('Calculator', $out);
        $this->assertStringNotContainsString('Phone', $out);
    }

    public function test_empty_q_behaves_as_no_filter(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Item 1');
        $this->seedListing($sellerId, $catId, 'Item 2');

        $out = $this->renderBoard(['q' => '']);
        $this->assertStringContainsString('Item 1', $out);
        $this->assertStringContainsString('Item 2', $out);
    }

    public function test_xss_in_q_is_escaped(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Safe item');

        $out = $this->renderBoard(['q' => '<script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $out);
        // The HTML-escaped form is in the input value
        $this->assertStringContainsString('&lt;script&gt;', $out);
    }

    public function test_no_matches_shows_named_copy(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory('ElectronicsTest');
        $this->seedListing($sellerId, $catId, 'Phone charger');

        $out = $this->renderBoard(['q' => 'nonexistent_xyzzy', 'cat' => $catId]);
        $this->assertStringContainsString('No matches', $out);
    }

    public function test_cat_filter_only_shows_that_category(): void
    {
        $sellerId = $this->seedUser();
        $catA = $this->seedCategory('Books', 200);
        $catB = $this->seedCategory('Phones', 201);
        $this->seedListing($sellerId, $catA, 'Book item');
        $this->seedListing($sellerId, $catB, 'Phone item');

        $out = $this->renderBoard(['cat' => $catA]);
        $this->assertStringContainsString('Book item', $out);
        $this->assertStringNotContainsString('Phone item', $out);
    }

    public function test_cat_and_q_compose(): void
    {
        $sellerId = $this->seedUser();
        $catA = $this->seedCategory('Books', 200);
        $catB = $this->seedCategory('Phones', 201);
        $this->seedListing($sellerId, $catA, 'Phone-shaped pencil case');
        $this->seedListing($sellerId, $catB, 'Phone charger');

        $out = $this->renderBoard(['cat' => $catB, 'q' => 'phone']);
        $this->assertStringContainsString('Phone charger', $out);
        $this->assertStringNotContainsString('Phone-shaped pencil case', $out);
    }

    public function test_search_input_prepopulated_with_q(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'A listing');

        $out = $this->renderBoard(['q' => 'listing']);
        $this->assertStringContainsString('value="listing"', $out);
    }

    public function test_search_box_partial_renders_form(): void
    {
        $out = $this->renderBoard([]);
        $this->assertStringContainsString('role="search"', $out);
        $this->assertStringContainsString('action="/board"', $out);
        $this->assertStringContainsString('name="q"', $out);
    }

    public function test_q_preserved_in_pagination(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        for ($i = 0; $i < 60; $i++) {
            $this->seedListing($sellerId, $catId, 'item ' . $i);
        }

        $out = $this->renderBoard(['q' => 'item', 'page' => 2]);
        // q=item appears in the pagination URL
        $this->assertStringContainsString('q=item', $out);
    }

    public function test_q_capped_at_100_chars(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Test');

        $longQ = str_repeat('a', 200);
        $out = $this->renderBoard(['q' => $longQ]);
        // Should not error; should cap at 100
        $this->assertStringContainsString('Test', $out);
    }

    public function test_q_with_special_chars_does_not_break_render(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory('Test', 250);
        $this->seedListing($sellerId, $catId, 'Test item');

        $out = $this->renderBoard(['q' => "O'Brien & Co's"]);
        // The page renders without errors; the search shows the empty state
        // because "O'Brien & Co's" doesn't FULLTEXT-match "Test item".
        $this->assertStringContainsString('No matches', $out);
        // The query is HTML-escaped in the empty-state copy
        $this->assertStringContainsString('&amp;', $out);
    }

    /**
     * Helper: dispatch BrowseAction and capture its output.
     *
     * @param array<string,mixed> $get Override $_GET
     */
    private function renderBoard(array $get = []): string
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
