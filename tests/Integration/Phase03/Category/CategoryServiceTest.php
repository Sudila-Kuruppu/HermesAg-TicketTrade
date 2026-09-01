<?php
/**
 * Phase 3 — CategoryServiceTest
 *
 * Verifies the 7-row seed + read paths on the Category service.
 * Also asserts soft-delete semantics (is_active=FALSE returns 404).
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase03\Category;

use App\Category\Service\category_service;
use App\Tests\Integration\Phase03\Fixtures\Fixtures;

class CategoryServiceTest extends Fixtures
{
    public function test_listActive_returns_seeded_categories(): void
    {
        // Fixture already seeded 7 categories via the migrate.php runner.
        $res = category_service::listActive();
        $this->assertTrue($res['ok']);
        $this->assertCount(7, $res['data']);
        $expected = ['Textbooks', 'Electronics', 'Fashion', 'Services', 'Food', 'Events', 'Other'];
        $names = array_column($res['data'], 'name');
        $this->assertSame($expected, $names);
    }

    public function test_getById_returns_404_for_inactive(): void
    {
        $res = category_service::getById(1);
        $this->assertTrue($res['ok']);
        $this->pdo->exec('UPDATE categories SET is_active = 0 WHERE id = 1');

        $res = category_service::getById(1);
        $this->assertFalse($res['ok']);
        $this->assertSame('E_CATEGORY_NOT_FOUND', $res['error']['code']);
    }

    public function test_getById_returns_404_for_missing(): void
    {
        $res = category_service::getById(99999);
        $this->assertFalse($res['ok']);
        $this->assertSame('E_CATEGORY_NOT_FOUND', $res['error']['code']);
    }
}
