<?php

/**
 * TicketTrade — Leaderboard\Action\LeaderboardAction
 *
 * Plan 06-03. The public `/leaderboards` endpoint (auth=false, csrf=false,
 * no rate limit). Reads JSON cache files written by the daily cron; on
 * cache miss (cold start before the first daily cron) falls back to a
 * direct read from the leaderboard_* summary tables.
 *
 * Privacy: rows are locked to {rank, user_id, nickname, tier, score,
 * metadata} — NEVER student_id, full_name, email, or whatsapp (T-06-13).
 */

declare(strict_types=1);

namespace App\Leaderboard\Action;

use App\Leaderboard\Service\leaderboard_service;
use App\Support\Db;
use App\Support\View;

class LeaderboardAction
{
    /**
     * GET /leaderboards.
     *
     * Renders the four-board grid. Each board reads its cached payload;
     * missing cache files render the cold-load skeleton + per-board empty
     * state.
     */
    public function handleGet(): void
    {
        $boards = [];
        foreach (leaderboard_service::BOARDS as $slug) {
            $cached = leaderboard_service::getCached($slug);
            if ($cached === null) {
                // Cold start: read the summary table directly. The View
                // treats this the same as a populated cache.
                $rows = leaderboard_service::readSummary(Db::pdo(), $slug);
                $cached = [
                    'generated_at' => (new \DateTime('now', new \DateTimeZone('Asia/Colombo')))
                        ->format('Y-m-d H:i:s'),
                    'rows' => $rows,
                ];
            }
            $boards[$slug] = $cached;
        }
        View::render(__DIR__ . '/../View/leaderboards.php', [
            'boards' => $boards,
        ]);
    }
}
