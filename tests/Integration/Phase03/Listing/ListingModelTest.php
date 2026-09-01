<?php
/**
 * Phase 3 — ListingModelTest
 *
 * Verifies the raw PDO accessors on App\Listing\Model\listing_model:
 * - insert / findById round-trip
 * - findBySellerId returns rows in created_at DESC
 * - search() uses the FULLTEXT index and respects category_id + status
 * - getSearchCount matches search()
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase03\Listing;

use App\Listing\Model\listing_model;
use App\Tests\Integration\Phase03\Fixtures\Fixtures;

class ListingModelTest extends Fixtures
{
    public function test_insert_and_findById_round_trip(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller1']);
        $catId = $this->seedCategory('TestCategoryLM', 200);

        $id = listing_model::insert([
            'seller_id' => $seller,
            'category_id' => $catId,
            'title' => 'Linear Algebra',
            'description' => 'Good condition.',
            'price_cents' => 100_000,
            'type' => 'product',
            'condition' => 'good',
            'quantity' => 1,
            'status' => 'active',
        ]);

        $row = listing_model::findById($id);
        $this->assertNotNull($row);
        $this->assertSame('Linear Algebra', $row['title']);
        $this->assertSame(100_000, (int) $row['price_cents']);
        $this->assertSame('active', $row['status']);
        $this->assertSame(0, (int) $row['quantity_sold']);
    }

    public function test_findBySellerId_filters_by_status(): void
    {
        $seller = $this->seedUser(['nickname' => 's2']);
        $catId = $this->seedCategory('TestCategoryLM', 200);

        listing_model::insert([
            'seller_id' => $seller, 'category_id' => $catId,
            'title' => 'A', 'description' => 'x', 'price_cents' => 100,
            'type' => 'product', 'quantity' => 1, 'status' => 'draft',
        ]);
        listing_model::insert([
            'seller_id' => $seller, 'category_id' => $catId,
            'title' => 'B', 'description' => 'x', 'price_cents' => 100,
            'type' => 'product', 'quantity' => 1, 'status' => 'active',
        ]);
        listing_model::insert([
            'seller_id' => $seller, 'category_id' => $catId,
            'title' => 'C', 'description' => 'x', 'price_cents' => 100,
            'type' => 'product', 'quantity' => 1, 'status' => 'active',
        ]);

        $rows = listing_model::findBySellerId($seller, 'active', 50);
        $this->assertCount(2, $rows);
        foreach ($rows as $r) {
            $this->assertSame('active', $r['status']);
        }
    }

    public function test_search_filters_inactive_and_other_categories(): void
    {
        $seller = $this->seedUser(['nickname' => 's3']);
        $cat1 = $this->seedCategory('TextbooksA', 100);
        $cat2 = $this->seedCategory('ElectronicsB', 101);

        listing_model::insert([
            'seller_id' => $seller, 'category_id' => $cat1,
            'title' => 'Calculus textbook', 'description' => 'Used.',
            'price_cents' => 100, 'type' => 'product', 'quantity' => 1,
            'status' => 'active',
        ]);
        listing_model::insert([
            'seller_id' => $seller, 'category_id' => $cat2,
            'title' => 'Calculator', 'description' => 'Brand new.',
            'price_cents' => 100, 'type' => 'product', 'quantity' => 1,
            'status' => 'active',
        ]);
        listing_model::insert([
            'seller_id' => $seller, 'category_id' => $cat1,
            'title' => 'Draft item', 'description' => 'Should be hidden.',
            'price_cents' => 100, 'type' => 'product', 'quantity' => 1,
            'status' => 'draft',
        ]);

        // No filter: returns only active rows.
        $rows = listing_model::search(null, null, 0, 50);
        $this->assertCount(2, $rows);

        // Filter by cat1: returns 1 row.
        $rows = listing_model::search(null, $cat1, 0, 50);
        $this->assertCount(1, $rows);

        // FULLTEXT match 'calculus*' on cat1: returns 1 row.
        $rows = listing_model::search('calculus', $cat1, 0, 50);
        $this->assertCount(1, $rows);
        $this->assertSame('Calculus textbook', $rows[0]['title']);
    }

    public function test_getSearchCount_matches_search(): void
    {
        $seller = $this->seedUser();
        $catId = $this->seedCategory('TestCategoryLM', 200);

        for ($i = 0; $i < 3; $i++) {
            listing_model::insert([
                'seller_id' => $seller, 'category_id' => $catId,
                'title' => 'Item ' . $i, 'description' => 'desc',
                'price_cents' => 100, 'type' => 'product', 'quantity' => 1,
                'status' => 'active',
            ]);
        }

        $rows = listing_model::search(null, null, 0, 50);
        $count = listing_model::getSearchCount(null, null);
        $this->assertCount(3, $rows);
        $this->assertSame(3, $count);
    }

    public function test_setStatus_updates_status_column(): void
    {
        $seller = $this->seedUser();
        $catId = $this->seedCategory('TestCategoryLM', 200);
        $id = listing_model::insert([
            'seller_id' => $seller, 'category_id' => $catId,
            'title' => 'T', 'description' => 'd', 'price_cents' => 100,
            'type' => 'product', 'quantity' => 1, 'status' => 'draft',
        ]);

        listing_model::setStatus($id, 'pending');
        $row = listing_model::findById($id);
        $this->assertSame('pending', $row['status']);

        listing_model::setStatus($id, 'active');
        $row = listing_model::findById($id);
        $this->assertSame('active', $row['status']);
        $this->assertNotNull($row['approved_at']);
    }

    public function test_set_review_flag_toggles_value(): void
    {
        $seller = $this->seedUser();
        $catId = $this->seedCategory('TestCategoryLM', 200);
        $id = listing_model::insert([
            'seller_id' => $seller, 'category_id' => $catId,
            'title' => 'T', 'description' => 'd', 'price_cents' => 100,
            'type' => 'product', 'quantity' => 1, 'status' => 'active',
        ]);

        listing_model::setReviewFlag($id, true);
        $row = listing_model::findById($id);
        $this->assertSame(1, (int) $row['review_flag']);
        $this->assertNotNull($row['review_flag_at']);

        listing_model::setReviewFlag($id, false);
        $row = listing_model::findById($id);
        $this->assertSame(0, (int) $row['review_flag']);
        $this->assertNull($row['review_flag_at']);
    }
}
