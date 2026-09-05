<?php
/**
 * Phase 6 Plan 06-03 — LeaderboardServiceTest
 *
 * Covers the Leaderboard\Service\leaderboard_service surface:
 *   - refreshAll returns rows-affected counts per board
 *   - writeJsonCache writes 4 files
 *   - getCached reads back the cached payload
 *   - getCached returns null on miss
 *   - readSummary falls back when no cache exists
 *   - Privacy: the readSummary SELECT NEVER references student_id,
 *     full_name (beyond the metadata column for campus_legends only),
 *     email, or whatsapp.
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase06\Leaderboard;

use App\Leaderboard\Service\leaderboard_service;
use App\Tests\Integration\Phase06\Fixtures\Fixtures;

class LeaderboardServiceTest extends Fixtures
{
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        // Use a per-test temp directory for cache files.
        $this->cacheDir = sys_get_temp_dir() . '/leaderboard-test-' . bin2hex(random_bytes(4));
        @mkdir($this->cacheDir, 0775, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cacheDir)) {
            foreach (glob($this->cacheDir . '/*.json') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->cacheDir);
        }
        parent::tearDown();
    }

    public function test_refresh_all_returns_counts_per_board(): void
    {
        // Seed users across multiple tiers + a redeemed ticket.
        $sUser = $this->seedUser(['tier' => 'S', 'points' => 2000]);
        $aUser = $this->seedUser(['tier' => 'A', 'points' => 900]);
        $bUser = $this->seedUser(['tier' => 'B', 'points' => 500]);
        $categoryId = $this->firstCategoryId();
        $listing = $this->seedListing($sUser, $categoryId);
        $ticket = $this->seedTicket([
            'listing_id' => $listing,
            'buyer_id' => $aUser,
            'seller_id' => $sUser,
            'status' => 'redeemed',
        ]);

        $result = leaderboard_service::refreshAll($this->pdo);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('campus_legends', $result);
        $this->assertArrayHasKey('weekly_risers', $result);
        $this->assertArrayHasKey('category_leaders', $result);
        $this->assertArrayHasKey('streak_kings', $result);
        $this->assertSame(1, $result['campus_legends'], 'only one tier-S user should be on Campus Legends');
        $this->assertSame(1, $result['category_leaders'], 'one redeemed ticket in one category');

        // Streak Kings: zero (no users have current_streak > 0 yet).
        $this->assertSame(0, $result['streak_kings']);
    }

    public function test_write_json_cache_writes_four_files(): void
    {
        $this->seedUser(['tier' => 'S', 'points' => 2000]);
        leaderboard_service::refreshAll($this->pdo);
        $files = leaderboard_service::writeJsonCache($this->pdo, $this->cacheDir);

        $this->assertCount(4, $files);
        $this->assertSame('campus_legends.json', $files['campus_legends']);
        $this->assertSame('weekly_risers.json', $files['weekly_risers']);
        $this->assertSame('category_leaders.json', $files['category_leaders']);
        $this->assertSame('streak_kings.json', $files['streak_kings']);
        foreach ($files as $basename) {
            $this->assertFileExists($this->cacheDir . '/' . $basename);
        }
    }

    public function test_get_cached_reads_back_written_file(): void
    {
        $this->seedUser(['tier' => 'S', 'points' => 2000]);
        leaderboard_service::refreshAll($this->pdo);
        leaderboard_service::writeJsonCache($this->pdo, $this->cacheDir);

        // Temporarily point the cacheDir to our test dir.
        $original = leaderboard_service::cacheDir();
        // Save the written file to the production cache dir as well so
        // getCached() picks it up.
        foreach (leaderboard_service::BOARDS as $slug) {
            $src = $this->cacheDir . '/' . $slug . '.json';
            $dst = $original . '/' . $slug . '.json';
            @mkdir(dirname($dst), 0775, true);
            copy($src, $dst);
        }

        try {
            $cached = leaderboard_service::getCached('campus_legends');
            $this->assertIsArray($cached);
            $this->assertArrayHasKey('generated_at', $cached);
            $this->assertArrayHasKey('rows', $cached);
            $this->assertNotEmpty($cached['rows']);
            $first = $cached['rows'][0];
            $this->assertSame(1, $first['rank']);
            $this->assertArrayHasKey('nickname', $first);
            $this->assertArrayHasKey('tier', $first);
            $this->assertSame('S', $first['tier']);
            $this->assertSame(2000, $first['score']);
        } finally {
            foreach (leaderboard_service::BOARDS as $slug) {
                @unlink($original . '/' . $slug . '.json');
            }
        }
    }

    public function test_get_cached_returns_null_on_miss(): void
    {
        // No writeJsonCache call — cache dir should not have the file.
        $original = leaderboard_service::cacheDir();
        foreach (leaderboard_service::BOARDS as $slug) {
            @unlink($original . '/' . $slug . '.json');
        }
        $this->assertNull(leaderboard_service::getCached('campus_legends'));
        $this->assertNull(leaderboard_service::getCached('unknown_board'));
    }

    public function test_read_summary_falls_back_when_cache_missing(): void
    {
        $this->seedUser(['tier' => 'S', 'points' => 2000]);
        leaderboard_service::refreshAll($this->pdo);
        // No writeJsonCache call.
        $rows = leaderboard_service::readSummary($this->pdo, 'campus_legends');
        $this->assertCount(1, $rows);
        $this->assertSame(1, $rows[0]['rank']);
        $this->assertSame(2000, $rows[0]['score']);
        $this->assertSame('S', $rows[0]['tier']);
    }

    public function test_read_summary_privacy_excludes_pii_columns(): void
    {
        $user = $this->seedUser([
            'tier' => 'S',
            'points' => 2000,
            'student_id' => 'NSBM/2023/PII',
            'whatsapp' => '+94770000001',
            'email' => 'pii@test.local',
        ]);
        leaderboard_service::refreshAll($this->pdo);
        $rows = leaderboard_service::readSummary($this->pdo, 'campus_legends');
        $this->assertCount(1, $rows);

        $row = $rows[0];
        $this->assertArrayHasKey('nickname', $row);
        $this->assertArrayHasKey('tier', $row);
        $this->assertArrayHasKey('score', $row);
        $this->assertArrayNotHasKey('student_id', $row);
        $this->assertArrayNotHasKey('email', $row);
        $this->assertArrayNotHasKey('whatsapp', $row);
        $this->assertArrayNotHasKey('full_name', $row);

        // Also check the raw JSON cache file does not contain the PII.
        $files = leaderboard_service::writeJsonCache($this->pdo, $this->cacheDir);
        $json = (string) file_get_contents($this->cacheDir . '/' . $files['campus_legends']);
        $this->assertStringNotContainsString('NSBM/2023/PII', $json);
        $this->assertStringNotContainsString('+94770000001', $json);
        $this->assertStringNotContainsString('pii@test.local', $json);
    }

    public function test_refresh_all_is_idempotent(): void
    {
        $this->seedUser(['tier' => 'S', 'points' => 2000]);
        $first = leaderboard_service::refreshAll($this->pdo);
        $second = leaderboard_service::refreshAll($this->pdo);
        // TRUNCATE+INSERT: same row counts.
        $this->assertSame($first, $second);
    }
}