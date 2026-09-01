<?php
/**
 * Phase 3 — DeleteListingFlowTest
 *
 * Verifies the soft-delete / hard-delete branch:
 *   - delete active/rejected/sold listing → status flips to removed
 *   - delete draft/pending listing → row is hard-deleted
 *   - delete non-owned → E_LISTING_FORBIDDEN
 *   - delete unknown → E_LISTING_NOT_FOUND
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase03\Listing;

use App\Listing\Service\listing_service;
use App\Tests\Integration\Phase03\Fixtures\Fixtures;

class DeleteListingFlowTest extends Fixtures
{
    public function test_soft_delete_active(): void
    {
        $userId = $this->seedUser();
        $catId = $this->firstCategoryId();
        $r = listing_service::createDraft($userId, $this->validData($catId));
        $listingId = (int) $r['data']['id'];
        $this->pdo->exec("UPDATE listings SET status='active' WHERE id = $listingId");

        $r2 = listing_service::softDelete($listingId, $userId);
        $this->assertTrue($r2['ok']);

        $row = $this->pdo->query("SELECT status FROM listings WHERE id = $listingId")->fetch();
        $this->assertSame('removed', $row['status']);
        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM listings WHERE id = $listingId")->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function test_soft_delete_sold(): void
    {
        $userId = $this->seedUser();
        $catId = $this->firstCategoryId();
        $r = listing_service::createDraft($userId, $this->validData($catId));
        $listingId = (int) $r['data']['id'];
        $this->pdo->exec("UPDATE listings SET status='sold' WHERE id = $listingId");

        $r2 = listing_service::softDelete($listingId, $userId);
        $this->assertTrue($r2['ok']);
        $row = $this->pdo->query("SELECT status FROM listings WHERE id = $listingId")->fetch();
        $this->assertSame('removed', $row['status']);
    }

    public function test_hard_delete_draft(): void
    {
        $userId = $this->seedUser();
        $catId = $this->firstCategoryId();
        $r = listing_service::createDraft($userId, $this->validData($catId));
        $listingId = (int) $r['data']['id'];

        $r2 = listing_service::hardDelete($listingId, $userId);
        $this->assertTrue($r2['ok']);

        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM listings WHERE id = $listingId")->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function test_hard_delete_pending(): void
    {
        $userId = $this->seedUser();
        $catId = $this->firstCategoryId();
        $r = listing_service::createDraft($userId, $this->validData($catId));
        $listingId = (int) $r['data']['id'];
        listing_service::submitDraft($listingId, $userId);

        $r2 = listing_service::hardDelete($listingId, $userId);
        $this->assertTrue($r2['ok']);
        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM listings WHERE id = $listingId")->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function test_hard_delete_active_returns_forbidden(): void
    {
        $userId = $this->seedUser();
        $catId = $this->firstCategoryId();
        $r = listing_service::createDraft($userId, $this->validData($catId));
        $listingId = (int) $r['data']['id'];
        $this->pdo->exec("UPDATE listings SET status='active' WHERE id = $listingId");

        $r2 = listing_service::hardDelete($listingId, $userId);
        $this->assertFalse($r2['ok']);
        $this->assertSame('E_LISTING_FORBIDDEN', $r2['error']['code']);
    }

    public function test_delete_non_owned_returns_forbidden(): void
    {
        $ownerId = $this->seedUser(['nickname' => 'ownerdel']);
        $otherId = $this->seedUser(['nickname' => 'otherdel', 'email' => 'otherdel@students.nsbm.ac.lk', 'student_id' => 'NSBM/2023/004']);
        $catId = $this->firstCategoryId();
        $r = listing_service::createDraft($ownerId, $this->validData($catId));
        $listingId = (int) $r['data']['id'];

        $r2 = listing_service::softDelete($listingId, $otherId);
        $this->assertFalse($r2['ok']);
        $this->assertSame('E_LISTING_FORBIDDEN', $r2['error']['code']);

        $r3 = listing_service::hardDelete($listingId, $otherId);
        $this->assertFalse($r3['ok']);
        $this->assertSame('E_LISTING_FORBIDDEN', $r3['error']['code']);

        // Row still exists.
        $count = (int) $this->pdo->query("SELECT COUNT(*) FROM listings WHERE id = $listingId")->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function test_delete_unknown_returns_not_found(): void
    {
        $userId = $this->seedUser();
        $r = listing_service::softDelete(99999, $userId);
        $this->assertFalse($r['ok']);
        $this->assertSame('E_LISTING_NOT_FOUND', $r['error']['code']);
    }

    public function test_route_map_has_delete_route(): void
    {
        $routes = require APP_ROOT . '/config/routes.php';
        $this->assertArrayHasKey('POST /listings/{id}/delete', $routes);
        $this->assertSame('App\\Listing\\Action\\DeleteListingAction', $routes['POST /listings/{id}/delete'][0]);
        $this->assertTrue($routes['POST /listings/{id}/delete'][2]['csrf']);
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
