<?php
/**
 * Phase 4 — SessionConfirmFlowTest
 *
 * End-to-end test for the per-session service handover:
 *   1. Buyer buys a 5-session tutoring ticket.
 *   2. Seller confirms session 1 (intermediate) - no points.
 *   3. ... session 2, 3, 4 (intermediate) - no points.
 *   4. Seller confirms session 5 (final) - points awarded, ticket auto-redeemed.
 *
 * Verifies audit_log rows with is_final flag and the final session
 * awards points + bumps redeemed_count.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase04\Ticket;

use App\Tests\Integration\Phase04\Fixtures\Fixtures;

class SessionConfirmFlowTest extends Fixtures
{
    public function test_intermediate_session_increments_no_points(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId(), [
            'type' => 'service',
            'quantity' => 3,
        ]);
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'session_number' => 1,
            'total_sessions' => 3,
        ]);

        $result = $this->dispatchAction(
            'App\Ticket\Action\ConfirmSessionAction',
            'handlePost',
            $seller,
            ['id' => $ticketId]
        );

        $this->assertSame(302, $result['status']);
        $row = $this->pdo->query('SELECT status, session_number FROM tickets WHERE id = ' . $ticketId)->fetch();
        $this->assertSame('active', $row['status']);
        $this->assertSame(2, (int) $row['session_number']);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM points_log')->fetchColumn();
        $this->assertSame(0, $count);

        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'ticket.session_confirmed'")->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function test_final_session_redeems_and_awards_points(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 5]);
        $buyer = $this->seedUser(['nickname' => 'buyer', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($seller, $this->firstCategoryId(), [
            'type' => 'service',
            'quantity' => 2,
        ]);
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'session_number' => 1,
            'total_sessions' => 2,
        ]);

        $result = $this->dispatchAction(
            'App\Ticket\Action\ConfirmSessionAction',
            'handlePost',
            $seller,
            ['id' => $ticketId]
        );

        $this->assertSame(302, $result['status']);
        $row = $this->pdo->query('SELECT status, session_number, redeemed_at FROM tickets WHERE id = ' . $ticketId)->fetch();
        $this->assertSame('redeemed', $row['status']);
        $this->assertSame(2, (int) $row['session_number']);
        $this->assertNotNull($row['redeemed_at']);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM points_log')->fetchColumn();
        $this->assertSame(2, $count);

        $b = (int) $this->pdo->query('SELECT redeemed_count FROM users WHERE user_id = ' . $buyer)->fetchColumn();
        $this->assertSame(6, $b);
    }

    public function test_full_lifecycle_5_sessions(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 5]);
        $buyer = $this->seedUser(['nickname' => 'buyer', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($seller, $this->firstCategoryId(), [
            'type' => 'service',
            'quantity' => 5,
        ]);
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'session_number' => 1,
            'total_sessions' => 5,
        ]);

        // 4 confirmations: session 1->2, 2->3, 3->4, 4->5 (final).
        for ($i = 0; $i < 4; $i++) {
            $result = $this->dispatchAction(
                'App\Ticket\Action\ConfirmSessionAction',
                'handlePost',
                $seller,
                ['id' => $ticketId]
            );
            $this->assertSame(302, $result['status']);
        }

        // After 4 confirmations: session_number = 5, ticket auto-redeemed.
        $row = $this->pdo->query('SELECT status, session_number FROM tickets WHERE id = ' . $ticketId)->fetch();
        $this->assertSame('redeemed', $row['status']);
        $this->assertSame(5, (int) $row['session_number']);

        // 2 points_log rows (buyer + seller) since the 4th call was final.
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM points_log')->fetchColumn();
        $this->assertSame(2, $count);

        // 4 audit_log rows for session_confirmed.
        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'ticket.session_confirmed'")->fetchColumn();
        $this->assertSame(4, $count);
    }


    public function test_wrong_seller_block(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $otherUser = $this->seedUser(['nickname' => 'other']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId(), [
            'type' => 'service',
            'quantity' => 3,
        ]);
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'session_number' => 1,
            'total_sessions' => 3,
        ]);

        $result = $this->dispatchAction(
            'App\Ticket\Action\ConfirmSessionAction',
            'handlePost',
            $otherUser,
            ['id' => $ticketId]
        );

        $this->assertSame(302, $result['status']);
        $row = $this->pdo->query('SELECT session_number FROM tickets WHERE id = ' . $ticketId)->fetch();
        $this->assertSame(1, (int) $row['session_number']);
    }

    public function test_dispute_pending_block(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId(), [
            'type' => 'service',
            'quantity' => 3,
        ]);
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'session_number' => 1,
            'total_sessions' => 3,
            'dispute_status' => 'pending',
        ]);

        $result = $this->dispatchAction(
            'App\Ticket\Action\ConfirmSessionAction',
            'handlePost',
            $seller,
            ['id' => $ticketId]
        );

        $this->assertSame(302, $result['status']);
        $row = $this->pdo->query('SELECT session_number FROM tickets WHERE id = ' . $ticketId)->fetch();
        $this->assertSame(1, (int) $row['session_number']);
    }
}
