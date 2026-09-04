<?php
/**
 * Phase 6 — TierFromPointsTest
 *
 * Verifies the canonical tierFromPoints() helper (config/ranks.php +
 * auth_service::tierFromPoints) resolves the 6-tier ladder correctly
 * at every threshold boundary. Per the plan's acceptance criteria,
 * the test exercises 0/49/50/149/150/399/400/799/800/1499/1500/50000.
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase06\Points;

use App\Auth\Service\auth_service;
use App\Tests\Integration\Phase06\Fixtures\Fixtures;

class TierFromPointsTest extends Fixtures
{
    /**
     * Data provider: [points, expectedTier].
     */
    public static function boundaryProvider(): array
    {
        return [
            'zero is E' => [0, 'E'],
            '49 is E' => [49, 'E'],
            '50 is D' => [50, 'D'],
            '149 is D' => [149, 'D'],
            '150 is C' => [150, 'C'],
            '399 is C' => [399, 'C'],
            '400 is B' => [400, 'B'],
            '799 is B' => [799, 'B'],
            '800 is A' => [800, 'A'],
            '1499 is A' => [1499, 'A'],
            '1500 is S' => [1500, 'S'],
            '50000 is S' => [50000, 'S'],
        ];
    }

    /**
     * @dataProvider boundaryProvider
     */
    public function test_tier_resolution_at_boundary(int $points, string $expected): void
    {
        $this->assertSame($expected, auth_service::tierFromPoints($points));
    }

    public function test_negative_points_is_E(): void
    {
        // Defensive: negative balance (shouldn't happen, but the
        // helper should be stable) resolves to E.
        $this->assertSame('E', auth_service::tierFromPoints(-100));
    }

    public function test_tier_from_config_ladder_matches(): void
    {
        // The auth helper routes through config/ranks.php. Direct
        // call to the local tierFromPoints() function should agree.
        $ladder = require APP_ROOT . '/config/ranks.php';
        $points = 200;
        $authTier = auth_service::tierFromPoints($points);
        $directTier = (function (int $p) use ($ladder): string {
            $current = 'E';
            foreach ($ladder as $tier => $def) {
                if ($p >= $def['min_points']) {
                    $current = $tier;
                }
            }
            return $current;
        })($points);
        $this->assertSame($directTier, $authTier);
    }
}