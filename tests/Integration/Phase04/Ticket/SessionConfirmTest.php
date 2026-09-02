<?php
/**
 * Phase 4 — SessionConfirmTest
 *
 * Covers Ticket\Service\ticket_service::confirmSession():
 *   - Intermediate session: session_number increments, no points,
 *     no redeemed_count increment, audit_log row with is_final=false.
 *   - Final session: status='redeemed', points awarded, redeemed_count +1,
 *     audit_log row with is_final=true.
 *   - Out-of-order block: trying to confirm past total_sessions.
 *   - Dispute pending block.
 *   - Wrong seller block.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase04\Ticket;

use App\Tests\Integration\Phase04\Fixtures\Fixtures;
use App\Ticket\Service\ticket_service;

class SessionConfirmTest extends Fixtures
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

        $res = ticket_service::confirmSession($ticketId, $seller);
        $this->assertTrue($res['ok']);
        $this->assertFalse((bool) $res['data']['is_final']);
        $this->assertSame(2, (int) $res['data']['session_number']);

        // Ticket still active.
        $row = $this->pdo->query('SELECT status, session_number FROM tickets WHERE id = ' . $ticketId)->fetch();
        $this->assertSame('active', $row['status']);
        $this->assertSame(2, (int) $row['session_number']);

        // No points_log rows.
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM points_log')->fetchColumn();
        $this->assertSame(0, $count);

        // redeemed_count NOT incremented.
        $b = (int) $this->pdo->query('SELECT redeemed_count FROM users WHERE user_id = ' . $buyer)->fetchColumn();
        $this->assertSame(0, $b);

        // audit_log row appended.
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

        $res = ticket_service::confirmSession($ticketId, $seller);
        $this->assertTrue($res['ok']);
        $this->assertTrue((bool) $res['data']['is_final']);
        $this->assertSame(2, (int) $res['data']['session_number']);

        // Ticket now 'redeemed'.
        $row = $this->pdo->query('SELECT status, session_number, redeemed_at FROM tickets WHERE id = ' . $ticketId)->fetch();
        $this->assertSame('redeemed', $row['status']);
        $this->assertSame(2, (int) $row['session_number']);
        $this->assertNotNull($row['redeemed_at']);

        // points_log rows present.
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM points_log')->fetchColumn();
        $this->assertSame(2, $count);

        // redeemed_count +1.
        $b = (int) $this->pdo->query('SELECT redeemed_count FROM users WHERE user_id = ' . $buyer)->fetchColumn();
        $this->assertSame(6, $b);  // started at 5, +1

        // audit_log rows.
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM audit_log')->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function test_out_of_order_block(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId(), [
            'type' => 'service',
            'quantity' => 2,
        ]);
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'session_number' => 2, // already at the last
            'total_sessions' => 2,
        ]);

        $res = ticket_service::confirmSession($ticketId, $seller);
        $this->assertFalse($res['ok']);
        $this->assertSame('E_TICKET_INVALID_STATE', $res['error']['code']);
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
            'dispute_status' => 'pending',
            'session_number' => 1,
            'total_sessions' => 3,
        ]);

        $res = ticket_service::confirmSession($ticketId, $seller);
        $this->assertFalse($res['ok']);
        $this->assertSame('E_TICKET_INVALID_STATE', $res['error']['code']);
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

        $res = ticket_service::confirmSession($ticketId, $otherUser);
        $this->assertFalse($res['ok']);
        $this->assertSame('E_TICKET_FORBIDDEN', $res['error']['code']);
    }
}
