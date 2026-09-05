<?php
/**
 * Phase 3 — ModalRenderTest
 *
 * Verifies the listing_modal View and listing_modal_carousel partial:
 *   - Modal has modal-fullscreen-sm-down + modal-dialog-centered
 *   - Modal carries data-component="listingModal"
 *   - Modal has prev/next nav buttons in the header
 *   - Modal has the close button (X)
 *   - Carousel uses data-bs-ride="false" + data-bs-interval="false"
 *   - Indicators and controls render only when > 1 image
 *   - Modal renders the first listing's content as initial state
 *   - No images → "No images available" message
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase03\Listing;

use App\Listing\Action\BrowseAction;
use App\Support\View;
use App\Tests\Integration\Phase03\Fixtures\Fixtures;

class ModalRenderTest extends Fixtures
{

    public function test_modal_has_fullscreen_and_centered_classes(): void
    {
        $out = $this->renderBoardSeeded();
        $this->assertStringContainsString('modal-fullscreen-sm-down', $out);
        $this->assertStringContainsString('modal-dialog-centered', $out);
    }

    public function test_modal_has_data_component_attribute(): void
    {
        $out = $this->renderBoardSeeded();
        $this->assertStringContainsString('data-component="listingModal"', $out);
    }

    public function test_modal_has_prev_next_buttons(): void
    {
        $out = $this->renderBoardSeeded();
        $this->assertStringContainsString('data-listing-nav="prev"', $out);
        $this->assertStringContainsString('data-listing-nav="next"', $out);
        $this->assertStringContainsString('aria-label="Previous listing"', $out);
        $this->assertStringContainsString('aria-label="Next listing"', $out);
    }

    public function test_modal_has_close_button(): void
    {
        $out = $this->renderBoardSeeded();
        $this->assertStringContainsString('listing-modal__close', $out);
        $this->assertStringContainsString('data-bs-dismiss="modal"', $out);
    }

    public function test_carousel_no_auto_advance(): void
    {
        $out = $this->renderBoardSeeded();
        $this->assertStringContainsString('data-bs-ride="false"', $out);
        $this->assertStringContainsString('data-bs-interval="false"', $out);
    }

    public function test_modal_renders_first_listing_title(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'First listing title');
        $this->seedListing($sellerId, $catId, 'Second listing title');

        $out = $this->renderBoard();
        // The modal's title is the FIRST listing's title
        $this->assertMatchesRegularExpression('/listingModalTitle[^>]*>\s*First listing title/', $out);
    }

    public function test_carousel_indicators_omitted_when_no_images(): void
    {
        $out = $this->renderBoardSeeded();
        // Without images, no carousel-indicators
        $this->assertStringNotContainsString('class="carousel-indicators"', $out);
    }

    public function test_carousel_indicators_rendered_with_multiple_images(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $lid = $this->seedListing($sellerId, $catId, 'Multi image listing');
        // Insert 2 'full' images (the carousel uses /img/{id}/full per D-22)
        $this->pdo->prepare(
            'INSERT INTO listing_images (listing_id, sha256, size, is_primary, sort_order, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, NOW())'
        )->execute([$lid, 'fullImage1', 'full', 1, 1]);
        $this->pdo->prepare(
            'INSERT INTO listing_images (listing_id, sha256, size, is_primary, sort_order, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, NOW())'
        )->execute([$lid, 'fullImage2', 'full', 0, 2]);

        $out = $this->renderBoard();
        $this->assertStringContainsString('class="carousel-indicators"', $out);
        $this->assertStringContainsString('carousel-control-prev', $out);
        $this->assertStringContainsString('carousel-control-next', $out);
    }

    public function test_modal_not_rendered_when_no_listings(): void
    {
        $out = $this->renderBoard();
        $this->assertStringNotContainsString('id="listingModal"', $out);
    }

    public function test_modal_includes_seller_info(): void
    {
        $sellerId = $this->seedUser(['nickname' => 'testseller']);
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Item');

        $out = $this->renderBoard();
        // The modal pre-renders seller info from the first listing
        $this->assertStringContainsString('listing-modal__seller', $out);
        $this->assertStringContainsString('rank-badge', $out);
    }

    public function test_modal_includes_buy_now_cta(): void
    {
        // Phase 4 Plan 04-02: the listing modal's Buy now is now a
        // <form method="POST" action="/listings/{id}/buy"> that the
        // Phase 4 BuyAction handles. The form class is the same
        // (listing-modal__buy) so the CSS contract is preserved.
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $lid = $this->seedListing($sellerId, $catId, 'Item');
        // A different (non-guest) user is needed to see the form;
        // the seller's own listing shows the self-owned badge instead.
        $buyerId = $this->seedUser([
            'email' => 'buyer@students.nsbm.ac.lk',
            'student_id' => 'NSBM/2023/099',
            'nickname' => 'buyer',
        ]);

        $out = $this->renderBoardAsUser($buyerId);
        $this->assertStringContainsString('listing-modal__buy', $out);
        $this->assertStringContainsString('action="/listings/' . $lid . '/buy"', $out);
        $this->assertStringContainsString('method="POST"', $out);
    }

    private function renderBoardAsUser(int $userId): string
    {
        $originalGet = $_GET ?? [];
        $originalUser = $GLOBALS['current_user'] ?? null;
        $_GET = [];
        $GLOBALS['current_user'] = $this->loadUserRow($userId);
        ob_start();
        try {
            $action = new \App\Listing\Action\BrowseAction();
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

    private function loadUserRow(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT user_id, email, nickname, is_admin, is_banned, is_verified, tier '
            . 'FROM users WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        return (array) $stmt->fetch();
    }

    public function test_buy_confirm_modal_rendered_with_scrim_guard_and_submit_target(): void
    {
        // Phase 4 Plan 04-02 ROADMAP #1: Buy Now opens a Bootstrap
        // confirmation modal (data-scrim-guard="2" suppresses backdrop
        // for 2s). The Confirm button targets the buy form by data-buy-form-id;
        // JS in tickettrade.js (ComponentRegistry.register('buyConfirmModal'))
        // submits it. We assert the HTML contract; the JS contract is a
        // data-attribute wiring covered by manual verification per
        // EXPERIENCE.md.
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $lid = $this->seedListing($sellerId, $catId, 'Item');
        $buyerId = $this->seedUser([
            'email' => 'buyer@students.nsbm.ac.lk',
            'student_id' => 'NSBM/2023/099',
            'nickname' => 'buyer',
        ]);

        $out = $this->renderBoardAsUser($buyerId);
        // The button opens the modal (Bootstrap toggle)
        $this->assertStringContainsString('data-bs-toggle="modal"', $out);
        $this->assertStringContainsString('data-bs-target="#buy-confirm-modal-' . $lid . '"', $out);
        // The confirmation modal carries data-scrim-guard="2" (reuses the
        // Phase 1 modalScrimGuard pattern — no new scrim handler).
        $this->assertStringContainsString('id="buy-confirm-modal-' . $lid . '"', $out);
        $this->assertMatchesRegularExpression(
            '/id="buy-confirm-modal-' . $lid . '"[^>]*data-scrim-guard="2"/',
            $out
        );
        // Spec copy
        $this->assertStringContainsString('Confirm purchase?', $out);
        $this->assertStringContainsString(
            'This reserves the item with a digital ticket',
            $out
        );
        $this->assertStringContainsString('(a reservation, not payment).', $out);
        // The Confirm button references the underlying form id so JS can
        // .submit() it on click.
        $this->assertStringContainsString('data-action="buy-confirm"', $out);
        $this->assertStringContainsString('data-buy-form-id="buy-form-' . $lid . '"', $out);
        $this->assertStringContainsString('id="buy-form-' . $lid . '"', $out);
    }

    public function test_modal_includes_report_link(): void
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $this->seedListing($sellerId, $catId, 'Item');

        $out = $this->renderBoard();
        $this->assertStringContainsString('listing-modal__report', $out);
    }

    public function test_listing_modal_view_is_a_valid_file(): void
    {
        $path = APP_ROOT . '/src/Listing/View/listing_modal.php';
        $this->assertFileExists($path);
        $this->assertNotEmpty(file_get_contents($path));
    }

    public function test_listing_modal_carousel_partial_omits_indicators_with_one_image(): void
    {
        $html = $this->renderCarouselPartial(1);
        $this->assertStringNotContainsString('class="carousel-indicators"', $html);
    }

    public function test_listing_modal_carousel_partial_omits_controls_with_one_image(): void
    {
        $html = $this->renderCarouselPartial(1);
        $this->assertStringNotContainsString('carousel-control-prev', $html);
        $this->assertStringNotContainsString('carousel-control-next', $html);
    }

    public function test_listing_modal_carousel_partial_renders_indicators_with_many_images(): void
    {
        $html = $this->renderCarouselPartial(3);
        $this->assertStringContainsString('class="carousel-indicators"', $html);
        $this->assertStringContainsString('carousel-control-prev', $html);
    }

    public function test_listing_modal_partial_renders_no_image_message_when_empty(): void
    {
        $html = $this->renderCarouselPartial(0);
        $this->assertStringContainsString('No images available', $html);
    }

    private function renderCarouselPartial(int $imageCount): string
    {
        $images = [];
        for ($i = 0; $i < $imageCount; $i++) {
            $images[] = ['sha256' => 'sha' . $i, 'size' => 'full'];
        }
        $vars = [
            'listing_id' => 1,
            'title' => 'Test',
            'images' => $images,
            'id_prefix' => 'testCarousel',
        ];
        $GLOBALS['_tt_view_vars'] = $vars;
        ob_start();
        require APP_ROOT . '/src/Support/View/partials/listing_modal_carousel.php';
        return (string) ob_get_clean();
    }

    private function renderBoardSeeded(): string
    {
        $sellerId = $this->seedUser();
        $catId = $this->seedCategory();
        $lid = $this->seedListing($sellerId, $catId, 'Modal test listing');
        // Add a 'full' image so the carousel partial actually renders
        $this->pdo->prepare(
            'INSERT INTO listing_images (listing_id, sha256, size, is_primary, sort_order, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, NOW())'
        )->execute([$lid, 'sha123full', 'full', 1, 1]);
        return $this->renderBoard([]);
    }

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
