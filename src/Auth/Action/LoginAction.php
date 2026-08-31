<?php
/**
 * TicketTrade — Auth\Action\LoginAction
 *
 * Stub Action for Phase 2 Plan 02-01. Plan 02-02 / 02-03 fill the body.
 */

declare(strict_types=1);

namespace App\Auth\Action;

class LoginAction
{
    public function handle(): void
    {
        \App\Support\View::render(
            __DIR__ . '/../../View/placeholder.php',
            ['note' => 'Login form + submit.']
        );
    }

    public function handlePost(): void
    {
        header('Content-Type: application/json');
        echo json_encode(\App\Support\Error::envelope(true, ['phase' => '2-prep', 'route' => $_SERVER['REQUEST_URI'] ?? '/']));
    }
}
