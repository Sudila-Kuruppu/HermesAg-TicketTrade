<?php
/**
 * TicketTrade — Auth\Action\VerifyAction
 *
 * Stub Action for Phase 2 Plan 02-01. Plan 02-02 / 02-03 fill the body.
 */

declare(strict_types=1);

namespace App\Auth\Action;

class VerifyAction
{
    public function handle(): void
    {
        \App\Support\View::render(
            __DIR__ . '/../../View/placeholder.php',
            ['note' => 'Email verification link.']
        );
    }
}
