<?php
/**
 * Phase 3 — ListingServiceTest
 *
 * Covers the AD-16 failure envelope, validation rules, the rate-limit
 * guard, and the 8-image cap on uploadImages.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase03\Listing;

use App\Listing\Service\listing_service;
use App\Tests\Integration\Phase03\Fixtures\Fixtures;

class ListingServiceTest extends Fixtures
{
    public function test_create_draft_happy_path(): void
    {
        $userId = $this->seedUser();
        $catId = $this->seedCategory('TextbooksTest');

        $data = [
            'title' => 'Calculus 101',
            'description' => 'Barely used.',
            'price_cents' => 150_000,
            'category_id' => $catId,
            'type' => 'product',
            'condition' => 'like_new',
            'quantity' => 1,
        ];

        $res = listing_service::createDraft($userId, $data);
        $this->assertTrue($res['ok']);
        $this->assertNotNull($res['data']);
        $this->assertSame('draft', $res['data']['status']);
        $this->assertSame(0, (int) $res['data']['quantity_sold']);
        $this->assertSame($userId, (int) $res['data']['seller_id']);

        // Verify the row exists in DB.
        $row = $this->pdo->query('SELECT * FROM listings WHERE id = ' . (int) $res['data']['id'])->fetch();
        $this->assertNotEmpty($row);
        $this->assertSame('draft', $row['status']);
        $this->assertSame('Calculus 101', $row['title']);
    }

    public function test_create_draft_validation_failures(): void
    {
        $userId = $this->seedUser();
        $catId = $this->seedCategory('TextbooksTest');

        // 1. Empty title
        $r = listing_service::createDraft($userId, $this->validData($catId, ['title' => '']));
        $this->assertFalse($r['ok']);
        $this->assertSame('E_VALIDATION', $r['error']['code']);
        $this->assertArrayHasKey('title', $r['error']['fields']);

        // 2. Oversized title (81 chars)
        $r = listing_service::createDraft($userId, $this->validData($catId, ['title' => str_repeat('a', 81)]));
        $this->assertFalse($r['ok']);
        $this->assertSame('E_VALIDATION', $r['error']['code']);
        $this->assertArrayHasKey('title', $r['error']['fields']);

        // 3. Negative price (0)
        $r = listing_service::createDraft($userId, $this->validData($catId, ['price_cents' => 0]));
        $this->assertFalse($r['ok']);
        $this->assertSame('E_VALIDATION', $r['error']['code']);
        $this->assertArrayHasKey('price_cents', $r['error']['fields']);

        // 4. Quantity 0
        $r = listing_service::createDraft($userId, $this->validData($catId, ['quantity' => 0]));
        $this->assertFalse($r['ok']);
        $this->assertSame('E_VALIDATION', $r['error']['code']);
        $this->assertArrayHasKey('quantity', $r['error']['fields']);

        // 5. Bad category_id
        $r = listing_service::createDraft($userId, $this->validData($catId, ['category_id' => 99999]));
        $this->assertFalse($r['ok']);
        $this->assertSame('E_VALIDATION', $r['error']['code']);
        $this->assertArrayHasKey('category_id', $r['error']['fields']);

        // 6. Bad type
        $r = listing_service::createDraft($userId, $this->validData($catId, ['type' => 'invalid']));
        $this->assertFalse($r['ok']);
        $this->assertSame('E_VALIDATION', $r['error']['code']);
        $this->assertArrayHasKey('type', $r['error']['fields']);

        // 7. Soft-deleted category
        $this->pdo->exec('UPDATE categories SET is_active = 0 WHERE id = ' . $catId);
        $r = listing_service::createDraft($userId, $this->validData($catId));
        $this->assertFalse($r['ok']);
        $this->assertSame('E_VALIDATION', $r['error']['code']);
        $this->assertArrayHasKey('category_id', $r['error']['fields']);
    }

    public function test_rate_limit_returns_e_rate_limit(): void
    {
        $this->pdo->exec('TRUNCATE TABLE cache_rate');
        $userId = $this->seedUser();
        $catId = $this->seedCategory();

        for ($i = 1; $i <= 20; $i++) {
            $r = listing_service::createDraft($userId, $this->validData($catId));
            $this->assertTrue($r['ok'], "Call $i should be allowed");
        }

        $r = listing_service::createDraft($userId, $this->validData($catId));
        $this->assertFalse($r['ok'], '21st call must be blocked');
        $this->assertSame('E_RATE_LIMIT', $r['error']['code']);
    }

    public function test_upload_images_eight_file_cap(): void
    {
        $userId = $this->seedUser();
        $catId = $this->seedCategory();

        // Create a draft listing.
        $r = listing_service::createDraft($userId, $this->validData($catId));
        $listingId = (int) $r['data']['id'];

        // Build 9 fake file descriptors; the 4-layer pipeline will reject
        // them because they aren't real images. We're only asserting the
        // cap-per-file behavior — 8 attempts, 9th rejected.
        $files = [];
        for ($i = 0; $i < 9; $i++) {
            $tmp = tempnam(sys_get_temp_dir(), 'fakeimg');
            file_put_contents($tmp, 'not an image ' . $i);
            $files[] = [
                'name' => "fake{$i}.jpg",
                'tmp_name' => $tmp,
                'size' => filesize($tmp),
                'error' => UPLOAD_ERR_OK,
                'type' => 'image/jpeg',
            ];
        }

        $r = listing_service::uploadImages($listingId, $userId, $files);
        $this->assertTrue($r['ok']);
        // 9 files → 9 errors (because none are valid images, none uploaded).
        $this->assertCount(9, $r['data']['errors']);
        // Last file's error is the cap-rejection with E_IMAGE_INVALID.
        $lastError = end($r['data']['errors']);
        $this->assertSame('E_IMAGE_INVALID', $lastError['code']);

        // No listing_images rows were inserted (all fake).
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM listing_images WHERE listing_id = ' . $listingId)->fetchColumn();
        $this->assertSame(0, $count);
    }

    private function validData(int $catId, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Calculus 101',
            'description' => 'Barely used.',
            'price_cents' => 150_000,
            'category_id' => $catId,
            'type' => 'product',
            'condition' => 'like_new',
            'quantity' => 1,
        ], $overrides);
    }
}
