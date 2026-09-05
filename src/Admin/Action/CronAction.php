<?php

/**
 * TicketTrade — Admin\Action\CronAction
 *
 * The hand-triggered admin cron endpoint (Plan 04-03). Per AD-11
 * (cron ownership single endpoint) + D-07 (dispatch order), this
 * Action runs three sweeps in sequence per invocation of
 * `POST /admin/cron/ticket-expiry`:
 *
 *   1. 24h listing auto-approve (Phase 3, kept) —
 *      `Listing\Service\listing_service::runAutoApproveSweep()`.
 *   2. 3-day dispute auto-dismiss —
 *      `Ticket\Service\ticket_service::runDisputeAutoDismissSweep()`.
 *   3. 7-day ticket expiry —
 *      `Ticket\Service\ticket_service::runTicketExpirySweep()`.
 *
 * Phase 6 Plan 06-03 ADDS `handleDaily()` for `POST /admin/cron/daily`:
 *   1. refresh the four leaderboard_* summary tables.
 *   2. recompute login_streaks + users.current_streak/longest_streak.
 *   3. write the var/leaderboards/*.json cache files.
 *   4. log the sweep to cron_log with job_name='daily'.
 *
 * Dispatch order is LOCKED per D-07: dispute auto-dismiss runs BEFORE
 * ticket expiry so a dismissed-then-expired ticket lands in `expired`
 * in the same tick (PRD §4.2 composition note).
 *
 * Gating (per AD-19):
 *   - Router opts.admin=true → non-admin gets 404 (D-10).
 *   - Router opts.csrf=true → POST must carry a CSRF token.
 *   - Router opts.rate_limit='admin_cron' → 5/min/IP.
 *   - `Support\Auth::requireReAuth(300)` → 403 JSON on stale.
 *
 * Response shape:
 *   ticket-expiry:
 *     {
 *       "ok": true,
 *       "sweeps": {
 *         "listing_auto_approve": {"processed": N1},
 *         "dispute_auto_dismiss": {"processed": N2, "affected_tickets": [...]},
 *         "ticket_expiry":        {"processed": N3, "affected_tickets": [...]}
 *       },
 *       "errors": []
 *     }
 *   daily:
 *     {
 *       "ok": true,
 *       "sweeps": {
 *         "leaderboard_refresh": {"processed": {...}, "rows_inserted": {...}},
 *         "streak_recompute":    {"processed": N, "awards": [...]},
 *         "cache_write":         {"files": ["..."]}
 *       },
 *       "errors": []
 *     }
 *
 * The cron is idempotent (NFR-REL-002): re-running the endpoint with
 * no eligible rows returns `processed: 0` for each sweep with no DB
 * state change. The 7-day expiry sweep's single guarded UPDATE means
 * a re-run on already-expired tickets is a no-op.
 */

declare(strict_types=1);

namespace App\Admin\Action;

use App\Leaderboard\Service\leaderboard_service;
use App\Listing\Service\listing_service;
use App\Support\Auth as AuthGuard;
use App\Support\Db;
use App\Support\Error;
use App\Ticket\Service\ticket_service;
use App\User\Service\user_service;

class CronAction
{
    /**
     * POST /admin/cron/ticket-expiry
     *
     * Validates re-auth then runs the three sweeps in order:
     * 24h listing auto-approve → 3-day dispute auto-dismiss →
     * 7-day ticket expiry. Each sweep appends a `cron_log` row.
     *
     * Emits 200 + JSON on success, 500 + error envelope on
     * unexpected failure. `requireReAuth()` handles the 403 path
     * before this method runs.
     */
    public function handle(): void
    {
        // The router has already enforced admin + csrf + admin_cron
        // rate limit. Re-check re-auth freshness per AD-19.
        $user = AuthGuard::requireReAuth(300);
        $actorUserId = (int) ($user['user_id'] ?? 0);

        $errors = [];

        // Sweep 1: 24h listing auto-approve (Phase 3 kept).
        $listingResult = listing_service::runAutoApproveSweep($actorUserId);
        $listingProcessed = 0;
        if ($listingResult['ok'] === true) {
            $listingProcessed = (int) ($listingResult['data']['processed'] ?? 0);
        } else {
            $errors[] = [
                'sweep' => 'listing_auto_approve',
                'error' => $listingResult['error'] ?? ['code' => 'E_INTERNAL', 'message' => 'Sweep failed.'],
            ];
        }

        // Sweep 2: 3-day dispute auto-dismiss (Phase 4 Plan 04-03).
        $disputeResult = ticket_service::runDisputeAutoDismissSweep($actorUserId);
        $disputeProcessed = 0;
        $disputeAffected = [];
        if ($disputeResult['ok'] === true) {
            $disputeProcessed = (int) ($disputeResult['data']['processed'] ?? 0);
            $disputeAffected = (array) ($disputeResult['data']['affected_tickets'] ?? []);
        } else {
            $errors[] = [
                'sweep' => 'dispute_auto_dismiss',
                'error' => $disputeResult['error'] ?? ['code' => 'E_INTERNAL', 'message' => 'Sweep failed.'],
            ];
        }

        // Sweep 3: 7-day ticket expiry (Phase 4 Plan 04-03).
        $expiryResult = ticket_service::runTicketExpirySweep($actorUserId);
        $expiryProcessed = 0;
        $expiryAffected = [];
        if ($expiryResult['ok'] === true) {
            $expiryProcessed = (int) ($expiryResult['data']['processed'] ?? 0);
            $expiryAffected = (array) ($expiryResult['data']['affected_tickets'] ?? []);
        } else {
            $errors[] = [
                'sweep' => 'ticket_expiry',
                'error' => $expiryResult['error'] ?? ['code' => 'E_INTERNAL', 'message' => 'Sweep failed.'],
            ];
        }

        // If any sweep failed, return 500 with the per-sweep error envelope.
        if (!empty($errors)) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => [
                    'code' => 'E_INTERNAL',
                    'message' => 'One or more sweeps failed.',
                ],
                'sweeps' => [
                    'listing_auto_approve' => ['processed' => $listingProcessed],
                    'dispute_auto_dismiss' => [
                        'processed' => $disputeProcessed,
                        'affected_tickets' => $disputeAffected,
                    ],
                    'ticket_expiry' => [
                        'processed' => $expiryProcessed,
                        'affected_tickets' => $expiryAffected,
                    ],
                ],
                'errors' => $errors,
            ]);
            exit;
        }

        // Success path: 200 + JSON envelope.
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'sweeps' => [
                'listing_auto_approve' => ['processed' => $listingProcessed],
                'dispute_auto_dismiss' => [
                    'processed' => $disputeProcessed,
                    'affected_tickets' => $disputeAffected,
                ],
                'ticket_expiry' => [
                    'processed' => $expiryProcessed,
                    'affected_tickets' => $expiryAffected,
                ],
            ],
            'errors' => [],
        ]);
        exit;
    }

    /**
     * POST /admin/cron/daily (Plan 06-03).
     *
     * Runs the daily sweep:
     *   1. refreshAll() populates the four leaderboard_* summary tables.
     *   2. recomputeStreakDisplay() updates login_streaks + users.current_streak
     *      + longest_streak + awards the 7/30-day streak bonus via points_service.
     *   3. writeJsonCache() writes the four var/leaderboards/*.json files.
     *   4. cron_log row with job_name='daily' (reuses the 012 column).
     *
     * Re-authed by Support\Auth::requireReAuth(300) — same 300s sliding
     * window as the ticket-expiry sweep (AD-19). The router has already
     * enforced admin + csrf + admin_cron rate limit.
     *
     * Idempotent (NFR-REL-002): the leaderboard summary tables TRUNCATE
     * before INSERT; login_streaks uses ON DUPLICATE KEY UPDATE; the
     * JSON cache files are overwritten; cron_log appends a row per run.
     * Re-running on the same day produces the same end state except for
     * the cron_log row (one extra row per re-run).
     */
    public function handleDaily(): void
    {
        $user = AuthGuard::requireReAuth(300);
        $actorUserId = (int) ($user['user_id'] ?? 0);

        $errors = [];
        $pdo = Db::pdo();

        // Sweep 1: refresh the four leaderboard summary tables.
        $refreshCounts = [];
        try {
            $refreshCounts = leaderboard_service::refreshAll($pdo);
        } catch (\Throwable $e) {
            error_log('[CronAction::handleDaily] refreshAll failed: ' . $e->getMessage());
            $errors[] = [
                'sweep' => 'leaderboard_refresh',
                'error' => ['code' => 'E_INTERNAL', 'message' => 'Leaderboard refresh failed.'],
            ];
        }

        // Sweep 2: recompute login_streaks + users.current_streak/longest_streak.
        $streakResult = ['processed' => 0, 'awards' => []];
        try {
            $streakResult = user_service::recomputeStreakDisplay($pdo);
        } catch (\Throwable $e) {
            error_log('[CronAction::handleDaily] recomputeStreakDisplay failed: ' . $e->getMessage());
            $errors[] = [
                'sweep' => 'streak_recompute',
                'error' => ['code' => 'E_INTERNAL', 'message' => 'Streak recompute failed.'],
            ];
        }

        // Sweep 3: write the var/leaderboards/*.json cache files.
        $cacheFiles = [];
        try {
            $cacheFiles = leaderboard_service::writeJsonCache($pdo, leaderboard_service::cacheDir());
        } catch (\Throwable $e) {
            error_log('[CronAction::handleDaily] writeJsonCache failed: ' . $e->getMessage());
            $errors[] = [
                'sweep' => 'cache_write',
                'error' => ['code' => 'E_INTERNAL', 'message' => 'Cache write failed.'],
            ];
        }

        // Sweep 4: cron_log row.
        //
        // processed_count is the sum of actual rows-touched (leaderboard
        // summary rows inserted + streak recompute users processed).
        // The 4 cache writes happen unconditionally (cache files are
        // always re-written on each run) so they are NOT counted —
        // counting them inflated the metric by 4 every run, masking
        // the real "rows actually changed" signal on ops dashboards.
        // The cache-write count is still surfaced in the response
        // envelope below for observability.
        $processedTotal = array_sum($refreshCounts) + $streakResult['processed'];
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO cron_log (job_name, run_at, processed_count, errors_json, actor_user_id, created_at) '
                . 'VALUES (?, NOW(), ?, ?, ?, NOW())'
            );
            $stmt->execute([
                'daily',
                $processedTotal,
                json_encode($errors, JSON_UNESCAPED_UNICODE),
                $actorUserId > 0 ? $actorUserId : null,
            ]);
        } catch (\Throwable $e) {
            error_log('[CronAction::handleDaily] cron_log write failed: ' . $e->getMessage());
            // Log failure does not break the sweep — keep going.
        }

        // Build the response envelope.
        $body = [
            'ok' => empty($errors),
            'sweeps' => [
                'leaderboard_refresh' => [
                    'processed' => $refreshCounts,
                ],
                'streak_recompute' => [
                    'processed' => $streakResult['processed'],
                    'awards' => $streakResult['awards'],
                ],
                'cache_write' => [
                    'files' => array_values($cacheFiles),
                ],
            ],
            'errors' => $errors,
        ];

        http_response_code(empty($errors) ? 200 : 500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($body, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
