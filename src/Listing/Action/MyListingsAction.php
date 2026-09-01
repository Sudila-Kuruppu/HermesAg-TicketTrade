<?php

/**
 * TicketTrade — Listing\Action\MyListingsAction
 *
 * Phase 2 Plan 02-02. Auth-required per D-08. The actual listings
 * data lands in Phase 3. This task ships the route guard + a
 * "coming soon" card so the bottom-nav /my-listings link doesn't 404.
 */

declare(strict_types=1);

namespace App\Listing\Action;

use App\Support\Auth as AuthGuard;
use App\Support\View;

class MyListingsAction
{
    public function handle(): void
    {
        $user = AuthGuard::currentUser();
        if ($user === null) {
            header('Location: /login?next=/my-listings');
            exit;
        }
        View::render(
            __DIR__ . '/../View/my_listings.php',
            [
                'phase_label' => 'Phase 3',
            ]
        );
    }
}
