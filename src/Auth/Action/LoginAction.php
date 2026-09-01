<?php

/**
 * TicketTrade — Auth\Action\LoginAction
 *
 * Phase 2 Plan 02-02.
 *  - GET /login: render the centered login form
 *  - POST /login: rate-limit check, call auth_service::login, redirect
 *    to $next (validated via auth_service::nextRedirectIsSafe) or /board
 *
 * Per D-12:
 *  - wrong creds return the form with the inline "Email or password is
 *    incorrect." alert-danger (NOT a flash toast)
 *  - rate-limited returns the form with "Too many attempts. Try again in 5 minutes."
 *
 * Per Pitfall 3: auth_service::login ALWAYS calls password_verify against
 * the user's hash OR the dummyHash sentinel, so missing-user and wrong-
 * password cases take the same wall-clock time.
 */

declare(strict_types=1);

namespace App\Auth\Action;

use App\Auth\Service\auth_service;
use App\Support\Auth as AuthGuard;
use App\Support\Csrf;
use App\Support\RateLimit;
use App\Support\View;

class LoginAction
{
    public function handle(): void
    {
        if (AuthGuard::currentUser() !== null) {
            $next = auth_service::nextRedirectIsSafe($_GET['next'] ?? null);
            header('Location: ' . $next);
            exit;
        }
        $GLOBALS['_tt_form_error'] = null;
        View::render(
            __DIR__ . '/../View/login.php',
            [
                'csrf_token' => Csrf::token(),
                'next' => (string) ($_GET['next'] ?? ''),
                'values' => ['email' => (string) ($_POST['email'] ?? '')],
            ]
        );
    }

    public function handlePost(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $next = (string) ($_POST['next'] ?? ($_GET['next'] ?? ''));

        // Rate-limit check FIRST (Pitfall ordering: rate-limit fires
        // before password verify so the constant-time guarantee is
        // preserved; D-12 NFR-SEC-007).
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $rl = RateLimit::hit('login', $ip);
        if (!$rl['allowed']) {
            $GLOBALS['_tt_form_error'] = [
                'code' => 'E_RATE_LIMIT',
                'message' => 'Too many attempts. Try again in 5 minutes.',
                'fields' => null,
            ];
            $this->renderForm($email, $next);
            return;
        }

        if ($email === '' || $password === '') {
            $GLOBALS['_tt_form_error'] = [
                'code' => 'E_VALIDATION',
                'message' => 'Email and password are required.',
                'fields' => null,
            ];
            $this->renderForm($email, $next);
            return;
        }

        $result = auth_service::login($email, $password);
        if (!$result['ok']) {
            error_log('[login] ' . ($result['error']['code'] ?? 'unknown') . ' ip=' . $ip);
            $GLOBALS['_tt_form_error'] = [
                'code' => $result['error']['code'] ?? 'E_AUTH_INVALID',
                'message' => 'Email or password is incorrect.',
                'fields' => null,
            ];
            $this->renderForm($email, $next);
            return;
        }

        // Success: redirect to $next (validated) or /board.
        $redirect = auth_service::nextRedirectIsSafe($next);
        header('Location: ' . $redirect);
        exit;
    }

    private function renderForm(string $email, string $next): void
    {
        View::render(
            __DIR__ . '/../View/login.php',
            [
                'csrf_token' => Csrf::token(),
                'next' => $next,
                'values' => ['email' => $email],
            ]
        );
    }
}
