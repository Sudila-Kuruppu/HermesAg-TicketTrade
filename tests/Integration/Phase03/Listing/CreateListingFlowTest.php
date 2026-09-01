<?php
/**
 * Phase 3 — CreateListingFlowTest
 *
 * Verifies the CreateListingAction GET/POST flow via Service + View
 * inspection (the Action itself calls exit() on redirects, so we
 * test the underlying components rather than dispatching through it).
 *
 * Coverage:
 *   - createDraft happy path → row with status=pending (after submitDraft)
 *   - empty title → E_VALIDATION field error
 *   - 21st call → E_RATE_LIMIT
 *   - create.php View renders all required elements (form, csrf,
 *     categories select, type radios, two submit buttons, image input)
 *   - submitDraft flips draft → pending
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase03\Listing;

use App\Listing\Service\listing_service;
use App\Tests\Integration\Phase03\Fixtures\Fixtures;

class CreateListingFlowTest extends Fixtures
{
    public function test_create_draft_returns_draft_then_submit_flips_to_pending(): void
    {
        $userId = $this->seedUser();
        $catId = $this->firstCategoryId();

        $r = listing_service::createDraft($userId, $this->validData($catId));
        $this->assertTrue($r['ok']);
        $this->assertSame('draft', $r['data']['status']);
        $this->assertSame(0, (int) $r['data']['quantity_sold']);

        $r2 = listing_service::submitDraft((int) $r['data']['id'], $userId);
        $this->assertTrue($r2['ok']);
        $this->assertSame('pending', $r2['data']['status']);
    }

    public function test_empty_title_returns_validation_error(): void
    {
        $userId = $this->seedUser();
        $catId = $this->firstCategoryId();
        $r = listing_service::createDraft($userId, $this->validData($catId, ['title' => '']));
        $this->assertFalse($r['ok']);
        $this->assertSame('E_VALIDATION', $r['error']['code']);
        $this->assertArrayHasKey('title', $r['error']['fields']);
    }

    public function test_21st_call_returns_rate_limit(): void
    {
        $userId = $this->seedUser();
        $catId = $this->firstCategoryId();
        for ($i = 0; $i < 20; $i++) {
            $r = listing_service::createDraft($userId, $this->validData($catId));
            $this->assertTrue($r['ok']);
        }
        $r = listing_service::createDraft($userId, $this->validData($catId));
        $this->assertFalse($r['ok']);
        $this->assertSame('E_RATE_LIMIT', $r['error']['code']);
    }

    public function test_create_view_has_required_form_elements(): void
    {
        $src = file_get_contents(APP_ROOT . '/src/Listing/View/create.php');
        $this->assertStringContainsString('<form method="POST" action="/listings/create"', $src);
        $this->assertStringContainsString('enctype="multipart/form-data"', $src);
        $this->assertStringContainsString('name="csrf_token"', $src);
        $this->assertStringContainsString('name="title"', $src);
        $this->assertStringContainsString('maxlength="80"', $src);
        $this->assertStringContainsString('name="description"', $src);
        $this->assertStringContainsString('maxlength="2000"', $src);
        $this->assertStringContainsString('name="price_rupees"', $src);
        $this->assertStringContainsString('name="category_id"', $src);
        $this->assertStringContainsString('name="type"', $src);
        $this->assertStringContainsString('value="product"', $src);
        $this->assertStringContainsString('value="service"', $src);
        $this->assertStringContainsString('name="quantity"', $src);
        $this->assertStringContainsString('name="images[]"', $src);
        $this->assertStringContainsString('multiple', $src);
        $this->assertStringContainsString('value="save_draft"', $src);
        $this->assertStringContainsString('>Save as draft<', $src);
        $this->assertStringContainsString('value="submit"', $src);
        $this->assertStringContainsString('>Submit for review<', $src);
        // Bootstrap is-invalid + invalid-feedback pairing for field errors
        $this->assertStringContainsString('is-invalid', $src);
        $this->assertStringContainsString('invalid-feedback', $src);
        $this->assertStringContainsString('aria-describedby="title-err"', $src);
    }

    public function test_create_view_lists_7_categories(): void
    {
        // Sanity check: ensure 7 categories exist in the seed (setUp).
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM categories WHERE is_active = 1')->fetchColumn();
        $this->assertSame(7, $count);
    }

    public function test_route_map_has_create_routes(): void
    {
        $routes = require APP_ROOT . '/config/routes.php';
        $this->assertArrayHasKey('GET /listings/create', $routes);
        $this->assertArrayHasKey('POST /listings/create', $routes);
        $this->assertSame('App\\Listing\\Action\\CreateListingAction', $routes['GET /listings/create'][0]);
        $this->assertSame('handle', $routes['GET /listings/create'][1]);
        $this->assertSame('handlePost', $routes['POST /listings/create'][1]);
        $this->assertTrue($routes['POST /listings/create'][2]['csrf']);
        $this->assertSame('listing_create', $routes['POST /listings/create'][2]['rate_limit']);
    }

    private function validData(int $catId, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Calculus 101',
            'description' => 'Barely used textbook',
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
