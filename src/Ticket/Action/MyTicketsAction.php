<?php

/**
 * TicketTrade — Ticket\Action\MyTicketsAction
 *
 * Phase 2 Plan 02-02. Auth-required per D-08. Real tickets data lands
 * in Phase 4. This task ships the route guard + "coming soon" card.
 */

declare(strict_types=1);

namespace App\Ticket\Action;

use App\Support\Auth as AuthGuard;
use App\Support\View;

class MyTicketsAction
{
    public function handle(): void
    {
        $user = AuthGuard::currentUser();
        if ($user === null) {
            header('Location: /login?next=/my-tickets');
            exit;
        }
        View::render(
            __DIR__ . '/../View/my_tickets.php',
            ['phase_label' => 'Phase 4']
        );
    }
}
