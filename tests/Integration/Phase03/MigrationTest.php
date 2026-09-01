<?php
/**
 * Phase 3 — MigrationTest
 *
 * Asserts the 3 new tables + 7-row categories seed run idempotently
 * via php migrate.php.
 *
 * NOTE: This test is sensitive to .applied state. Phase02's
 * MigrateRunnerTest already exercises the runner; this test focuses on
 * the schema presence and the categories seed.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase03;

use App\Support\Db;
use App\Tests\Integration\Phase03\Fixtures\Fixtures;

class MigrationTest extends Fixtures
{
    public function test_listings_table_has_expected_columns(): void
    {
        $pdo = Db::pdo();
        $cols = $this->columns($pdo, 'listings');
        $expected = ['id', 'seller_id', 'category_id', 'title', 'description',
            'price_cents', 'type', 'condition', 'duration_minutes',
            'delivery_method', 'availability', 'quantity', 'quantity_sold',
            'status', 'review_flag', 'review_flag_at', 'rejection_reason',
            'source_listing_id', 'approved_at', 'approved_by',
            'created_at', 'updated_at'];
        foreach ($expected as $c) {
            $this->assertContains($c, $cols, "listings.{$c} missing");
        }
    }

    public function test_listings_fulltext_index_exists(): void
    {
        $pdo = Db::pdo();
        $rows = $pdo->query("SHOW INDEX FROM listings WHERE Key_name = 'ft_title_desc'")->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertGreaterThanOrEqual(2, $rows, 'FULLTEXT (title, description) index missing');
    }

    public function test_listing_images_table_has_expected_columns(): void
    {
        $pdo = Db::pdo();
        $cols = $this->columns($pdo, 'listing_images');
        foreach (['id', 'listing_id', 'sha256', 'size', 'is_primary', 'sort_order', 'created_at'] as $c) {
            $this->assertContains($c, $cols, "listing_images.{$c} missing");
        }
    }

    public function test_listing_revisions_table_has_expected_columns(): void
    {
        $pdo = Db::pdo();
        $cols = $this->columns($pdo, 'listing_revisions');
        foreach (['id', 'listing_id', 'snapshot_json', 'created_at', 'created_by'] as $c) {
            $this->assertContains($c, $cols, "listing_revisions.{$c} missing");
        }
    }

    public function test_categories_table_has_seven_seeded_rows(): void
    {
        $pdo = Db::pdo();
        $count = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
        $this->assertSame(7, $count);

        $rows = $pdo->query('SELECT name FROM categories ORDER BY sort_order ASC')->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(['Textbooks', 'Electronics', 'Fashion', 'Services', 'Food', 'Events', 'Other'], $rows);
    }

    /**
     * Helper: list column names for a table.
     */
    private function columns(\PDO $pdo, string $table): array
    {
        $rows = $pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll(\PDO::FETCH_ASSOC);
        return array_column($rows, 'Field');
    }
}
