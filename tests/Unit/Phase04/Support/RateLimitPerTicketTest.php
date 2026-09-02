<?php
/**
 * Phase 4 — RateLimitPerTicketTest (unit)
 *
 * Covers Support\RateLimit::hit($route, $ip, $key):
 *   - When $key is non-empty, the bucket key is composed with the
 *     $key included (so different ticket+user scopes do not share
 *     a counter).
 *   - When $key is empty, the legacy bucket shape is preserved.
 *   - 6th call on the same key within window fails.
 *   - 1st call on a different key succeeds even when the previous
 *     scope is exhausted.
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase04\Support;

use App\Support\Db;
use App\Support\RateLimit;
use App\Tests\Integration\Phase04\Fixtures\Fixtures;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__ . '/../../../..'));
}

require_once APP_ROOT . '/vendor/autoload.php';

class RateLimitPerTicketTest extends Fixtures
{
    public function test_empty_key_uses_legacy_bucket_shape(): void
    {
        // Clean cache_rate.
        $this->pdo->exec('TRUNCATE TABLE cache_rate');
        RateLimit::hit('redemption', '127.0.0.1', '');
        $rows = $this->pdo->query("SELECT rate_key FROM cache_rate")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertCount(1, $rows);
        $this->assertStringStartsWith('redemption:ip:127.0.0.1:', $rows[0]);
    }

    public function test_non_empty_key_includes_key_in_bucket(): void
    {
        $this->pdo->exec('TRUNCATE TABLE cache_rate');
        RateLimit::hit('redemption', '127.0.0.1', 'ticket:42:7');
        $rows = $this->pdo->query("SELECT rate_key FROM cache_rate")->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertCount(1, $rows);
        $key = $rows[0];
        $this->assertStringStartsWith('redemption:ticket:42:7:ip=127.0.0.1:', $key);
    }

    public function test_different_keys_have_independent_buckets(): void
    {
        $this->pdo->exec('TRUNCATE TABLE cache_rate');
        // Hit ticket:42:7 five times (max is 5).
        for ($i = 0; $i < 5; $i++) {
            $r = RateLimit::hit('redemption', '127.0.0.1', 'ticket:42:7');
            $this->assertTrue($r['allowed'], "Call " . ($i+1) . " should be allowed");
        }
        $r = RateLimit::hit('redemption', '127.0.0.1', 'ticket:42:7');
        $this->assertFalse($r['allowed'], '6th call on same key should be blocked');

        // Different ticket key: 1st call should be allowed.
        $r = RateLimit::hit('redemption', '127.0.0.1', 'ticket:43:7');
        $this->assertTrue($r['allowed'], '1st call on a different key should succeed');

        // Different user on same ticket: 1st call should be allowed.
        $r = RateLimit::hit('redemption', '127.0.0.1', 'ticket:42:8');
        $this->assertTrue($r['allowed'], '1st call on same ticket/different user should succeed');
    }

    public function test_purchase_limit_per_user(): void
    {
        $this->pdo->exec('TRUNCATE TABLE cache_rate');
        // 10/hr max for purchase.
        for ($i = 0; $i < 10; $i++) {
            $r = RateLimit::hit('purchase', '127.0.0.1', '7');
            $this->assertTrue($r['allowed'], "Call " . ($i+1) . " should be allowed");
        }
        $r = RateLimit::hit('purchase', '127.0.0.1', '7');
        $this->assertFalse($r['allowed'], '11th call should be blocked');

        // Different user: allowed.
        $r = RateLimit::hit('purchase', '127.0.0.1', '8');
        $this->assertTrue($r['allowed'], 'Different user should be allowed');
    }
}
