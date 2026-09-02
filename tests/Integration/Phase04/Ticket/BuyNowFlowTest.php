<?php
/**
 * Phase 4 — BuyNowFlowTest
 *
 * End-to-end test for the buy-now flow:
 *   1. Buyer logs in (startSessionFor).
 *   2. POST /listings/{id}/buy via BuyAction::handlePost.
 *   3. Assert: ticket inserted, listings.quantity_sold +1, audit row
 *      appended, response status 302.
 *
 * Note: PHP CLI's `header()` is a silent no-op (header() doesn't
 * actually write to the response stream), so we can't capture the
 * Location header. We instead verify the side effects (DB state
 * changes) which are the meaningful contract.
 *
 * Failure paths:
 *   - Self-purchase block: same user as seller returns no ticket insert.
 *   - Sold-out block: no ticket insert.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase04\Ticket;

use App\Tests\Integration\Phase04\Fixtures\Fixtures;

class BuyNowFlowTest extends Fixtures
{
    public function test_buy_now_creates_ticket_and_returns_redirect(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());

        $result = $this->dispatchAction(
            'App\Ticket\Action\BuyAction',
            'handlePost',
            $buyer,
            ['id' => $listingId]
        );

        // Action exits with a 302 status (PHP CLI's header() is a no-op
        // for the actual stream, but http_response_code() captures the
        // intended status code).
        $this->assertSame(302, $result['status']);

        // Ticket inserted.
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
        $this->assertSame(1, $count);

        // listings.quantity_sold incremented.
        $row = $this->pdo->query('SELECT quantity_sold FROM listings WHERE id = ' . $listingId)->fetch();
        $this->assertSame(1, (int) $row['quantity_sold']);

        // audit_log row appended.
        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'ticket.created'")->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function test_buy_now_self_purchase_blocked(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());

        $result = $this->dispatchAction(
            'App\Ticket\Action\BuyAction',
            'handlePost',
            $seller,
            ['id' => $listingId]
        );

        // Action returns 302 (redirect to /board with the error flash).
        $this->assertSame(302, $result['status']);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function test_buy_now_sold_out_blocked(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId(), [
            'quantity' => 1,
            'quantity_sold' => 1,
        ]);

        $result = $this->dispatchAction(
            'App\Ticket\Action\BuyAction',
            'handlePost',
            $buyer,
            ['id' => $listingId]
        );

        $this->assertSame(302, $result['status']);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function test_buy_now_service_ticket_increments_quantity_sold_by_total_sessions(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId(), [
            'type' => 'service',
            'quantity' => 5,
        ]);

        $result = $this->dispatchAction(
            'App\Ticket\Action\BuyAction',
            'handlePost',
            $buyer,
            ['id' => $listingId]
        );

        $this->assertSame(302, $result['status']);

        $row = $this->pdo->query('SELECT quantity_sold FROM listings WHERE id = ' . $listingId)->fetch();
        $this->assertSame(5, (int) $row['quantity_sold']);

        $ticketRow = $this->pdo->query('SELECT total_sessions FROM tickets LIMIT 1')->fetch();
        $this->assertSame(5, (int) $ticketRow['total_sessions']);
    }
}
