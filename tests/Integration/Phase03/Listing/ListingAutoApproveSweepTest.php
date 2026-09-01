<?php
/**
 * Phase 3 — ListingAutoApproveSweepTest
 *
 * Verifies the hand-triggered admin auto-approve sweep:
 *   - Approves pending listings older than 24h (25h-old → active)
 *   - Ignores listings younger than 24h (23h-old → still pending)
 *   - Idempotent: a second run after the first finds 0 eligible rows
 *   - Logs to cron_log with the correct job_name + actor_user_id
 *   - Rejects with 403 JSON when re-auth is stale
 *
 * Tests bypass listing_service::createDraft to insert listings with
 * a controlled `created_at` (the Service stamps NOW() and there is no
 * knob for the past).
 *
 * Action dispatch uses pcntl_fork() to isolate the action's exit() call.
 * The parent reads the result file written by the child's shutdown
 * function (which fires before exit() terminates the child process).
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase03\Listing;

use App\Listing\Action\ListingAutoApproveAction;
use App\Tests\Integration\Phase03\Fixtures\Fixtures;

class ListingAutoApproveSweepTest extends Fixtures
{
    public function testSweepApproves25HourOldPendingListings(): void
    {
        $sellerId = $this->seedUser();
        $adminId = $this->seedAdminUser();
        $catId = $this->firstCategoryId();

        for ($i = 0; $i < 25; $i++) {
            $this->seedListingPast($sellerId, $catId, 'Item ' . $i, 25);
        }
        for ($i = 0; $i < 5; $i++) {
            $this->seedListingPast($sellerId, $catId, 'Recent ' . $i, 2);
        }

        $pendingCount = (int) $this->pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'pending'")->fetchColumn();
        $this->assertSame(30, $pendingCount);

        $payload = $this->dispatchAutoApprove($adminId);

        $this->assertSame(200, $payload['status']);
        $this->assertTrue($payload['body']['ok']);
        $this->assertSame(25, (int) $payload['body']['processed']);

        $active = (int) $this->pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'active'")->fetchColumn();
        $pending = (int) $this->pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'pending'")->fetchColumn();
        $this->assertSame(25, $active);
        $this->assertSame(5, $pending);

        $approvedAt = (int) $this->pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'active' AND approved_at IS NOT NULL")->fetchColumn();
        $approvedBy = (int) $this->pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'active' AND approved_by IS NULL")->fetchColumn();
        $this->assertSame(25, $approvedAt);
        $this->assertSame(25, $approvedBy);
    }

    public function testSweepIgnoresRecentListings(): void
    {
        $sellerId = $this->seedUser();
        $adminId = $this->seedAdminUser();
        $catId = $this->firstCategoryId();

        for ($i = 0; $i < 10; $i++) {
            $this->seedListingPast($sellerId, $catId, 'Fresh ' . $i, 23);
        }

        $payload = $this->dispatchAutoApprove($adminId);

        $this->assertSame(200, $payload['status']);
        $this->assertTrue($payload['body']['ok']);
        $this->assertSame(0, (int) $payload['body']['processed']);

        $pending = (int) $this->pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'pending'")->fetchColumn();
        $this->assertSame(10, $pending);
    }

    public function testSweepIsIdempotent(): void
    {
        $sellerId = $this->seedUser();
        $adminId = $this->seedAdminUser();
        $catId = $this->firstCategoryId();

        for ($i = 0; $i < 25; $i++) {
            $this->seedListingPast($sellerId, $catId, 'Item ' . $i, 25);
        }

        $first = $this->dispatchAutoApprove($adminId);
        $this->assertSame(200, $first['status']);
        $this->assertSame(25, (int) $first['body']['processed']);

        $second = $this->dispatchAutoApprove($adminId);
        $this->assertSame(200, $second['status']);
        $this->assertSame(0, (int) $second['body']['processed']);
    }

    public function testSweepLogsToCronLog(): void
    {
        $sellerId = $this->seedUser();
        $adminId = $this->seedAdminUser();
        $catId = $this->firstCategoryId();

        $this->seedListingPast($sellerId, $catId, 'Single', 25);

        $payload = $this->dispatchAutoApprove($adminId);
        $this->assertSame(1, (int) $payload['body']['processed']);

        $rows = $this->pdo->query('SELECT job_name, processed_count, actor_user_id FROM cron_log')->fetchAll();
        $this->assertCount(1, $rows);
        $this->assertSame('listing.auto_approve', $rows[0]['job_name']);
        $this->assertSame(1, (int) $rows[0]['processed_count']);
        $this->assertSame($adminId, (int) $rows[0]['actor_user_id']);
    }

    public function testSweepWithoutReAuthReturns403(): void
    {
        $sellerId = $this->seedUser();
        $adminId = $this->seedAdminUser();
        $catId = $this->firstCategoryId();

        $this->seedListingPast($sellerId, $catId, 'Will be left pending', 25);

        $oldLastSeen = (new \DateTime('-10 minutes', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $payload = $this->dispatchAutoApprove($adminId, $oldLastSeen);

        $this->assertSame(403, $payload['status']);
        $this->assertFalse($payload['body']['ok']);
        $this->assertSame('re-auth required', $payload['body']['error']);

        $pending = (int) $this->pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'pending'")->fetchColumn();
        $this->assertSame(1, $pending);
    }

    /**
     * Seed a listing with `created_at` shifted $hoursBack into the past.
     */
    private function seedListingPast(int $sellerId, int $catId, string $title, int $hoursBack): int
    {
        $past = (new \DateTime("-{$hoursBack} hours", new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO listings (seller_id, category_id, title, description, price_cents, type, '
            . '`condition`, quantity, quantity_sold, status, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, \'pending\', ?, ?)'
        );
        $stmt->execute([$sellerId, $catId, $title, 'Auto-test listing.', 10000, 'product', 'like_new', 1, $past, $past]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Seed an admin user; returns user_id.
     */
    private function seedAdminUser(): int
    {
        return $this->seedUser([
            'email' => 'admin@students.nsbm.ac.lk',
            'student_id' => 'NSBM/2023/ADM',
            'nickname' => 'admin',
            'is_admin' => true,
        ]);
    }

    /**
     * Look up the full user row (with is_admin, etc.) so the action can pass adminGuard.
     */
    private function loadUserRow(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT user_id, email, student_id, nickname, full_name, is_admin, is_banned, is_verified FROM users WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (array) $stmt->fetch();
    }

    /**
     * Dispatch POST /admin/cron/ticket-expiry as the given admin.
     *
     * The action calls exit() after echoing the response body. We use
     * pcntl_fork() to isolate the exit() from the phpunit runner; the
     * child writes its captured body + http_response_code to a side
     * file via register_shutdown_function (which fires before exit()
     * terminates the child). After pcntl_waitpid returns, the parent
     * reads the side file.
     *
     * @param string|null $forcedLastSeen Override session.last_seen (null = NOW = fresh re-auth).
     * @return array{status:int, body:array<string,mixed>}
     */
    private function dispatchAutoApprove(int $adminId, ?string $forcedLastSeen = null): array
    {
        // Set up a session for the admin IN THE TEST PROCESS (the DB
        // is shared with the child via the unix socket).
        $sid = 'test-sid-' . bin2hex(random_bytes(4));
        $now = $forcedLastSeen ?? (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $this->pdo->prepare('INSERT INTO sessions (session_id, user_id, last_seen, ip, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$sid, $adminId, $now, '127.0.0.1', 'phpunit', $now]);
        $_COOKIE[session_name()] = $sid;
        $GLOBALS['current_user'] = $this->loadUserRow($adminId);

        $capturePath = tempnam(sys_get_temp_dir(), 'auto-approve-');
        @unlink($capturePath);
        touch($capturePath);

        $pid = pcntl_fork();
        if ($pid === -1) {
            $this->fail('pcntl_fork failed');
        }
        if ($pid === 0) {
            // CHILD: invoke the action. The shutdown function writes the
            // captured body + status to the side file before exit()
            // terminates this child process.
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
            $action = new ListingAutoApproveAction();
            $action->handle();
            // If we get here, the action did NOT call exit(); the
            // shutdown function still fires at child shutdown.
            exit(0);
        }
        // PARENT: wait for child to exit.
        pcntl_waitpid($pid, $status);

        // The forked child shares our PDO handle; after the child
        // terminates, our PDO may be in an inconsistent state. Reset
        // the singleton so subsequent queries reopen the connection.
        \App\Support\Db::reset();
        $this->pdo = \App\Support\Db::pdo();

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

    /**
     * First category_id from the seed. Phase 03 Fixtures seeds 7 categories.
     */
    private function firstCategoryId(): int
    {
        return (int) $this->pdo->query('SELECT id FROM categories ORDER BY sort_order LIMIT 1')->fetchColumn();
    }
}
