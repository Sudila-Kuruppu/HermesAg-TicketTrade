<?php
/**
 * Phase 6 — VoidAndClearFreezeTest
 *
 * Covers points_service::voidPoints() and clearPointsFreeze():
 *   - voidPoints(50) on user with points=100 -> new_balance=50,
 *     negative-delta row written, tier recomputed.
 *   - voidPoints(100) on user with points=50 -> {ok:true, data.voided=50,
 *     balance_after=0} (floored, never below zero).
 *   - voidPoints(200) on user with points=0 -> E_VOID_INSUFFICIENT_BALANCE,
 *     no row.
 *   - clearPointsFreeze() flips points_frozen to FALSE, writes audit
 *     row 'points.unfrozen'.
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase06\Points;

use App\Points\Service\points_service;
use App\Tests\Integration\Phase06\Fixtures\Fixtures;

class VoidAndClearFreezeTest extends Fixtures
{
    public function test_void_deducts_and_writes_negative_delta_row(): void
    {
        $user = $this->seedUser(['nickname' => 'seller', 'points' => 100, 'tier' => 'D', 'redeemed_count' => 5]);

        $res = points_service::voidPoints($user, 50, 'admin manual adjustment');
        $this->assertTrue($res['ok']);
        $this->assertSame(50, (int) $res['data']['voided']);
        $this->assertSame(50, (int) $res['data']['balance_after']);
        $this->assertArrayHasKey('event_uuid', $res['data']);

        $row = $this->pdo->query('SELECT * FROM points_log WHERE user_id = ' . $user . ' ORDER BY id')->fetch();
        $this->assertSame(-50, (int) $row['delta']);
        $this->assertSame('void', (string) $row['reference_type']);
        $this->assertSame(50, (int) $row['balance_after']);
        $meta = json_decode((string) $row['metadata'], true);
        $this->assertTrue((bool) $meta['voided']);
        $this->assertSame('admin manual adjustment', $meta['reason']);

        $userRow = $this->pdo->query('SELECT points, tier FROM users WHERE user_id = ' . $user)->fetch();
        $this->assertSame(50, (int) $userRow['points']);
        $this->assertSame('D', (string) $userRow['tier']);
    }

    public function test_void_is_floored_at_zero(): void
    {
        $user = $this->seedUser(['nickname' => 'low', 'points' => 50, 'tier' => 'E', 'redeemed_count' => 5]);

        $res = points_service::voidPoints($user, 100, 'overage');
        $this->assertTrue($res['ok']);
        $this->assertSame(50, (int) $res['data']['voided']);
        $this->assertSame(0, (int) $res['data']['balance_after']);

        $row = $this->pdo->query('SELECT * FROM points_log WHERE user_id = ' . $user . ' ORDER BY id')->fetch();
        $this->assertSame(-50, (int) $row['delta']);

        $userRow = $this->pdo->query('SELECT points, tier FROM users WHERE user_id = ' . $user)->fetch();
        $this->assertSame(0, (int) $userRow['points']);
    }

    public function test_void_on_zero_balance_returns_insufficient(): void
    {
        $user = $this->seedUser(['nickname' => 'empty', 'points' => 0, 'tier' => 'E', 'redeemed_count' => 0]);

        $res = points_service::voidPoints($user, 200, 'should not write');
        $this->assertFalse($res['ok']);
        $this->assertSame('E_VOID_INSUFFICIENT_BALANCE', $res['error']['code']);

        // No points_log row.
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM points_log WHERE user_id = ' . $user)->fetchColumn();
        $this->assertSame(0, $count);

        $userRow = $this->pdo->query('SELECT points FROM users WHERE user_id = ' . $user)->fetch();
        $this->assertSame(0, (int) $userRow['points']);
    }

    public function test_void_drops_tier_when_balance_below_threshold(): void
    {
        // 100 points -> tier E; void 80 -> 20 points, still E. Use a
        // larger balance that crosses a tier boundary downward.
        $user = $this->seedUser(['nickname' => 'downgrade', 'points' => 200, 'tier' => 'C', 'redeemed_count' => 5]);
        $res = points_service::voidPoints($user, 150, 'penalty');
        $this->assertTrue($res['ok']);
        $userRow = $this->pdo->query('SELECT points, tier FROM users WHERE user_id = ' . $user)->fetch();
        $this->assertSame(50, (int) $userRow['points']);
        $this->assertSame('D', (string) $userRow['tier']);
    }

    public function test_clear_points_freeze_flips_flag_and_audits(): void
    {
        $user = $this->seedUser([
            'nickname' => 'frozen',
            'points_frozen' => true,
        ]);
        $res = points_service::clearPointsFreeze($user);
        $this->assertTrue($res['ok']);
        $this->assertSame($user, (int) $res['data']['unfrozen_user_id']);

        $userRow = $this->pdo->query(
            'SELECT points_frozen, frozen_at, last_unfrozen_at FROM users WHERE user_id = ' . $user
        )->fetch();
        $this->assertSame(0, (int) $userRow['points_frozen']);
        $this->assertNull($userRow['frozen_at']);
        $this->assertNotNull($userRow['last_unfrozen_at']);

        // Audit row written.
        $audit = $this->pdo->query(
            'SELECT action, target_type, target_id FROM audit_log WHERE action = \'points.unfrozen\' ORDER BY id DESC LIMIT 1'
        )->fetch();
        $this->assertNotFalse($audit);
        $this->assertSame('points.unfrozen', (string) $audit['action']);
        $this->assertSame('user', (string) $audit['target_type']);
        $this->assertSame($user, (int) $audit['target_id']);
    }

    public function test_clear_points_freeze_unknown_user_returns_error(): void
    {
        $res = points_service::clearPointsFreeze(9999999);
        $this->assertFalse($res['ok']);
        $this->assertSame('E_POINTS_WRITE', $res['error']['code']);
    }
}