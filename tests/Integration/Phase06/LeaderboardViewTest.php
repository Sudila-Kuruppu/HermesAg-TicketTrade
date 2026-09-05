<?php
/**
 * Phase 6 Plan 06-03 — LeaderboardViewTest
 *
 * Renders the leaderboards View and asserts the four board titles +
 * a leaderboard_row partial output appear in the response body.
 *
 * Dispatch is via the public route map (auth=false, csrf=false) — we
 * use the support Router directly so the test exercises the route
 * wiring + the Action + the View in one path.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase06;

use App\Leaderboard\Service\leaderboard_service;
use App\Support\Db;
use App\Support\Router;
use App\Tests\Integration\Phase06\Fixtures\Fixtures;

class LeaderboardViewTest extends Fixtures
{
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir = sys_get_temp_dir() . '/leaderboard-view-test-' . bin2hex(random_bytes(4));
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

    public function test_view_renders_four_board_titles_and_row(): void
    {
        // Seed one user so Campus Legends has a row.
        $user = $this->seedUser(['tier' => 'S', 'points' => 2000, 'nickname' => 'singleton']);
        leaderboard_service::refreshAll($this->pdo);
        leaderboard_service::writeJsonCache($this->pdo, $this->cacheDir);

        // Temporarily redirect the cacheDir to our test dir.
        $originalDir = leaderboard_service::cacheDir();
        foreach (leaderboard_service::BOARDS as $slug) {
            $src = $this->cacheDir . '/' . $slug . '.json';
            $dst = $originalDir . '/' . $slug . '.json';
            @mkdir(dirname($dst), 0775, true);
            copy($src, $dst);
        }

        // Capture the rendered output via output buffering + Router::dispatch.
        try {
            ob_start();
            Router::dispatch('student', '/leaderboards');
            $body = (string) ob_get_clean();

            $this->assertStringContainsString('Leaderboards', $body, 'page title missing');
            $this->assertStringContainsString('Campus Legends Wall', $body, 'Campus Legends title missing');
            $this->assertStringContainsString('Weekly Risers', $body, 'Weekly Risers title missing');
            $this->assertStringContainsString('Category Leaders', $body, 'Category Leaders title missing');
            $this->assertStringContainsString('Streak Kings', $body, 'Streak Kings title missing');
            $this->assertStringContainsString('singleton', $body, 'leaderboard row nickname missing');
        } finally {
            foreach (leaderboard_service::BOARDS as $slug) {
                @unlink($originalDir . '/' . $slug . '.json');
            }
        }
    }
}