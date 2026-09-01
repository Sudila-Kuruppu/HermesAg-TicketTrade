<?php

/**
 * TicketTrade — User\Action\SettingsAction
 *
 * Phase 2 Plan 02-02.
 *
 * /settings (auth-required). The page contains:
 *  - theme toggle (Light / Dark / System radios) — values are read
 *    client-side from localStorage.tickettrade.theme; the server
 *    does NOT persist the choice (Phase 1 D-07)
 *  - a destructive-styled Log out button (btn-outline-danger) that
 *    opens a Bootstrap confirm modal; on confirm the form posts to
 *    POST /logout
 *
 * POST /settings is a no-op (theme lives in localStorage). The
 * handlePost simply redirects back to /settings.
 */

declare(strict_types=1);

namespace App\User\Action;

use App\Support\Auth as AuthGuard;
use App\Support\Csrf;
use App\Support\View;

class SettingsAction
{
    public function handle(): void
    {
        $user = AuthGuard::currentUser();
        if ($user === null) {
            header('Location: /login?next=/settings');
            exit;
        }
        $GLOBALS['_tt_form_error'] = null;
        View::render(
            __DIR__ . '/../View/settings.php',
            [
                'csrf_token' => Csrf::token(),
                'profile' => $user,
            ]
        );
    }

    public function handlePost(): void
    {
        // No server-side state change for the theme. Redirect back.
        header('Location: /settings');
        exit;
    }
}
