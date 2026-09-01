<?php
/**
 * Phase 3 — PaginationTest
 *
 * Verifies the pagination control on /board (D-16):
 *   - 50 cards per page (the per-page cap)
 *   - Prev/1/2/Next renders when more than 1 page
 *   - The top copy is mobile-hidden (d-none d-md-block), bottom is always
 *   - ?page=999 coerces to 1
 *   - ?page=0 coerces to 1
 *   - cat + q preserved in pagination URLs
 *   - 1 page total → no pagination
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase03\Listing;

use App\Listing\Action\BrowseAction;
use App\Tests\Integration\Phase03\Fixtures\Fixtures;

class PaginationTest extends Fixtures
{

    public function test_50_listings_shows_no_pagination(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        for ($i = 0; $i < 50; $i++) {
            $this->seedListing($sellerId, $catId, 'Item ' . $i);
        }
        $out = $this->renderBoard([]);
        $this->assertStringNotContainsString('>Next<', $out);
        $this->assertStringNotContainsString('>Prev<', $out);
    }

    public function test_51_listings_shows_two_pages(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        for ($i = 0; $i < 51; $i++) {
            $this->seedListing($sellerId, $catId, 'Item ' . $i);
        }
        $out = $this->renderBoard([]);
        $this->assertStringContainsString('>1<', $out);
        $this->assertStringContainsString('>2<', $out);
        $this->assertStringContainsString('>Next<', $out);
        $this->assertStringContainsString('>Prev<', $out);
    }

    public function test_page_999_coerces_to_one(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Only item');

        $out = $this->renderBoard(['page' => 999]);
        // Single page means no pagination control
        $this->assertStringNotContainsString('>Next<', $out);
    }

    public function test_page_0_coerces_to_one(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Only item');

        $out = $this->renderBoard(['page' => 0]);
        $this->assertStringNotContainsString('>Next<', $out);
    }

    public function test_top_pagination_is_mobile_hidden(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        for ($i = 0; $i < 60; $i++) {
            $this->seedListing($sellerId, $catId, 'Item ' . $i);
        }
        $out = $this->renderBoard([]);
        // The top pagination nav has the d-none d-md-block classes
        $this->assertStringContainsString('class="board-pagination d-none d-md-block mb-3"', $out);
    }

    public function test_pagination_preserves_q(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        for ($i = 0; $i < 60; $i++) {
            $this->seedListing($sellerId, $catId, 'calculus ' . $i);
        }
        $out = $this->renderBoard(['q' => 'calculus']);
        // Pagination URLs include q=calculus
        $this->assertStringContainsString('q=calculus', $out);
    }

    public function test_pagination_preserves_cat(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        for ($i = 0; $i < 60; $i++) {
            $this->seedListing($sellerId, $catId, 'Item ' . $i);
        }
        $out = $this->renderBoard(['cat' => $catId]);
        $this->assertStringContainsString('cat=' . $catId, $out);
    }

    public function test_pagination_preserves_q_and_cat(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        for ($i = 0; $i < 60; $i++) {
            $this->seedListing($sellerId, $catId, 'phone ' . $i);
        }
        $out = $this->renderBoard(['cat' => $catId, 'q' => 'phone']);
        $this->assertStringContainsString('cat=' . $catId, $out);
        $this->assertStringContainsString('q=phone', $out);
    }

    public function test_pagination_first_page_aria_current(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        for ($i = 0; $i < 60; $i++) {
            $this->seedListing($sellerId, $catId, 'Item ' . $i);
        }
        $out = $this->renderBoard(['page' => 1]);
        $this->assertStringContainsString('aria-current="page"', $out);
    }

    public function test_prev_button_disabled_on_first_page(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        for ($i = 0; $i < 60; $i++) {
            $this->seedListing($sellerId, $catId, 'Item ' . $i);
        }
        $out = $this->renderBoard(['page' => 1]);
        $this->assertMatchesRegularExpression('/page-item disabled[^>]*>\s*<a[^>]*>\s*Prev/', $out);
    }

    public function test_next_button_disabled_on_last_page(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        for ($i = 0; $i < 60; $i++) {
            $this->seedListing($sellerId, $catId, 'Item ' . $i);
        }
        $out = $this->renderBoard(['page' => 2]);
        $this->assertMatchesRegularExpression('/page-item disabled[^>]*>\s*<a[^>]*>\s*Next/', $out);
    }

    public function test_pagination_partial_renders_aria_label(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        for ($i = 0; $i < 60; $i++) {
            $this->seedListing($sellerId, $catId, 'Item ' . $i);
        }
        $out = $this->renderBoard([]);
        $this->assertStringContainsString('aria-label="Page navigation"', $out);
    }

    public function test_pagination_partial_hidden_when_one_page(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Just one item');
        $out = $this->renderBoard([]);
        $this->assertStringNotContainsString('aria-label="Page navigation"', $out);
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
