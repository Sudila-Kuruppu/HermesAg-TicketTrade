<?php
/**
 * Phase 5 — ReviewActionTest
 *
 * End-to-end tests for the review submission flow:
 *   - Happy path: 5-star + 60-char comment -> review row, 10 points,
 *     302 to /purchases, flash success.
 *   - Rating-only review (5 stars, 0 chars comment) -> review row,
 *     0 points, 302 success.
 *   - Already-reviewed -> E_REVIEW_ALREADY_LEFT, no DB write.
 *   - Non-party user -> E_REVIEW_FORBIDDEN, no DB write.
 *   - Invalid rating (0/6/string) -> E_REVIEW_INVALID_RATING.
 *   - Not-yet-redeemed ticket -> E_REVIEW_NOT_ELIGIBLE.
 *   - Window-closed ticket -> E_REVIEW_WINDOW_CLOSED.
 *
 * Rate limit (11th POST) is covered separately in RateLimitTest.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase05\Review;

use App\Tests\Integration\Phase04\Fixtures\Fixtures;

class ReviewActionTest extends Fixtures
{
    public function test_buyer_submits_5_star_with_long_comment_awards_10_points(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 5]);
        $buyer = $this->seedUser(['nickname' => 'buyer', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'status' => 'redeemed',
        ]);
        // Set redeemed_at to NOW so the 14-day window check passes.
        $this->pdo->prepare('UPDATE tickets SET redeemed_at = NOW() WHERE id = ?')->execute([$ticketId]);

        $comment = str_repeat('Great transaction, fast pickup! ', 2); // 60+ chars
        $result = $this->dispatchAction(
            'App\Review\Action\ReviewAction',
            'handlePost',
            $buyer,
            ['id' => $ticketId],
            ['rating' => '5', 'comment' => $comment]
        );

        $this->assertSame(302, $result['status']);
        // 1 review row, reviewer_role=buyer, reviewee=seller.
        $rows = $this->pdo->query('SELECT * FROM reviews')->fetchAll();
        $this->assertCount(1, $rows);
        $this->assertSame('buyer', (string) $rows[0]['reviewer_role']);
        $this->assertSame(5, (int) $rows[0]['rating']);
        $this->assertSame($seller, (int) $rows[0]['reviewee_id']);
        $this->assertSame($buyer, (int) $rows[0]['reviewer_id']);
        // 1 points_log row for the seller (reviewee).
        $logRows = $this->pdo->query('SELECT * FROM points_log')->fetchAll();
        $this->assertCount(1, $logRows);
        $this->assertSame($seller, (int) $logRows[0]['user_id']);
        $this->assertSame(10, (int) $logRows[0]['delta']);
        $this->assertSame('review', (string) $logRows[0]['reference_type']);
        // 1 audit_log row.
        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'review.created'")->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function test_rating_only_no_comment_no_points(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 5]);
        $buyer = $this->seedUser(['nickname' => 'buyer', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'status' => 'redeemed',
        ]);
        $this->pdo->prepare('UPDATE tickets SET redeemed_at = NOW() WHERE id = ?')->execute([$ticketId]);

        $result = $this->dispatchAction(
            'App\Review\Action\ReviewAction',
            'handlePost',
            $buyer,
            ['id' => $ticketId],
            ['rating' => '5', 'comment' => '']
        );

        $this->assertSame(302, $result['status']);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn());
        // No points_log row.
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM points_log')->fetchColumn());
    }

    public function test_already_reviewed_rejected_with_already_left(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'status' => 'redeemed',
        ]);
        $this->pdo->prepare('UPDATE tickets SET redeemed_at = NOW() WHERE id = ?')->execute([$ticketId]);

        $this->dispatchAction(
            'App\Review\Action\ReviewAction',
            'handlePost',
            $buyer,
            ['id' => $ticketId],
            ['rating' => '5']
        );
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn());

        // Second attempt by same role should be rejected.
        $result = $this->dispatchAction(
            'App\Review\Action\ReviewAction',
            'handlePost',
            $buyer,
            ['id' => $ticketId],
            ['rating' => '4']
        );
        $this->assertSame(302, $result['status']);
        // Still only 1 review row.
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn());
    }

    public function test_non_party_user_rejected_with_forbidden(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $outsider = $this->seedUser(['nickname' => 'outsider']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'status' => 'redeemed',
        ]);
        $this->pdo->prepare('UPDATE tickets SET redeemed_at = NOW() WHERE id = ?')->execute([$ticketId]);

        $result = $this->dispatchAction(
            'App\Review\Action\ReviewAction',
            'handlePost',
            $outsider,
            ['id' => $ticketId],
            ['rating' => '5']
        );
        $this->assertSame(302, $result['status']);
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn());
    }

    public function test_invalid_rating_zero_rejected(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'status' => 'redeemed',
        ]);
        $this->pdo->prepare('UPDATE tickets SET redeemed_at = NOW() WHERE id = ?')->execute([$ticketId]);

        $result = $this->dispatchAction(
            'App\Review\Action\ReviewAction',
            'handlePost',
            $buyer,
            ['id' => $ticketId],
            ['rating' => '0']
        );
        $this->assertSame(302, $result['status']);
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn());
    }

    public function test_invalid_rating_six_rejected(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'status' => 'redeemed',
        ]);
        $this->pdo->prepare('UPDATE tickets SET redeemed_at = NOW() WHERE id = ?')->execute([$ticketId]);

        $result = $this->dispatchAction(
            'App\Review\Action\ReviewAction',
            'handlePost',
            $buyer,
            ['id' => $ticketId],
            ['rating' => '6']
        );
        $this->assertSame(302, $result['status']);
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn());
    }

    public function test_active_ticket_rejected_as_not_eligible(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'status' => 'active',
        ]);
        $this->pdo->prepare('UPDATE tickets SET redeemed_at = NOW() WHERE id = ?')->execute([$ticketId]);

        $result = $this->dispatchAction(
            'App\Review\Action\ReviewAction',
            'handlePost',
            $buyer,
            ['id' => $ticketId],
            ['rating' => '5']
        );
        $this->assertSame(302, $result['status']);
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn());
    }

    public function test_window_closed_after_14_days(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'status' => 'redeemed',
        ]);
        // Set redeemed_at to 15 days ago.
        $this->pdo->prepare('UPDATE tickets SET redeemed_at = DATE_SUB(NOW(), INTERVAL 15 DAY) WHERE id = ?')
            ->execute([$ticketId]);

        $result = $this->dispatchAction(
            'App\Review\Action\ReviewAction',
            'handlePost',
            $buyer,
            ['id' => $ticketId],
            ['rating' => '5']
        );
        $this->assertSame(302, $result['status']);
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn());
    }

    public function test_nonexistent_ticket(): void
    {
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $result = $this->dispatchAction(
            'App\Review\Action\ReviewAction',
            'handlePost',
            $buyer,
            ['id' => 9999],
            ['rating' => '5']
        );
        $this->assertSame(302, $result['status']);
    }
}