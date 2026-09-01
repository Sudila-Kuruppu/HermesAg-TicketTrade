<?php

/**
 * TicketTrade — Auth\Action\ForgotPasswordAction
 *
 * Phase 2 Plan 02-02.
 *
 * Per D-07 (anti-enumeration): the response is always the same toast
 * "If that email is registered, a reset link is in your inbox."
 * regardless of whether the email exists. The raw token is NEVER
 * surfaced in the UI; in dev mode the Action writes
 * error_log('[dev-reset-link] /reset-password?token=... for <email>')
 * so a developer can copy the token from the dev log (OQ-7).
 */

declare(strict_types=1);

namespace App\Auth\Action;

use App\Auth\Service\auth_service;
use App\Support\Auth as AuthGuard;
use App\Support\Csrf;
use App\Support\View;

class ForgotPasswordAction
{
    public function handle(): void
    {
        if (AuthGuard::currentUser() !== null) {
            header('Location: /board');
            exit;
        }
        $GLOBALS['_tt_form_error'] = null;
        View::render(
            __DIR__ . '/../View/forgot_password.php',
            [
                'csrf_token' => Csrf::token(),
                'values' => [],
            ]
        );
    }

    public function handlePost(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $GLOBALS['_tt_form_error'] = [
                'code' => 'E_VALIDATION',
                'message' => 'Enter a valid email.',
                'fields' => ['email' => 'Enter a valid email.'],
            ];
            View::render(
                __DIR__ . '/../View/forgot_password.php',
                [
                    'csrf_token' => Csrf::token(),
                    'values' => ['email' => $email],
                ]
            );
            return;
        }

        $result = auth_service::requestPasswordReset($email);
        // Dev-only log line per OQ-7 — never surfaced in the UI.
        $devToken = (string) ($result['token'] ?? '');
        if ($devToken !== '' && getenv('APP_ENV') !== 'production') {
            error_log('[dev-reset-link] /reset-password?token=' . $devToken . ' for ' . $email);
        }
        // Always the same toast (D-07 anti-enumeration).
        View::flash(
            'info',
            'If that email is registered, a reset link is in your inbox.'
        );
        header('Location: /login');
        exit;
    }
}
