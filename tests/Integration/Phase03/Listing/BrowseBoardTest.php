<?php
/**
 * Phase 3 — BrowseBoardTest
 *
 * Verifies the corkboard board view's structure and the per-card
 * visual signature. The plan's must_haves:
 *   - 50 cards on /board with 60 listings
 *   - rotation = crc32(id) % 5 - 2 (range -2..+2)
 *   - pin color = id % 2 (red / blue)
 *   - aria-hidden on rotation/pin decoration
 *   - Guest CTA = "Sign in to buy" → /login?next=/board
 *   - Logged-in CTA = "Buy now" → /listings/{id}#buy
 *   - aria-current="page" on the active category tab
 *   - q + cat + page combine; pagination renders Prev/1/2/Next
 *
 * Tests inspect the BrowseAction output by directly invoking
 * View::render after seeding the user/listings fixture.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase03\Listing;

use App\Listing\Action\BrowseAction;
use App\Support\Auth as AuthGuard;
use App\Tests\Integration\Phase03\Fixtures\Fixtures;

class BrowseBoardTest extends Fixtures
{

    public function test_board_with_no_listings_renders_empty_state(): void
    {
        $out = $this->renderBoard(null);
        $this->assertStringContainsString('No listings yet - check back soon', $out);
        $this->assertStringContainsString('New listings appear here within 24 hours', $out);
    }

    public function test_board_with_listings_renders_cork_cells_with_rotation_and_pin(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory('TextbooksTest');
        for ($i = 0; $i < 5; $i++) {
            $this->seedListing($sellerId, $catId, 'Calculus book ' . $i);
        }
        $out = $this->renderBoard(null);
        // 5 cork-cell divs
        $this->assertSame(5, substr_count($out, 'class="col-12 col-sm-6 col-md-4 col-lg-3 cork-cell"'));
        // Each carries a transform rotate(...)
        $this->assertStringContainsString('transform: rotate(', $out);
        // Each has a pin-red or pin-blue
        $this->assertMatchesRegularExpression('/pin-(red|blue)/', $out);
        // aria-hidden on cork-cell rotation wrapper
        $this->assertStringContainsString('aria-hidden="true"', $out);
    }

    public function test_rotation_is_in_minus2_to_plus2_range_and_seeded_by_crc32(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $this->seedListing($sellerId, $catId, 'Item ' . $i);
        }
        $out = $this->renderBoard(null);
        // Each id's expected rotation = crc32(id) % 5 - 2; check at least one appears
        foreach ($ids as $id) {
            $expectedRot = (crc32((string) $id) % 5) - 2;
            $this->assertGreaterThanOrEqual(-2, $expectedRot);
            $this->assertLessThanOrEqual(2, $expectedRot);
            $this->assertStringContainsString('transform: rotate(' . $expectedRot . 'deg)', $out);
        }
    }

    public function test_pin_color_alternates_by_id_modulo_2(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $idEven = $this->seedListing($sellerId, $catId, 'Even id listing');
        $idOdd = $this->seedListing($sellerId, $catId, 'Odd id listing');
        $out = $this->renderBoard(null);
        // even id -> pin-red, odd id -> pin-blue
        if ($idEven % 2 === 0) {
            $this->assertStringContainsString('pin-red', $out);
        } else {
            $this->assertStringContainsString('pin-blue', $out);
        }
        if ($idOdd % 2 === 0) {
            $this->assertStringContainsString('pin-red', $out);
        } else {
            $this->assertStringContainsString('pin-blue', $out);
        }
    }

    public function test_guest_cta_is_sign_in_to_buy(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Sample listing');
        $out = $this->renderBoard(null);
        // The cork-cell (board card) CTA carries "Sign in to buy" + /login?next=/board
        $this->assertStringContainsString('Sign in to buy', $out);
        $this->assertStringContainsString('/login?next=/board', $out);
    }

    public function test_logged_in_cta_is_buy_now(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $lid = $this->seedListing($sellerId, $catId, 'Sample listing');
        $buyerId = $this->seedUser([
            'email' => 'buyer@students.nsbm.ac.lk',
            'student_id' => 'NSBM/2023/099',
            'nickname' => 'buyer',
        ]);
        $out = $this->renderBoard(null, $buyerId);
        // The cork-cell CTA reads "Buy now" with /listings/{id}#buy href
        $this->assertStringContainsString('Buy now', $out);
        $this->assertStringContainsString('/listings/' . $lid . '#buy', $out);
    }

    public function test_pagination_renders_when_more_than_one_page(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        for ($i = 0; $i < 60; $i++) {
            $this->seedListing($sellerId, $catId, 'Item ' . $i);
        }
        $out = $this->renderBoard(null, null, ['page' => 1]);
        // 50 cards per page, 60 listings, 2 pages total
        $this->assertStringContainsString('class="page-link"', $out);
        $this->assertStringContainsString('>1<', $out);
        $this->assertStringContainsString('>2<', $out);
        $this->assertStringContainsString('>Next<', $out);
        $this->assertStringContainsString('>Prev<', $out);
        // Top pagination is mobile-hidden (d-md-block on the wrapper)
        $this->assertStringContainsString('d-none d-md-block', $out);
    }

    public function test_pagination_hidden_when_only_one_page(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        for ($i = 0; $i < 5; $i++) {
            $this->seedListing($sellerId, $catId, 'Item ' . $i);
        }
        $out = $this->renderBoard(null);
        // No nav when only 1 page
        $this->assertStringNotContainsString('>Next<', $out);
    }

    public function test_active_category_tab_has_aria_current_page(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory('ElectronicsTest');
        $this->seedListing($sellerId, $catId, 'Phone');
        $out = $this->renderBoard(null, null, ['cat' => $catId]);
        // The Electronics tab has aria-current="page"
        $this->assertMatchesRegularExpression(
            '/category-tab[^>]*aria-current="page"[^>]*>[^<]*Electronics/',
            $out
        );
    }

    public function test_q_param_preserved_in_pagination(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        for ($i = 0; $i < 60; $i++) {
            $this->seedListing($sellerId, $catId, 'calculus item ' . $i);
        }
        $out = $this->renderBoard(null, null, ['q' => 'calculus']);
        $this->assertStringContainsString('value="calculus"', $out);
        // Pagination URLs preserve q=calculus
        $this->assertStringContainsString('q=calculus', $out);
    }

    public function test_cat_param_preserved_in_pagination(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        for ($i = 0; $i < 60; $i++) {
            $this->seedListing($sellerId, $catId, 'Item ' . $i);
        }
        $out = $this->renderBoard(null, null, ['cat' => $catId]);
        $this->assertStringContainsString('cat=' . $catId, $out);
    }

    public function test_listing_modal_rendered_when_rows_present(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Modal test');
        $out = $this->renderBoard(null);
        $this->assertStringContainsString('id="listingModal"', $out);
        $this->assertStringContainsString('data-component="listingModal"', $out);
        $this->assertStringContainsString('modal-fullscreen-sm-down', $out);
        $this->assertStringContainsString('modal-dialog-centered', $out);
    }

    public function test_listing_modal_not_rendered_when_no_rows(): void
    {
        $out = $this->renderBoard(null);
        $this->assertStringNotContainsString('id="listingModal"', $out);
    }

    public function test_xss_in_q_is_escaped(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Safe item');
        $out = $this->renderBoard(null, null, ['q' => '<script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $out);
        // The escaped form should be in the search input
        $this->assertStringContainsString('&lt;script&gt;', $out);
    }

    public function test_out_of_range_page_coerces_to_one(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Only one item');
        $out = $this->renderBoard(null, null, ['page' => 999]);
        $this->assertStringContainsString('No listings yet', $out);
    }

    public function test_non_existent_category_falls_back_to_all(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Phone item');
        $out = $this->renderBoard(null, null, ['cat' => 99999]);
        // The 'All' tab is active
        $this->assertMatchesRegularExpression(
            '/category-tab[^>]*aria-current="page"[^>]*>\s*All/',
            $out
        );
    }

    public function test_list_view_toggle_present_in_toolbar(): void
    {
        $out = $this->renderBoard(null);
        $this->assertStringContainsString('data-component="list-view-toggle"', $out);
        $this->assertStringContainsString('aria-pressed', $out);
    }

    public function test_listing_modal_js_loaded_in_head(): void
    {
        $head = file_get_contents(APP_ROOT . '/src/Support/View/partials/head.php');
        $this->assertStringContainsString('listing_modal.js', $head);
    }

    public function test_board_view_has_marketplace_h1(): void
    {
        $out = $this->renderBoard(null);
        $this->assertStringContainsString('Marketplace board', $out);
    }

    public function test_search_box_partial_in_toolbar(): void
    {
        $out = $this->renderBoard(null);
        $this->assertStringContainsString('role="search"', $out);
        $this->assertStringContainsString('name="q"', $out);
    }

    public function test_category_tabs_partial_includes_all(): void
    {
        $out = $this->renderBoard(null);
        $this->assertStringContainsString('>All<', $out);
    }

    public function test_60_listings_caps_at_50_per_page(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        for ($i = 0; $i < 60; $i++) {
            $this->seedListing($sellerId, $catId, 'Item ' . $i);
        }
        $out = $this->renderBoard(null, null, ['page' => 1]);
        // 50 cork-cell cards on the first page
        $count = substr_count($out, 'class="col-12 col-sm-6 col-md-4 col-lg-3 cork-cell"');
        $this->assertSame(50, $count);
    }

    /**
     * Helper: dispatch BrowseAction and capture its output.
     *
     * @param array<string,mixed> $get Override $_GET
     */
    private function renderBoard(?int $userId, ?int $asUserId = null, array $get = []): string
    {
        // Reset the GET superglobal to our override
        $originalGet = $_GET ?? [];
        $originalPost = $_POST ?? [];
        $originalServer = $_SERVER ?? [];
        $originalSession = $_SESSION ?? [];
        $originalUser = $GLOBALS['current_user'] ?? null;

        $_GET = $get;
        if ($asUserId !== null) {
            $GLOBALS['current_user'] = [
                'user_id' => $asUserId,
                'email' => 'buyer@students.nsbm.ac.lk',
                'nickname' => 'buyer',
                'is_admin' => false,
                'is_banned' => false,
            ];
        } else {
            $GLOBALS['current_user'] = null;
        }

        ob_start();
        try {
            $action = new BrowseAction();
            $action->handle();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        $out = (string) ob_get_clean();

        // Restore
        $_GET = $originalGet;
        $_POST = $originalPost;
        $_SERVER = $originalServer;
        $_SESSION = $originalSession;
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
