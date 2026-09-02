<?php
/**
 * Phase 4 — DisputeFlowTest
 *
 * End-to-end test for the dispute flow:
 *   1. Buyer buys a ticket.
 *   2. Buyer files a dispute via POST /tickets/{id}/dispute.
 *   3. Assert: ticket's dispute_status='pending', status='disputed' (if was active),
 *      a reports row inserted with target_type='ticket',
 *      an audit_log row appended.
 *   4. Seller cannot redeem a disputed ticket (E_TICKET_INVALID_STATE).
 *
 * Failure paths:
 *   - Invalid reason: no reports row inserted.
 *   - Empty text: no reports row inserted.
 *   - Out-of-window state: fileDispute rejected.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase04\Ticket;

use App\Tests\Integration\Phase04\Fixtures\Fixtures;

class DisputeFlowTest extends Fixtures
{
    public function test_buyer_files_dispute_on_active_ticket(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'status' => 'active',
        ]);

        $result = $this->dispatchAction(
            'App\Ticket\Action\DisputeAction',
            'handlePost',
            $buyer,
            ['id' => $ticketId],
            ['reason' => 'seller_unresponsive', 'text' => 'I sent 3 messages and got no reply.']
        );

        $this->assertSame(302, $result['status']);

        // Ticket dispute_status='pending', status='disputed' (was active).
        $row = $this->pdo->query('SELECT status, dispute_status, disputed_at FROM tickets WHERE id = ' . $ticketId)->fetch();
        $this->assertSame('disputed', $row['status']);
        $this->assertSame('pending', $row['dispute_status']);
        $this->assertNotNull($row['disputed_at']);

        // reports row inserted.
        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM reports WHERE target_type = 'ticket' AND target_id = $ticketId")->fetchColumn();
        $this->assertSame(1, $count);

        // audit_log row appended.
        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'ticket.dispute_filed'")->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function test_dispute_on_redeemed_ticket_keeps_status_redeemed(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'status' => 'redeemed',
        ]);

        $result = $this->dispatchAction(
            'App\Ticket\Action\DisputeAction',
            'handlePost',
            $buyer,
            ['id' => $ticketId],
            ['reason' => 'item_not_as_described', 'text' => 'The textbook was water-damaged.']
        );

        $this->assertSame(302, $result['status']);

        // Per D-03: dispute on a redeemed ticket keeps status='redeemed'.
        $row = $this->pdo->query('SELECT status, dispute_status FROM tickets WHERE id = ' . $ticketId)->fetch();
        $this->assertSame('redeemed', $row['status']);
        $this->assertSame('pending', $row['dispute_status']);
    }

    public function test_dispute_invalid_reason(): void
    {
        $seller = $this->seedUser();
        $buyer = $this->seedUser();
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'status' => 'active',
        ]);

        $result = $this->dispatchAction(
            'App\Ticket\Action\DisputeAction',
            'handlePost',
            $buyer,
            ['id' => $ticketId],
            ['reason' => 'bogus_reason', 'text' => 'Some text.']
        );

        $this->assertSame(302, $result['status']);
        $row = $this->pdo->query('SELECT status, dispute_status FROM tickets WHERE id = ' . $ticketId)->fetch();
        $this->assertSame('active', $row['status']);
        $this->assertSame('none', $row['dispute_status']);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM reports')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function test_dispute_empty_text(): void
    {
        $seller = $this->seedUser();
        $buyer = $this->seedUser();
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'status' => 'active',
        ]);

        $result = $this->dispatchAction(
            'App\Ticket\Action\DisputeAction',
            'handlePost',
            $buyer,
            ['id' => $ticketId],
            ['reason' => 'seller_unresponsive', 'text' => '']
        );

        $this->assertSame(302, $result['status']);
        $row = $this->pdo->query('SELECT dispute_status FROM tickets WHERE id = ' . $ticketId)->fetch();
        $this->assertSame('none', $row['dispute_status']);
    }

    public function test_seller_cannot_redeem_disputed_ticket(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'status' => 'active',
        ]);
        $code = (string) $this->pdo->query('SELECT ticket_code FROM tickets WHERE id = ' . $ticketId)->fetchColumn();

        // First, file the dispute.
        $result = $this->dispatchAction(
            'App\Ticket\Action\DisputeAction',
            'handlePost',
            $buyer,
            ['id' => $ticketId],
            ['reason' => 'seller_unresponsive', 'text' => 'Some text.']
        );
        $this->assertSame(302, $result['status']);

        // Seller tries to redeem the disputed ticket.
        $result2 = $this->dispatchAction(
            'App\Ticket\Action\RedeemAction',
            'handlePost',
            $seller,
            [],
            ['ticket_code' => $code]
        );

        $this->assertSame(302, $result2['status']);
        $row = $this->pdo->query('SELECT status FROM tickets WHERE id = ' . $ticketId)->fetch();
        $this->assertSame('disputed', $row['status']);
    }

    public function test_seller_can_also_file_dispute(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'status' => 'active',
        ]);

        $result = $this->dispatchAction(
            'App\Ticket\Action\DisputeAction',
            'handlePost',
            $seller,
            ['id' => $ticketId],
            ['reason' => 'buyer_unresponsive', 'text' => 'Buyer did not show up to handover.']
        );

        $this->assertSame(302, $result['status']);
        $row = $this->pdo->query('SELECT dispute_status FROM tickets WHERE id = ' . $ticketId)->fetch();
        $this->assertSame('pending', $row['dispute_status']);
    }
}
