<?php
/**
 * Phase 5 — ReviewsTabTest
 *
 * Tests the Reviews tab + stats-row aggregation wiring on the public
 * profile:
 *   - GET /profile/{nickname} renders the summary row (avg, count,
 *     dispute count) and the reviews list.
 *   - Pagination renders Prev/Next when offset > 0 or more pages
 *     remain (D-08).
 *   - Empty state renders for users with 0 reviews.
 *   - FR-RAT-003: reviewer's full name is NEVER rendered in the View.
 *
 * Uses View::render() directly (rather than the Action + Router) to
 * verify the template vars surface correctly. The Action's network
 * wrapping is covered by Fixtures::dispatchAction() in Plan 02-03.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase05\Profile;

use App\Support\View;
use App\Tests\Integration\Phase04\Fixtures\Fixtures;

class ReviewsTabTest extends Fixtures
{
    /**
     * Capture the rendered public profile HTML without exiting the test.
     */
    private function renderPublicProfile(int $userId, string $nickname, int $offset = 0): string
    {
        $pdo = $this->pdo;
        // Mirror user_service::getByNicknameForPublicProfile + the
        // summary + reviews reads the Action performs.
        $stmt = $pdo->prepare(
            'SELECT user_id, nickname, full_name, bio, avatar_id, tier, '
            . 'points, is_verified, created_at '
            . 'FROM users WHERE BINARY nickname = ? AND is_banned = FALSE LIMIT 1'
        );
        $stmt->execute([$nickname]);
        $profile = $stmt->fetch();
        if ($profile === false) {
            $this->fail("Profile row missing for $nickname");
        }

        $summary = \App\Review\Service\review_service::getSummaryForUser($userId);
        [$reviews, $total] = \App\Review\Service\review_service::listReviewsForUser(
            $userId,
            10,
            $offset
        );

        $GLOBALS['_tt_view_vars'] = [];
        ob_start();
        View::render(
            APP_ROOT . '/src/User/View/public_profile.php',
            [
                'profile' => $profile,
                'is_owner' => false,
                'summary' => $summary,
                'reviews' => $reviews,
                'reviews_total' => $total,
                'reviews_offset' => $offset,
                'reviews_per_page' => 10,
            ]
        );
        return (string) ob_get_clean();
    }

    private function seedReview(
        int $ticketId,
        int $reviewerId,
        int $revieweeId,
        int $rating,
        ?string $comment,
        string $reviewerRole
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO reviews (ticket_id, reviewer_id, reviewee_id, rating, comment, '
            . 'reviewer_role, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$ticketId, $reviewerId, $revieweeId, $rating, $comment, $reviewerRole]);
        return (int) $this->pdo->lastInsertId();
    }

    public function test_renders_avg_and_count_in_stats_row(): void
    {
        $seller = $this->seedUser(['nickname' => 'topseller']);
        $reviewer = $this->seedUser(['nickname' => 'buyer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $t = $this->seedTicket([
            'listing_id' => $listingId, 'buyer_id' => $reviewer, 'seller_id' => $seller,
        ]);
        $this->seedReview($t, $reviewer, $seller, 5, 'Smooth.', 'buyer');

        $html = $this->renderPublicProfile($seller, 'topseller');

        // 1 review → 1.0 average (5/5) shown in the stats row.
        $this->assertStringContainsString('5.0', $html);
        // "(1 review)" copy with the singular form.
        $this->assertStringContainsString('(1 review)', $html);
    }

    public function test_renders_zero_disputes_for_clean_profile(): void
    {
        $user = $this->seedUser(['nickname' => 'clean']);
        $html = $this->renderPublicProfile($user, 'clean');
        $this->assertStringContainsString('0 disputes', $html);
    }

    public function test_renders_empty_state_when_no_reviews(): void
    {
        $user = $this->seedUser(['nickname' => 'nope']);
        $html = $this->renderPublicProfile($user, 'nope');
        $this->assertStringContainsString('No reviews yet', $html);
        $this->assertStringContainsString(
            'Reviews appear after transactions complete.',
            $html
        );
    }

    public function test_renders_review_cards_with_nickname_and_rating(): void
    {
        $seller = $this->seedUser(['nickname' => 'shop']);
        $r1 = $this->seedUser(['nickname' => 'happyshopper']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $t = $this->seedTicket([
            'listing_id' => $listingId, 'buyer_id' => $r1, 'seller_id' => $seller,
        ]);
        $this->seedReview($t, $r1, $seller, 5, 'Met on campus, super smooth.', 'buyer');

        $html = $this->renderPublicProfile($seller, 'shop');

        $this->assertStringContainsString('@happyshopper', $html);
        $this->assertStringContainsString('Met on campus, super smooth.', $html);
        // Role badge.
        $this->assertStringContainsString('Buyer', $html);
    }

    public function test_renders_placeholder_when_comment_null(): void
    {
        $seller = $this->seedUser(['nickname' => 'ratingonly']);
        $r1 = $this->seedUser(['nickname' => 'r1']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $t = $this->seedTicket([
            'listing_id' => $listingId, 'buyer_id' => $r1, 'seller_id' => $seller,
        ]);
        $this->seedReview($t, $r1, $seller, 4, null, 'buyer');

        $html = $this->renderPublicProfile($seller, 'ratingonly');
        $this->assertStringContainsString('Rating only', $html);
        $this->assertStringContainsString('no comment', $html);
    }

    public function test_does_not_render_reviewer_full_name_per_FR_RAT_003(): void
    {
        // The profile owner's full_name is "Profile Owner Full Name"
        // (visible — it IS their profile). The reviewer's full_name is
        // "Kasun Perera" (must NOT leak — only the nickname per FR-RAT-003).
        $seller = $this->seedUser([
            'nickname' => 'freshtarget',
            'full_name' => 'Profile Owner Full Name',
        ]);
        $reviewer = $this->seedUser([
            'nickname' => 'privateReviewer',
            'full_name' => 'Kasun Perera',
        ]);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $t = $this->seedTicket([
            'listing_id' => $listingId, 'buyer_id' => $reviewer, 'seller_id' => $seller,
        ]);
        $this->seedReview($t, $reviewer, $seller, 5, 'Good', 'buyer');

        $html = $this->renderPublicProfile($seller, 'freshtarget');

        // The reviewer's nickname is rendered; the reviewer's full name is NOT.
        $this->assertStringContainsString('@privateReviewer', $html);
        // FR-RAT-003: the reviewer's full_name never leaks into the View.
        // The profile owner's full_name IS allowed (it's their own profile).
        $this->assertStringNotContainsString('Kasun Perera', $html);
    }

    public function test_renders_prev_next_links_when_more_than_one_page(): void
    {
        $seller = $this->seedUser(['nickname' => 'manyreviews']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        // 12 reviews → 2 pages of 10 + 2.
        for ($i = 0; $i < 12; $i++) {
            $reviewer = $this->seedUser(['nickname' => 'r' . $i]);
            $t = $this->seedTicket([
                'listing_id' => $listingId, 'buyer_id' => $reviewer, 'seller_id' => $seller,
            ]);
            $this->seedReview($t, $reviewer, $seller, 5, 'ok', 'buyer');
        }

        // Page 1.
        $html = $this->renderPublicProfile($seller, 'manyreviews', 0);
        $this->assertStringContainsString('Next', $html);
        $this->assertStringContainsString('?offset=10', $html);

        // Page 2.
        $html = $this->renderPublicProfile($seller, 'manyreviews', 10);
        $this->assertStringContainsString('Prev', $html);
        $this->assertStringContainsString('?offset=0', $html);
    }

    public function test_no_pagination_links_when_single_page(): void
    {
        $seller = $this->seedUser(['nickname' => 'fewreviews']);
        $reviewer = $this->seedUser(['nickname' => 'r1']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $t = $this->seedTicket([
            'listing_id' => $listingId, 'buyer_id' => $reviewer, 'seller_id' => $seller,
        ]);
        $this->seedReview($t, $reviewer, $seller, 5, 'good', 'buyer');

        $html = $this->renderPublicProfile($seller, 'fewreviews', 0);
        $this->assertStringNotContainsString('Next', $html);
        $this->assertStringNotContainsString('Prev', $html);
    }

    public function test_distribution_buckets_render_for_multi_review_profile(): void
    {
        $seller = $this->seedUser(['nickname' => 'distro']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ratings = [5, 5, 4];
        foreach ($ratings as $i => $r) {
            $reviewer = $this->seedUser(['nickname' => 'r' . $i]);
            $t = $this->seedTicket([
                'listing_id' => $listingId, 'buyer_id' => $reviewer, 'seller_id' => $seller,
            ]);
            $this->seedReview($t, $reviewer, $seller, $r, null, 'buyer');
        }
        $html = $this->renderPublicProfile($seller, 'distro');
        // All 5-bucket rows render (label, bar, count).
        $this->assertStringContainsString('5 stars', $html);
        $this->assertStringContainsString('4 stars', $html);
        $this->assertStringContainsString('3 stars', $html);
        $this->assertStringContainsString('2 stars', $html);
        $this->assertStringContainsString('1 star', $html);
    }
}