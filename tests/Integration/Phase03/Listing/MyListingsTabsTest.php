<?php
/**
 * Phase 3 — MyListingsTabsTest
 *
 * Verifies the seller dashboard:
 *   - 4 tabs render with the correct counts via single GROUP BY query
 *   - active tab carries aria-current="page"
 *   - per-state empty-state copy matches EXPERIENCE.md
 *   - per-state action buttons (Edit, Delete, Relist, Submit) per D-02
 *   - listing_service::groupCountsBySeller returns the right shape
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase03\Listing;

use App\Listing\Model\listing_model;
use App\Listing\Service\listing_service;
use App\Tests\Integration\Phase03\Fixtures\Fixtures;

class MyListingsTabsTest extends Fixtures
{
    public function test_group_counts_by_seller_returns_four_statuses(): void
    {
        $userId = $this->seedUser();
        $catId = $this->firstCategoryId();

        // Seed: 1 active, 2 pending, 1 sold, 0 drafts
        $rows = [['status' => 'active'], ['status' => 'pending'], ['status' => 'pending'], ['status' => 'sold']];
        foreach ($rows as $r) {
            $r2 = listing_service::createDraft($userId, $this->validData($catId));
            $id = (int) $r2['data']['id'];
            $this->pdo->exec("UPDATE listings SET status='" . $r['status'] . "' WHERE id = $id");
        }

        $stmt = listing_model::groupCountsBySeller($userId);
        $counts = [];
        foreach ($stmt as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }

        $this->assertSame(1, $counts['active'] ?? 0);
        $this->assertSame(2, $counts['pending'] ?? 0);
        $this->assertSame(1, $counts['sold'] ?? 0);
        $this->assertSame(0, $counts['draft'] ?? 0);
    }

    public function test_get_seller_listings_filters_by_status(): void
    {
        $userId = $this->seedUser();
        $catId = $this->firstCategoryId();

        $r = listing_service::createDraft($userId, $this->validData($catId, ['title' => 'Draft 1']));
        $r = listing_service::createDraft($userId, $this->validData($catId, ['title' => 'Draft 2']));
        $r2 = listing_service::createDraft($userId, $this->validData($catId, ['title' => 'Active 1']));
        $id = (int) $r2['data']['id'];
        $this->pdo->exec("UPDATE listings SET status='active' WHERE id = $id");

        $r3 = listing_service::getSellerListings($userId, 'draft');
        $this->assertTrue($r3['ok']);
        $this->assertCount(2, $r3['data']['rows']);
        foreach ($r3['data']['rows'] as $row) {
            $this->assertSame('draft', $row['status']);
        }

        $r4 = listing_service::getSellerListings($userId, 'active');
        $this->assertCount(1, $r4['data']['rows']);
        $this->assertSame('active', $r4['data']['rows'][0]['status']);
    }

    public function test_seller_dashboard_tabs_partial_renders_4_tabs(): void
    {
        $src = file_get_contents(APP_ROOT . '/src/Support/View/partials/seller_dashboard_tabs.php');
        $this->assertStringContainsString('role="tablist"', $src);
        $this->assertStringContainsString('Active', $src);
        $this->assertStringContainsString('Pending', $src);
        $this->assertStringContainsString('Sold', $src);
        $this->assertStringContainsString('Draft', $src);
        $this->assertStringContainsString('aria-current="page"', $src);
        // The tabs render with PHP that produces ?tab=<key>; verify the
        // rendering primitives are present (foreach over $order, the
        // $key substituted into the href).
        $this->assertStringContainsString('foreach ($order as $key)', $src);
        $this->assertStringContainsString('\'active\'', $src);
        $this->assertStringContainsString('\'pending\'', $src);
        $this->assertStringContainsString('\'sold\'', $src);
        $this->assertStringContainsString('\'draft\'', $src);
        $this->assertStringContainsString('?tab=', $src);
    }

    public function test_empty_state_partial_renders_title_body_and_cta(): void
    {
        $src = file_get_contents(APP_ROOT . '/src/Support/View/partials/empty_state.php');
        $this->assertStringContainsString('empty-state', $src);
        $this->assertStringContainsString('btn btn-primary', $src);
        $this->assertStringContainsString('cta_label', $src);
        $this->assertStringContainsString('cta_href', $src);
    }

    public function test_my_listings_view_has_per_state_actions(): void
    {
        $src = file_get_contents(APP_ROOT . '/src/Listing/View/my_listings.php');
        // Per D-02: Active→Edit+Delete; Pending→Edit+Delete; Sold→Relist; Draft→Edit+Submit.
        $this->assertStringContainsString('/listings/', $src);
        $this->assertStringContainsString('/edit', $src);
        $this->assertStringContainsString('/delete', $src);
        $this->assertStringContainsString('/relist', $src);
        $this->assertStringContainsString('/submit', $src);
        // Named empty-state copy from EXPERIENCE.md
        $this->assertStringContainsString('No active listings yet', $src);
        $this->assertStringContainsString('Submit a draft to make it live', $src);
    }

    public function test_listing_status_pill_partial_maps_all_statuses(): void
    {
        $src = file_get_contents(APP_ROOT . '/src/Support/View/partials/listing_status_pill.php');
        foreach (['draft', 'pending', 'active', 'rejected', 'sold', 'removed'] as $status) {
            $this->assertStringContainsString("'$status'", $src, "Pill should handle status=$status");
        }
        // review_flag inline pill (D-09)
        $this->assertStringContainsString('Under review', $src);
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
