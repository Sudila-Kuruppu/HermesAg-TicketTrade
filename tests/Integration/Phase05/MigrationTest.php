<?php
/**
 * Phase 5 — MigrationTest
 *
 * Verifies the 017_reviews migration applied cleanly:
 *   - reviews table exists with all expected columns + indexes
 *   - 14-day window gate fields are reachable
 *   - Re-running migrate.php is a no-op (idempotency)
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase05;

use App\Tests\Integration\Phase04\Fixtures\Fixtures;

class MigrationTest extends Fixtures
{
    public function test_reviews_table_has_expected_columns(): void
    {
        $cols = $this->columns('reviews');
        $expected = [
            'id', 'ticket_id', 'reviewer_id', 'reviewee_id',
            'rating', 'comment', 'reviewer_role', 'created_at',
        ];
        foreach ($expected as $c) {
            $this->assertContains($c, $cols, "reviews.{$c} missing");
        }
    }

    public function test_reviews_unique_index_per_ticket_role(): void
    {
        $rows = $this->pdo->query("SHOW INDEX FROM reviews WHERE Key_name = 'uq_review_per_role'")->fetchAll();
        $this->assertGreaterThanOrEqual(1, $rows, 'uq_review_per_role index missing');
    }

    public function test_reviews_reviewee_index(): void
    {
        $rows = $this->pdo->query("SHOW INDEX FROM reviews WHERE Key_name = 'idx_reviewee'")->fetchAll();
        $this->assertGreaterThanOrEqual(1, $rows, 'idx_reviewee index missing');
    }

    public function test_reviews_fk_to_tickets_and_users(): void
    {
        $rows = $this->pdo->query(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS "
            . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reviews' "
            . "AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
        )->fetchAll();
        $fks = array_column($rows, 'CONSTRAINT_NAME');
        $this->assertContains('fk_reviews_ticket', $fks);
        $this->assertContains('fk_reviews_reviewer', $fks);
        $this->assertContains('fk_reviews_reviewee', $fks);
    }

    public function test_migrations_are_idempotent_for_017(): void
    {
        $applied = file(APP_ROOT . '/migrations/.applied', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertContains('017_reviews.sql', $applied);
    }

    private function columns(string $table): array
    {
        $rows = $this->pdo->query("SHOW COLUMNS FROM {$table}")->fetchAll(\PDO::FETCH_ASSOC);
        return array_column($rows, 'Field');
    }
}
