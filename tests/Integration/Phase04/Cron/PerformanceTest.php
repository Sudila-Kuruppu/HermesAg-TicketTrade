<?php
/**
 * Phase 4 — PerformanceTest (Plan 04-03)
 *
 * Per NFR-PER-004: the cron must complete in < 30s for 10k tickets.
 * The single guarded UPDATE on 10k rows is the dominant cost; the
 * per-ticket loop for the listings.quantity_sold decrement is
 * bounded by the number of expiring tickets (10k in this test).
 *
 * Test seeds 10k tickets with `expires_at` 1 hour in the past,
 * status='active', dispute_status='none'. Each ticket is on its
 * own listing (10k listings, quantity_sold=1, quantity=2 so the
 * decrement brings each to 0, restoring 'active').
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase04\Cron;

use App\Tests\Integration\Phase04\Fixtures\Fixtures;

class PerformanceTest extends Fixtures
{
    private const TICKET_COUNT = 10000;
    private const MAX_DURATION_SECONDS = 30;

    public function test10kTicketsExpireInUnder30Seconds(): void
    {
        $seller = $this->seedUser(['nickname' => 'perfseller']);
        $buyer = $this->seedUser(['nickname' => 'perfbuyer']);
        $adminId = $this->seedAdminUser();
        $catId = $this->firstCategoryId();

        // Seed listings + tickets in bulk INSERT batches.
        $this->seedListingsAndTickets($seller, $buyer, $catId, self::TICKET_COUNT);

        // Sanity check before sweep.
        $this->assertSame(
            self::TICKET_COUNT,
            (int) $this->pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'active'")->fetchColumn()
        );

        // Run the cron, measure wall-clock duration.
        $start = microtime(true);
        $payload = $this->dispatchCron($adminId);
        $duration = microtime(true) - $start;

        $this->assertSame(200, $payload['status']);
        $this->assertTrue($payload['body']['ok']);
        $this->assertGreaterThanOrEqual(self::TICKET_COUNT, (int) $payload['body']['sweeps']['ticket_expiry']['processed']);

        // End state: all 10k tickets 'expired'.
        $expiredCount = (int) $this->pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'expired'")->fetchColumn();
        $this->assertGreaterThanOrEqual(self::TICKET_COUNT, $expiredCount);

        // All 10k listings restored to active with quantity_sold=0.
        $zeroSoldCount = (int) $this->pdo->query("SELECT COUNT(*) FROM listings WHERE quantity_sold = 0 AND status = 'active'")->fetchColumn();
        $this->assertGreaterThanOrEqual(self::TICKET_COUNT, $zeroSoldCount);

        // Duration under 30s per NFR-PER-004.
        $this->assertLessThan(
            self::MAX_DURATION_SECONDS,
            $duration,
            sprintf('Cron took %.2fs, expected < %ds', $duration, self::MAX_DURATION_SECONDS)
        );
    }

    /**
     * Bulk seed N listings + N tickets. Uses prepared INSERT batches
     * to keep the seeding itself fast (a 10k-row INSERT runs in ~1-2s).
     */
    private function seedListingsAndTickets(int $sellerId, int $buyerId, int $catId, int $n): void
    {
        $expires = (new \DateTime('-1 hour', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $created = (new \DateTime('-8 days', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        $listStmt = $this->pdo->prepare(
            'INSERT INTO listings (seller_id, category_id, title, description, price_cents, type, '
            . '`condition`, quantity, quantity_sold, status, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ticketStmt = $this->pdo->prepare(
            'INSERT INTO tickets (ticket_code, listing_id, buyer_id, seller_id, status, dispute_status, '
            . 'price_cents, session_number, total_sessions, expires_at, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, \'active\', \'none\', ?, 1, 1, ?, ?, ?)'
        );
        $batchSize = 500;
        for ($i = 0; $i < $n; $i++) {
            $title = 'Perf ' . $i;
            $listStmt->execute([
                $sellerId,
                $catId,
                $title,
                'Perf test listing.',
                1000,
                'product',
                'like_new',
                2,
                1,
                'sold',
                $created,
                $created,
            ]);
            $listingId = (int) $this->pdo->lastInsertId();
            $code = \App\Ticket\Model\ticket_model::formatCode(random_bytes(16));
            $ticketStmt->execute([$code, $listingId, $buyerId, $sellerId, 1000, $expires, $created, $created]);
            if (($i + 1) % $batchSize === 0) {
                $this->pdo->commit();
                $this->pdo->beginTransaction();
            }
        }
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
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

        $capturePath = tempnam(sys_get_temp_dir(), 'cron-perf-');
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
