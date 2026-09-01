<?php

/**
 * TicketTrade — Auth\Action\HomeAction
 *
 * Phase 2 Plan 02-02. The / route. Marketing landing page with a
 * "Get Started" CTA → /register and a "Sign In" CTA → /login.
 * Phase 3 replaces this with the real landing.
 */

declare(strict_types=1);

namespace App\Auth\Action;

use App\Support\Auth as AuthGuard;
use App\Support\View;

class HomeAction
{
    public function handle(): void
    {
        // Already logged in → bounce to /board.
        if (AuthGuard::currentUser() !== null) {
            header('Location: /board');
            exit;
        }
        View::render(
            __DIR__ . '/../View/home.php',
            []
        );
    }
}
