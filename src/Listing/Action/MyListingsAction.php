<?php
/**
 * TicketTrade — Listing\Action\MyListingsAction
 *
 * Stub Action for Phase 2 Plan 02-01. Plan 02-02 / 02-03 fill the body.
 */

declare(strict_types=1);

namespace App\Listing\Action;

class MyListingsAction
{
    public function handle(): void
    {
        \App\Support\View::render(
            __DIR__ . '/../../View/placeholder.php',
            ['note' => 'My listings dashboard.']
        );
    }
}
