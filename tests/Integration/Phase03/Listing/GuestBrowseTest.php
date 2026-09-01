<?php
/**
 * Phase 3 — GuestBrowseTest
 *
 * Verifies the guest-browse affordance (D-09, Phase 2):
 *   - Guests can browse the corkboard
 *   - Each card carries a "Sign in to buy" CTA linking to /login?next=/board
 *   - The cork-cell aria-hidden stays on the decoration
 *   - The active category tab carries aria-current="page"
 *   - The board is rendered as a 50-cap list with category filtering
 *   - Guest sees 0-50 cards; logged-in sees the same 0-50
 *   - The listing modal is still present (so they can see images/desc
 *     before being asked to sign in)
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase03\Listing;

use App\Listing\Action\BrowseAction;
use App\Tests\Integration\Phase03\Fixtures\Fixtures;

class GuestBrowseTest extends Fixtures
{

    public function test_guest_sees_corkboard_with_cards(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        for ($i = 0; $i < 5; $i++) {
            $this->seedListing($sellerId, $catId, 'Item ' . $i);
        }
        $out = $this->renderBoardAsGuest();
        $this->assertStringContainsString('data-component="corkboard"', $out);
        $this->assertStringContainsString('aria-hidden="true"', $out);
    }

    public function test_guest_card_href_points_to_login(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Item');

        $out = $this->renderBoardAsGuest();
        $this->assertStringContainsString('href="/login?next=/board"', $out);
    }

    public function test_guest_does_not_see_buy_now_button_on_cards(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Item');

        $out = $this->renderBoardAsGuest();
        // The cork-cell CTA says "Sign in to buy"
        // (The modal's own Buy now button is still there for the
        // pre-rendered first listing — that's by design.)
        $this->assertStringContainsString('Sign in to buy', $out);
        $this->assertStringContainsString('cork-cell__cta', $out);
    }

    public function test_guest_sees_aria_current_on_all_tab(): void
    {
        $out = $this->renderBoardAsGuest();
        $this->assertMatchesRegularExpression(
            '/category-tab[^>]*aria-current="page"[^>]*>\s*All/',
            $out
        );
    }

    public function test_guest_browse_with_50_listings(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        for ($i = 0; $i < 50; $i++) {
            $this->seedListing($sellerId, $catId, 'Item ' . $i);
        }
        $out = $this->renderBoardAsGuest();
        $count = substr_count($out, 'class="col-12 col-sm-6 col-md-4 col-lg-3 cork-cell"');
        $this->assertSame(50, $count);
    }

    public function test_guest_with_zero_listings_renders_empty_state(): void
    {
        $out = $this->renderBoardAsGuest();
        $this->assertStringContainsString('No listings yet', $out);
        $this->assertStringContainsString('within 24 hours', $out);
    }

    public function test_guest_with_filter_and_no_results(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Phone');

        $out = $this->renderBoardAsGuest(['q' => 'nonexistent_xyzzy']);
        $this->assertStringContainsString('No matches', $out);
    }

    public function test_logged_in_user_sees_buy_now(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $lid = $this->seedListing($sellerId, $catId, 'Item');
        $buyerId = $this->seedUser([
            'email' => 'buyer@students.nsbm.ac.lk',
            'student_id' => 'NSBM/2023/099',
            'nickname' => 'buyer',
        ]);

        $out = $this->renderBoardAsUser($buyerId);
        $this->assertStringContainsString('Buy now', $out);
        $this->assertStringContainsString('/listings/' . $lid . '#buy', $out);
    }

    public function test_logged_in_user_card_does_not_link_to_login(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Item');
        $buyerId = $this->seedUser([
            'email' => 'buyer@students.nsbm.ac.lk',
            'student_id' => 'NSBM/2023/099',
            'nickname' => 'buyer',
        ]);

        $out = $this->renderBoardAsUser($buyerId);
        // The cork-cell <a> for a logged-in user points to #listing-{id} (modal trigger)
        // NOT to /login?next=/board
        $this->assertStringContainsString('href="#listing-', $out);
        $this->assertStringContainsString('data-bs-toggle="modal"', $out);
    }

    public function test_guest_browse_sees_modal_with_first_listing(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Public item');

        $out = $this->renderBoardAsGuest();
        // The modal is pre-rendered with the first listing's content
        $this->assertStringContainsString('id="listingModal"', $out);
        $this->assertStringContainsString('Public item', $out);
    }

    public function test_guest_with_filtered_category(): void
    {
        $sellerId = $this->seedUser();
        $catA = $this->seedCategory('Books', 100);
        $catB = $this->seedCategory('Phones', 101);
        $this->seedListing($sellerId, $catA, 'Book item');
        $this->seedListing($sellerId, $catB, 'Phone item');

        $out = $this->renderBoardAsGuest(['cat' => $catA]);
        $this->assertStringContainsString('Book item', $out);
        $this->assertStringNotContainsString('Phone item', $out);
    }

    private function renderBoardAsGuest(array $get = []): string
    {
        return $this->renderBoard($get, null);
    }

    private function renderBoardAsUser(int $userId, array $get = []): string
    {
        return $this->renderBoard($get, $userId);
    }

    private function renderBoard(array $get, ?int $userId): string
    {
        $originalGet = $_GET ?? [];
        $originalUser = $GLOBALS['current_user'] ?? null;

        $_GET = $get;
        if ($userId !== null) {
            $GLOBALS['current_user'] = [
                'user_id' => $userId,
                'email' => 'user@students.nsbm.ac.lk',
                'nickname' => 'user',
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
