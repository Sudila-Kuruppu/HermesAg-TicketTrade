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
 * IN-04: the deprecation warning is emitted ONCE per process via a
 * static guard, instead of on every invocation. The log fills with
 * noise if any leftover caller hits the deprecated route per request.
 */

declare(strict_types=1);

namespace App\Listing\Action;

use App\Admin\Action\CronAction;

class ListingAutoApproveAction
{
    /** IN-04: process-local "did we already warn?" guard. */
    private static bool $warned = false;

    /**
     * POST /admin/cron/ticket-expiry (deprecated entrypoint).
     *
     * Forwards to `App\Admin\Action\CronAction::handle()`.
     */
    public function handle(): void
    {
        if (!self::$warned) {
            self::$warned = true;
            error_log('[cron] deprecated route hit: App\\Listing\\Action\\ListingAutoApproveAction — use App\\Admin\\Action\\CronAction');
        }
        (new CronAction())->handle();
    }
}
