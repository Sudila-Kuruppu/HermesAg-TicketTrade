<?php
/**
 * Phase 3 — ImageProxyTest (Unit)
 *
 * Verifies ImageProxy::serve() auth + rate-limit behavior. The proxy
 * writes binary WebP bytes; we use output buffering to capture and
 * assert the response code via xdebug_get_headers (if available) or by
 * directly observing http_response_code.
 *
 * To keep the unit test self-contained, we stub the file lookup by
 * writing one real WebP file (via GD) to the storage root and inserting
 * the listing_images row via the DB.
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase03\Support;

use App\Support\ImageProxy;
use App\Support\RateLimit;
use PHPUnit\Framework\TestCase;

class ImageProxyTest extends TestCase
{
    /**
     * Monotonic counter so seedCategory() / seedUser() generate unique
     * sort_order / student_id values across every test invocation in the
     * suite. Categories has UNIQUE KEY uniq_categories_sort and
     * UNIQUE KEY uniq_categories_name; users has UNIQUE KEY on email,
     * student_id, nickname. random_int collisions against those indexes
     * were the source of the ~25% flake rate under --random-order-seed
     * sweep. Static counters reset per PHPUnit run (PHP process restarts).
     */
    private static int $catSeq = 1000;
    private static int $userSeq = 10000;

    public static function setUpBeforeClass(): void
    {
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', dirname(__DIR__, 4));
        }
        if (!class_exists('App\\Support\\Db')) {
            return;
        }
        // Best-effort DB connection.
        try {
            \App\Support\Db::pdo();
        } catch (\Throwable $e) {
            // Tests will skip when DB is unavailable.
            return;
        }
        // Seed counters from the existing max so monotonic sequences stay
        // unique even when the test DB already has leftover categories /
        // users from a prior phpunit invocation. (bin/test drops+remigrates
        // so this branch only matters when running phpunit directly.)
        try {
            $pdo = \App\Support\Db::pdo();
            $maxSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM categories')->fetchColumn();
            $maxUid = (int) $pdo->query('SELECT COALESCE(MAX(user_id), 0) FROM users')->fetchColumn();
            if ($maxSort >= self::$catSeq) {
                self::$catSeq = $maxSort + 1;
            }
            if ($maxUid >= self::$userSeq) {
                self::$userSeq = $maxUid + 1;
            }
        } catch (\Throwable $e) {
            // Tables may not exist yet — counters stay at their defaults.
        }
    }

    private function skipIfNoDb(): void
    {
        try {
            \App\Support\Db::pdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('ImageProxy test requires DB: ' . $e->getMessage());
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfNoDb();
        // Override storage_root to /tmp (project uploads dir may be on a
        // full filesystem).
        $tmpRoot = sys_get_temp_dir() . '/tt-proxy-' . bin2hex(random_bytes(4));
        @mkdir($tmpRoot, 0775, true);
        putenv('UPLOAD_STORAGE_ROOT=' . $tmpRoot);
        // Clear any existing rate-limit state for this IP/user.
        // FK checks must be off: cron_log.actor_user_id references users.
        $pdo = \App\Support\Db::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec('TRUNCATE TABLE cache_rate');
        $pdo->exec('TRUNCATE TABLE users');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        // Clear session for the test.
        $_SESSION = [];
        $_COOKIE = [];
    }

    public function test_full_size_without_session_returns_404(): void
    {
        // listing_id that doesn't exist; with no session, full size must 404.
        ob_start();
        ImageProxy::serve(99999, 'full');
        $body = ob_get_clean();

        $this->assertSame(404, http_response_code());
    }

    public function test_invalid_size_returns_404(): void
    {
        ob_start();
        ImageProxy::serve(1, 'huge');
        ob_end_clean();
        $this->assertSame(404, http_response_code());
    }

    public function test_missing_listing_returns_404_for_thumb(): void
    {
        ob_start();
        ImageProxy::serve(99999, 'thumb');
        ob_end_clean();
        $this->assertSame(404, http_response_code());
    }

    public function test_thumb_rate_limit_returns_429_after_cap(): void
    {
        $pdo = \App\Support\Db::pdo();
        $pdo->exec('TRUNCATE TABLE cache_rate');

        // Insert one listing + listing_images row + a real WebP file so the
        // first 60 calls succeed.
        $seller = $this->seedUser();
        $cat = $this->seedCategory();
        $pdo->query("INSERT INTO listings (seller_id, category_id, title, description, price_cents, type, quantity, status, created_at, updated_at) VALUES ($seller, $cat, 'T', 'd', 100, 'product', 1, 'active', NOW(), NOW())");
        $listingId = (int) $pdo->lastInsertId();

        $storage = require APP_ROOT . '/config/uploads.php';
        $root = (string) $storage['storage_root'];
        if (!is_dir($root)) {
            @mkdir($root, 0775, true);
        }
        $sha = str_repeat('a', 64);
        foreach (['thumb', 'medium', 'full'] as $sz) {
            $path = sprintf('%s/%s_%s.webp', $root, $sha, $sz);
            // Write a minimal valid WebP file (1x1 pixel transparent).
            $bytes = base64_decode('UklGRiQAAABXRUJQVlA4IBgAAAAwAQCdASoBAAEAAwA0JaQAA3AA/vuUAAA=');
            file_put_contents($path, $bytes);
            $pdo->prepare("INSERT INTO listing_images (listing_id, sha256, size, is_primary, sort_order, created_at) VALUES (?, ?, ?, 1, 1, NOW())")
                ->execute([$listingId, $sha, $sz]);
        }

        $ip = '10.0.0.99';
        $_SERVER['REMOTE_ADDR'] = $ip;

        for ($i = 0; $i < 60; $i++) {
            ob_start();
            ImageProxy::serve($listingId, 'thumb');
            ob_end_clean();
            $code = http_response_code();
            $this->assertNotSame(429, $code, "Hit $i should not be rate-limited");
        }

        // 61st hit should be 429.
        ob_start();
        ImageProxy::serve($listingId, 'thumb');
        $body = ob_get_clean();
        $this->assertSame(429, http_response_code());
        $this->assertStringContainsString('Rate limit exceeded', $body);

        // Cleanup
        @unlink(sprintf('%s/%s_thumb.webp', $root, $sha));
        @unlink(sprintf('%s/%s_medium.webp', $root, $sha));
        @unlink(sprintf('%s/%s_full.webp', $root, $sha));
        $pdo->exec('DELETE FROM listing_images WHERE listing_id = ' . $listingId);
        $pdo->exec('DELETE FROM listings WHERE id = ' . $listingId);
    }

    public function test_full_size_for_logged_in_non_seller_non_admin_returns_404(): void
    {
        $pdo = \App\Support\Db::pdo();
        $pdo->exec('TRUNCATE TABLE cache_rate');

        $seller = $this->seedUser(['nickname' => 'seller']);
        $other = $this->seedUser(['nickname' => 'other', 'email' => 'o@students.nsbm.ac.lk', 'student_id' => 'NSBM/002']);
        $cat = $this->seedCategory();

        $stmt = $pdo->prepare("INSERT INTO listings (seller_id, category_id, title, description, price_cents, type, quantity, status, created_at, updated_at) VALUES (?, ?, 'T', 'd', 100, 'product', 1, 'active', NOW(), NOW())");
        $stmt->execute([$seller, $cat]);
        $listingId = (int) $pdo->lastInsertId();
        // Insert listing_images row for full size.
        $sha = str_repeat('b', 64);
        $pdo->prepare("INSERT INTO listing_images (listing_id, sha256, size, is_primary, sort_order, created_at) VALUES (?, ?, 'full', 1, 1, NOW())")->execute([$listingId, $sha]);
        $storage = require APP_ROOT . '/config/uploads.php';
        $root = (string) $storage['storage_root'];
        file_put_contents(sprintf('%s/%s_full.webp', $root, $sha), base64_decode('UklGRiQAAABXRUJQVlA4IBgAAAAwAQCdASoBAAEAAwA0JaQAA3AA/vuUAAA='));

        $_SESSION['user_id'] = $other;
        $_SERVER['REMOTE_ADDR'] = '10.0.0.100';

        ob_start();
        ImageProxy::serve($listingId, 'full');
        ob_end_clean();
        $this->assertSame(404, http_response_code());

        // Cleanup
        @unlink(sprintf('%s/%s_full.webp', $root, $sha));
        $pdo->exec('DELETE FROM listing_images WHERE listing_id = ' . $listingId);
        $pdo->exec('DELETE FROM listings WHERE id = ' . $listingId);
    }

    private function seedUser(array $overrides = []): int
    {
        $pdo = \App\Support\Db::pdo();
        $now = date('Y-m-d H:i:s');
        $uid = ++self::$userSeq;
        $defaults = [
            'email' => 'u' . $uid . '@students.nsbm.ac.lk',
            'student_id' => 'NSBM/' . $uid,
            'nickname' => 'u',
            'password_hash' => password_hash('x', PASSWORD_BCRYPT, ['cost' => 12]),
            'full_name' => 'U',
            'bio' => '', 'whatsapp' => null, 'avatar_id' => 1,
            'points' => 0, 'points_frozen' => 0, 'tier' => 'E',
            'is_admin' => 0, 'is_banned' => 0, 'is_verified' => 0,
        ];
        $data = array_merge($defaults, $overrides);
        $stmt = $pdo->prepare(
            'INSERT INTO users (email, student_id, nickname, password_hash, full_name, bio, whatsapp, '
            . 'avatar_id, points, points_frozen, tier, is_admin, is_banned, is_verified, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (string) $data['email'], (string) $data['student_id'],
            (string) $data['nickname'], (string) $data['password_hash'],
            (string) $data['full_name'], (string) $data['bio'],
            $data['whatsapp'], (int) $data['avatar_id'], (int) $data['points'],
            (int) $data['points_frozen'], (string) $data['tier'],
            (int) $data['is_admin'], (int) $data['is_banned'], (int) $data['is_verified'],
            $now, $now,
        ]);
        return (int) $pdo->lastInsertId();
    }

    private function seedCategory(): int
    {
        $pdo = \App\Support\Db::pdo();
        $sort = ++self::$catSeq;
        $name = 'T' . $sort;
        $pdo->prepare("INSERT INTO categories (name, description, sort_order, is_active, created_at) VALUES (?, 'desc', ?, 1, NOW())")
            ->execute([$name, $sort]);
        return (int) $pdo->lastInsertId();
    }
}
