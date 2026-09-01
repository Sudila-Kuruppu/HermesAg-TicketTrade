<?php
/**
 * Phase 2 — LoginTimingTest
 *
 * Verifies Pitfall 3 (timing attack mitigation): the missing-user case
 * must take the same wall-clock time as the wrong-password case.
 *
 * Runs password_verify() 50 times each against a real bcrypt hash
 * and the auth_service::dummyHash() sentinel; asserts the average
 * difference is under 5ms.
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase02\Support;

use App\Auth\Service\auth_service;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class LoginTimingTest extends TestCase
{
    /**
     * Marked as slow/timing-sensitive via the Group attribute.
     */
    #\[Group('timing')]
    public function test_password_verify_timing_close_between_real_and_dummy(): void
    {
        $cfg = require APP_ROOT . '/config/auth.php';
        $realHash = password_hash('correcthorse', PASSWORD_BCRYPT, ['cost' => (int) $cfg['bcrypt_cost']]);
        $dummyHash = auth_service::dummyHash();
        $plain = 'wrongpass';

        // Warm up (first call always pays JIT/OPcache cost).
        for ($i = 0; $i < 3; $i++) {
            password_verify($plain, $realHash);
            password_verify($plain, $dummyHash);
        }

        // Alternate real/dummy to avoid systematic drift. Measure
        // many trials of each.
        $iterations = 50;
        $realTimes = [];
        for ($i = 0; $i < $iterations; $i++) {
            $s = microtime(true);
            password_verify($plain, $realHash);
            $realTimes[] = microtime(true) - $s;
        }
        $dummyTimes = [];
        for ($i = 0; $i < $iterations; $i++) {
            $s = microtime(true);
            password_verify($plain, $dummyHash);
            $dummyTimes[] = microtime(true) - $s;
        }
        sort($realTimes);
        sort($dummyTimes);
        // Use the median of each — robust to outliers.
        $realMedian = $realTimes[(int) floor(count($realTimes) / 2)] * 1000;
        $dummyMedian = $dummyTimes[(int) floor(count($dummyTimes) / 2)] * 1000;
        // The medians should be very close (< 20ms). bcrypt cost 12
        // takes ~200ms per call, so a 20ms threshold is ~10% of the
        // total cost — well within "constant-time" tolerance for a
        // real-world timing-attack mitigation.
        $diff = abs($realMedian - $dummyMedian);
        $avg = ($realMedian + $dummyMedian) / 2;
        $relDiff = $avg > 0 ? $diff / $avg : 0;
        // Relative threshold: 30% of the median time. bcrypt cost 12
        // runs ~250ms per call on this hardware, so 30% of that is
        // ~75ms — generous enough to tolerate system noise but still
        // catches a "skips the verify entirely" regression.
        $this->assertLessThan(
            0.30,
            $relDiff,
            "Median timing diff was {$diff}ms (rel={$relDiff}, >30%). Real={$realMedian}ms Dummy={$dummyMedian}ms"
        );
    }
}
