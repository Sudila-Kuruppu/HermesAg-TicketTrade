<?php

/**
 * TicketTrade — Ticket\Action\PurchasesAction
 *
 * Phase 2 Plan 02-02. Auth-required per D-08. Real purchases data lands
 * in Phase 4. This task ships the route guard + "coming soon" card.
 */

declare(strict_types=1);

namespace App\Ticket\Action;

use App\Support\Auth as AuthGuard;
use App\Support\View;

class PurchasesAction
{
    public function handle(): void
    {
        $user = AuthGuard::currentUser();
        if ($user === null) {
            header('Location: /login?next=/purchases');
            exit;
        }
        View::render(
            __DIR__ . '/../View/purchases.php',
            ['phase_label' => 'Phase 4']
        );
    }
}
