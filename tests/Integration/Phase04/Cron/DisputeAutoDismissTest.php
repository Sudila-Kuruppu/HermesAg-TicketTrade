<?php
/**
 * Phase 4 — DisputeAutoDismissTest (Plan 04-03)
 *
 * Covers the 3-day dispute auto-dismiss sweep:
 *   - Stale disputes (disputed_at > 3 days ago) are auto-rejected.
 *   - Pre-dispute status is restored: 'active' or 'disputed' -> 'active',
 *     'redeemed' -> 'redeemed'.
 *   - `created_at` is NEVER touched (D-07 invariant).
 *   - `disputed_at` is NEVER touched.
 *   - `audit_log` row appended with action='ticket.dispute_auto_dismissed'.
 *   - Fresh disputes (< 3 days) are NOT auto-dismissed.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase04\Cron;

use App\Tests\Integration\Phase04\Fixtures\Fixtures;

class DisputeAutoDismissTest extends Fixtures
{
    public function testStaleDisputeActiveIsAutoRejectedAndStatusRestored(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $adminId = $this->seedAdminUser();
        $catId = $this->firstCategoryId();

        // Pre-dispute active ticket — after dispute filed, status='disputed'.
        // The auto-dismiss sweep should restore it to 'active'.
        $listingId = $this->seedListing($seller, $catId);
        $ticketId = $this->seedDisputedTicket($listingId, $buyer, $seller, daysAgo: 4, preStatus: 'active');
        $originalCreatedAt = (string) $this->pdo->query("SELECT created_at FROM tickets WHERE id = $ticketId")->fetchColumn();
        $originalDisputedAt = (string) $this->pdo->query("SELECT disputed_at FROM tickets WHERE id = $ticketId")->fetchColumn();

        $payload = $this->dispatchCron($adminId);
        $this->assertSame(200, $payload['status']);
        $this->assertTrue($payload['body']['ok']);
        $this->assertSame(1, (int) $payload['body']['sweeps']['dispute_auto_dismiss']['processed']);

        $row = $this->pdo->query("SELECT status, dispute_status, created_at, disputed_at FROM tickets WHERE id = $ticketId")->fetch();
        $this->assertSame('active', $row['status'], 'status restored to pre-dispute active');
        $this->assertSame('rejected', $row['dispute_status']);
        $this->assertSame($originalCreatedAt, $row['created_at'], 'created_at NEVER touched');
        $this->assertSame($originalDisputedAt, $row['disputed_at'], 'disputed_at NEVER touched');

        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'ticket.dispute_auto_dismissed'")->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testStaleDisputeRedeemedKeepsStatusRedeemed(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $adminId = $this->seedAdminUser();
        $catId = $this->firstCategoryId();

        // Pre-dispute redeemed ticket — after dispute filed, status='redeemed' (per D-03).
        // The auto-dismiss sweep should keep it 'redeemed'.
        $listingId = $this->seedListing($seller, $catId);
        $ticketId = $this->seedDisputedTicket($listingId, $buyer, $seller, daysAgo: 4, preStatus: 'redeemed');

        $payload = $this->dispatchCron($adminId);
        $this->assertSame(1, (int) $payload['body']['sweeps']['dispute_auto_dismiss']['processed']);

        $row = $this->pdo->query("SELECT status, dispute_status FROM tickets WHERE id = $ticketId")->fetch();
        $this->assertSame('redeemed', $row['status']);
        $this->assertSame('rejected', $row['dispute_status']);
    }

    public function testFreshDisputeIsNotAutoDismissed(): void
    {
        $seller = $this->seedUser();
        $buyer = $this->seedUser();
        $adminId = $this->seedAdminUser();
        $catId = $this->firstCategoryId();

        // Dispute filed 1 hour ago — NOT stale.
        $listingId = $this->seedListing($seller, $catId);
        $disputedAt = (new \DateTime('-1 hour', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $createdAt = (new \DateTime('-5 days', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $code = \App\Ticket\Model\ticket_model::formatCode(random_bytes(16));
        $expires = (new \DateTime('+5 days', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO tickets (ticket_code, listing_id, buyer_id, seller_id, status, dispute_status, '
            . 'price_cents, session_number, total_sessions, expires_at, disputed_at, '
            . 'created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, \'disputed\', \'pending\', ?, 1, 1, ?, ?, ?, ?)'
        );
        $stmt->execute([$code, $listingId, $buyer, $seller, 10000, $expires, $disputedAt, $createdAt, $createdAt]);
        $ticketId = (int) $this->pdo->lastInsertId();

        $payload = $this->dispatchCron($adminId);
        $this->assertSame(0, (int) $payload['body']['sweeps']['dispute_auto_dismiss']['processed']);

        $row = $this->pdo->query("SELECT status, dispute_status FROM tickets WHERE id = $ticketId")->fetch();
        $this->assertSame('disputed', $row['status']);
        $this->assertSame('pending', $row['dispute_status']);

        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'ticket.dispute_auto_dismissed'")->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testDispatchOrderDismissesThenExpires(): void
    {
        // Per D-07 + PRD §4.2 composition note: the dispute sweep
        // runs BEFORE the expiry sweep so a disputed-then-expired
        // ticket lands in 'expired' in the same tick. We seed a
        // ticket that is BOTH disputed (>3 days) AND expired.
        // Expected end state: status='expired', dispute_status='rejected'.
        $seller = $this->seedUser();
        $buyer = $this->seedUser();
        $adminId = $this->seedAdminUser();
        $catId = $this->firstCategoryId();

        $listingId = $this->seedListing($seller, $catId);
        $disputedAt = (new \DateTime('-4 days', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $expiresAt = (new \DateTime('-1 hour', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $createdAt = (new \DateTime('-10 days', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $code = \App\Ticket\Model\ticket_model::formatCode(random_bytes(16));
        $stmt = $this->pdo->prepare(
            'INSERT INTO tickets (ticket_code, listing_id, buyer_id, seller_id, status, dispute_status, '
            . 'price_cents, session_number, total_sessions, expires_at, disputed_at, '
            . 'created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, \'disputed\', \'pending\', ?, 1, 1, ?, ?, ?, ?)'
        );
        $stmt->execute([$code, $listingId, $buyer, $seller, 10000, $expiresAt, $disputedAt, $createdAt, $createdAt]);
        $ticketId = (int) $this->pdo->lastInsertId();

        $payload = $this->dispatchCron($adminId);
        $this->assertSame(200, $payload['status']);
        $this->assertTrue($payload['body']['ok']);
        $this->assertSame(1, (int) $payload['body']['sweeps']['dispute_auto_dismiss']['processed']);
        $this->assertSame(1, (int) $payload['body']['sweeps']['ticket_expiry']['processed']);

        // End state: ticket.status='expired' (expiry sweep ran AFTER
        // dispute dismiss), dispute_status='rejected'.
        $row = $this->pdo->query("SELECT status, dispute_status FROM tickets WHERE id = $ticketId")->fetch();
        $this->assertSame('expired', $row['status']);
        $this->assertSame('rejected', $row['dispute_status']);
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

        $capturePath = tempnam(sys_get_temp_dir(), 'cron-dispute-');
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
