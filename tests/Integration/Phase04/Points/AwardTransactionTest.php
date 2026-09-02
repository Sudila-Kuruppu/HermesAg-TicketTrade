<?php
/**
 * Phase 4 — AwardTransactionTest
 *
 * Covers Points\Service\points_service::awardTransaction():
 *   - Happy path: buyer +10, seller +30, both points_log rows,
 *     users.points/tier updated, distinct UUID v7 event_uuids.
 *   - FR-PTS-007 halving: buyer with redeemed_count < 5 gets
 *     half points.
 *   - FR-PTS-010: points_frozen=TRUE short-circuits (no points_log).
 *   - redeemed_count increments on final_session referenceType.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase04\Points;

use App\Points\Service\points_service;
use App\Support\Db;
use App\Tests\Integration\Phase04\Fixtures\Fixtures;

class AwardTransactionTest extends Fixtures
{
    public function test_happy_path_awards_points_to_both(): void
    {
        $buyer = $this->seedUser(['nickname' => 'buyer', 'redeemed_count' => 5]);
        $seller = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
        ]);

        $res = points_service::awardTransaction($buyer, $seller, $ticketId, 10, 30, 'redemption');
        $this->assertTrue($res['ok']);
        $this->assertArrayHasKey('event_uuid_buyer', $res['data']);
        $this->assertArrayHasKey('event_uuid_seller', $res['data']);
        $this->assertNotSame($res['data']['event_uuid_buyer'], $res['data']['event_uuid_seller']);
        $this->assertSame(10, (int) $res['data']['delta_buyer']);
        $this->assertSame(30, (int) $res['data']['delta_seller']);

        // Verify points_log rows exist.
        $rows = $this->pdo->query('SELECT * FROM points_log WHERE user_id IN (' . $buyer . ',' . $seller . ') ORDER BY id')->fetchAll();
        $this->assertCount(2, $rows);

        // Verify users.points + tier updated.
        $buyerRow = $this->pdo->query('SELECT points, tier FROM users WHERE user_id = ' . $buyer)->fetch();
        $sellerRow = $this->pdo->query('SELECT points, tier FROM users WHERE user_id = ' . $seller)->fetch();
        $this->assertSame(10, (int) $buyerRow['points']);
        $this->assertSame(30, (int) $sellerRow['points']);
    }

    public function test_fr_pts_007_halving_for_first_5_redemptions(): void
    {
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $seller = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 2]);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
        ]);

        $res = points_service::awardTransaction($buyer, $seller, $ticketId, 10, 30, 'redemption');
        $this->assertTrue($res['ok']);
        // Seller has redeemed_count=2 (<5), so delta_seller = floor(30*0.5) = 15.
        // Buyer has redeemed_count=0 (<5), so delta_buyer = floor(10*0.5) = 5.
        $this->assertSame(5, (int) $res['data']['delta_buyer']);
        $this->assertSame(15, (int) $res['data']['delta_seller']);
    }

    public function test_fr_pts_007_no_halving_after_5_redemptions(): void
    {
        $buyer = $this->seedUser(['nickname' => 'buyer', 'redeemed_count' => 5]);
        $seller = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
        ]);

        $res = points_service::awardTransaction($buyer, $seller, $ticketId, 10, 30, 'redemption');
        $this->assertTrue($res['ok']);
        // Seller has redeemed_count=5 (>=5), so delta_seller = 30 (full).
        $this->assertSame(10, (int) $res['data']['delta_buyer']);
        $this->assertSame(30, (int) $res['data']['delta_seller']);
    }

    public function test_fr_pts_010_points_frozen_skips(): void
    {
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $seller = $this->seedUser(['nickname' => 'seller', 'points_frozen' => true]);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
        ]);

        $res = points_service::awardTransaction($buyer, $seller, $ticketId, 10, 30, 'redemption');
        $this->assertTrue($res['ok']);
        $this->assertSame('points_frozen', $res['data']['skipped']);

        // No points_log rows.
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM points_log')->fetchColumn();
        $this->assertSame(0, $count);

        // No users.points changes.
        $buyerRow = $this->pdo->query('SELECT points FROM users WHERE user_id = ' . $buyer)->fetch();
        $sellerRow = $this->pdo->query('SELECT points FROM users WHERE user_id = ' . $seller)->fetch();
        $this->assertSame(0, (int) $buyerRow['points']);
        $this->assertSame(0, (int) $sellerRow['points']);
    }

    public function test_uuid_v7_event_uuids_are_distinct(): void
    {
        $buyer = $this->seedUser(['nickname' => 'buyer', 'redeemed_count' => 5]);
        $seller = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
        ]);

        $res = points_service::awardTransaction($buyer, $seller, $ticketId, 10, 30, 'redemption');
        $this->assertNotSame(
            $res['data']['event_uuid_buyer'],
            $res['data']['event_uuid_seller']
        );
        // Both should be UUID v7 (time-based). Format check.
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $res['data']['event_uuid_buyer']);
    }

    public function test_redeemed_count_increments_on_final_session(): void
    {
        $buyer = $this->seedUser(['nickname' => 'buyer', 'redeemed_count' => 5]);
        $seller = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
        ]);

        // redemption referenceType: redeemed_count NOT incremented.
        points_service::awardTransaction($buyer, $seller, $ticketId, 10, 30, 'redemption');
        $b1 = (int) $this->pdo->query('SELECT redeemed_count FROM users WHERE user_id = ' . $buyer)->fetchColumn();
        $s1 = (int) $this->pdo->query('SELECT redeemed_count FROM users WHERE user_id = ' . $seller)->fetchColumn();
        $this->assertSame(5, $b1);  // unchanged from seed
        $this->assertSame(5, $s1);

        // final_session referenceType: redeemed_count +1.
        points_service::awardTransaction($buyer, $seller, $ticketId, 10, 30, 'final_session');
        $b2 = (int) $this->pdo->query('SELECT redeemed_count FROM users WHERE user_id = ' . $buyer)->fetchColumn();
        $s2 = (int) $this->pdo->query('SELECT redeemed_count FROM users WHERE user_id = ' . $seller)->fetchColumn();
        $this->assertSame(6, $b2);
        $this->assertSame(6, $s2);
    }
}
