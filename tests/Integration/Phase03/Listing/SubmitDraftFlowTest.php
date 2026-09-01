<?php
/**
 * Phase 3 — SubmitDraftFlowTest
 *
 * Verifies the SubmitDraftAction flow:
 *   - POST /listings/{id}/submit on a draft listing: row.status flips
 *     to pending.
 *   - Submitting a non-owned listing: returns E_LISTING_FORBIDDEN
 *     (mapped to 404 by the Action per D-14).
 *   - Submitting a listing in a non-draft state: returns
 *     E_LISTING_FORBIDDEN.
 *   - submitDraft is idempotent on a rejected → draft → pending flow.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase03\Listing;

use App\Listing\Service\listing_service;
use App\Tests\Integration\Phase03\Fixtures\Fixtures;

class SubmitDraftFlowTest extends Fixtures
{
    public function test_submit_draft_flips_to_pending(): void
    {
        $userId = $this->seedUser();
        $catId = $this->firstCategoryId();
        $r = listing_service::createDraft($userId, $this->validData($catId));
        $listingId = (int) $r['data']['id'];

        $r2 = listing_service::submitDraft($listingId, $userId);
        $this->assertTrue($r2['ok']);
        $this->assertSame('pending', $r2['data']['status']);
    }

    public function test_submit_rejected_listing_allowed(): void
    {
        $userId = $this->seedUser();
        $catId = $this->firstCategoryId();
        $r = listing_service::createDraft($userId, $this->validData($catId));
        $listingId = (int) $r['data']['id'];

        // Manually flip to rejected (the rejection Action is Phase 8).
        $this->pdo->exec("UPDATE listings SET status='rejected', rejection_reason = 'Needs more details' WHERE id = $listingId");

        $r2 = listing_service::submitDraft($listingId, $userId);
        $this->assertTrue($r2['ok']);
        $this->assertSame('pending', $r2['data']['status']);
    }

    public function test_submit_non_owned_returns_forbidden(): void
    {
        $ownerId = $this->seedUser(['nickname' => 'owner']);
        $otherId = $this->seedUser(['nickname' => 'other', 'email' => 'other@students.nsbm.ac.lk', 'student_id' => 'NSBM/2023/002']);
        $catId = $this->firstCategoryId();
        $r = listing_service::createDraft($ownerId, $this->validData($catId));
        $listingId = (int) $r['data']['id'];

        $r2 = listing_service::submitDraft($listingId, $otherId);
        $this->assertFalse($r2['ok']);
        $this->assertSame('E_LISTING_FORBIDDEN', $r2['error']['code']);
    }

    public function test_submit_active_listing_returns_forbidden(): void
    {
        $userId = $this->seedUser();
        $catId = $this->firstCategoryId();
        $r = listing_service::createDraft($userId, $this->validData($catId));
        $listingId = (int) $r['data']['id'];
        $this->pdo->exec("UPDATE listings SET status='active' WHERE id = $listingId");

        $r2 = listing_service::submitDraft($listingId, $userId);
        $this->assertFalse($r2['ok']);
        $this->assertSame('E_LISTING_FORBIDDEN', $r2['error']['code']);
    }

    public function test_route_map_has_submit_route(): void
    {
        $routes = require APP_ROOT . '/config/routes.php';
        $this->assertArrayHasKey('POST /listings/{id}/submit', $routes);
        $this->assertSame('App\\Listing\\Action\\SubmitDraftAction', $routes['POST /listings/{id}/submit'][0]);
        $this->assertTrue($routes['POST /listings/{id}/submit'][2]['csrf']);
        $this->assertTrue($routes['POST /listings/{id}/submit'][2]['auth']);
    }

    private function validData(int $catId, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Calculus 101',
            'description' => 'Barely used',
            'price_cents' => 150000,
            'category_id' => $catId,
            'type' => 'product',
            'condition' => 'like_new',
            'quantity' => 1,
        ], $overrides);
    }

    private function firstCategoryId(): int
    {
        return (int) $this->pdo->query('SELECT id FROM categories ORDER BY sort_order LIMIT 1')->fetchColumn();
    }
}
