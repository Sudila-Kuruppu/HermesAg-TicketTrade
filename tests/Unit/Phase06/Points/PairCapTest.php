<?php
/**
 * Phase 6 Plan 06-02 — PairCapTest
 *
 * Covers points_service::awardTransaction() pair-cap (FR-PTS-006):
 * 2 counted transactions per (buyer, seller, ticket) tuple per day.
 *
 *   - 2nd counted tx of the same pair+ticket today: pair_cap_hit row
 *     (delta=0), return skipped='pair_cap'.
 *   - First tx of a new day: normal award path.
 *   - Different buyer-seller pair: not capped.
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase06\Points;

use App\Points\Service\points_service;
use App\Tests\Integration\Phase06\Fixtures\Fixtures;

class PairCapTest extends Fixtures
{
    public function test_second_counted_tx_of_same_pair_inserts_pair_cap_row(): void
    {
        $buyer = $this->seedUser(['nickname' => 'buyer', 'redeemed_count' => 5]);
        $seller = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 5]);
        $cat = $this->firstCategoryId();

        // Pre-seed two counted-tx rows on TWO DIFFERENT ticket ids
        // for the same (buyer, seller) pair — countPairInDay counts
        // DISTINCT reference_id (per D-08: each ticket is one row
        // pair). With 2 distinct tickets in the pair today, the next
        // ticket hits the pair cap.
        $listingA = $this->seedListing($seller, $cat);
        $listingB = $this->seedListing($seller, $cat);
        $ticketA = $this->seedTicket([
            'listing_id' => $listingA,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
        ]);
        $ticketB = $this->seedTicket([
            'listing_id' => $listingB,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
        ]);
        $this->seedPointsLog($buyer, 30, 'final_session', $ticketA);
        $this->seedPointsLog($seller, 10, 'final_session', $ticketA);
        $this->seedPointsLog($buyer, 30, 'final_session', $ticketB);
        $this->seedPointsLog($seller, 10, 'final_session', $ticketB);

        // The next awardTransaction on a THIRD ticket triggers the
        // pair-cap (countPairInDay returns 2 → >= 2).
        $listingC = $this->seedListing($seller, $cat);
        $ticketC = $this->seedTicket([
            'listing_id' => $listingC,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
        ]);
        $res = points_service::awardTransaction(
            $buyer,
            $seller,
            $ticketC,
            30,
            10,
            'final_session'
        );
        $this->assertTrue($res['ok']);
        $this->assertSame('pair_cap', $res['data']['skipped']);
        $this->assertArrayHasKey('event_uuid', $res['data']);
        $this->assertSame(2, (int) $res['data']['pair_count_today']);

        // The pair_cap row is for the buyer with delta=0.
        $rows = $this->pdo->query(
            "SELECT user_id, delta, metadata FROM points_log "
            . "WHERE JSON_EXTRACT(metadata, '$.pair_cap_hit') = true ORDER BY id"
        )->fetchAll();
        $this->assertCount(1, $rows);
        $this->assertSame($buyer, (int) $rows[0]['user_id']);
        $this->assertSame(0, (int) $rows[0]['delta']);
        $meta = json_decode((string) $rows[0]['metadata'], true);
        $this->assertTrue((bool) $meta['pair_cap_hit']);
        $this->assertSame('pts05_pair', (string) $meta['cap']);
    }

    public function test_first_tx_of_new_pair_awards_normally(): void
    {
        $buyer = $this->seedUser(['nickname' => 'buyer', 'redeemed_count' => 5]);
        $seller = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
        ]);

        // No prior rows → countPairInDay returns 0 → normal award path.
        $res = points_service::awardTransaction(
            $buyer,
            $seller,
            $ticketId,
            30,
            10,
            'final_session'
        );
        $this->assertTrue($res['ok']);
        $this->assertArrayNotHasKey('skipped', $res['data']);
        $this->assertSame(30, (int) $res['data']['delta_buyer']);
        $this->assertSame(10, (int) $res['data']['delta_seller']);
    }

    public function test_different_pair_ticket_not_capped(): void
    {
        $buyer = $this->seedUser(['nickname' => 'buyer', 'redeemed_count' => 5]);
        $seller1 = $this->seedUser(['nickname' => 'seller1', 'redeemed_count' => 5]);
        $seller2 = $this->seedUser(['nickname' => 'seller2', 'redeemed_count' => 5]);
        $cat = $this->firstCategoryId();
        $listing1 = $this->seedListing($seller1, $cat);
        $listing2 = $this->seedListing($seller2, $cat);
        $ticketA = $this->seedTicket([
            'listing_id' => $listing1,
            'buyer_id' => $buyer,
            'seller_id' => $seller1,
        ]);
        $ticketB = $this->seedTicket([
            'listing_id' => $listing2,
            'buyer_id' => $buyer,
            'seller_id' => $seller2,
        ]);

        // Fill pair-cap on ticketA (buyer/seller1).
        $this->seedPointsLog($buyer, 30, 'final_session', $ticketA);
        $this->seedPointsLog($seller1, 10, 'final_session', $ticketA);
        $this->seedPointsLog($buyer, 30, 'final_session', $ticketA);
        $this->seedPointsLog($seller1, 10, 'final_session', $ticketA);

        // Now award on ticketB (buyer/seller2). countPairInDay for
        // (buyer, seller2, ticketB) is 0 → not capped.
        $res = points_service::awardTransaction(
            $buyer,
            $seller2,
            $ticketB,
            30,
            10,
            'final_session'
        );
        $this->assertTrue($res['ok']);
        $this->assertArrayNotHasKey('skipped', $res['data']);
        $this->assertSame(30, (int) $res['data']['delta_buyer']);
        $this->assertSame(10, (int) $res['data']['delta_seller']);
    }

    /**
     * WR-02 regression: when only BUYER hits the velocity cap,
     * SELLER must still receive their award. Prior implementation
     * returned early on the first cap hit, silently dropping the
     * seller's + delta. Now the loop evaluates both parties
     * independently — buyer's cap row + audit are written (zero
     * delta), then seller's award proceeds normally.
     *
     * Setup: pre-seed buyer with 140 counted-tx points today.
     * deltaBuyer=20 → 140+20=160 > 150 → buyer caps. deltaSeller=30,
     * seller's day_total is 0 → seller doesn't cap.
     *
     * Expectations:
     *   - envelope carries skipped='velocity_cap' + partial_cap_party='buyer'
     *   - seller's points_log row exists with delta=30 (the award)
     *   - buyer's points_log has the cap row (delta=0) AND no new +20 row
     *   - users.points bumped for seller only
     */
    public function test_buyer_cap_does_not_silently_drop_seller_award(): void
    {
        $buyer = $this->seedUser(['nickname' => 'buyer', 'redeemed_count' => 5]);
        $seller = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
        ]);

        // Pre-seed buyer at 140 counted-tx points today (under 150
        // until the +20 puts them at 160).
        $this->seedPointsLog($buyer, 140, 'final_session', $ticketId);
        // Seller starts at 0 — no pre-seed needed.

        $res = points_service::awardTransaction(
            $buyer,
            $seller,
            $ticketId,
            20,  // deltaBuyer — caps
            30,  // deltaSeller — clean
            'final_session'
        );

        // Envelope shape: cap hit on buyer, but seller was awarded.
        $this->assertTrue($res['ok']);
        $this->assertSame('velocity_cap', $res['data']['skipped']);
        $this->assertSame('buyer', $res['data']['partial_cap_party']);
        $this->assertSame(0, (int) $res['data']['delta_buyer'], 'buyer delta is 0 (capped)');
        $this->assertSame(30, (int) $res['data']['delta_seller'], 'seller delta is 30');
        $this->assertNull($res['data']['event_uuid_buyer'], 'no buyer INSERT');
        $this->assertNotNull($res['data']['event_uuid_seller'], 'seller got a points_log row');

        // Seller's points_log row exists with the award.
        $sellerRows = $this->pdo->query(
            "SELECT user_id, delta, reference_id FROM points_log "
            . "WHERE user_id = $seller AND reference_type = 'final_session'"
        )->fetchAll();
        $this->assertCount(1, $sellerRows);
        $this->assertSame(30, (int) $sellerRows[0]['delta']);
        $this->assertSame($ticketId, (int) $sellerRows[0]['reference_id']);

        // Seller users.points bumped to 30.
        $sellerRow = $this->pdo->query(
            'SELECT points FROM users WHERE user_id = ' . $seller
        )->fetch();
        $this->assertSame(30, (int) $sellerRow['points']);

        // Buyer's points_log has the cap row (delta=0, velocity_cap_hit=true).
        // No +20 row was inserted for buyer.
        $buyerRows = $this->pdo->query(
            "SELECT delta, JSON_EXTRACT(metadata, '$.velocity_cap_hit') AS is_cap "
            . "FROM points_log WHERE user_id = $buyer ORDER BY id"
        )->fetchAll();
        $this->assertCount(2, $buyerRows, 'seed 140 + cap 0 = 2 rows');
        // Row 0: the pre-seeded 140 (non-cap)
        $this->assertSame(140, (int) $buyerRows[0]['delta']);
        $this->assertNotSame('true', (string) $buyerRows[0]['is_cap']);
        // Row 1: the cap row (delta=0, velocity_cap_hit=true)
        $this->assertSame(0, (int) $buyerRows[1]['delta']);
        $this->assertSame('true', (string) $buyerRows[1]['is_cap']);
    }

    /**
     * WR-02 mirror: when only SELLER hits the cap, BUYER still
     * receives their award.
     */
    public function test_seller_cap_does_not_silently_drop_buyer_award(): void
    {
        $buyer = $this->seedUser(['nickname' => 'buyer', 'redeemed_count' => 5]);
        $seller = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
        ]);

        // Pre-seed SELLER at 140 — the +30 delta puts them at 170 > 150.
        $this->seedPointsLog($seller, 140, 'final_session', $ticketId);

        $res = points_service::awardTransaction(
            $buyer,
            $seller,
            $ticketId,
            30,
            30,  // seller will cap
            'final_session'
        );

        $this->assertTrue($res['ok']);
        $this->assertSame('velocity_cap', $res['data']['skipped']);
        $this->assertSame('seller', $res['data']['partial_cap_party']);
        $this->assertSame(30, (int) $res['data']['delta_buyer']);
        $this->assertSame(0, (int) $res['data']['delta_seller']);
        $this->assertNotNull($res['data']['event_uuid_buyer']);
        $this->assertNull($res['data']['event_uuid_seller']);

        // Buyer got the award.
        $buyerPoints = (int) $this->pdo->query(
            'SELECT points FROM users WHERE user_id = ' . $buyer
        )->fetchColumn();
        $this->assertSame(30, $buyerPoints);
    }

    /**
     * WR-02: when BOTH parties cap, return the buyer's cap envelope
     * (preserves the prior call-site contract). Both cap rows are
     * already written.
     */
    public function test_both_parties_cap_returns_single_envelope(): void
    {
        $buyer = $this->seedUser(['nickname' => 'buyer', 'redeemed_count' => 5]);
        $seller = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
        ]);

        // Pre-seed both at 140.
        $this->seedPointsLog($buyer, 140, 'final_session', $ticketId);
        $this->seedPointsLog($seller, 140, 'final_session', $ticketId);

        $res = points_service::awardTransaction(
            $buyer,
            $seller,
            $ticketId,
            20, // both cap (140+20=160 > 150)
            30, // both cap (140+30=170 > 150)
            'final_session'
        );

        $this->assertTrue($res['ok']);
        $this->assertSame('velocity_cap', $res['data']['skipped']);
        // Both parties capped → no partial flag, just the first cap.
        $this->assertArrayNotHasKey('partial_cap_party', $res['data']);

        // Two cap rows in points_log (buyer + seller).
        $capRows = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM points_log "
            . "WHERE JSON_EXTRACT(metadata, '$.velocity_cap_hit') = true"
        )->fetchColumn();
        $this->assertSame(2, $capRows);
    }
}
