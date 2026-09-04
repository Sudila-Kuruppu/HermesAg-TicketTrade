<?php
/**
 * Phase 6 — AwardStreakBonusTest
 *
 * Covers points_service::awardStreakBonus():
 *   - 7-day streak -> +15 with reference_type='streak_7day'.
 *   - 30-day streak -> +50 with reference_type='streak_30day'.
 *   - streakDays in {3, 14, 60} -> E_VALIDATION, no row.
 *   - Frozen short-circuit.
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase06\Points;

use App\Points\Service\points_service;
use App\Tests\Integration\Phase06\Fixtures\Fixtures;

class AwardStreakBonusTest extends Fixtures
{
    public function test_seven_day_streak_awards_15(): void
    {
        $user = $this->seedUser(['nickname' => 'streaker', 'redeemed_count' => 0]);
        $res = points_service::awardStreakBonus($user, 7);
        $this->assertTrue($res['ok']);
        $this->assertSame(15, (int) $res['data']['delta']);
        $this->assertSame(15, (int) $res['data']['balance_after']);

        $row = $this->pdo->query(
            'SELECT * FROM points_log WHERE user_id = ' . $user . ' ORDER BY id'
        )->fetch();
        $this->assertSame('streak_7day', (string) $row['reference_type']);
        $this->assertSame(7, (int) $row['reference_id']);
        $this->assertSame(15, (int) $row['delta']);
        $meta = json_decode((string) $row['metadata'], true);
        $this->assertSame(7, (int) $meta['streak_days']);
    }

    public function test_thirty_day_streak_awards_50(): void
    {
        $user = $this->seedUser(['nickname' => 'thirty', 'redeemed_count' => 0]);
        $res = points_service::awardStreakBonus($user, 30);
        $this->assertTrue($res['ok']);
        $this->assertSame(50, (int) $res['data']['delta']);

        $row = $this->pdo->query(
            'SELECT * FROM points_log WHERE user_id = ' . $user . ' ORDER BY id'
        )->fetch();
        $this->assertSame('streak_30day', (string) $row['reference_type']);
        $this->assertSame(30, (int) $row['reference_id']);
        $this->assertSame(50, (int) $row['delta']);
        $meta = json_decode((string) $row['metadata'], true);
        $this->assertSame(30, (int) $meta['streak_days']);
    }

    /**
 *     @dataProvider invalidStreakDaysProvider
 */
    public function test_invalid_streak_days_rejected(int $days): void
    {
        $user = $this->seedUser(['nickname' => 'bad', 'redeemed_count' => 0]);
        $res = points_service::awardStreakBonus($user, $days);
        $this->assertFalse($res['ok']);
        $this->assertSame('E_VALIDATION', $res['error']['code']);
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM points_log')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public static function invalidStreakDaysProvider(): array
    {
        return [
            'three' => [3],
            'fourteen' => [14],
            'sixty' => [60],
        ];
    }

    public function test_frozen_short_circuit(): void
    {
        $user = $this->seedUser([
            'nickname' => 'frozen',
            'redeemed_count' => 0,
            'points_frozen' => true,
        ]);
        $res = points_service::awardStreakBonus($user, 7);
        $this->assertTrue($res['ok']);
        $this->assertSame('points_frozen', $res['data']['skipped']);
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM points_log')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function test_no_halving(): void
    {
        // Per D-15 the multiplier is transaction-only; streak bonuses
        // award the full delta at any redeemed_count.
        $user = $this->seedUser(['nickname' => 'fresh', 'redeemed_count' => 0]);
        $res = points_service::awardStreakBonus($user, 7);
        $this->assertSame(15, (int) $res['data']['delta']);
    }
}