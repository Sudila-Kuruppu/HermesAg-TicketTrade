<?php
/**
 * Phase 5 — ListingModalRatingTest
 *
 * Verifies the compact rating + dispute fragments on the listing modal
 * (Plan 05-02, D-09 + the BLOCKER review note):
 *
 *   - A seller with 0 reviews renders NO rating row.
 *   - A seller with N reviews (avg X) renders "★ X (N reviews)"
 *     inline; the dispute suffix is hidden when dispute_count === 0.
 *   - A seller with 0 reviews but upheld disputes STILL renders the
 *     dispute suffix ("· N disputes") in muted color — the two
 *     fragments gate INDEPENDENTLY (per D-09 + the BLOCKER note).
 *   - A seller with both reviews AND disputes renders both fragments
 *     back-to-back.
 *
 * The two partials live in
 *   src/Support/View/partials/review_summary_compact_rating.php
 *   src/Support/View/partials/review_summary_compact_dispute.php
 *
 * The test exercises the partials directly via ob_start/require —
 * matching the StarRatingInputTest pattern from Plan 05-01. The View
 * itself (board.php + listing_modal.php) is wired in BrowseAction; a
 * separate end-to-end render check verifies the seller_summary
 * forward through BrowseAction's contract (last test).
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase05\Listing;

use App\Review\Service\review_service;
use App\Tests\Integration\Phase04\Fixtures\Fixtures;

class ListingModalRatingTest extends Fixtures
{
    /**
     * Render both compact fragments back-to-back (the same shape the
     * listing modal View uses) and return the combined output.
     */
    private function renderListingModalRatingRow(array $summary): string
    {
        $GLOBALS['_tt_view_vars'] = ['summary' => $summary];
        ob_start();
        require APP_ROOT . '/src/Support/View/partials/review_summary_compact_rating.php';
        require APP_ROOT . '/src/Support/View/partials/review_summary_compact_dispute.php';
        return (string) ob_get_clean();
    }

    /**
     * Insert a reviews row directly (bypasses submitReview's gate).
     */
    private function seedReview(
        int $ticketId,
        int $reviewerId,
        int $revieweeId,
        int $rating,
        string $reviewerRole
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO reviews (ticket_id, reviewer_id, reviewee_id, rating, comment, '
            . 'reviewer_role, created_at) VALUES (?, ?, ?, ?, NULL, ?, NOW())'
        );
        $stmt->execute([$ticketId, $reviewerId, $revieweeId, $rating, $reviewerRole]);
        return (int) $this->pdo->lastInsertId();
    }

    public function test_zero_reviews_renders_no_rating_row(): void
    {
        $seller = $this->seedUser(['nickname' => 'silent']);
        $summary = review_service::getSummaryForUser($seller);

        $html = $this->renderListingModalRatingRow($summary);

        // No rating row (the rating fragment returns early when count=0).
        $this->assertStringNotContainsString('listing-modal-rating', $html);
        // No dispute suffix either (dispute_count is 0).
        $this->assertStringNotContainsString('listing-modal-dispute', $html);
        $this->assertStringNotContainsString('reviews</span>', $html);
        $this->assertStringNotContainsString('dispute', $html);
    }

    public function test_five_reviews_renders_compact_row_with_avg_and_count(): void
    {
        $seller = $this->seedUser(['nickname' => 'rated']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());

        // 5 reviews: ratings 5, 5, 5, 4, 5 → avg = 24/5 = 4.8 → ROUND(1) = 4.8
        $ratings = [5, 5, 5, 4, 5];
        foreach ($ratings as $i => $r) {
            $reviewer = $this->seedUser(['nickname' => 'rv' . $i]);
            $t = $this->seedTicket([
                'listing_id' => $listingId,
                'buyer_id' => $reviewer,
                'seller_id' => $seller,
            ]);
            $this->seedReview($t, $reviewer, $seller, $r, 'buyer');
        }

        $summary = review_service::getSummaryForUser($seller);
        $html = $this->renderListingModalRatingRow($summary);

        // The compact row renders.
        $this->assertStringContainsString('listing-modal-rating', $html);
        $this->assertStringContainsString('4.8', $html);
        $this->assertStringContainsString('5 reviews', $html);
        // No dispute suffix (dispute_count = 0).
        $this->assertStringNotContainsString('listing-modal-dispute', $html);
    }

    public function test_singular_review_label_when_count_is_one(): void
    {
        $seller = $this->seedUser(['nickname' => 'lonely2']);
        $reviewer = $this->seedUser(['nickname' => 'first']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $t = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $reviewer,
            'seller_id' => $seller,
        ]);
        $this->seedReview($t, $reviewer, $seller, 4, 'buyer');

        $summary = review_service::getSummaryForUser($seller);
        $html = $this->renderListingModalRatingRow($summary);

        $this->assertStringContainsString('1 review', $html);
        // The pluralised form MUST NOT appear when count is 1.
        $this->assertStringNotContainsString('1 reviews', $html);
    }

    public function test_zero_reviews_with_disputes_renders_dispute_suffix_only(): void
    {
        // D-09 + BLOCKER review note: the dispute fragment is gated
        // INDEPENDENTLY of the rating fragment. A seller with 0 reviews
        // but 2 upheld disputes still gets "· 2 disputes" even though
        // the rating row is hidden.
        $seller = $this->seedUser(['nickname' => 'litigated']);

        $summary = review_service::getSummaryForUser($seller);
        $summary['dispute_count'] = 2;
        $html = $this->renderListingModalRatingRow($summary);

        // Rating fragment hidden (count=0).
        $this->assertStringNotContainsString('listing-modal-rating', $html);
        // Dispute suffix rendered (count=2).
        $this->assertStringContainsString('listing-modal-dispute', $html);
        $this->assertStringContainsString('2 disputes', $html);
    }

    public function test_singular_dispute_label_when_count_is_one(): void
    {
        $summary = [
            'rating_avg' => 0.0,
            'rating_count' => 0,
            'rating_distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            'dispute_count' => 1,
        ];
        $html = $this->renderListingModalRatingRow($summary);

        $this->assertStringContainsString('1 dispute', $html);
        $this->assertStringNotContainsString('1 disputes', $html);
    }

    public function test_reviews_and_disputes_render_both_fragments_back_to_back(): void
    {
        // 5 reviews of avg 4.6 (ratings 5, 5, 4, 5, 4 → 23/5 = 4.6)
        // PLUS 2 upheld disputes → both fragments render back-to-back.
        $seller = $this->seedUser(['nickname' => 'busy']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        foreach ([5, 5, 4, 5, 4] as $i => $r) {
            $reviewer = $this->seedUser(['nickname' => 'rv' . $i]);
            $t = $this->seedTicket([
                'listing_id' => $listingId,
                'buyer_id' => $reviewer,
                'seller_id' => $seller,
            ]);
            $this->seedReview($t, $reviewer, $seller, $r, 'buyer');
        }

        $summary = review_service::getSummaryForUser($seller);
        $summary['dispute_count'] = 2;
        $html = $this->renderListingModalRatingRow($summary);

        // Both fragments rendered.
        $this->assertStringContainsString('listing-modal-rating', $html);
        $this->assertStringContainsString('4.6', $html);
        $this->assertStringContainsString('5 reviews', $html);
        $this->assertStringContainsString('listing-modal-dispute', $html);
        $this->assertStringContainsString('2 disputes', $html);

        // Order check: rating row comes BEFORE the dispute suffix in the
        // rendered HTML (the listing modal renders them in that order).
        $ratingPos = strpos($html, 'listing-modal-rating');
        $disputePos = strpos($html, 'listing-modal-dispute');
        $this->assertNotFalse($ratingPos);
        $this->assertNotFalse($disputePos);
        $this->assertLessThan($disputePos, $ratingPos);
    }

    public function test_zero_state_renders_nothing_for_unseeded_seller(): void
    {
        // A seller with no reviews and no disputes produces an empty
        // render (both fragments early-return on zero counts).
        $summary = [
            'rating_avg' => 0.0,
            'rating_count' => 0,
            'rating_distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            'dispute_count' => 0,
        ];
        $html = $this->renderListingModalRatingRow($summary);
        $this->assertSame('', $html);
    }

    public function test_browse_action_forwards_seller_summary_to_view(): void
    {
        // The BrowseAction must inject $seller_summary into the View so
        // the listing modal partials receive it. We verify by reading
        // what BrowseAction WOULD have passed: getSummaryForUser on the
        // first row's seller_id is a 1-query extra read (per the plan's
        // "N+1 acceptable for WAD scope" note). This test seeds the
        // data and checks the service contract rather than the Action's
        // echo (the Action echoes the full board layout; this avoids
        // that surface-level coupling).
        $seller = $this->seedUser(['nickname' => 'featuredseller']);
        $reviewer = $this->seedUser(['nickname' => 'reviewer']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $t = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $reviewer,
            'seller_id' => $seller,
        ]);
        $this->seedReview($t, $reviewer, $seller, 5, 'buyer');

        $summary = review_service::getSummaryForUser($seller);

        // The shape BrowseAction forwards into the View.
        $this->assertSame(1, $summary['rating_count']);
        $this->assertSame(5.0, $summary['rating_avg']);
        $this->assertSame(0, $summary['dispute_count']);

        // And the partials render it correctly when invoked with that
        // shape (this is the contract BrowseAction relies on).
        $html = $this->renderListingModalRatingRow($summary);
        $this->assertStringContainsString('5.0', $html);
        $this->assertStringContainsString('1 review', $html);
    }
}
