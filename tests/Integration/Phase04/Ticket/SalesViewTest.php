<?php
/**
 * Phase 4 — SalesViewTest
 *
 * Verifies the Sales page (Plan 04-02) renders the redemption input
 * form, the per-listing-group cards, and the empty state.
 *
 * The Action is invoked in-process; the rendered HTML is captured
 * via output buffering and inspected.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase04\Ticket;

use App\Tests\Integration\Phase04\Fixtures\Fixtures;
use App\Ticket\Action\SalesAction;

class SalesViewTest extends Fixtures
{
    public function test_renders_redemption_input_form(): void
    {
        $userId = $this->seedUser(['nickname' => 'seller']);
        $out = $this->renderSales($userId);

        // Form posts to /tickets/redeem with the CSRF token.
        $this->assertStringContainsString('action="/tickets/redeem"', $out);
        $this->assertStringContainsString('name="csrf_token"', $out);
        $this->assertStringContainsString('name="ticket_code"', $out);
        $this->assertStringContainsString('>Redeem<', $out);
    }

    public function test_renders_listing_group_with_tickets(): void
    {
        $userId = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($userId, $this->firstCategoryId());
        $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $userId,
            'status' => 'active',
        ]);
        $out = $this->renderSales($userId);

        // Listing title + ticket code block render.
        $this->assertStringContainsString('Test Item', $out);
        $this->assertStringContainsString('data-component="ticket-code-block"', $out);
        $this->assertStringContainsString('Buyer: ', $out);
        $this->assertStringContainsString('@buyer', $out);
    }

    public function test_per_listing_progress_chip_for_service(): void
    {
        $userId = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($userId, $this->firstCategoryId(), [
            'type' => 'service',
            'quantity' => 5,
        ]);
        $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $userId,
            'status' => 'active',
            'session_number' => 2,
            'total_sessions' => 5,
        ]);
        $out = $this->renderSales($userId);

        $this->assertStringContainsString('2/5 sessions confirmed', $out);
    }

    public function test_renders_confirm_next_session_button_for_in_progress_ticket(): void
    {
        $userId = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($userId, $this->firstCategoryId(), [
            'type' => 'service',
            'quantity' => 5,
        ]);
        $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $userId,
            'status' => 'active',
            'session_number' => 1,
            'total_sessions' => 5,
        ]);
        $out = $this->renderSales($userId);

        $this->assertStringContainsString('action="/tickets/', $out);
        $this->assertStringContainsString('/confirm-session"', $out);
        $this->assertStringContainsString('Confirm next session', $out);
    }

    public function test_no_confirm_button_for_completed_ticket(): void
    {
        $userId = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($userId, $this->firstCategoryId(), [
            'type' => 'service',
            'quantity' => 2,
        ]);
        $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $userId,
            'status' => 'redeemed',
            'session_number' => 2,
            'total_sessions' => 2,
        ]);
        $out = $this->renderSales($userId);

        // No confirm-next-session form for a fully-redeemed ticket.
        $this->assertStringNotContainsString('Confirm next session', $out);
    }

    public function test_renders_empty_state_when_no_sales(): void
    {
        $userId = $this->seedUser(['nickname' => 'seller']);
        $out = $this->renderSales($userId);

        $this->assertStringContainsString('No sales yet. Your first sale happens when someone buys one of your listings.', $out);
        $this->assertStringContainsString('View your listings', $out);
    }

    public function test_renders_status_badge_for_each_ticket(): void
    {
        $userId = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($userId, $this->firstCategoryId());
        $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $userId,
            'status' => 'active',
        ]);
        $out = $this->renderSales($userId);

        $this->assertStringContainsString('status-badge status-active', $out);
    }

    /**
     * Render the Sales page by invoking the Action in-process.
     */
    private function renderSales(int $userId): string
    {
        $GLOBALS['current_user'] = $this->loadUserRow($userId);
        $originalGet = $_GET ?? [];
        $_GET = [];
        ob_start();
        try {
            $action = new SalesAction();
            $action->handle();
        } catch (\Throwable $e) {
            ob_end_clean();
            $_GET = $originalGet;
            throw $e;
        }
        $out = (string) ob_get_clean();
        $_GET = $originalGet;
        $GLOBALS['current_user'] = null;
        return $out;
    }
}
