<?php
/**
 * Phase 6 — AwardReportValidatedTest
 *
 * Covers points_service::awardReportValidated():
 *   - Happy path: +20 awarded, points_log row written with
 *     reference_type='report_validated'.
 *   - Frozen: short-circuits.
 *   - No halving.
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase06\Points;

use App\Points\Service\points_service;
use App\Tests\Integration\Phase06\Fixtures\Fixtures;

class AwardReportValidatedTest extends Fixtures
{
    public function test_happy_path_awards_20(): void
    {
        $user = $this->seedUser(['nickname' => 'reporter', 'redeemed_count' => 0]);
        $reportId = 42;

        $res = points_service::awardReportValidated($user, $reportId);
        $this->assertTrue($res['ok']);
        $this->assertSame(20, (int) $res['data']['delta']);
        $this->assertSame(20, (int) $res['data']['balance_after']);
        $this->assertArrayHasKey('event_uuid', $res['data']);

        $row = $this->pdo->query(
            'SELECT * FROM points_log WHERE user_id = ' . $user . ' ORDER BY id'
        )->fetch();
        $this->assertSame('report_validated', (string) $row['reference_type']);
        $this->assertSame($reportId, (int) $row['reference_id']);
        $this->assertSame(20, (int) $row['delta']);
        $this->assertSame(20, (int) $row['balance_after']);
        $meta = json_decode((string) $row['metadata'], true);
        $this->assertSame($reportId, (int) $meta['report_id']);

        $userRow = $this->pdo->query('SELECT points, tier FROM users WHERE user_id = ' . $user)->fetch();
        $this->assertSame(20, (int) $userRow['points']);
        $this->assertSame('E', (string) $userRow['tier']);
    }

    public function test_no_halving_for_low_redeemed_count(): void
    {
        $user = $this->seedUser(['nickname' => 'fresh', 'redeemed_count' => 0]);
        $res = points_service::awardReportValidated($user, 99);
        $this->assertTrue($res['ok']);
        $this->assertSame(20, (int) $res['data']['delta']);
    }

    public function test_frozen_short_circuit(): void
    {
        $user = $this->seedUser([
            'nickname' => 'frozen',
            'redeemed_count' => 0,
            'points_frozen' => true,
        ]);
        $res = points_service::awardReportValidated($user, 7);
        $this->assertTrue($res['ok']);
        $this->assertSame('points_frozen', $res['data']['skipped']);
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM points_log')->fetchColumn();
        $this->assertSame(0, $count);
        $row = $this->pdo->query('SELECT points FROM users WHERE user_id = ' . $user)->fetch();
        $this->assertSame(0, (int) $row['points']);
    }
}