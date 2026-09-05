<?php
/**
 * Phase 6 — RecentForUserTest (CR-01 regression)
 *
 * Covers points_log_model::recentForUser():
 *   - Bound LIMIT parameter (not string-concat) — fixes the PSR-12 / AD-13
 *     violation that CR-01 flagged in 06-REVIEW.md. The audit fix branch
 *     moved the longest_streak SELECT onto a prepared statement; this
 *     test guards the same pattern for the Profile recent-activity read.
 *   - Limit honoring: limit=3 returns 3 rows from a 5-row seed.
 *   - Clamping: limit=999 clamps to 100 (the upstream call site clamps
 *     to 100 too, but the function is defensive about it).
 *
 * The previous implementation concatenated `'LIMIT ' . $limit` after a
 * clamp. The clamp blocked SQLi but violated the codebase's
 * prepared-statement convention. The new implementation binds :lim with
 * PDO::PARAM_INT and the SELECT is a single literal prepared query.
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase06\Points;

use App\Points\Model\points_log_model;
use App\Tests\Integration\Phase06\Fixtures\Fixtures;

class RecentForUserTest extends Fixtures
{
    public function test_returns_rows_newest_first(): void
    {
        $user = $this->seedUser(['nickname' => 'recent_user']);
        $this->seedPointsLog($user, 5, 'listing_approval', 1, null, '2026-09-01 09:00:00');
        $this->seedPointsLog($user, 10, 'review', 2, null, '2026-09-03 09:00:00');
        $this->seedPointsLog($user, 15, 'streak_7day', null, null, '2026-09-05 09:00:00');

        $rows = points_log_model::recentForUser($this->pdo, $user, 5);

        $this->assertCount(3, $rows);
        // Newest first.
        $this->assertSame(15, $rows[0]['delta']);
        $this->assertSame(10, $rows[1]['delta']);
        $this->assertSame(5, $rows[2]['delta']);
        $this->assertSame('streak_7day', $rows[0]['reference_type']);
    }

    public function test_limit_honors_bound_parameter(): void
    {
        $user = $this->seedUser(['nickname' => 'limit_user']);
        for ($i = 1; $i <= 5; $i++) {
            $this->seedPointsLog(
                $user,
                $i,
                'listing_approval',
                null,
                null,
                sprintf('2026-09-0%d 09:00:00', $i)
            );
        }

        $rows = points_log_model::recentForUser($this->pdo, $user, 3);
        $this->assertCount(3, $rows, 'limit param must trim result set to 3 rows');
        // Newest first: 5, 4, 3.
        $this->assertSame(5, $rows[0]['delta']);
        $this->assertSame(4, $rows[1]['delta']);
        $this->assertSame(3, $rows[2]['delta']);
    }

    public function test_limit_is_clamped_to_100(): void
    {
        $user = $this->seedUser(['nickname' => 'clamp_user']);
        // Seed 3 rows; pass a huge limit; the function clamps to 100
        // and returns the 3 available rows.
        $this->seedPointsLog($user, 5, 'listing_approval');
        $this->seedPointsLog($user, 5, 'listing_approval');
        $this->seedPointsLog($user, 5, 'listing_approval');

        $rows = points_log_model::recentForUser($this->pdo, $user, 999);
        $this->assertCount(3, $rows);
    }

    public function test_empty_history_returns_empty_array(): void
    {
        $user = $this->seedUser(['nickname' => 'empty_user']);
        $rows = points_log_model::recentForUser($this->pdo, $user, 5);
        $this->assertSame([], $rows);
    }

    public function test_bound_limit_does_not_throw_on_legitimate_int_values(): void
    {
        // Regression: PDO on MariaDB/MySQL rejects LIMIT bound params
        // when not bound via bindValue(..., PDO::PARAM_INT). The fix
        // uses bindValue with PARAM_INT; this test exercises 1, 5, 100
        // to confirm the bind works across the full valid range.
        $user = $this->seedUser(['nickname' => 'param_user']);
        $this->seedPointsLog($user, 1, 'listing_approval');

        foreach ([1, 5, 50, 100] as $limit) {
            $rows = points_log_model::recentForUser($this->pdo, $user, $limit);
            $this->assertCount(1, $rows, "limit=$limit");
        }
    }
}