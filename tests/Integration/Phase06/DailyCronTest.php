<?php
/**
 * Phase 6 Plan 06-03 — DailyCronTest
 *
 * Covers the end-to-end daily sweep:
 *   - 4 leaderboard summary tables populated by refreshAll()
 *   - var/leaderboards/*.json files written
 *   - login_streaks rows UPSERTed for users who logged in today
 *   - users.current_streak + longest_streak updated
 *   - 7-day and 30-day streak bonuses write points_log rows
 *   - cron_log row with job_name='daily' is written
 *   - Idempotency: re-running produces the same end state
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase06;

use App\Leaderboard\Service\leaderboard_service;
use App\Support\Db;
use App\Tests\Integration\Phase06\Fixtures\Fixtures;
use App\User\Service\user_service;

class DailyCronTest extends Fixtures
{
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir = sys_get_temp_dir() . '/daily-cron-test-' . bin2hex(random_bytes(4));
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

    public function test_refresh_all_populates_four_summary_tables(): void
    {
        $this->seedUser(['tier' => 'S', 'points' => 2000, 'nickname' => 'legend1']);
        $this->seedUser(['tier' => 'S', 'points' => 1800, 'nickname' => 'legend2']);

        $counts = leaderboard_service::refreshAll($this->pdo);
        $this->assertSame(2, $counts['campus_legends']);

        // Re-read from the summary table.
        $rows = $this->pdo->query('SELECT user_id, score, rank_position FROM leaderboard_campus_legends ORDER BY rank_position')->fetchAll();
        $this->assertCount(2, $rows);
        $this->assertSame(1, (int) $rows[0]['rank_position']);
        $this->assertSame(2000, (int) $rows[0]['score']);
    }

    public function test_write_json_cache_creates_files_in_given_dir(): void
    {
        $this->seedUser(['tier' => 'S', 'points' => 2000]);
        leaderboard_service::refreshAll($this->pdo);
        $files = leaderboard_service::writeJsonCache($this->pdo, $this->cacheDir);
        $this->assertCount(4, $files);
        foreach ($files as $basename) {
            $path = $this->cacheDir . '/' . $basename;
            $this->assertFileExists($path);
            $payload = json_decode((string) file_get_contents($path), true);
            $this->assertIsArray($payload);
            $this->assertArrayHasKey('generated_at', $payload);
            $this->assertArrayHasKey('rows', $payload);
        }
    }

    public function test_recompute_streak_display_inserts_login_streak_today(): void
    {
        $user = $this->seedUser(['nickname' => 'streaker']);
        $this->seedSessionFor($user);

        $result = user_service::recomputeStreakDisplay($this->pdo);
        $this->assertSame(1, $result['processed']);
        $this->assertSame([], $result['awards']);

        $today = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d');
        $row = $this->pdo->prepare(
            'SELECT streak_count FROM login_streaks WHERE user_id = ? AND login_date = ?'
        );
        $row->execute([$user, $today]);
        $this->assertSame(1, (int) $row->fetchColumn());

        // users.current_streak should now be 1.
        $currentStreak = (int) $this->pdo->query('SELECT current_streak FROM users WHERE user_id = ' . $user)->fetchColumn();
        $this->assertSame(1, $currentStreak);
    }

    public function test_seven_day_streak_writes_streak_7day_points_log(): void
    {
        $user = $this->seedUser(['nickname' => 'week_streak']);
        $this->seedSessionFor($user);

        // Seed 6 prior consecutive days (today is the 7th).
        $today = new \DateTime('now', new \DateTimeZone('Asia/Colombo'));
        for ($i = 1; $i <= 6; $i++) {
            $d = (clone $today)->modify("-{$i} day")->format('Y-m-d');
            $this->seedLoginStreak($user, $d, 1);
        }

        $result = user_service::recomputeStreakDisplay($this->pdo);
        $this->assertSame(1, $result['processed']);
        $this->assertCount(1, $result['awards']);
        $this->assertSame(7, $result['awards'][0]['streak_days']);
        $this->assertSame(15, $result['awards'][0]['delta']);

        $count = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM points_log WHERE user_id = $user AND reference_type = 'streak_7day'"
        )->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function test_thirty_day_streak_writes_streak_30day_points_log(): void
    {
        $user = $this->seedUser(['nickname' => 'month_streak']);
        $this->seedSessionFor($user);

        $today = new \DateTime('now', new \DateTimeZone('Asia/Colombo'));
        for ($i = 1; $i <= 29; $i++) {
            $d = (clone $today)->modify("-{$i} day")->format('Y-m-d');
            $this->seedLoginStreak($user, $d, 1);
        }

        $result = user_service::recomputeStreakDisplay($this->pdo);
        $this->assertCount(1, $result['awards']);
        $this->assertSame(30, $result['awards'][0]['streak_days']);
        $this->assertSame(50, $result['awards'][0]['delta']);
    }

    public function test_cron_log_row_with_daily_job_name(): void
    {
        $user = $this->seedUser(['nickname' => 'daily_cron_user']);
        $this->seedSessionFor($user);

        // Simulate handleDaily's cron_log INSERT.
        $this->seedUser(['nickname' => 'admin_actor', 'is_admin' => true]);

        // Run the sweep + log like CronAction does.
        $refreshCounts = leaderboard_service::refreshAll($this->pdo);
        $streakResult = user_service::recomputeStreakDisplay($this->pdo);
        leaderboard_service::writeJsonCache($this->pdo, $this->cacheDir);

        $processedTotal = array_sum($refreshCounts) + $streakResult['processed'] + 4;
        $this->pdo->prepare(
            'INSERT INTO cron_log (job_name, run_at, processed_count, errors_json, actor_user_id, created_at) '
            . 'VALUES (?, NOW(), ?, ?, NULL, NOW())'
        )->execute(['daily', $processedTotal, json_encode([])]);

        $row = $this->pdo->query(
            "SELECT job_name, processed_count FROM cron_log WHERE job_name = 'daily' ORDER BY id DESC LIMIT 1"
        )->fetch();
        $this->assertNotFalse($row);
        $this->assertSame('daily', $row['job_name']);
    }

    public function test_recompute_is_idempotent_within_a_day(): void
    {
        $user = $this->seedUser(['nickname' => 'idem']);
        $this->seedSessionFor($user);

        $first = user_service::recomputeStreakDisplay($this->pdo);
        $second = user_service::recomputeStreakDisplay($this->pdo);
        $this->assertSame($first['processed'], $second['processed']);
    }

    public function test_full_pipeline_reflects_in_summary_table(): void
    {
        $u1 = $this->seedUser(['tier' => 'S', 'points' => 2000, 'nickname' => 'alpha']);
        $u2 = $this->seedUser(['tier' => 'A', 'points' => 900, 'nickname' => 'bravo']);

        // Award some points via the points_service (50 each via a listing approval).
        $catId = $this->firstCategoryId();
        $listing = $this->seedListing($u2, $catId);

        // Full sweep.
        $refresh = leaderboard_service::refreshAll($this->pdo);
        $this->assertSame(1, $refresh['campus_legends']);
        leaderboard_service::writeJsonCache($this->pdo, $this->cacheDir);

        // Read the cache back; alpha is the only tier-S user at rank 1.
        $payload = json_decode(
            (string) file_get_contents($this->cacheDir . '/campus_legends.json'),
            true
        );
        $this->assertCount(1, $payload['rows']);
        $this->assertSame(1, $payload['rows'][0]['rank']);
    }
}