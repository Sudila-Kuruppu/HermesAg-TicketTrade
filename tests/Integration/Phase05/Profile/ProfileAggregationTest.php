<?php
/**
 * Phase 5 — ProfileAggregationTest
 *
 * Tests the review_service read path used by PublicProfileAction:
 *   - review_service::getSummaryForUser($userId) returns the four
 *     fields (rating_avg, rating_count, rating_distribution,
 *     dispute_count) from two SQL statements (D-07).
 *   - review_service::listReviewsForUser($userId, $limit, $offset)
 *     returns [rows, total] where rows are reviews received, ordered
 *     created_at DESC, with reviewer_nickname (never full name)
 *     (D-08 + FR-RAT-003).
 *
 * Tests seed reviews rows directly (bypassing submitReview's gate)
 * so the aggregation logic is exercised in isolation from the
 * transaction-heavy write path.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase05\Profile;

use App\Review\Service\review_service;
use App\Tests\Integration\Phase04\Fixtures\Fixtures;

class ProfileAggregationTest extends Fixtures
{
    /**
     * Insert a reviews row directly (bypasses the submitReview gate).
     * Returns the review id.
     */
    private function seedReview(
        int $ticketId,
        int $reviewerId,
        int $revieweeId,
        int $rating,
        ?string $comment,
        string $reviewerRole,
        ?string $createdAt = null
    ): int {
        $createdAt ??= (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))
            ->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO reviews (ticket_id, reviewer_id, reviewee_id, rating, comment, '
            . 'reviewer_role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $ticketId,
            $reviewerId,
            $revieweeId,
            $rating,
            $comment,
            $reviewerRole,
            $createdAt,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function test_getSummaryForUser_returns_zeros_when_no_reviews(): void
    {
        $user = $this->seedUser(['nickname' => 'lonely']);
        $summary = review_service::getSummaryForUser($user);
        $this->assertSame(0.0, $summary['rating_avg']);
        $this->assertSame(0, $summary['rating_count']);
        $this->assertSame(
            [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            $summary['rating_distribution']
        );
        $this->assertSame(0, $summary['dispute_count']);
    }

    public function test_getSummaryForUser_returns_avg_and_distribution_with_three_reviews(): void
    {
        $reviewee = $this->seedUser(['nickname' => 'subject']);
        $r1 = $this->seedUser(['nickname' => 'r1']);
        $r2 = $this->seedUser(['nickname' => 'r2']);
        $r3 = $this->seedUser(['nickname' => 'r3']);

        // Need a ticket row for the FK; we don't care about its state here.
        $listingId = $this->seedListing($reviewee, $this->firstCategoryId());
        $t1 = $this->seedTicket([
            'listing_id' => $listingId, 'buyer_id' => $r1, 'seller_id' => $reviewee,
        ]);
        $t2 = $this->seedTicket([
            'listing_id' => $listingId, 'buyer_id' => $r2, 'seller_id' => $reviewee,
        ]);
        $t3 = $this->seedTicket([
            'listing_id' => $listingId, 'buyer_id' => $r3, 'seller_id' => $reviewee,
        ]);

        // 5 + 4 + 4 = 13/3 = 4.333... → ROUND(..,1) = 4.3
        $this->seedReview($t1, $r1, $reviewee, 5, 'Great!', 'buyer');
        $this->seedReview($t2, $r2, $reviewee, 4, 'Good', 'buyer');
        $this->seedReview($t3, $r3, $reviewee, 4, 'Solid', 'buyer');

        $summary = review_service::getSummaryForUser($reviewee);

        $this->assertSame(4.3, $summary['rating_avg']);
        $this->assertSame(3, $summary['rating_count']);
        $this->assertSame(0, $summary['rating_distribution'][1]);
        $this->assertSame(0, $summary['rating_distribution'][2]);
        $this->assertSame(0, $summary['rating_distribution'][3]);
        $this->assertSame(2, $summary['rating_distribution'][4]);
        $this->assertSame(1, $summary['rating_distribution'][5]);
        $this->assertSame(0, $summary['dispute_count']);
    }

    public function test_getSummaryForUser_distribution_buckets_are_independent(): void
    {
        $reviewee = $this->seedUser(['nickname' => 'bucket']);
        $reviewers = [];
        for ($i = 0; $i < 6; $i++) {
            $reviewers[] = $this->seedUser(['nickname' => 'rw' . $i]);
        }
        $listingId = $this->seedListing($reviewee, $this->firstCategoryId());
        // Ratings: 5, 5, 4, 3, 2, 1 → buckets {1:1, 2:1, 3:1, 4:1, 5:2}
        $ratings = [5, 5, 4, 3, 2, 1];
        foreach ($ratings as $i => $rating) {
            $t = $this->seedTicket([
                'listing_id' => $listingId, 'buyer_id' => $reviewers[$i], 'seller_id' => $reviewee,
            ]);
            $this->seedReview($t, $reviewers[$i], $reviewee, $rating, null, 'buyer');
        }
        $summary = review_service::getSummaryForUser($reviewee);
        $this->assertSame(6, $summary['rating_count']);
        $this->assertSame(
            [1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 2],
            $summary['rating_distribution']
        );
        // avg = (5+5+4+3+2+1)/6 = 20/6 = 3.333... → ROUND(..,1) = 3.3
        $this->assertSame(3.3, $summary['rating_avg']);
    }

    public function test_getSummaryForUser_dispute_count_is_zero_when_no_upheld_disputes(): void
    {
        // disputeCountForSeller filters on dispute_status='upheld'. No
        // disputes in the test DB → 0.
        $seller = $this->seedUser(['nickname' => 'unlucky']);
        $summary = review_service::getSummaryForUser($seller);
        $this->assertSame(0, $summary['dispute_count']);
    }

    public function test_listReviewsForUser_returns_recent_first_with_nickname(): void
    {
        $reviewee = $this->seedUser(['nickname' => 'target']);
        $r1 = $this->seedUser(['nickname' => 'reviewer1']);
        $r2 = $this->seedUser(['nickname' => 'reviewer2']);

        $listingId = $this->seedListing($reviewee, $this->firstCategoryId());
        $t1 = $this->seedTicket(['listing_id' => $listingId, 'buyer_id' => $r1, 'seller_id' => $reviewee]);
        $t2 = $this->seedTicket(['listing_id' => $listingId, 'buyer_id' => $r2, 'seller_id' => $reviewee]);

        // Older review (created 2 days ago).
        $older = (new \DateTime('-2 days', new \DateTimeZone('Asia/Colombo')))
            ->format('Y-m-d H:i:s');
        $newer = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))
            ->format('Y-m-d H:i:s');

        $this->seedReview($t1, $r1, $reviewee, 4, 'Older', 'buyer', $older);
        $this->seedReview($t2, $r2, $reviewee, 5, 'Newer', 'buyer', $newer);

        [$reviews, $returnedTotal] = review_service::listReviewsForUser($reviewee, 10, 0);

        $this->assertSame(2, $returnedTotal);
        $this->assertCount(2, $reviews);
        // Newest first.
        $this->assertSame(5, (int) $reviews[0]['rating']);
        $this->assertSame('Newer', $reviews[0]['comment']);
        $this->assertSame('reviewer2', $reviews[0]['reviewer_nickname']);
        $this->assertSame(4, (int) $reviews[1]['rating']);
        $this->assertSame('reviewer1', $reviews[1]['reviewer_nickname']);
        // FR-RAT-003: full name is NEVER rendered. The Model doesn't
        // return full_name; the reviewer_nickname is the only name field.
        $this->assertArrayNotHasKey('full_name', $reviews[0]);
    }

    public function test_listReviewsForUser_pagination_respects_limit_and_offset(): void
    {
        $reviewee = $this->seedUser(['nickname' => 'paginated']);
        $listingId = $this->seedListing($reviewee, $this->firstCategoryId());
        // Seed 12 reviews.
        for ($i = 0; $i < 12; $i++) {
            $reviewer = $this->seedUser(['nickname' => 'rv' . $i]);
            $t = $this->seedTicket([
                'listing_id' => $listingId, 'buyer_id' => $reviewer, 'seller_id' => $reviewee,
            ]);
            $this->seedReview($t, $reviewer, $reviewee, 5, 'review #' . $i, 'buyer');
        }

        // Page 1: 0..10.
        [$rows, $total] = review_service::listReviewsForUser($reviewee, 10, 0);
        $this->assertSame(12, $total);
        $this->assertCount(10, $rows);

        // Page 2: 10..20 (only 2 remaining).
        [$rows2, $total2] = review_service::listReviewsForUser($reviewee, 10, 10);
        $this->assertSame(12, $total2);
        $this->assertCount(2, $rows2);
    }

    public function test_listReviewsForUser_returns_empty_when_no_reviews(): void
    {
        $user = $this->seedUser(['nickname' => 'nothing']);
        [$rows, $total] = review_service::listReviewsForUser($user, 10, 0);
        $this->assertSame(0, $total);
        $this->assertSame([], $rows);
    }

    public function test_listReviewsForUser_clamps_negative_offset_to_zero(): void
    {
        $reviewee = $this->seedUser(['nickname' => 'clamp']);
        $listingId = $this->seedListing($reviewee, $this->firstCategoryId());
        $reviewer = $this->seedUser(['nickname' => 'clampR']);
        $t = $this->seedTicket([
            'listing_id' => $listingId, 'buyer_id' => $reviewer, 'seller_id' => $reviewee,
        ]);
        $this->seedReview($t, $reviewer, $reviewee, 5, 'ok', 'buyer');
        [$rows, $total] = review_service::listReviewsForUser($reviewee, 10, -5);
        $this->assertSame(1, $total);
        $this->assertCount(1, $rows);
    }
}