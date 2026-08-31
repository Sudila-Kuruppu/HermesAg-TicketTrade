<?php
/**
 * Phase 2 — Integration Test Fixtures
 *
 * Test base class that sets up a fresh tickettrade_test schema and
 * provides helpers for seeding users, sessions, and tokens.
 *
 * Defines APP_ROOT for tests that load it via require.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase02\Fixtures;

use App\Support\Db;
use PHPUnit\Framework\TestCase;
use PDO;

// Ensure APP_ROOT is defined before any code that uses it runs.
if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__ . '/../../../..'));
}

abstract class Fixtures extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        // Reset the singleton so each test gets a fresh PDO
        Db::reset();
        $this->pdo = Db::pdo();
        $this->resetTables();
    }

    protected function tearDown(): void
    {
        Db::reset();
        parent::tearDown();
    }

    /**
     * Truncate all 7 Phase 2 tables (preserve schema).
     */
    protected function resetTables(): void
    {
        $tables = [
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
                // Table may not exist; ignore.
            }
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Insert a user row with default profile fields. Returns user_id.
     */
    protected function seedUser(array $overrides = []): int
    {
        $now = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $defaults = [
            'email' => 'kasun@students.nsbm.ac.lk',
            'student_id' => 'NSBM/2023/001',
            'nickname' => 'kasun',
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
        ];
        $data = array_merge($defaults, $overrides);
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, student_id, nickname, password_hash, full_name, bio, whatsapp, '
            . 'avatar_id, points, points_frozen, tier, is_admin, is_banned, is_verified, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
            (string) $now,
            (string) $now,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    protected function seedSession(string $sessionId, int $userId, string $lastSeen = null): void
    {
        $now = $lastSeen ?: (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions (session_id, user_id, last_seen, ip, user_agent, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$sessionId, $userId, $now, null, 'phpunit', $now]);
    }
}
