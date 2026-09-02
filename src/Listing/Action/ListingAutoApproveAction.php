<?php

/**
 * TicketTrade — Listing\Action\ListingAutoApproveAction (DEPRECATED)
 *
 * Plan 04-03 renames this Action to `App\Admin\Action\CronAction`.
 * The cron endpoint's admin context is the canonical home per AD-2
 * bounded contexts. This file remains as a thin deprecation shim
 * that forwards `handle()` to the new Action. New code should use
 * `App\Admin\Action\CronAction` directly; the shim exists for
 * backward compatibility with Phase 3 callers and tests.
 *
 * On any invocation it emits a one-time `error_log` warning so
 * leftover callers are visible during the Phase 4 demo.
 */

declare(strict_types=1);

namespace App\Listing\Action;

use App\Admin\Action\CronAction;

class ListingAutoApproveAction
{
    /**
     * POST /admin/cron/ticket-expiry (deprecated entrypoint).
     *
     * Forwards to `App\Admin\Action\CronAction::handle()`.
     */
    public function handle(): void
    {
        error_log('[cron] deprecated route hit: App\\Listing\\Action\\ListingAutoApproveAction — use App\\Admin\\Action\\CronAction');
        (new CronAction())->handle();
    }
}
