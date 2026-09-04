<?php
/**
 * Phase 6 — AwardListingApprovalTest
 *
 * Covers points_service::awardListingApproval():
 *   - Happy path: +5 awarded, points_log row written, users.points
 *     and tier updated (no halving per D-15).
 *   - Frozen: short-circuits with data.skipped='points_frozen'.
 *   - No halving even at redeemed_count=0 (the multiplier is
 *     transaction-only per D-15 — the 50% rule does NOT apply here).
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase06\Points;

use App\Points\Service\points_service;
use App\Tests\Integration\Phase06\Fixtures\Fixtures;

class AwardListingApprovalTest extends Fixtures
{
    public function test_happy_path_awards_5_no_halving(): void
    {
        $user = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 0]);
        $listingId = $this->seedListing($user, $this->firstCategoryId());

        $res = points_service::awardListingApproval($user, $listingId);
        $this->assertTrue($res['ok']);
        $this->assertSame(5, (int) $res['data']['delta']);
        $this->assertSame(5, (int) $res['data']['balance_after']);
        $this->assertArrayHasKey('event_uuid', $res['data']);

        // points_log row
        $rows = $this->pdo->query('SELECT * FROM points_log ORDER BY id')->fetchAll();
        $this->assertCount(1, $rows);
        $this->assertSame('listing_approval', (string) $rows[0]['reference_type']);
        $this->assertSame($listingId, (int) $rows[0]['reference_id']);
        $this->assertSame(5, (int) $rows[0]['delta']);
        $this->assertSame(5, (int) $rows[0]['balance_after']);

        // Metadata carries listing_id and cap_hit
        $meta = json_decode((string) $rows[0]['metadata'], true);
        $this->assertIsArray($meta);
        $this->assertSame($listingId, (int) $meta['listing_id']);
        $this->assertFalse((bool) $meta['cap_hit']);

        // users.points + tier
        $row = $this->pdo->query('SELECT points, tier FROM users WHERE user_id = ' . $user)->fetch();
        $this->assertSame(5, (int) $row['points']);
        $this->assertSame('E', (string) $row['tier']);
    }

    public function test_no_halving_when_redeemed_count_is_zero(): void
    {
        // D-15: the FR-PTS-007 50% multiplier is transaction-only.
        // Listing approval awards the full +5 even on first redemption.
        $user = $this->seedUser(['nickname' => 'new', 'redeemed_count' => 0]);
        $listingId = $this->seedListing($user, $this->firstCategoryId());
        $res = points_service::awardListingApproval($user, $listingId);
        $this->assertTrue($res['ok']);
        $this->assertSame(5, (int) $res['data']['delta']);
    }

    public function test_frozen_short_circuit(): void
    {
        $user = $this->seedUser([
            'nickname' => 'frozen',
            'redeemed_count' => 0,
            'points_frozen' => true,
        ]);
        $listingId = $this->seedListing($user, $this->firstCategoryId());

        $res = points_service::awardListingApproval($user, $listingId);
        $this->assertTrue($res['ok']);
        $this->assertSame('points_frozen', $res['data']['skipped']);

        // No row written.
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM points_log')->fetchColumn();
        $this->assertSame(0, $count);

        // users.points unchanged.
        $row = $this->pdo->query('SELECT points FROM users WHERE user_id = ' . $user)->fetch();
        $this->assertSame(0, (int) $row['points']);
    }

    public function test_tier_transition_updates_users_tier(): void
    {
        // 45 points + 5 = 50 -> tier moves E -> D.
        $user = $this->seedUser(['nickname' => 'tierup', 'points' => 45, 'redeemed_count' => 5]);
        $listingId = $this->seedListing($user, $this->firstCategoryId());
        $res = points_service::awardListingApproval($user, $listingId);
        $this->assertTrue($res['ok']);
        $row = $this->pdo->query('SELECT points, tier FROM users WHERE user_id = ' . $user)->fetch();
        $this->assertSame(50, (int) $row['points']);
        $this->assertSame('D', (string) $row['tier']);
    }
}