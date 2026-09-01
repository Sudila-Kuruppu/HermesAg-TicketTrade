<?php
/**
 * Phase 3 — Integration Test Fixtures
 *
 * Sets up a fresh schema (via migrate.php), seeds users and categories,
 * and rolls back each test. Phase 3 has additional tables: listings,
 * listing_images, listing_revisions, categories.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase03\Fixtures;

use App\Support\Db;
use PHPUnit\Framework\TestCase;
use PDO;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__ . '/../../../..'));
}

abstract class Fixtures extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        Db::reset();
        $this->pdo = Db::pdo();
        $this->pdo->exec("SET time_zone = '+05:30'");
        $this->resetTables();
        $this->ensureCategories();
    }

    /**
     * Insert the 7 seed categories if they don't already exist.
     * The categories table is truncated in resetTables(); the seed rows
     * are re-inserted here so Phase 3 tests have a working taxonomy.
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

    protected function tearDown(): void
    {
        // Clear the rate-limit cache so tests don't bleed across runs.
        try {
            $this->pdo->exec('TRUNCATE TABLE cache_rate');
        } catch (\Throwable $e) {
            // ignore
        }
        Db::reset();
        parent::tearDown();
    }

    /**
     * Truncate all Phase 3 tables (preserve schema).
     * Order matters: respect FK dependencies.
     */
    protected function resetTables(): void
    {
        $tables = [
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

    protected function seedCategory(string $name = 'TestCat', int $sortOrder = 100, ?string $description = null): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO categories (name, description, sort_order, is_active, created_at) '
            . 'VALUES (?, ?, ?, 1, NOW())'
        );
        $stmt->execute([$name, $description, $sortOrder]);
        return (int) $this->pdo->lastInsertId();
    }
}
