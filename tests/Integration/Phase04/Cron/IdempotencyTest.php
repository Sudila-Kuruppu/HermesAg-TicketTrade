<?php
/**
 * Phase 4 — IdempotencyTest (Plan 04-03)
 *
 * Per NFR-REL-002: the cron is idempotent. Re-running the endpoint
 * with the same DB state must produce the same end state, and the
 * `cron_log` row count must increase by exactly 1 per run.
 *
 * This test seeds an eligible listing + disputed ticket + expired
 * ticket, runs the cron 5 times, and asserts:
 *   - Run 1: each sweep processed >= 1 row.
 *   - Runs 2-5: each sweep processed = 0 rows.
 *   - cron_log row count delta = 5 (one row per run, 3 sweeps each).
 *   - The business state (ticket statuses, listing quantity_sold)
 *     is identical after run 1 vs after run 5.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase04\Cron;

use App\Tests\Integration\Phase04\Fixtures\Fixtures;

class IdempotencyTest extends Fixtures
{
    public function testFiveSuccessiveRunsAreIdempotent(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $adminId = $this->seedAdminUser();
        $catId = $this->firstCategoryId();

        // Seed one eligible row per sweep.
        $pendingListingId = $this->seedListingPast($seller, $catId, 'Pending 25h', 25);
        $disputeListingId = $this->seedListing($seller, $catId);
        $disputeTicketId = $this->seedDisputedTicket($disputeListingId, $buyer, $seller, 4, 'active');
        $expiryListingId = $this->seedListing($seller, $catId);
        $expiryTicketId = $this->seedExpiredTicket($expiryListingId, $buyer, $seller, 1);

        // Run the cron 5 times.
        $snapshots = [];
        for ($run = 1; $run <= 5; $run++) {
            $payload = $this->dispatchCron($adminId);
            $this->assertSame(200, $payload['status']);
            $this->assertTrue($payload['body']['ok']);
            $snapshots[$run] = [
                'listing_processed' => (int) $payload['body']['sweeps']['listing_auto_approve']['processed'],
                'dispute_processed' => (int) $payload['body']['sweeps']['dispute_auto_dismiss']['processed'],
                'expiry_processed' => (int) $payload['body']['sweeps']['ticket_expiry']['processed'],
                'state' => $this->snapshotBusinessState(
                    $pendingListingId,
                    $disputeTicketId,
                    $expiryTicketId,
                    $expiryListingId
                ),
            ];
        }

        // Run 1: each sweep processed >= 1 row.
        $this->assertGreaterThanOrEqual(1, $snapshots[1]['listing_processed']);
        $this->assertGreaterThanOrEqual(1, $snapshots[1]['dispute_processed']);
        $this->assertGreaterThanOrEqual(1, $snapshots[1]['expiry_processed']);

        // Runs 2-5: each sweep processed = 0.
        for ($run = 2; $run <= 5; $run++) {
            $this->assertSame(0, $snapshots[$run]['listing_processed'], "run $run listing");
            $this->assertSame(0, $snapshots[$run]['dispute_processed'], "run $run dispute");
            $this->assertSame(0, $snapshots[$run]['expiry_processed'], "run $run expiry");
        }

        // Business state identical after runs 1-5.
        $this->assertSame($snapshots[1]['state'], $snapshots[5]['state']);

        // cron_log rows: 5 runs * 3 sweeps = 15 rows.
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM cron_log')->fetchColumn();
        $this->assertSame(15, $count);
    }

    /**
     * Capture a snapshot of the relevant business state for comparison.
     */
    private function snapshotBusinessState(int $listingId, int $ticketId, int $expiryTicketId, int $expiryListingId): array
    {
        $row1 = $this->pdo->query("SELECT status FROM listings WHERE id = $listingId")->fetch();
        $row2 = $this->pdo->query("SELECT status, dispute_status FROM tickets WHERE id = $ticketId")->fetch();
        $row3 = $this->pdo->query("SELECT status FROM tickets WHERE id = $expiryTicketId")->fetch();
        $row4 = $this->pdo->query("SELECT quantity_sold, status FROM listings WHERE id = $expiryListingId")->fetch();
        return [
            'listing_status' => (string) $row1['status'],
            'ticket_status' => (string) $row2['status'],
            'ticket_dispute_status' => (string) $row2['dispute_status'],
            'expiry_ticket_status' => (string) $row3['status'],
            'expiry_listing_quantity_sold' => (int) $row4['quantity_sold'],
            'expiry_listing_status' => (string) $row4['status'],
        ];
    }

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

    private function seedDisputedTicket(int $listingId, int $buyerId, int $sellerId, int $daysAgo, string $preStatus): int
    {
        $disputedAt = (new \DateTime("-$daysAgo days", new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $createdAt = (new \DateTime('-10 days', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $code = \App\Ticket\Model\ticket_model::formatCode(random_bytes(16));
        $ticketStatus = $preStatus === 'active' ? 'disputed' : $preStatus;
        $expires = (new \DateTime('+5 days', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO tickets (ticket_code, listing_id, buyer_id, seller_id, status, dispute_status, '
            . 'price_cents, session_number, total_sessions, expires_at, disputed_at, '
            . 'created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, ?, ?, ?, ?)'
        );
        $stmt->execute([$code, $listingId, $buyerId, $sellerId, $ticketStatus, 'pending', 10000, $expires, $disputedAt, $createdAt, $createdAt]);
        return (int) $this->pdo->lastInsertId();
    }

    private function seedExpiredTicket(int $listingId, int $buyerId, int $sellerId, int $hoursAgo): int
    {
        $expires = (new \DateTime("-$hoursAgo hours", new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $created = (new \DateTime('-8 days', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $code = \App\Ticket\Model\ticket_model::formatCode(random_bytes(16));
        $stmt = $this->pdo->prepare(
            'INSERT INTO tickets (ticket_code, listing_id, buyer_id, seller_id, status, dispute_status, '
            . 'price_cents, session_number, total_sessions, expires_at, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, \'active\', \'none\', ?, 1, 1, ?, ?, ?)'
        );
        $stmt->execute([$code, $listingId, $buyerId, $sellerId, 10000, $expires, $created, $created]);
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

        $capturePath = tempnam(sys_get_temp_dir(), 'cron-idem-');
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
