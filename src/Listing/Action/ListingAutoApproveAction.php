<?php

/**
 * TicketTrade — Listing\Action\ListingAutoApproveAction
 *
 * Phase 3 Plan 03-02. The /admin/cron/ticket-expiry endpoint.
 * Hand-triggered cron sweep: approves pending listings older than
 * 24 hours (CONTEXT D-09: Computer Crimes Act sec 26 review window).
 *
 * Gating:
 *   - router opts.admin=true → non-admin gets 404 (D-10)
 *   - router opts.csrf=true  → POST must carry a CSRF token
 *   - router opts.rate_limit='admin_cron' → 5/min/IP (Plan 03-01)
 *   - Support\Auth::requireReAuth(300) → 403 JSON on stale
 *
 * Idempotency: the underlying UPDATE has no rows when run inside the
 * 24h window, so re-runs return processed=0 cleanly.
 *
 * Logging: each successful run appends a row to `cron_log` with the
 * processed count + actor user_id. Phase 9 migrates to audit_log.
 */

declare(strict_types=1);

namespace App\Listing\Action;

use App\Listing\Service\listing_service;
use App\Support\Auth as AuthGuard;

class ListingAutoApproveAction
{
    /**
     * POST /admin/cron/ticket-expiry
     */
    public function handle(): void
    {
        // Router has already enforced admin + csrf + admin_cron rate
        // limit by the time we get here. Re-check re-auth freshness.
        $user = AuthGuard::requireReAuth(300);
        $userId = (int) ($user['user_id'] ?? 0);

        $result = listing_service::runAutoApproveSweep($userId);

        // Emit JSON regardless of processed count; failures emit 500.
        if ($result['ok'] === true) {
            $data = $result['data'] ?? ['processed' => 0, 'errors' => []];
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'processed' => (int) ($data['processed'] ?? 0),
                'errors' => (array) ($data['errors'] ?? []),
            ]);
            exit;
        }

        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => $result['error'] ?? ['code' => 'E_INTERNAL', 'message' => 'Sweep failed.'],
        ]);
        exit;
    }
}
