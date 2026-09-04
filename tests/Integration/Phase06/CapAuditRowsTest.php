<?php

/**
 * Phase 6 Plan 06-02 — CapAuditRowsTest
 *
 * Integration coverage of the Support\Audit::log() writes that every
 * cap-hit must produce (D-04). The Service tests cover the data-side
 * state (zero-delta points_log row, frozen flag flip); this test
 * covers the audit_log side that Phase 8 wraps with the hash chain.
 *
 *   - velocity-cap hit on buyer → 1 audit_log row, action='points.velocity_cap',
 *     metadata.event_uuid matches the zero-delta points_log row.
 *   - pair-cap hit on (buyer, seller) → 1 audit_log row,
 *     action='points.pair_cap'.
 *   - freeze-trigger fires alongside the velocity cap → 1 row for each
 *     audit action ('points.velocity_cap' + 'points.frozen').
 *   - normal under-cap award → 0 cap audit rows.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase06;

use App\Points\Service\points_service;
use App\Tests\Integration\Phase06\Fixtures\Fixtures;

class CapAuditRowsTest extends Fixtures
{
    public function test_velocity_cap_hit_writes_points_velocity_cap_audit_row(): void
    {
        $buyer = $this->seedUser(['nickname' => 'buyer', 'redeemed_count' => 5]);
        $seller = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
        ]);

        // Seed buyer with 140 counted-tx points; award 20 → 140+20=160 > 150 → cap fires.
        $this->seedPointsLog($buyer, 140, 'final_session', $ticketId);

        $res = points_service::awardTransaction(
            $buyer,
            $seller,
            $ticketId,
            20,
            30,
            'final_session'
        );
        $this->assertTrue($res['ok']);
        $this->assertSame('velocity_cap', $res['data']['skipped']);
        $capEventUuid = (string) $res['data']['event_uuid'];

        // One audit row for the cap.
        $rows = $this->pdo->query(
            "SELECT actor_user_id, action, target_type, target_id, metadata_json "
            . "FROM audit_log WHERE action = 'points.velocity_cap' "
            . "ORDER BY id DESC LIMIT 1"
        )->fetchAll();
        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame('user', (string) $row['target_type']);
        $this->assertSame($buyer, (int) $row['target_id']);
        $this->assertNull($row['actor_user_id'], 'actor_user_id is null on the Service-side cap audit');
        $meta = json_decode((string) $row['metadata_json'], true);
        $this->assertIsArray($meta);
        $this->assertSame($capEventUuid, (string) $meta['event_uuid']);
        $this->assertSame('pts05_daily', (string) $meta['cap']);
        $this->assertSame(140, (int) $meta['day_total_before']);
        $this->assertSame(20, (int) $meta['effective_delta']);
        $this->assertSame('buyer', (string) $meta['party']);
    }

    public function test_pair_cap_hit_writes_points_pair_cap_audit_row(): void
    {
        $buyer = $this->seedUser(['nickname' => 'buyer', 'redeemed_count' => 5]);
        $seller = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 5]);
        $cat = $this->firstCategoryId();

        // Pre-seed 2 distinct ticket rows for the pair so the third ticket
        // triggers the pair-cap.
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

        // Third ticket fires the pair-cap.
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
        $pairEventUuid = (string) $res['data']['event_uuid'];

        $rows = $this->pdo->query(
            "SELECT actor_user_id, action, target_type, target_id, metadata_json "
            . "FROM audit_log WHERE action = 'points.pair_cap' "
            . "ORDER BY id DESC LIMIT 1"
        )->fetchAll();
        $this->assertCount(1, $rows);
        $row = $rows[0];
        $this->assertSame('user', (string) $row['target_type']);
        $this->assertSame($buyer, (int) $row['target_id']);
        $this->assertNull($row['actor_user_id']);
        $meta = json_decode((string) $row['metadata_json'], true);
        $this->assertIsArray($meta);
        $this->assertSame($pairEventUuid, (string) $meta['event_uuid']);
        $this->assertSame('pts05_pair', (string) $meta['cap']);
        $this->assertSame(2, (int) $meta['pair_count_today']);
    }

    public function test_freeze_trigger_alongside_cap_writes_two_audit_rows(): void
    {
        $buyer = $this->seedUser(['nickname' => 'buyer', 'redeemed_count' => 5]);
        $seller = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
        ]);

// 310 (2 hours ago) + 30 effective = 340. dayTotal > 300 → freeze flips
// AND cap fires (340 > 150). Seed with a timestamp 2 hours ago so the
// hourly window is empty and only the daily threshold trips (otherwise
// both fire and the trigger type is 'hour_overflow' instead of
// 'day_overflow').
        $this->seedPointsLog(
            $buyer,
            310,
            'final_session',
            $ticketId,
            null,
            (new \DateTime('-2 hours', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s')
        );

        $res = points_service::awardTransaction(
            $buyer,
            $seller,
            $ticketId,
            30,
            30,
            'final_session'
        );
        $this->assertSame('velocity_cap', $res['data']['skipped']);

        // Two audit rows on this call: cap + freeze.
        $capRows = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM audit_log WHERE action = 'points.velocity_cap'"
        )->fetchColumn();
        $frzRows = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM audit_log WHERE action = 'points.frozen'"
        )->fetchColumn();
        $this->assertSame(1, $capRows);
        $this->assertSame(1, $frzRows);

        // Freeze audit metadata carries the trigger reason + day_total.
        $frzMeta = json_decode(
            (string) $this->pdo->query(
                "SELECT metadata_json FROM audit_log WHERE action = 'points.frozen' ORDER BY id DESC LIMIT 1"
            )->fetchColumn(),
            true
        );
        $this->assertIsArray($frzMeta);
        $this->assertSame('day_overflow', (string) $frzMeta['trigger']);
        $this->assertSame(310, (int) $frzMeta['day_total']);
        $this->assertSame('buyer', (string) $frzMeta['party']);
    }

    public function test_under_cap_award_writes_no_cap_audit_rows(): void
    {
        $buyer = $this->seedUser(['nickname' => 'buyer', 'redeemed_count' => 5]);
        $seller = $this->seedUser(['nickname' => 'seller', 'redeemed_count' => 5]);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
        ]);

        // No pre-seed; the award writes normal points_log rows only.
        $res = points_service::awardTransaction(
            $buyer,
            $seller,
            $ticketId,
            20,
            30,
            'final_session'
        );
        $this->assertTrue($res['ok']);
        $this->assertArrayNotHasKey('skipped', $res['data']);

        $capRows = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM audit_log WHERE action IN ('points.velocity_cap', 'points.pair_cap', 'points.frozen')"
        )->fetchColumn();
        $this->assertSame(0, $capRows);
    }
}
