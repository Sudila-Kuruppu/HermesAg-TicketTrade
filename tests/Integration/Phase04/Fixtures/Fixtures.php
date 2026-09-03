<?php
/**
 * Phase 4 — Integration Test Fixtures
 *
 * Extends the Phase 3 fixtures to also reset the Phase 4 tables:
 * tickets, reports, audit_log. Adds Phase 4-specific seed helpers
 * (seedTicket, seedAdminUser, seedServiceListing, dispatchAction).
 *
 * The fixture base class already handles categories + listings + users
 * reset; Phase 4 extends resetTables() to include the new tables.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase04\Fixtures;

use App\Support\Db;
use PHPUnit\Framework\TestCase;
use PDO;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__ . '/../../../..'));
}

abstract class Fixtures extends TestCase
{
    protected PDO $pdo;
    private static int $seedCounter = 0;

    private function nextEmail(): string
    {
        self::$seedCounter++;
        return 'user' . self::$seedCounter . '@students.nsbm.ac.lk';
    }

    private function nextStudentId(): string
    {
        self::$seedCounter++;
        return 'NSBM/2023/' . str_pad((string) self::$seedCounter, 3, '0', STR_PAD_LEFT);
    }

    private function nextNickname(): string
    {
        self::$seedCounter++;
        return 'user' . self::$seedCounter;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['current_user'] = null;
        $GLOBALS['_tt_view_vars'] = [];
        $GLOBALS['_tt_content_view'] = '';
        $GLOBALS['_tt_surface'] = null;
        $GLOBALS['_tt_path_params'] = [];
        Db::reset();
        $this->pdo = Db::pdo();
        $this->pdo->exec("SET time_zone = '+05:30'");
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        $this->resetTables();
        $this->ensureCategories();
    }

    protected function tearDown(): void
    {
        try {
            $this->pdo->exec('TRUNCATE TABLE cache_rate');
        } catch (\Throwable $e) {
            // ignore
        }
        Db::reset();
        parent::tearDown();
    }

    /**
     * Truncate all Phase 4 tables (preserve schema).
     * Order matters: respect FK dependencies.
     */
    protected function resetTables(): void
    {
        $tables = [
            'audit_log',
            'reviews',
            'reports',
            'tickets',
            'cron_log',
            'listing_revisions',
            'listing_images',
            'listings',
            'categories',
            'cache_rate',
            'email_verifications',
            'password_resets',
            'points_log',
            'sessions',
            'student_id_allowlist',
            'users',
        ];
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $t) {
            try {
                $this->pdo->exec('TRUNCATE TABLE ' . $t);
            } catch (\Throwable $e) {
                // ignore
            }
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Insert the 7 seed categories if they don't already exist.
     */
    protected function ensureCategories(): void
    {
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
        if ($count >= 7) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO categories (name, description, sort_order, is_active, created_at) '
            . 'VALUES (?, ?, ?, 1, NOW())'
        );
        $rows = [
            ['Textbooks',  'Course books, reference material, notes',     1],
            ['Electronics','Phones, laptops, accessories, gadgets',       2],
            ['Fashion',    'Clothing, shoes, accessories',                3],
            ['Services',   'Tutoring, design, freelance help',            4],
            ['Food',       'Homemade, snacks, baked goods',               5],
            ['Events',     'Tickets, group buys, event services',         6],
            ['Other',      'Anything else campus-trade',                   7],
        ];
        foreach ($rows as $r) {
            $stmt->execute($r);
        }
    }

    protected function seedUser(array $overrides = []): int
    {
        $now = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $defaults = [
            'email' => $this->nextEmail(),
            'student_id' => $this->nextStudentId(),
            'nickname' => $this->nextNickname(),
            'password_hash' => password_hash('test', PASSWORD_BCRYPT, ['cost' => 12]),
            'full_name' => 'Kasun Perera',
            'bio' => '',
            'whatsapp' => null,
            'avatar_id' => 1,
            'points' => 0,
            'points_frozen' => false,
            'tier' => 'E',
            'is_admin' => false,
            'is_banned' => false,
            'is_verified' => false,
            'redeemed_count' => 0,
        ];
        $data = array_merge($defaults, $overrides);
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, student_id, nickname, password_hash, full_name, bio, whatsapp, '
            . 'avatar_id, points, points_frozen, tier, is_admin, is_banned, is_verified, redeemed_count, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (string) $data['email'],
            (string) $data['student_id'],
            (string) $data['nickname'],
            (string) $data['password_hash'],
            (string) ($data['full_name'] ?? ''),
            (string) ($data['bio'] ?? ''),
            $data['whatsapp'],
            (int) $data['avatar_id'],
            (int) $data['points'],
            (int) (bool) ($data['points_frozen'] ?? false),
            (string) $data['tier'],
            (int) (bool) ($data['is_admin'] ?? false),
            (int) (bool) ($data['is_banned'] ?? false),
            (int) (bool) ($data['is_verified'] ?? false),
            (int) ($data['redeemed_count'] ?? 0),
            (string) $now,
            (string) $now,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    protected function seedAdminUser(): int
    {
        return $this->seedUser([
            'email' => 'admin@students.nsbm.ac.lk',
            'student_id' => 'NSBM/2023/ADM',
            'nickname' => 'admin',
            'is_admin' => true,
        ]);
    }

    protected function seedCategory(string $name = 'TestCat', int $sortOrder = 100, ?string $description = null): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO categories (name, description, sort_order, is_active, created_at) '
            . 'VALUES (?, ?, ?, 1, NOW())'
        );
        $stmt->execute([$name, $description, $sortOrder]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Seed an active listing (defaults to product, quantity=1).
     */
    protected function seedListing(int $sellerId, int $categoryId, array $overrides = []): int
    {
        $defaults = [
            'title' => 'Test Item',
            'description' => 'Test description.',
            'price_cents' => 100_00,
            'type' => 'product',
            'condition' => 'like_new',
            'duration_minutes' => null,
            'delivery_method' => null,
            'availability' => null,
            'quantity' => 1,
            'quantity_sold' => 0,
            'status' => 'active',
        ];
        $data = array_merge($defaults, $overrides);
        $now = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO listings (seller_id, category_id, title, description, price_cents, type, '
            . '`condition`, duration_minutes, delivery_method, availability, quantity, quantity_sold, '
            . 'status, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $sellerId,
            $categoryId,
            (string) $data['title'],
            (string) $data['description'],
            (int) $data['price_cents'],
            (string) $data['type'],
            $data['condition'],
            $data['duration_minutes'],
            $data['delivery_method'],
            $data['availability'],
            (int) $data['quantity'],
            (int) $data['quantity_sold'],
            (string) $data['status'],
            $now,
            $now,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert a tickets row directly with controlled values (bypasses
     * the ticket_service so tests can set custom dates, status, etc).
     */
    protected function seedTicket(array $overrides = []): int
    {
        $defaults = [
            'ticket_code' => \App\Ticket\Model\ticket_model::formatCode(random_bytes(16)),
            'listing_id' => 1,
            'buyer_id' => 2,
            'seller_id' => 1,
            'status' => 'active',
            'dispute_status' => 'none',
            'price_cents' => 100_00,
            'session_number' => 1,
            'total_sessions' => 1,
        ];
        $data = array_merge($defaults, $overrides);
        $now = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $expiresAt = (new \DateTime('+7 days', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO tickets (ticket_code, listing_id, buyer_id, seller_id, status, dispute_status, '
            . 'price_cents, session_number, total_sessions, expires_at, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (string) $data['ticket_code'],
            (int) $data['listing_id'],
            (int) $data['buyer_id'],
            (int) $data['seller_id'],
            (string) $data['status'],
            (string) $data['dispute_status'],
            (int) $data['price_cents'],
            (int) $data['session_number'],
            (int) $data['total_sessions'],
            $data['expires_at'] ?? $expiresAt,
            $data['created_at'] ?? $now,
            $now,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Look up the full user row (with is_admin, etc.) so the action can pass adminGuard.
     */
    protected function loadUserRow(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT user_id, email, student_id, nickname, full_name, whatsapp, is_admin, '
            . 'is_banned, is_verified, points, points_frozen, redeemed_count, tier '
            . 'FROM users WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        return (array) $stmt->fetch();
    }

    /**
     * Set up a session for the given user (mimics Auth::boot()).
     */
    protected function startSessionFor(int $userId): void
    {
        $sid = 'test-sid-' . bin2hex(random_bytes(4));
        $now = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $this->pdo->prepare(
            'INSERT INTO sessions (session_id, user_id, last_seen, ip, user_agent, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$sid, $userId, $now, '127.0.0.1', 'phpunit', $now]);
        $_COOKIE[session_name()] = $sid;
        $GLOBALS['current_user'] = $this->loadUserRow($userId);
    }

    /**
     * Dispatch an Action method via pcntl_fork. The Action may exit()
     * after echoing its response; the shutdown function captures the
     * body + http_response_code before the child terminates.
     *
     * @param class-string $actionClass
     * @param string $methodName
     * @param int $userId The user to authenticate as (sets session + current_user).
     * @param array $pathParams Optional route path params ($GLOBALS['_tt_path_params']).
     * @return array{status:int, body:array<string,mixed>|string}
     */
    protected function dispatchAction(
        string $actionClass,
        string $methodName,
        int $userId,
        array $pathParams = [],
        array $postVars = []
    ): array {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl_fork not available');
        }
        $this->startSessionFor($userId);
        $GLOBALS['_tt_path_params'] = $pathParams;
        // Reset POST so each test starts clean. Tests can pass
        // $postVars to inject $_POST content for the child.
        $_POST = $postVars;

        $capturePath = tempnam(sys_get_temp_dir(), 'action-');
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
            $action = new $actionClass();
            $action->$methodName();
            exit(0);
        }
        pcntl_waitpid($pid, $status);
        Db::reset();
        $this->pdo = Db::pdo();
        $this->pdo->exec("SET time_zone = '+05:30'");

        $captured = (string) file_get_contents($capturePath);
        @unlink($capturePath);
        $data = json_decode($captured, true);
        if (!is_array($data)) {
            return ['status' => 0, 'body' => $captured];
        }
        $body = $data['body'] ?? '';
        $decoded = json_decode((string) $body, true);
        return [
            'status' => (int) ($data['status'] ?? 0),
            'body' => $decoded ?? $body,
        ];
    }

    /**
     * First category_id from the seed.
     */
    protected function firstCategoryId(): int
    {
        return (int) $this->pdo->query('SELECT id FROM categories ORDER BY sort_order LIMIT 1')->fetchColumn();
    }
}
