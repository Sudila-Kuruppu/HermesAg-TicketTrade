<?php
/**
 * Phase 4 — RedemptionFlowTest
 *
 * End-to-end test for the redemption flow.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase04\Ticket;

use App\Tests\Integration\Phase04\Fixtures\Fixtures;

class RedemptionFlowTest extends Fixtures
{
    public function test_redeem_happy_path(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 5]);
        $buyer = $this->seedUser(['nickname' => 'buyer', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
        ]);
        $code = (string) $this->pdo->query('SELECT ticket_code FROM tickets WHERE id = ' . $ticketId)->fetchColumn();
        $result = $this->dispatchAction(
            'App\Ticket\Action\RedeemAction',
            'handlePost',
            $seller,
            [],
            ['ticket_code' => $code]
        );

        $this->assertSame(302, $result['status']);

        $row = $this->pdo->query('SELECT status, redeemed_at FROM tickets WHERE id = ' . $ticketId)->fetch();
        $this->assertSame('redeemed', $row['status']);
        $this->assertNotNull($row['redeemed_at']);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM points_log')->fetchColumn();
        $this->assertSame(2, $count);

        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'ticket.redeemed'")->fetchColumn();
        $this->assertSame(1, $count);

        $buyerRow = $this->pdo->query('SELECT points FROM users WHERE user_id = ' . $buyer)->fetch();
        $sellerRow = $this->pdo->query('SELECT points FROM users WHERE user_id = ' . $seller)->fetch();
        $this->assertSame(10, (int) $buyerRow['points']);
        $this->assertSame(30, (int) $sellerRow['points']);
    }

    public function test_redeem_wrong_code(): void
    {
        $seller = $this->seedUser();
        $result = $this->dispatchAction(
            'App\Ticket\Action\RedeemAction',
            'handlePost',
            $seller,
            [],
            ['ticket_code' => 'TK-XXXX-XXXX-XXXX-XXXX-XXXX']
        );
        $this->assertSame(302, $result['status']);
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
        $this->assertSame(0, $count);
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM points_log')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function test_redeem_wrong_seller(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $otherUser = $this->seedUser(['nickname' => 'other']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
        ]);
        $code = (string) $this->pdo->query('SELECT ticket_code FROM tickets WHERE id = ' . $ticketId)->fetchColumn();
        $result = $this->dispatchAction(
            'App\Ticket\Action\RedeemAction',
            'handlePost',
            $otherUser,
            [],
            ['ticket_code' => $code]
        );
        $this->assertSame(302, $result['status']);
        $row = $this->pdo->query('SELECT status FROM tickets WHERE id = ' . $ticketId)->fetch();
        $this->assertSame('active', $row['status']);
    }

    public function test_redeem_dispute_pending(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'dispute_status' => 'pending',
        ]);
        $code = (string) $this->pdo->query('SELECT ticket_code FROM tickets WHERE id = ' . $ticketId)->fetchColumn();
        $result = $this->dispatchAction(
            'App\Ticket\Action\RedeemAction',
            'handlePost',
            $seller,
            [],
            ['ticket_code' => $code]
        );
        $this->assertSame(302, $result['status']);
        $row = $this->pdo->query('SELECT status FROM tickets WHERE id = ' . $ticketId)->fetch();
        $this->assertSame('active', $row['status']);
    }

    public function test_redeem_normalizes_code_with_whitespace_and_dashes(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 5]);
        $buyer = $this->seedUser(['nickname' => 'buyer', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
        ]);
        $code = (string) $this->pdo->query('SELECT ticket_code FROM tickets WHERE id = ' . $ticketId)->fetchColumn();
        $rawCode = str_replace('-', ' ', strtolower($code));

        $result = $this->dispatchAction(
            'App\Ticket\Action\RedeemAction',
            'handlePost',
            $seller,
            [],
            ['ticket_code' => $rawCode]
        );
        $this->assertSame(302, $result['status']);
        $row = $this->pdo->query('SELECT status FROM tickets WHERE id = ' . $ticketId)->fetch();
        $this->assertSame('redeemed', $row['status']);
    }
}
