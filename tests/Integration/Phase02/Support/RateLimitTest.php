<?php
/**
 * Phase 2 — RateLimitTest
 *
 * Verifies Support\RateLimit::hit() enforces 5/5min/IP for the `login`
 * bucket. Six hits within the window: the sixth returns
 * allowed=false and retry_after > 0.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase02\Support;

use App\Tests\Integration\Phase02\Fixtures\Fixtures;
use App\Support\RateLimit;

class RateLimitTest extends Fixtures
{
    public function test_sixth_login_hit_is_blocked(): void
    {
        // Clear cache_rate so each test starts fresh
        $this->pdo->exec('TRUNCATE TABLE cache_rate');

        $ip = '192.168.1.1';
        for ($i = 1; $i <= 5; $i++) {
            $r = RateLimit::hit('login', $ip);
            $this->assertTrue($r['allowed'], "Hit $i should be allowed");
            $this->assertSame($i, $r['count']);
        }
        $r = RateLimit::hit('login', $ip);
        $this->assertFalse($r['allowed'], 'Sixth hit must be blocked');
        $this->assertSame(6, $r['count']);
        $this->assertGreaterThanOrEqual(0, $r['retry_after']);
        $this->assertLessThanOrEqual(300, $r['retry_after']);
    }

    public function test_different_routes_have_separate_buckets(): void
    {
        $this->pdo->exec('TRUNCATE TABLE cache_rate');
        $r = RateLimit::hit('register', '10.0.0.1');
        $this->assertTrue($r['allowed']);
        // login on the same IP should still be allowed (different bucket)
        $r = RateLimit::hit('login', '10.0.0.1');
        $this->assertTrue($r['allowed']);
    }

    public function test_unknown_route_is_always_allowed(): void
    {
        $r = RateLimit::hit('nonexistent_route', '127.0.0.1');
        $this->assertTrue($r['allowed']);
        $this->assertSame(0, $r['count']);
    }
}
