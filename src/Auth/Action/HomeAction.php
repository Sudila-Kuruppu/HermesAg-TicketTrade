<?php

/**
 * TicketTrade — Auth\Action\HomeAction
 *
 * Phase 3 Plan 03-04. The / route. Renders the public landing page:
 * hero, vision/mission, how-it-works, team (6 cards from
 * config/team.php), and the footer (NSBM branding + simulation
 * disclaimer + GitHub/Drive links).
 *
 * The Action is intentionally thin: no DB call. The team config is
 * a static require; the landing does NOT show a listings count.
 *
 * Logged-in users see `My listings` instead of `Get Started`.
 */

declare(strict_types=1);

namespace App\Auth\Action;

use App\Support\Auth as AuthGuard;
use App\Support\View;

class HomeAction
{
    public function handle(): void
    {
        // Public landing surface — light theme by default per UX-06.
        // The layout's theme default treats 'public' like 'admin'
        // (light); CSS body class surface-public is a no-op class
        // (no rules) but harmless if present.
        $GLOBALS['_tt_surface'] = 'public';

        $currentUser = AuthGuard::currentUser();
        $team = require __DIR__ . '/../../../config/team.php';

        View::render(
            __DIR__ . '/../View/home.php',
            [
                'current_user' => $currentUser,
                'team' => $team,
                'is_logged_in' => $currentUser !== null,
            ]
        );
    }
}
