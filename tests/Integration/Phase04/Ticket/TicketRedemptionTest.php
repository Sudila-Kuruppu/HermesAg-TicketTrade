<?php
/**
 * Phase 4 — TicketRedemptionTest
 *
 * Covers Ticket\Service\ticket_service::redeemTicket():
 *   - Happy path: ticket redeemed, redeemed_at set, points_log rows
 *     for buyer + seller, users.points updated, redeemed_count +1.
 *   - Wrong code: E_TICKET_NOT_FOUND.
 *   - Wrong seller: E_TICKET_FORBIDDEN.
 *   - Dispute pending: E_TICKET_INVALID_STATE.
 *   - FR-PTS-007 halving: redeemed_count < 5 yields half points.
 *   - audit_log + 2 points_log rows with distinct UUID v7 event_uuids.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase04\Ticket;

use App\Tests\Integration\Phase04\Fixtures\Fixtures;
use App\Ticket\Service\ticket_service;

class TicketRedemptionTest extends Fixtures
{
    public function test_happy_path_redeems_ticket(): void
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

        $res = ticket_service::redeemTicket($code, $seller);
        $this->assertTrue($res['ok']);
        $this->assertSame($ticketId, (int) $res['data']['ticket_id']);

        // Ticket status updated.
        $row = $this->pdo->query('SELECT status, redeemed_at FROM tickets WHERE id = ' . $ticketId)->fetch();
        $this->assertSame('redeemed', $row['status']);
        $this->assertNotNull($row['redeemed_at']);

        // 2 points_log rows.
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM points_log')->fetchColumn();
        $this->assertSame(2, $count);

        // users.points updated.
        $buyerRow = $this->pdo->query('SELECT points FROM users WHERE user_id = ' . $buyer)->fetch();
        $sellerRow = $this->pdo->query('SELECT points FROM users WHERE user_id = ' . $seller)->fetch();
        $this->assertSame(10, (int) $buyerRow['points']);
        $this->assertSame(30, (int) $sellerRow['points']);

        // redeemed_count incremented on final_session.
        $b = (int) $this->pdo->query('SELECT redeemed_count FROM users WHERE user_id = ' . $buyer)->fetchColumn();
        $s = (int) $this->pdo->query('SELECT redeemed_count FROM users WHERE user_id = ' . $seller)->fetchColumn();
        $this->assertSame(6, $b);  // started at 5, +1
        $this->assertSame(6, $s);
    }

    public function test_wrong_code_returns_not_found(): void
    {
        $seller = $this->seedUser();
        $res = ticket_service::redeemTicket('TK-XXXX-XXXX-XXXX-XXXX-XXXX', $seller);
        $this->assertFalse($res['ok']);
        $this->assertSame('E_TICKET_NOT_FOUND', $res['error']['code']);
    }

    public function test_wrong_seller_returns_forbidden(): void
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

        $res = ticket_service::redeemTicket($code, $otherUser);
        $this->assertFalse($res['ok']);
        $this->assertSame('E_TICKET_FORBIDDEN', $res['error']['code']);
    }

    public function test_dispute_pending_block(): void
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

        $res = ticket_service::redeemTicket($code, $seller);
        $this->assertFalse($res['ok']);
        $this->assertSame('E_TICKET_INVALID_STATE', $res['error']['code']);
    }

    public function test_fr_pts_007_halving(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 2]);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
        ]);
        $code = (string) $this->pdo->query('SELECT ticket_code FROM tickets WHERE id = ' . $ticketId)->fetchColumn();

        $res = ticket_service::redeemTicket($code, $seller);
        $this->assertTrue($res['ok']);
        // Seller has redeemed_count=2 (<5), so delta_seller = floor(30*0.5) = 15.
        $this->assertSame(15, (int) $res['data']['points']['delta_seller']);
    }

    public function test_audit_log_and_two_points_log_rows(): void
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

        $res = ticket_service::redeemTicket($code, $seller);
        $this->assertTrue($res['ok']);

        // 2 points_log rows with distinct UUID v7 event_uuid.
        $rows = $this->pdo->query('SELECT event_uuid, delta, user_id FROM points_log ORDER BY id')->fetchAll();
        $this->assertCount(2, $rows);
        $this->assertNotSame($rows[0]['event_uuid'], $rows[1]['event_uuid']);
        $this->assertSame((int) $buyer, (int) $rows[0]['user_id']);
        $this->assertSame((int) $seller, (int) $rows[1]['user_id']);

        // audit_log row appended.
        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'ticket.redeemed'")->fetchColumn();
        $this->assertSame(1, $count);
    }
}
