<?php
/**
 * Phase 3 — RelistFlowTest
 *
 * Verifies the relist flow:
 *   - relist a sold listing → new draft row created with reset
 *     quantity_sold=0, all fields copied, source_listing_id set.
 *   - relist non-sold listing → E_LISTING_FORBIDDEN.
 *   - relist non-owned → E_LISTING_FORBIDDEN.
 *   - relist unknown → E_LISTING_NOT_FOUND.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase03\Listing;

use App\Listing\Service\listing_service;
use App\Tests\Integration\Phase03\Fixtures\Fixtures;

class RelistFlowTest extends Fixtures
{
    public function test_relist_sold_creates_new_draft(): void
    {
        $userId = $this->seedUser();
        $catId = $this->firstCategoryId();
        $r = listing_service::createDraft($userId, $this->validData($catId));
        $originalId = (int) $r['data']['id'];
        $this->pdo->exec("UPDATE listings SET status='sold', quantity_sold=1 WHERE id = $originalId");

        $r2 = listing_service::relist($originalId, $userId);
        $this->assertTrue($r2['ok']);
        $this->assertSame('draft', $r2['data']['status']);
        $newId = (int) $r2['data']['id'];
        $this->assertNotSame($originalId, $newId);

        // All fields copied.
        $newRow = $this->pdo->query("SELECT * FROM listings WHERE id = $newId")->fetch();
        $this->assertSame('Calculus 101', $newRow['title']);
        $this->assertSame($userId, (int) $newRow['seller_id']);
        $this->assertSame(150000, (int) $newRow['price_cents']);
        $this->assertSame('like_new', $newRow['condition']);
        $this->assertSame(1, (int) $newRow['quantity']);
        // quantity_sold reset to 0 in the new draft.
        $this->assertSame(0, (int) $newRow['quantity_sold']);
        // source_listing_id set for the approved-content fast-track.
        $this->assertSame($originalId, (int) $newRow['source_listing_id']);

        // Original row still exists with status=sold.
        $origRow = $this->pdo->query("SELECT status, quantity_sold FROM listings WHERE id = $originalId")->fetch();
        $this->assertSame('sold', $origRow['status']);
        $this->assertSame(1, (int) $origRow['quantity_sold']);
    }

    public function test_relist_active_returns_forbidden(): void
    {
        $userId = $this->seedUser();
        $catId = $this->firstCategoryId();
        $r = listing_service::createDraft($userId, $this->validData($catId));
        $listingId = (int) $r['data']['id'];
        $this->pdo->exec("UPDATE listings SET status='active' WHERE id = $listingId");

        $r2 = listing_service::relist($listingId, $userId);
        $this->assertFalse($r2['ok']);
        $this->assertSame('E_LISTING_FORBIDDEN', $r2['error']['code']);
    }

    public function test_relist_draft_returns_forbidden(): void
    {
        $userId = $this->seedUser();
        $catId = $this->firstCategoryId();
        $r = listing_service::createDraft($userId, $this->validData($catId));
        $listingId = (int) $r['data']['id'];

        $r2 = listing_service::relist($listingId, $userId);
        $this->assertFalse($r2['ok']);
        $this->assertSame('E_LISTING_FORBIDDEN', $r2['error']['code']);
    }

    public function test_relist_non_owned_returns_forbidden(): void
    {
        $ownerId = $this->seedUser(['nickname' => 'ownerre']);
        $otherId = $this->seedUser(['nickname' => 'otherre', 'email' => 'otherre@students.nsbm.ac.lk', 'student_id' => 'NSBM/2023/005']);
        $catId = $this->firstCategoryId();
        $r = listing_service::createDraft($ownerId, $this->validData($catId));
        $listingId = (int) $r['data']['id'];
        $this->pdo->exec("UPDATE listings SET status='sold' WHERE id = $listingId");

        $r2 = listing_service::relist($listingId, $otherId);
        $this->assertFalse($r2['ok']);
        $this->assertSame('E_LISTING_FORBIDDEN', $r2['error']['code']);
    }

    public function test_relist_unknown_returns_not_found(): void
    {
        $userId = $this->seedUser();
        $r = listing_service::relist(99999, $userId);
        $this->assertFalse($r['ok']);
        $this->assertSame('E_LISTING_NOT_FOUND', $r['error']['code']);
    }

    public function test_route_map_has_relist_route(): void
    {
        $routes = require APP_ROOT . '/config/routes.php';
        $this->assertArrayHasKey('POST /listings/{id}/relist', $routes);
        $this->assertSame('App\\Listing\\Action\\RelistListingAction', $routes['POST /listings/{id}/relist'][0]);
        $this->assertTrue($routes['POST /listings/{id}/relist'][2]['csrf']);
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
