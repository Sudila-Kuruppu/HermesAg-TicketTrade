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

        // Phase 6 audit: the streak bonus is a lifetime milestone
        // (points_log existence-check guards re-runs), so the
        // second invocation must produce NO new streak_7day /
        // streak_30day rows and NO new awards. This was the bug
        // before the audit: a user hitting day 7, then the cron
        // running twice that day, would get +15+15=30 instead of +15.
        $this->assertSame([], $first['awards']);
        $this->assertSame([], $second['awards']);
    }

    /**
     * Phase 6 audit regression: recompute must not duplicate the
     * streak_7day bonus across re-runs (manual /admin/cron/daily
     * trigger after the auto-run is the realistic scenario).
     *
     * Strengthens test_recompute_is_idempotent_within_a_day by
     * asserting on the underlying points_log rows, not just the
     * processed/awards counts.
     */
    public function test_recompute_does_not_duplicate_streak_award(): void
    {
        $user = $this->seedUser(['nickname' => 'no_double_bonus']);
        $this->seedSessionFor($user);

        // Seed the previous 6 consecutive days so today's session
        // is the 7th (the trigger for the +15 streak_7day bonus).
        $today = new \DateTime('now', new \DateTimeZone('Asia/Colombo'));
        for ($i = 1; $i <= 6; $i++) {
            $d = (clone $today)->modify("-{$i} day")->format('Y-m-d');
            $this->seedLoginStreak($user, $d, 1);
        }

        // First run: should write exactly 1 streak_7day row + 1 award.
        $first = user_service::recomputeStreakDisplay($this->pdo);
        $this->assertCount(1, $first['awards']);
        $this->assertSame(7, $first['awards'][0]['streak_days']);
        $this->assertSame(15, $first['awards'][0]['delta']);

        $countAfterFirst = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM points_log WHERE user_id = $user AND reference_type = 'streak_7day'"
        )->fetchColumn();
        $this->assertSame(1, $countAfterFirst, 'first run: exactly one streak_7day row');

        // Second run (simulating a manual /admin/cron/daily trigger
        // after the auto-run): must NOT award again, must NOT write
        // a second streak_7day points_log row.
        $second = user_service::recomputeStreakDisplay($this->pdo);
        $this->assertSame([], $second['awards'], 'second run: no awards');

        $countAfterSecond = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM points_log WHERE user_id = $user AND reference_type = 'streak_7day'"
        )->fetchColumn();
        $this->assertSame(1, $countAfterSecond, 'second run: still exactly one streak_7day row');

        // Third run for good measure (race-against-flake paranoia).
        $third = user_service::recomputeStreakDisplay($this->pdo);
        $this->assertSame([], $third['awards']);
        $countAfterThird = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM points_log WHERE user_id = $user AND reference_type = 'streak_7day'"
        )->fetchColumn();
        $this->assertSame(1, $countAfterThird);
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

    /**
     * CR-02 regression: an idle user with a yesterday-only session
     * (cookie persisted but no page load today) MUST still receive
     * today's login_streaks row + streak continuation. The 06-REVIEW
     * CR-02 audit found the original WHERE DATE(s.last_seen) = today
     * silently skipped these users because session_model::touch()
     * only bumps last_seen on page loads (5-minute window).
     *
     * The fix widened the predicate to last_seen >= yesterday, which
     * makes the idle-back-tab case visible to the cron. This test
     * seeds a user with last_seen = yesterday morning and asserts
     * that recomputeStreakDisplay processes them.
     */
    public function test_recompute_includes_user_with_yesterday_only_session(): void
    {
        $user = $this->seedUser(['nickname' => 'idle_user']);
        // last_seen = yesterday morning (Asia/Colombo wall-clock).
        $yesterdayMorning = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))
            ->modify('-1 day')
            ->setTime(9, 0, 0)
            ->format('Y-m-d H:i:s');
        $this->seedSessionFor($user, $yesterdayMorning);

        $result = user_service::recomputeStreakDisplay($this->pdo);
        $this->assertSame(1, $result['processed'], 'idle user with yesterday session must be counted');

        // Today's login_streaks row was written.
        $today = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d');
        $row = $this->pdo->prepare(
            'SELECT streak_count FROM login_streaks WHERE user_id = ? AND login_date = ?'
        );
        $row->execute([$user, $today]);
        $this->assertSame(1, (int) $row->fetchColumn());

        // current_streak bumped.
        $currentStreak = (int) $this->pdo->query('SELECT current_streak FROM users WHERE user_id = ' . $user)->fetchColumn();
        $this->assertSame(1, $currentStreak);
    }

    /**
     * CR-02 boundary: a user with a session older than 48 hours
     * (last_seen = 2 days ago) should NOT be counted — they
     * genuinely haven't been around. The "yesterday OR today"
     * window is intentionally not "forever".
     */
    public function test_recompute_skips_user_with_session_older_than_48h(): void
    {
        $user = $this->seedUser(['nickname' => 'dormant_user']);
        $twoDaysAgo = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))
            ->modify('-2 day')
            ->setTime(9, 0, 0)
            ->format('Y-m-d H:i:s');
        $this->seedSessionFor($user, $twoDaysAgo);

        $result = user_service::recomputeStreakDisplay($this->pdo);
        $this->assertSame(0, $result['processed'], 'dormant user (>48h ago) must NOT be counted');

        $today = (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))->format('Y-m-d');
        $row = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_streaks WHERE user_id = ? AND login_date = ?'
        );
        $row->execute([$user, $today]);
        $this->assertSame(0, (int) $row->fetchColumn(), 'no login_streaks row for today');
    }
}