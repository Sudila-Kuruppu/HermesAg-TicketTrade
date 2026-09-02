<?php
/**
 * Phase 4 — TicketExpiryTest (Plan 04-03)
 *
 * Covers the 7-day ticket expiry sweep:
 *   - Active tickets with expires_at < NOW() are marked 'expired'.
 *   - Products: listings.quantity_sold decremented by 1.
 *   - Services: listings.quantity_sold decremented by
 *     total_sessions - (session_number - 1).
 *   - If quantity_sold < quantity AND status='sold', the listing
 *     is restored to 'active'.
 *   - SKIPS tickets with dispute_status='pending' (admin resolves
 *     disputes first per PRD §4.2).
 *   - audit_log row appended with action='ticket.expired'.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase04\Cron;

use App\Tests\Integration\Phase04\Fixtures\Fixtures;

class TicketExpiryTest extends Fixtures
{
    public function testActiveTicketIsMarkedExpiredAndQuantityDecremented(): void
    {
        $seller = $this->seedUser();
        $buyer = $this->seedUser();
        $adminId = $this->seedAdminUser();
        $catId = $this->firstCategoryId();

        // Listing has quantity_sold=2, quantity=5; status='active'.
        // The expired ticket decrements quantity_sold to 1, status stays 'active'.
        $listingId = $this->seedListing($seller, $catId, [
            'status' => 'active',
            'quantity' => 5,
            'quantity_sold' => 2,
        ]);
        $ticketId = $this->seedExpiredTicket($listingId, $buyer, $seller, hoursAgo: 1);

        $payload = $this->dispatchCron($adminId);
        $this->assertSame(200, $payload['status']);
        $this->assertSame(1, (int) $payload['body']['sweeps']['ticket_expiry']['processed']);

        $row = $this->pdo->query("SELECT status FROM tickets WHERE id = $ticketId")->fetch();
        $this->assertSame('expired', $row['status']);

        $row = $this->pdo->query("SELECT quantity_sold, status FROM listings WHERE id = $listingId")->fetch();
        $this->assertSame(1, (int) $row['quantity_sold']);
        $this->assertSame('active', $row['status']);

        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'ticket.expired'")->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testSoldListingIsReactivatedWhenStockReturns(): void
    {
        $seller = $this->seedUser();
        $buyer = $this->seedUser();
        $adminId = $this->seedAdminUser();
        $catId = $this->firstCategoryId();

        // Listing is 'sold' with quantity_sold=1, quantity=1.
        // The expired ticket decrements to 0, so the listing is
        // restored to 'active'.
        $listingId = $this->seedListing($seller, $catId, [
            'status' => 'sold',
            'quantity' => 1,
            'quantity_sold' => 1,
        ]);
        $ticketId = $this->seedExpiredTicket($listingId, $buyer, $seller, hoursAgo: 1);

        $payload = $this->dispatchCron($adminId);
        $this->assertSame(1, (int) $payload['body']['sweeps']['ticket_expiry']['processed']);

        $row = $this->pdo->query("SELECT status FROM tickets WHERE id = $ticketId")->fetch();
        $this->assertSame('expired', $row['status']);

        $row = $this->pdo->query("SELECT quantity_sold, status FROM listings WHERE id = $listingId")->fetch();
        $this->assertSame(0, (int) $row['quantity_sold']);
        $this->assertSame('active', $row['status'], 'listing restored from sold to active');
    }

    public function testServiceTicketDecrementsByRemainingSessions(): void
    {
        $seller = $this->seedUser();
        $buyer = $this->seedUser();
        $adminId = $this->seedAdminUser();
        $catId = $this->firstCategoryId();

        // Service ticket with 5 sessions total, session_number=2 (3 sessions delivered).
        // Remaining: total_sessions - (session_number - 1) = 5 - 1 = 4.
        $listingId = $this->seedListing($seller, $catId, [
            'type' => 'service',
            'quantity' => 5,
            'quantity_sold' => 5,
            'status' => 'sold',
        ]);
        $ticketId = $this->seedExpiredTicket($listingId, $buyer, $seller, 1, [
            'total_sessions' => 5,
            'session_number' => 2,
        ]);

        $payload = $this->dispatchCron($adminId);
        $this->assertSame(1, (int) $payload['body']['sweeps']['ticket_expiry']['processed']);

        $row = $this->pdo->query("SELECT quantity_sold, status FROM listings WHERE id = $listingId")->fetch();
        // Decrement by 4 (5 - (2 - 1) = 4) → quantity_sold goes 5 -> 1.
        $this->assertSame(1, (int) $row['quantity_sold']);
        $this->assertSame('active', $row['status'], 'sold listing restored (1 < 5)');
    }

    public function testDisputedTicketIsSkipped(): void
    {
        $seller = $this->seedUser();
        $buyer = $this->seedUser();
        $adminId = $this->seedAdminUser();
        $catId = $this->firstCategoryId();

        // Disputed ticket — must NOT be touched by the expiry sweep.
        $listingId = $this->seedListing($seller, $catId, [
            'status' => 'active',
            'quantity' => 5,
            'quantity_sold' => 2,
        ]);
        $code = \App\Ticket\Model\ticket_model::formatCode(random_bytes(16));
        $expires = (new \DateTime('-1 hour', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $disputedAt = (new \DateTime('-2 days', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $created = (new \DateTime('-5 days', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO tickets (ticket_code, listing_id, buyer_id, seller_id, status, dispute_status, '
            . 'price_cents, session_number, total_sessions, expires_at, disputed_at, '
            . 'created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, \'disputed\', \'pending\', ?, 1, 1, ?, ?, ?, ?)'
        );
        $stmt->execute([$code, $listingId, $buyer, $seller, 10000, $expires, $disputedAt, $created, $created]);
        $ticketId = (int) $this->pdo->lastInsertId();

        $payload = $this->dispatchCron($adminId);
        // The dispute sweep does NOT auto-dismiss this (it's only 2 days old).
        // The expiry sweep SKIPS it (dispute_status='pending').
        $this->assertSame(0, (int) $payload['body']['sweeps']['ticket_expiry']['processed']);

        $row = $this->pdo->query("SELECT status, dispute_status FROM tickets WHERE id = $ticketId")->fetch();
        $this->assertSame('disputed', $row['status']);
        $this->assertSame('pending', $row['dispute_status']);

        // Listing quantity_sold is unchanged.
        $row = $this->pdo->query("SELECT quantity_sold FROM listings WHERE id = $listingId")->fetch();
        $this->assertSame(2, (int) $row['quantity_sold']);
    }

    private function seedExpiredTicket(int $listingId, int $buyerId, int $sellerId, int $hoursAgo, array $overrides = []): int
    {
        $expires = (new \DateTime("-$hoursAgo hours", new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $created = (new \DateTime('-8 days', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $code = \App\Ticket\Model\ticket_model::formatCode(random_bytes(16));
        $stmt = $this->pdo->prepare(
            'INSERT INTO tickets (ticket_code, listing_id, buyer_id, seller_id, status, dispute_status, '
            . 'price_cents, session_number, total_sessions, expires_at, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, \'active\', \'none\', ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $code,
            $listingId,
            $buyerId,
            $sellerId,
            10000,
            (int) ($overrides['session_number'] ?? 1),
            (int) ($overrides['total_sessions'] ?? 1),
            $expires,
            $created,
            $created,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function dispatchCron(int $adminId): array
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl_fork not available');
        }
        $sid = 'test-sid-' . bin2hex(random_bytes(4));
        $now = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $this->pdo->prepare(
            'INSERT INTO sessions (session_id, user_id, last_seen, ip, user_agent, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$sid, $adminId, $now, '127.0.0.1', 'phpunit', $now]);
        $_COOKIE[session_name()] = $sid;
        $stmt = $this->pdo->prepare(
            'SELECT user_id, email, student_id, nickname, full_name, is_admin, is_banned, is_verified '
            . 'FROM users WHERE user_id = ?'
        );
        $stmt->execute([$adminId]);
        $GLOBALS['current_user'] = (array) $stmt->fetch();

        $capturePath = tempnam(sys_get_temp_dir(), 'cron-expiry-');
        @unlink($capturePath);
        touch($capturePath);

        $pid = pcntl_fork();
        if ($pid === -1) {
            $this->fail('pcntl_fork failed');
        }
        if ($pid === 0) {
            register_shutdown_function(function () use ($capturePath) {
                $body = (string) ob_get_contents();
                @ob_end_clean();
                $status = http_response_code() ?: 200;
                file_put_contents($capturePath, json_encode([
                    'status' => (int) $status,
                    'body' => $body,
                ]));
            });
            ob_start();
            $action = new \App\Admin\Action\CronAction();
            $action->handle();
            exit(0);
        }
        pcntl_waitpid($pid, $status);
        \App\Support\Db::reset();
        $this->pdo = \App\Support\Db::pdo();
        $this->pdo->exec("SET time_zone = '+05:30'");

        $captured = (string) file_get_contents($capturePath);
        @unlink($capturePath);
        $data = json_decode($captured, true);
        if (!is_array($data)) {
            $this->fail('Child process did not write a result file');
        }
        $body = json_decode((string) ($data['body'] ?? ''), true);
        if (!is_array($body)) {
            $this->fail('Child process body is not valid JSON');
        }
        return [
            'status' => (int) ($data['status'] ?? 0),
            'body' => $body,
        ];
    }
}
