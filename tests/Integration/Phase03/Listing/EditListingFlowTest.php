<?php
/**
 * Phase 3 — EditListingFlowTest
 *
 * Verifies saveDraft behavior on listings in various states.
 *   - edit on active → review_flag=1, listing_revisions row appended
 *   - edit on draft/pending/rejected → fields update, no revision row
 *   - edit non-owned → E_LISTING_FORBIDDEN
 *   - edit.php View has the rejection banner + review_flag warning hooks
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase03\Listing;

use App\Listing\Service\listing_service;
use App\Tests\Integration\Phase03\Fixtures\Fixtures;

class EditListingFlowTest extends Fixtures
{
    public function test_edit_active_sets_review_flag_and_appends_revision(): void
    {
        $userId = $this->seedUser();
        $catId = $this->firstCategoryId();
        $r = listing_service::createDraft($userId, $this->validData($catId));
        $listingId = (int) $r['data']['id'];

        // Manually flip to active (admin approval is Phase 8).
        $this->pdo->exec("UPDATE listings SET status='active', approved_at=NOW(), approved_by=NULL WHERE id = $listingId");

        $revsBefore = (int) $this->pdo->query("SELECT COUNT(*) FROM listing_revisions WHERE listing_id = $listingId")->fetchColumn();

        $r2 = listing_service::saveDraft($listingId, $userId, $this->validData($catId, ['title' => 'Updated Title']));
        $this->assertTrue($r2['ok']);
        $this->assertTrue($r2['data']['review_flagged']);

        $row = $this->pdo->query("SELECT title, review_flag FROM listings WHERE id = $listingId")->fetch();
        $this->assertSame('Updated Title', $row['title']);
        $this->assertSame(1, (int) $row['review_flag']);

        $revsAfter = (int) $this->pdo->query("SELECT COUNT(*) FROM listing_revisions WHERE listing_id = $listingId")->fetchColumn();
        $this->assertSame($revsBefore + 1, $revsAfter);
    }

    public function test_edit_draft_does_not_set_review_flag_or_revision(): void
    {
        $userId = $this->seedUser();
        $catId = $this->firstCategoryId();
        $r = listing_service::createDraft($userId, $this->validData($catId));
        $listingId = (int) $r['data']['id'];

        $revsBefore = (int) $this->pdo->query("SELECT COUNT(*) FROM listing_revisions WHERE listing_id = $listingId")->fetchColumn();

        $r2 = listing_service::saveDraft($listingId, $userId, $this->validData($catId, ['title' => 'Better Title']));
        $this->assertTrue($r2['ok']);
        $this->assertFalse($r2['data']['review_flagged']);

        $row = $this->pdo->query("SELECT title, review_flag FROM listings WHERE id = $listingId")->fetch();
        $this->assertSame('Better Title', $row['title']);
        $this->assertSame(0, (int) $row['review_flag']);

        $revsAfter = (int) $this->pdo->query("SELECT COUNT(*) FROM listing_revisions WHERE listing_id = $listingId")->fetchColumn();
        $this->assertSame($revsBefore, $revsAfter);
    }

    public function test_edit_rejected_keeps_status_until_submit(): void
    {
        $userId = $this->seedUser();
        $catId = $this->firstCategoryId();
        $r = listing_service::createDraft($userId, $this->validData($catId));
        $listingId = (int) $r['data']['id'];
        $this->pdo->exec("UPDATE listings SET status='rejected', rejection_reason='needs more details' WHERE id = $listingId");

        $r2 = listing_service::saveDraft($listingId, $userId, $this->validData($catId, ['title' => 'Fix and resubmit']));
        $this->assertTrue($r2['ok']);
        // Status remains rejected until seller calls submitDraft
        $row = $this->pdo->query("SELECT title, status FROM listings WHERE id = $listingId")->fetch();
        $this->assertSame('Fix and resubmit', $row['title']);
        $this->assertSame('rejected', $row['status']);
    }

    public function test_edit_non_owned_returns_forbidden(): void
    {
        $ownerId = $this->seedUser(['nickname' => 'owner']);
        $otherId = $this->seedUser(['nickname' => 'other2', 'email' => 'other2@students.nsbm.ac.lk', 'student_id' => 'NSBM/2023/003']);
        $catId = $this->firstCategoryId();
        $r = listing_service::createDraft($ownerId, $this->validData($catId));
        $listingId = (int) $r['data']['id'];

        $r2 = listing_service::saveDraft($listingId, $otherId, $this->validData($catId, ['title' => 'Should be blocked']));
        $this->assertFalse($r2['ok']);
        $this->assertSame('E_LISTING_FORBIDDEN', $r2['error']['code']);

        $row = $this->pdo->query("SELECT title FROM listings WHERE id = $listingId")->fetch();
        $this->assertSame('Calculus 101', $row['title']);
    }

    public function test_edit_view_has_rejection_banner_and_review_flag_warning(): void
    {
        $src = file_get_contents(APP_ROOT . '/src/Listing/View/edit.php');
        $this->assertStringContainsString('rejection_reason', $src);
        $this->assertStringContainsString('alert-danger', $src);
        $this->assertStringContainsString('alert-warning', $src);
        $this->assertStringContainsString('review_flag', $src);
        $this->assertStringContainsString('value="save_draft"', $src);
        $this->assertStringContainsString('value="submit"', $src);
        $this->assertStringContainsString('Resubmit for review', $src);
    }

    public function test_route_map_has_edit_routes(): void
    {
        $routes = require APP_ROOT . '/config/routes.php';
        $this->assertArrayHasKey('GET /listings/{id}/edit', $routes);
        $this->assertArrayHasKey('POST /listings/{id}/edit', $routes);
        $this->assertSame('App\\Listing\\Action\\EditListingAction', $routes['GET /listings/{id}/edit'][0]);
        $this->assertTrue($routes['GET /listings/{id}/edit'][2]['auth']);
        $this->assertFalse($routes['GET /listings/{id}/edit'][2]['csrf']);
        $this->assertTrue($routes['POST /listings/{id}/edit'][2]['csrf']);
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
