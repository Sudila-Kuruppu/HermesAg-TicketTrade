<?php

/**
 * TicketTrade — Ticket\Action\PurchasesAction
 *
 * Phase 4 Plan 04-02. GET /purchases.
 *
 * Per AD-1: thin Action. Calls Ticket\Service\ticket_service::getPurchaseHistory()
 * and renders the chronological purchases table (desktop) /
 * stacked rows (mobile). The `Leave review` affordance is NOT
 * rendered (Phase 5).
 */

declare(strict_types=1);

namespace App\Ticket\Action;

use App\Support\Auth as AuthGuard;
use App\Support\Csrf;
use App\Support\View;
use App\Ticket\Service\ticket_service;

class PurchasesAction
{
    public function handle(): void
    {
        $user = AuthGuard::currentUser();
        if ($user === null) {
            header('Location: /login?next=/purchases');
            exit;
        }
        $userId = (int) $user['user_id'];

        $tickets = ticket_service::getPurchaseHistory($userId);

        View::render(
            __DIR__ . '/../View/purchases.php',
            [
                'tickets' => $tickets,
                'csrf_token' => Csrf::token(),
                'user' => AuthGuard::sanitizeUser($user),
            ]
        );
    }
}
