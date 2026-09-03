<?php
/**
 * Phase 5 — AwardReviewPointsTest
 *
 * Covers Points\Service\points_service::awardReviewPoints():
 *   - Happy path: reviewee +10, points_log row written, users.points/
 *     tier/redeemed_count updated, distinct UUID v7 event_uuid.
 *   - FR-PTS-007 halving: reviewee with redeemed_count < 5 gets +5.
 *   - FR-PTS-010: reviewee.points_frozen=TRUE short-circuits (no row).
 *   - Short comment (<50 chars) returns skipped='no_points', no row.
 *   - Reviewer row in users is NOT updated.
 *   - Participates in outer transaction (no double-begin when called
 *     from review_service::submitReview).
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase05\Points;

use App\Points\Service\points_service;
use App\Support\Db;
use App\Tests\Integration\Phase04\Fixtures\Fixtures;

class AwardReviewPointsTest extends Fixtures
{
    public function test_happy_path_awards_10_to_reviewee(): void
    {
        $reviewer = $this->seedUser(['nickname' => 'reviewer', 'redeemed_count' => 5]);
        $reviewee = $this->seedUser(['nickname' => 'reviewee', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($reviewee, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $reviewer,
            'seller_id' => $reviewee,
        ]);

        $res = points_service::awardReviewPoints($reviewee, $reviewer, $ticketId, 60);
        $this->assertTrue($res['ok']);
        $this->assertSame(10, (int) $res['data']['delta']);
        $this->assertArrayHasKey('event_uuid', $res['data']);

        // 1 points_log row for the reviewee only.
        $rows = $this->pdo->query('SELECT * FROM points_log ORDER BY id')->fetchAll();
        $this->assertCount(1, $rows);
        $this->assertSame($reviewee, (int) $rows[0]['user_id']);
        $this->assertSame('review', (string) $rows[0]['reference_type']);
        $this->assertSame($ticketId, (int) $rows[0]['reference_id']);
        $this->assertSame(10, (int) $rows[0]['delta']);
        $this->assertSame(10, (int) $rows[0]['balance_after']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            (string) $rows[0]['event_uuid']
        );

        // Reviewee updated.
        $row = $this->pdo->query('SELECT points, tier, redeemed_count FROM users WHERE user_id = ' . $reviewee)->fetch();
        $this->assertSame(10, (int) $row['points']);
        $this->assertSame('E', (string) $row['tier']);
        $this->assertSame(6, (int) $row['redeemed_count']); // 5 + 1

        // Reviewer NOT updated.
        $rowR = $this->pdo->query('SELECT points, redeemed_count FROM users WHERE user_id = ' . $reviewer)->fetch();
        $this->assertSame(0, (int) $rowR['points']);
        $this->assertSame(5, (int) $rowR['redeemed_count']);
    }

    public function test_short_comment_skips_no_points(): void
    {
        $reviewer = $this->seedUser(['nickname' => 'reviewer', 'redeemed_count' => 5]);
        $reviewee = $this->seedUser(['nickname' => 'reviewee', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($reviewee, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $reviewer,
            'seller_id' => $reviewee,
        ]);

        $res = points_service::awardReviewPoints($reviewee, $reviewer, $ticketId, 30);
        $this->assertTrue($res['ok']);
        $this->assertSame('no_points', $res['data']['skipped']);

        // No points_log rows.
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM points_log')->fetchColumn();
        $this->assertSame(0, $count);

        // Reviewee redeemed_count NOT incremented.
        $row = $this->pdo->query('SELECT redeemed_count FROM users WHERE user_id = ' . $reviewee)->fetch();
        $this->assertSame(5, (int) $row['redeemed_count']);
    }

    public function test_points_frozen_skips(): void
    {
        $reviewer = $this->seedUser(['nickname' => 'reviewer', 'redeemed_count' => 5]);
        $reviewee = $this->seedUser([
            'nickname' => 'reviewee',
            'redeemed_count' => 5,
            'points_frozen' => true,
        ]);
        $listingId = $this->seedListing($reviewee, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $reviewer,
            'seller_id' => $reviewee,
        ]);

        $res = points_service::awardReviewPoints($reviewee, $reviewer, $ticketId, 60);
        $this->assertTrue($res['ok']);
        $this->assertSame('points_frozen', $res['data']['skipped']);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM points_log')->fetchColumn();
        $this->assertSame(0, $count);

        $row = $this->pdo->query('SELECT points, redeemed_count FROM users WHERE user_id = ' . $reviewee)->fetch();
        $this->assertSame(0, (int) $row['points']);
        $this->assertSame(5, (int) $row['redeemed_count']);
    }

    public function test_fr_pts_007_halving_for_first_5_redemptions(): void
    {
        $reviewer = $this->seedUser(['nickname' => 'reviewer', 'redeemed_count' => 5]);
        // Reviewee has only 2 redemptions -> halving applies.
        $reviewee = $this->seedUser(['nickname' => 'reviewee', 'redeemed_count' => 2]);
        $listingId = $this->seedListing($reviewee, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $reviewer,
            'seller_id' => $reviewee,
        ]);

        $res = points_service::awardReviewPoints($reviewee, $reviewer, $ticketId, 75);
        $this->assertTrue($res['ok']);
        $this->assertSame(5, (int) $res['data']['delta']); // floor(10*0.5) = 5
    }

    public function test_fr_pts_007_no_halving_after_5_redemptions(): void
    {
        $reviewer = $this->seedUser(['nickname' => 'reviewer', 'redeemed_count' => 5]);
        $reviewee = $this->seedUser(['nickname' => 'reviewee', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($reviewee, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $reviewer,
            'seller_id' => $reviewee,
        ]);

        $res = points_service::awardReviewPoints($reviewee, $reviewer, $ticketId, 50);
        $this->assertTrue($res['ok']);
        $this->assertSame(10, (int) $res['data']['delta']);
    }

    public function test_comment_at_threshold_50_earns_points(): void
    {
        $reviewer = $this->seedUser(['nickname' => 'reviewer', 'redeemed_count' => 5]);
        $reviewee = $this->seedUser(['nickname' => 'reviewee', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($reviewee, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $reviewer,
            'seller_id' => $reviewee,
        ]);

        $res = points_service::awardReviewPoints($reviewee, $reviewer, $ticketId, 50);
        $this->assertTrue($res['ok']);
        $this->assertSame(10, (int) $res['data']['delta']);
    }

    public function test_participates_in_outer_transaction(): void
    {
        $reviewer = $this->seedUser(['nickname' => 'reviewer', 'redeemed_count' => 5]);
        $reviewee = $this->seedUser(['nickname' => 'reviewee', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($reviewee, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $reviewer,
            'seller_id' => $reviewee,
        ]);

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        $res = points_service::awardReviewPoints($reviewee, $reviewer, $ticketId, 60);
        $this->assertTrue($res['ok']);
        // The inner method must NOT have committed (outer transaction still active).
        $this->assertTrue($pdo->inTransaction(), 'Inner method should not commit an outer transaction');
        $pdo->rollBack();
    }

    public function test_no_point_paths_omit_redeemed_count_increment(): void
    {
        $reviewer = $this->seedUser(['nickname' => 'reviewer', 'redeemed_count' => 5]);
        $reviewee = $this->seedUser(['nickname' => 'reviewee', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($reviewee, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $reviewer,
            'seller_id' => $reviewee,
        ]);

        // Short comment: no row, no redeemed_count bump.
        points_service::awardReviewPoints($reviewee, $reviewer, $ticketId, 10);
        $row = $this->pdo->query('SELECT redeemed_count FROM users WHERE user_id = ' . $reviewee)->fetch();
        $this->assertSame(5, (int) $row['redeemed_count']);

        // Frozen: no row, no redeemed_count bump.
        $this->pdo->prepare('UPDATE users SET points_frozen = TRUE WHERE user_id = ?')->execute([$reviewee]);
        points_service::awardReviewPoints($reviewee, $reviewer, $ticketId, 60);
        $row = $this->pdo->query('SELECT redeemed_count FROM users WHERE user_id = ' . $reviewee)->fetch();
        $this->assertSame(5, (int) $row['redeemed_count']);
    }
}
