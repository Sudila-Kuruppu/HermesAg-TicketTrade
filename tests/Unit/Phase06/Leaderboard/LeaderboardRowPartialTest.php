<?php
/**
 * Phase 6 Plan 06-03 — LeaderboardRowPartialTest
 *
 * Renders leaderboard_row.php with a known tier and asserts the
 * partial's output contains the rank number, the nickname, the score,
 * and the rank_badge partial output (the SVG path data of the matching
 * tier).
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase06\Leaderboard;

use App\Support\View;
use App\Tests\Integration\Phase06\Fixtures\Fixtures;

class LeaderboardRowPartialTest extends Fixtures
{
    public function test_renders_rank_nickname_score_and_tier_badge(): void
    {
        ob_start();
        View::partial('leaderboard_row', [
            'rank' => 7,
            'userId' => 42,
            'nickname' => 'kasun',
            'meta' => '',
            'tier' => 'C',
            'score' => 320,
            'isSelf' => false,
        ]);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('7', $html, 'rank number missing');
        $this->assertStringContainsString('kasun', $html, 'nickname missing');
        $this->assertStringContainsString('320', $html, 'score missing');
        $this->assertStringContainsString('data-tier="C"', $html, 'tier badge missing');
        $this->assertStringContainsString('href="/profile/kasun"', $html, 'profile link missing');
        $this->assertStringContainsString('data-user-id="42"', $html, 'user id missing');
    }

    public function test_renders_meta_cell_when_provided(): void
    {
        ob_start();
        View::partial('leaderboard_row', [
            'rank' => 1,
            'userId' => 1,
            'nickname' => 'top',
            'meta' => '7 sales',
            'tier' => 'B',
            'score' => 12,
        ]);
        $html = (string) ob_get_clean();
        $this->assertStringContainsString('7 sales', $html);
    }

    public function test_omits_meta_cell_when_empty(): void
    {
        ob_start();
        View::partial('leaderboard_row', [
            'rank' => 1,
            'userId' => 1,
            'nickname' => 'top',
            'meta' => '',
            'tier' => 'B',
            'score' => 12,
        ]);
        $html = (string) ob_get_clean();
        // No meta cell rendered when meta is empty.
        $this->assertStringNotContainsString('leaderboard-row__meta', $html);
    }

    public function test_self_row_adds_self_modifier_class(): void
    {
        ob_start();
        View::partial('leaderboard_row', [
            'rank' => 1,
            'userId' => 1,
            'nickname' => 'me',
            'meta' => '',
            'tier' => 'A',
            'score' => 1000,
            'isSelf' => true,
        ]);
        $html = (string) ob_get_clean();
        $this->assertStringContainsString('leaderboard-row--self', $html);
    }
}