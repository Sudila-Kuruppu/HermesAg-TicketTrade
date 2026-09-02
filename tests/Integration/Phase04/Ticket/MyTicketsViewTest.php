<?php
/**
 * Phase 4 — MyTicketsViewTest
 *
 * Verifies the My Tickets page (Plan 04-02) renders the 5 tabs,
 * the ticket cards, and the empty state.
 *
 * We render the View by directly invoking MyTicketsAction::handle()
 * with $GLOBALS['current_user'] set, capture the output buffer, and
 * inspect the HTML for required markers.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase04\Ticket;

use App\Support\Csrf;
use App\Tests\Integration\Phase04\Fixtures\Fixtures;
use App\Ticket\Action\MyTicketsAction;

class MyTicketsViewTest extends Fixtures
{
    public function test_renders_five_tabs(): void
    {
        $userId = $this->seedUser(['nickname' => 'buyer']);
        $out = $this->renderMyTickets($userId);

        // 5 tab labels in the nav strip. Each label is followed by a
        // <span class="badge..."> with the count, so we match the
        // tab id anchor instead (more reliable than whitespace).
        $this->assertStringContainsString('id="tab-all"', $out);
        $this->assertStringContainsString('id="tab-active"', $out);
        $this->assertStringContainsString('id="tab-redeemed"', $out);
        $this->assertStringContainsString('id="tab-expired"', $out);
        $this->assertStringContainsString('id="tab-disputed"', $out);
        // And the labels themselves (may have surrounding whitespace).
        $this->assertMatchesRegularExpression('/\bAll\b/', $out);
        $this->assertMatchesRegularExpression('/\bActive\b/', $out);
        $this->assertMatchesRegularExpression('/\bRedeemed\b/', $out);
        $this->assertMatchesRegularExpression('/\bExpired\b/', $out);
        $this->assertMatchesRegularExpression('/\bDisputed\b/', $out);
    }

    public function test_active_tab_has_aria_current(): void
    {
        $userId = $this->seedUser();
        $out = $this->renderMyTickets($userId);

        // The default tab is 'active'; it should carry aria-current="page".
        $this->assertMatchesRegularExpression(
            '/id="tab-active"[^>]*aria-current="page"/',
            $out
        );
    }

    public function test_tab_counts_render_as_badges(): void
    {
        $userId = $this->seedUser(['nickname' => 'buyer']);
        $seller = $this->seedUser(['nickname' => 'seller']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $userId,
            'seller_id' => $seller,
            'status' => 'active',
        ]);
        $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $userId,
            'seller_id' => $seller,
            'status' => 'redeemed',
        ]);
        $out = $this->renderMyTickets($userId);

        // All tab shows 2.
        $this->assertMatchesRegularExpression(
            '/id="tab-all"[^>]*>[^<]*<span[^>]*>2<\/span>/',
            $out
        );
        // Active tab shows 1.
        $this->assertMatchesRegularExpression(
            '/id="tab-active"[^>]*>[^<]*<span[^>]*>1<\/span>/',
            $out
        );
    }

    public function test_renders_ticket_card_with_code_block(): void
    {
        $userId = $this->seedUser(['nickname' => 'buyer']);
        $seller = $this->seedUser(['nickname' => 'seller']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $userId,
            'seller_id' => $seller,
            'status' => 'active',
        ]);
        $code = (string) $this->pdo->query('SELECT ticket_code FROM tickets WHERE id = ' . $ticketId)->fetchColumn();

        $out = $this->renderMyTickets($userId);

        // Card carries data-ticket-id.
        $this->assertStringContainsString('data-ticket-id="' . $ticketId . '"', $out);
        // Ticket code block partial renders the masked default.
        $this->assertStringContainsString('data-component="ticket-code-block"', $out);
        $this->assertStringContainsString('data-code-value="' . $code . '"', $out);
        $this->assertStringContainsString('TK-****-****-****-****-****', $out);
    }

    public function test_renders_status_badge(): void
    {
        $userId = $this->seedUser();
        $seller = $this->seedUser();
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $userId,
            'seller_id' => $seller,
            'status' => 'active',
        ]);
        $out = $this->renderMyTickets($userId);

        $this->assertStringContainsString('class="status-badge status-active"', $out);
    }

    public function test_renders_seller_info_row(): void
    {
        $userId = $this->seedUser();
        $seller = $this->seedUser(['nickname' => 'seller']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $userId,
            'seller_id' => $seller,
            'status' => 'active',
        ]);
        $out = $this->renderMyTickets($userId);

        // Seller nickname + rank badge.
        $this->assertStringContainsString('@seller', $out);
        $this->assertStringContainsString('rank-badge rank-e', $out);
    }

    public function test_renders_session_progress_for_service_tickets(): void
    {
        $userId = $this->seedUser();
        $seller = $this->seedUser();
        $listingId = $this->seedListing($seller, $this->firstCategoryId(), [
            'type' => 'service',
            'quantity' => 5,
        ]);
        $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $userId,
            'seller_id' => $seller,
            'status' => 'active',
            'session_number' => 2,
            'total_sessions' => 5,
        ]);
        $out = $this->renderMyTickets($userId);

        $this->assertStringContainsString('session-progress', $out);
        $this->assertStringContainsString('2/5', $out);
    }

    public function test_renders_dispute_button_when_eligible(): void
    {
        $userId = $this->seedUser();
        $seller = $this->seedUser();
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $userId,
            'seller_id' => $seller,
            'status' => 'active',
            'dispute_status' => 'none',
        ]);
        $out = $this->renderMyTickets($userId);

        $this->assertStringContainsString('data-bs-target="#dispute-modal-', $out);
        $this->assertStringContainsString('>Dispute<', $out);
    }

    public function test_no_dispute_button_when_dispute_pending(): void
    {
        $userId = $this->seedUser();
        $seller = $this->seedUser();
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $userId,
            'seller_id' => $seller,
            'status' => 'disputed',
            'dispute_status' => 'pending',
        ]);
        $out = $this->renderMyTickets($userId, ['tab' => 'disputed']);

        $this->assertStringNotContainsString('data-bs-target="#dispute-modal-', $out);
    }

    public function test_renders_empty_state_when_no_tickets(): void
    {
        $userId = $this->seedUser();
        $out = $this->renderMyTickets($userId);

        // Per agent's Discretion: "No tickets yet. Buy your first item."
        $this->assertStringContainsString('No tickets yet. Buy your first item.', $out);
        $this->assertStringContainsString('Browse Board', $out);
    }

    public function test_filter_by_tab_redeemed(): void
    {
        $userId = $this->seedUser();
        $seller = $this->seedUser();
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $userId,
            'seller_id' => $seller,
            'status' => 'active',
        ]);
        $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $userId,
            'seller_id' => $seller,
            'status' => 'redeemed',
        ]);
        $out = $this->renderMyTickets($userId, ['tab' => 'redeemed']);

        // Only the redeemed ticket is in the list.
        $this->assertStringContainsString('class="status-badge status-redeemed"', $out);
        $this->assertStringNotContainsString('class="status-badge status-active"', $out);
    }

    /**
     * Render the My Tickets page by invoking the Action in-process.
     * We override $GLOBALS['current_user'] + $_GET to drive the Action.
     */
    private function renderMyTickets(int $userId, array $get = []): string
    {
        $GLOBALS['current_user'] = $this->loadUserRow($userId);
        $originalGet = $_GET ?? [];
        $_GET = $get;
        // Bypass session_start for tests
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // No-op
        }
        ob_start();
        try {
            $action = new MyTicketsAction();
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
