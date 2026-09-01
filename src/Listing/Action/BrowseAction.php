<?php

/**
 * TicketTrade — Listing\Action\BrowseAction
 *
 * Phase 2 Plan 02-02. The /board route. Public-browse per D-09:
 * - Guests see "Welcome, guest" + "Sign in to buy" placeholder cards.
 * - Logged-in users see their nickname + a flash-toast container.
 *
 * Real listings data lands in Phase 3.
 */

declare(strict_types=1);

namespace App\Listing\Action;

use App\Auth\Service\auth_service;
use App\Support\Auth as AuthGuard;
use App\Support\View;

class BrowseAction
{
    public function handle(): void
    {
        $user = AuthGuard::currentUser();
        $nickname = null;
        if ($user !== null) {
            $sanitized = auth_service::sanitizeUser($user);
            $nickname = (string) ($sanitized['nickname'] ?? '');
        }
        View::render(
            __DIR__ . '/../View/board.php',
            [
                'nickname' => $nickname,
            ]
        );
    }
}
