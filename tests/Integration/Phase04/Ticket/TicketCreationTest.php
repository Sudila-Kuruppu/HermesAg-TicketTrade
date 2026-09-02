<?php
/**
 * Phase 4 — TicketCreationTest
 *
 * Covers Ticket\Service\ticket_service::createTicket():
 *   - Happy path: ticket inserted, listings.quantity_sold +N, audit
 *     row appended, ticket_code matches the dashed format.
 *   - Self-purchase block: same user as seller returns E_TICKET_SELF_PURCHASE.
 *   - Sold-out block: quantity_sold == quantity returns E_LISTING_SOLD_OUT.
 *   - Ticket-expiry write-once: expires_at == created_at + 7 DAY exactly.
 *   - audit_log row appended.
 *   - status='disputed' not set on creation (status='active' on insert).
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase04\Ticket;

use App\Support\Db;
use App\Tests\Integration\Phase04\Fixtures\Fixtures;
use App\Ticket\Service\ticket_service;

class TicketCreationTest extends Fixtures
{
    public function test_happy_path_creates_ticket(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());

        $res = ticket_service::createTicket($listingId, $buyer);
        $this->assertTrue($res['ok']);
        $this->assertSame($listingId, (int) $res['data']['listing_id']);
        $code = $res['data']['ticket_code'];
        $this->assertMatchesRegularExpression(
            '/^TK-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}$/',
            $code
        );

        // listings.quantity_sold incremented.
        $row = $this->pdo->query('SELECT quantity_sold FROM listings WHERE id = ' . $listingId)->fetch();
        $this->assertSame(1, (int) $row['quantity_sold']);

        // audit_log row appended.
        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'ticket.created'")->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function test_self_purchase_block(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());

        $res = ticket_service::createTicket($listingId, $seller);
        $this->assertFalse($res['ok']);
        $this->assertSame('E_TICKET_SELF_PURCHASE', $res['error']['code']);

        // No ticket inserted.
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function test_sold_out_block(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId(), [
            'quantity' => 1,
            'quantity_sold' => 1, // already sold out
        ]);

        $res = ticket_service::createTicket($listingId, $buyer);
        $this->assertFalse($res['ok']);
        $this->assertSame('E_LISTING_SOLD_OUT', $res['error']['code']);
    }

    public function test_not_active_listing_block(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId(), [
            'status' => 'draft',
        ]);

        $res = ticket_service::createTicket($listingId, $buyer);
        $this->assertFalse($res['ok']);
        $this->assertSame('E_LISTING_NOT_ACTIVE', $res['error']['code']);
    }

    public function test_ticket_not_found(): void
    {
        $buyer = $this->seedUser();
        $res = ticket_service::createTicket(99999, $buyer);
        $this->assertFalse($res['ok']);
        $this->assertSame('E_LISTING_NOT_FOUND', $res['error']['code']);
    }

    public function test_expiry_write_once_7_days(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());

        $res = ticket_service::createTicket($listingId, $buyer);
        $this->assertTrue($res['ok']);

        $ticketId = (int) $res['data']['ticket_id'];
        $row = $this->pdo->query('SELECT created_at, expires_at FROM tickets WHERE id = ' . $ticketId)->fetch();
        $created = strtotime($row['created_at']);
        $expires = strtotime($row['expires_at']);
        $diffDays = ($expires - $created) / 86400;
        $this->assertEqualsWithDelta(7.0, $diffDays, 0.01);
    }

    public function test_audit_log_row_appended(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());

        ticket_service::createTicket($listingId, $buyer);
        $row = $this->pdo->query('SELECT * FROM audit_log ORDER BY id DESC LIMIT 1')->fetch();
        $this->assertSame('ticket.created', $row['action']);
        $this->assertSame('ticket', $row['target_type']);
        $this->assertSame((int) $buyer, (int) $row['actor_user_id']);
        $decoded = json_decode((string) $row['metadata_json'], true);
        $this->assertSame($listingId, (int) $decoded['listing_id']);
        $this->assertArrayHasKey('ticket_code', $decoded);
    }

    public function test_status_active_on_creation(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());

        $res = ticket_service::createTicket($listingId, $buyer);
        $ticketId = (int) $res['data']['ticket_id'];
        $row = $this->pdo->query('SELECT status, dispute_status, session_number, total_sessions FROM tickets WHERE id = ' . $ticketId)->fetch();
        $this->assertSame('active', $row['status']);
        $this->assertSame('none', $row['dispute_status']);
        $this->assertSame(1, (int) $row['session_number']);
        $this->assertSame(1, (int) $row['total_sessions']);
    }

    public function test_service_listing_total_sessions_equals_quantity(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId(), [
            'type' => 'service',
            'quantity' => 5,
        ]);

        $res = ticket_service::createTicket($listingId, $buyer);
        $this->assertTrue($res['ok']);
        $ticketId = (int) $res['data']['ticket_id'];
        $row = $this->pdo->query('SELECT total_sessions FROM tickets WHERE id = ' . $ticketId)->fetch();
        $this->assertSame(5, (int) $row['total_sessions']);

        // listings.quantity_sold incremented by total_sessions (5).
        $lst = $this->pdo->query('SELECT quantity_sold FROM listings WHERE id = ' . $listingId)->fetch();
        $this->assertSame(5, (int) $lst['quantity_sold']);
    }
}
