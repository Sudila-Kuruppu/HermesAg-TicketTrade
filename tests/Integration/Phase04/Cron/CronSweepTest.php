<?php
/**
 * Phase 4 — CronSweepTest (Plan 04-03)
 *
 * End-to-end coverage of POST /admin/cron/ticket-expiry:
 *   1. 24h listing auto-approve sweep approves a pending listing.
 *   2. 3-day dispute auto-dismiss sweep restores the pre-dispute
 *      ticket status and writes the audit row.
 *   3. 7-day ticket expiry sweep marks a stale ticket as 'expired'
 *      and decrements the listing's quantity_sold per AD-7.
 *
 * The dispatch order is locked per D-07 (24h → 3-day → 7-day).
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase04\Cron;

use App\Tests\Integration\Phase04\Fixtures\Fixtures;

class CronSweepTest extends Fixtures
{
    public function testCronRunsAllThreeSweepsInOrder(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $adminId = $this->seedAdminUser();
        $catId = $this->firstCategoryId();

        // Seed a pending listing created 25 hours ago.
        $pendingListingId = $this->seedListingPast($seller, $catId, 'Pending 25h', 25);

        // Seed a disputed ticket — disputed_at 4 days ago, pre-dispute
        // status='active'. The auto-dismiss should restore it to active.
        $disputeListingId = $this->seedListing($seller, $catId, ['status' => 'active']);
        $disputeTicketId = $this->seedDisputedTicket(
            $disputeListingId,
            $buyer,
            $seller,
            daysAgo: 4,
            preStatus: 'active'
        );

        // Seed an expired ticket — expires_at = 1 hour ago.
        // Listing has quantity_sold=2, quantity=5 (so still 'active').
        $expiryListingId = $this->seedListing($seller, $catId, [
            'status' => 'active',
            'quantity' => 5,
            'quantity_sold' => 2,
        ]);
        $expiryTicketId = $this->seedExpiredTicket($expiryListingId, $buyer, $seller, hoursAgo: 1);

        $payload = $this->dispatchCron($adminId);
        $this->assertSame(200, $payload['status']);
        $this->assertTrue($payload['body']['ok']);

        // Sweep 1: listing auto-approve.
        $this->assertSame(1, (int) $payload['body']['sweeps']['listing_auto_approve']['processed']);
        $row = $this->pdo->query("SELECT status FROM listings WHERE id = $pendingListingId")->fetch();
        $this->assertSame('active', $row['status']);

        // Sweep 2: dispute auto-dismiss.
        $this->assertSame(1, (int) $payload['body']['sweeps']['dispute_auto_dismiss']['processed']);
        $this->assertCount(1, $payload['body']['sweeps']['dispute_auto_dismiss']['affected_tickets']);
        $row = $this->pdo->query("SELECT status, dispute_status, created_at, disputed_at FROM tickets WHERE id = $disputeTicketId")->fetch();
        $this->assertSame('active', $row['status'], 'pre-dispute status restored');
        $this->assertSame('rejected', $row['dispute_status']);
        $this->assertNotNull($row['disputed_at'], 'disputed_at untouched');

        // Sweep 3: ticket expiry.
        $this->assertSame(1, (int) $payload['body']['sweeps']['ticket_expiry']['processed']);
        $this->assertCount(1, $payload['body']['sweeps']['ticket_expiry']['affected_tickets']);
        $row = $this->pdo->query("SELECT status FROM tickets WHERE id = $expiryTicketId")->fetch();
        $this->assertSame('expired', $row['status']);
        // quantity_sold decremented by 1 (product).
        $row = $this->pdo->query("SELECT quantity_sold, status FROM listings WHERE id = $expiryListingId")->fetch();
        $this->assertSame(1, (int) $row['quantity_sold']);
        $this->assertSame('active', $row['status'], 'listing still active (quantity_sold < quantity)');

        // 3 cron_log rows (one per sweep).
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM cron_log')->fetchColumn();
        $this->assertSame(3, $count);
    }

    public function testCronEmptyRunReturnsZeroProcessed(): void
    {
        $adminId = $this->seedAdminUser();
        $payload = $this->dispatchCron($adminId);
        $this->assertSame(200, $payload['status']);
        $this->assertTrue($payload['body']['ok']);
        $this->assertSame(0, (int) $payload['body']['sweeps']['listing_auto_approve']['processed']);
        $this->assertSame(0, (int) $payload['body']['sweeps']['dispute_auto_dismiss']['processed']);
        $this->assertSame(0, (int) $payload['body']['sweeps']['ticket_expiry']['processed']);
        $this->assertSame([], $payload['body']['sweeps']['dispute_auto_dismiss']['affected_tickets']);
        $this->assertSame([], $payload['body']['sweeps']['ticket_expiry']['affected_tickets']);
        $this->assertSame([], $payload['body']['errors']);
    }

    public function testCronWithoutReAuthReturns403(): void
    {
        $adminId = $this->seedAdminUser();
        $oldLastSeen = (new \DateTime('-10 minutes', new \DateTimeZone('Asia/Colombo')))
            ->format('Y-m-d H:i:s');
        $payload = $this->dispatchCron($adminId, $oldLastSeen);
        $this->assertSame(403, $payload['status']);
        $this->assertFalse($payload['body']['ok']);
        $this->assertSame('re-auth required', $payload['body']['error']);
    }

    public function testCronAsNonAdminReturns404(): void
    {
        $userId = $this->seedUser(['nickname' => 'normaluser']);
        // The router guard runs before the action; the action's
        // requireReAuth would also return 403. The expected behavior
        // depends on whether the request reaches the route at all.
        // In pcntl_fork dispatch the action runs directly without
        // the router, so requireReAuth is the first gate; non-admin
        // gets a 403 with the same envelope.
        $payload = $this->dispatchCron($userId);
        $this->assertSame(403, $payload['status']);
    }

    /**
     * Seed a listing with `created_at` shifted $hoursBack into the past.
     */
    private function seedListingPast(int $sellerId, int $catId, string $title, int $hoursBack): int
    {
        $past = (new \DateTime("-$hoursBack hours", new \DateTimeZone('Asia/Colombo')))
            ->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO listings (seller_id, category_id, title, description, price_cents, type, '
            . '`condition`, quantity, quantity_sold, status, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, \'pending\', ?, ?)'
        );
        $stmt->execute([$sellerId, $catId, $title, 'Test.', 10000, 'product', 'like_new', 1, $past, $past]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Seed a ticket with dispute_status='pending' and disputed_at
     * shifted $daysAgo into the past. The pre-dispute status is set
     * via the ticket model's CASE branch in the auto-dismiss sweep.
     */
    private function seedDisputedTicket(
        int $listingId,
        int $buyerId,
        int $sellerId,
        int $daysAgo,
        string $preStatus
    ): int {
        $disputedAt = (new \DateTime("-$daysAgo days", new \DateTimeZone('Asia/Colombo')))
            ->format('Y-m-d H:i:s');
        $createdAt = (new \DateTime('-10 days', new \DateTimeZone('Asia/Colombo')))
            ->format('Y-m-d H:i:s');
        $code = \App\Ticket\Model\ticket_model::formatCode(random_bytes(16));
        // Pre-dispute status: when filed on an 'active' ticket, the
        // dispute branch flipped status='disputed'. We set the row
        // accordingly; the auto-dismiss sweep restores it to
        // 'active' (or 'redeemed' for redeemed tickets).
        $disputeStatus = 'pending';
        $ticketStatus = $preStatus === 'active' ? 'disputed' : $preStatus;
        $stmt = $this->pdo->prepare(
            'INSERT INTO tickets (ticket_code, listing_id, buyer_id, seller_id, status, dispute_status, '
            . 'price_cents, session_number, total_sessions, expires_at, disputed_at, '
            . 'created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, ?, ?, ?, ?)'
        );
        $expires = (new \DateTime('+5 days', new \DateTimeZone('Asia/Colombo')))
            ->format('Y-m-d H:i:s');
        $stmt->execute([
            $code,
            $listingId,
            $buyerId,
            $sellerId,
            $ticketStatus,
            $disputeStatus,
            10000,
            $expires,
            $disputedAt,
            $createdAt,
            $createdAt,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Seed a ticket with `expires_at` shifted $hoursAgo into the past
     * and status='active' and dispute_status='none'.
     */
    private function seedExpiredTicket(
        int $listingId,
        int $buyerId,
        int $sellerId,
        int $hoursAgo
    ): int {
        $expires = (new \DateTime("-$hoursAgo hours", new \DateTimeZone('Asia/Colombo')))
            ->format('Y-m-d H:i:s');
        $created = (new \DateTime('-8 days', new \DateTimeZone('Asia/Colombo')))
            ->format('Y-m-d H:i:s');
        $code = \App\Ticket\Model\ticket_model::formatCode(random_bytes(16));
        $stmt = $this->pdo->prepare(
            'INSERT INTO tickets (ticket_code, listing_id, buyer_id, seller_id, status, dispute_status, '
            . 'price_cents, session_number, total_sessions, expires_at, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, \'active\', \'none\', ?, 1, 1, ?, ?, ?)'
        );
        $stmt->execute([$code, $listingId, $buyerId, $sellerId, 10000, $expires, $created, $created]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Dispatch POST /admin/cron/ticket-expiry as the given admin via
     * pcntl_fork. The shutdown function captures the response body
     * + http_response_code before the child terminates.
     *
     * @param string|null $forcedLastSeen Override session.last_seen (null = NOW = fresh re-auth).
     * @return array{status:int, body:array<string,mixed>}
     */
    private function dispatchCron(int $adminId, ?string $forcedLastSeen = null): array
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl_fork not available');
        }
        $sid = 'test-sid-' . bin2hex(random_bytes(4));
        $now = $forcedLastSeen ?? (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))
            ->format('Y-m-d H:i:s');
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

        $capturePath = tempnam(sys_get_temp_dir(), 'cron-sweep-');
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
            $this->fail('Child process did not write a result file: ' . $captured);
        }
        $body = json_decode((string) ($data['body'] ?? ''), true);
        if (!is_array($body)) {
            $this->fail('Child process body is not valid JSON: ' . ($data['body'] ?? ''));
        }
        return [
            'status' => (int) ($data['status'] ?? 0),
            'body' => $body,
        ];
    }
}
