<?php

/**
 * TicketTrade — Auth\Action\ResetPasswordAction
 *
 * Phase 2 Plan 02-02.
 *
 * GET /reset-password?token=...: read the token, peek the row (no
 * consumption), render the password form OR the "invalid/expired"
 * card.
 *
 * POST /reset-password: validate passwords, call
 * auth_service::consumePasswordReset which marks the row used,
 * updates users.password_hash, starts a session, and redirects to /board.
 *
 * Re-using a token returns E_TOKEN_INVALID (row is already used).
 */

declare(strict_types=1);

namespace App\Auth\Action;

use App\Auth\Service\auth_service;
use App\Support\Csrf;
use App\Support\View;

class ResetPasswordAction
{
    public function handle(): void
    {
        $rawToken = (string) ($_GET['token'] ?? '');
        if ($rawToken === '') {
            View::flash('error', 'Verification link is missing the token.');
            header('Location: /login');
            exit;
        }
        $row = auth_service::peekPasswordReset($rawToken);
        if ($row === null) {
            http_response_code(400);
            $GLOBALS['_tt_form_error'] = [
                'code' => 'E_TOKEN_INVALID',
                'message' => 'Verification link is invalid or expired.',
            ];
            View::render(
                __DIR__ . '/../View/reset_password.php',
                [
                    'csrf_token' => Csrf::token(),
                    'token' => '',
                    'values' => [],
                    'invalid' => true,
                ]
            );
            return;
        }
        View::render(
            __DIR__ . '/../View/reset_password.php',
            [
                'csrf_token' => Csrf::token(),
                'token' => $rawToken,
                'values' => [],
                'invalid' => false,
            ]
        );
    }

    public function handlePost(): void
    {
        $rawToken = (string) ($_POST['token'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if ($rawToken === '') {
            View::flash('error', 'Verification link is missing the token.');
            header('Location: /login');
            exit;
        }

        $fieldErrors = [];
        if (strlen($password) < 8) {
            $fieldErrors['password'] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $fieldErrors['password_confirm'] = 'Passwords do not match.';
        }
        if (!empty($fieldErrors)) {
            $GLOBALS['_tt_form_error'] = [
                'code' => 'E_VALIDATION',
                'message' => 'Please fix the highlighted fields.',
                'fields' => $fieldErrors,
            ];
            View::render(
                __DIR__ . '/../View/reset_password.php',
                [
                    'csrf_token' => Csrf::token(),
                    'token' => $rawToken,
                    'values' => [],
                    'invalid' => false,
                ]
            );
            return;
        }

        $result = auth_service::consumePasswordReset($rawToken, $password);
        if (!$result['ok']) {
            http_response_code(400);
            $GLOBALS['_tt_form_error'] = $result['error'];
            View::render(
                __DIR__ . '/../View/reset_password.php',
                [
                    'csrf_token' => Csrf::token(),
                    'token' => '',
                    'values' => [],
                    'invalid' => true,
                ]
            );
            return;
        }

        View::flash('success', 'Password reset. You\'re now signed in.');
        header('Location: /board');
        exit;
    }
}
