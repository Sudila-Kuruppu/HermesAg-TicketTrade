<?php
/**
 * TicketTrade — Auth\Action\HomeAction
 *
 * Stub Action for Phase 2 Plan 02-01. Plan 02-02 / 02-03 fill the body.
 */

declare(strict_types=1);

namespace App\Auth\Action;

class HomeAction
{
    public function handle(): void
    {
        \App\Support\View::render(
            __DIR__ . '/../../View/placeholder.php',
            ['note' => 'Phase 2 Plan 02-02 fills the landing page.']
        );
    }
}
