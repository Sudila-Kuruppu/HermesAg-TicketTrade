<?php

/**
 * TicketTrade — Auth\Action\RegisterAction
 *
 * Phase 2 Plan 02-02. Handles GET (form) + POST (validation + auth_service::register).
 *
 * Per D-13:
 *  - field-level format errors are public
 *  - allowlist miss + duplicate email collapse to a single
 *    "Email or student ID not recognized. Check both and try again."
 *  - nickname taken is its own field-level error (public)
 *
 * Per D-02: on success, the user is auto-logged-in via
 * auth_service::startSession() and a flash toast contains the raw
 * /verify?token=... URL for dev simulation of email delivery.
 */

declare(strict_types=1);

namespace App\Auth\Action;

use App\Auth\Service\auth_service;
use App\Support\Auth as AuthGuard;
use App\Support\Csrf;
use App\Support\View;

class RegisterAction
{
    public function handle(): void
    {
        // Already logged in → bounce to /board.
        if (AuthGuard::currentUser() !== null) {
            header('Location: /board');
            exit;
        }
        $GLOBALS['_tt_form_error'] = null;
        View::render(
            __DIR__ . '/../View/register.php',
            [
                'csrf_token' => Csrf::token(),
                'values' => [],
            ]
        );
    }

    public function handlePost(): void
    {
        // Field-level validation FIRST (public errors). Combined
        // anti-enumeration errors come from auth_service::register.
        $email = trim((string) ($_POST['email'] ?? ''));
        $studentId = trim((string) ($_POST['student_id'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $nickname = trim((string) ($_POST['nickname'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $fieldErrors = [];

        if (
            $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || !str_ends_with(strtolower($email), '@students.nsbm.ac.lk')
        ) {
            $fieldErrors['email'] = 'Use your `@students.nsbm.ac.lk` email.';
        }
        if ($studentId === '') {
            $fieldErrors['student_id'] = 'Student ID is required.';
        }
        if ($fullName === '') {
            $fieldErrors['full_name'] = 'Full name is required.';
        }
        if ($nickname === '' || !preg_match('/^[A-Za-z0-9_]{3,30}$/', $nickname)) {
            $fieldErrors['nickname'] = 'Nickname must be 3–30 letters, numbers, or underscores.';
        }
        if (strlen($password) < 8) {
            $fieldErrors['password'] = 'Password must be at least 8 characters.';
        }

        if (!empty($fieldErrors)) {
            $GLOBALS['_tt_form_error'] = [
                'code' => 'E_VALIDATION',
                'message' => 'Please fix the highlighted fields.',
                'fields' => $fieldErrors,
            ];
            View::render(
                __DIR__ . '/../View/register.php',
                [
                    'csrf_token' => Csrf::token(),
                    'values' => [
                        'email' => $email,
                        'student_id' => $studentId,
                        'full_name' => $fullName,
                        'nickname' => $nickname,
                    ],
                ]
            );
            return;
        }

        $result = auth_service::register($email, $password, $nickname, $studentId, $fullName);
        if (!$result['ok']) {
            $GLOBALS['_tt_form_error'] = $result['error'];
            View::render(
                __DIR__ . '/../View/register.php',
                [
                    'csrf_token' => Csrf::token(),
                    'values' => [
                        'email' => $email,
                        'student_id' => $studentId,
                        'full_name' => $fullName,
                        'nickname' => $nickname,
                    ],
                ]
            );
            return;
        }

        // Auto-login per D-02.
        auth_service::startSession((int) $result['user_id']);

        // Flash toast with the actual verify URL (D-02 dev simulation).
        $token = (string) ($result['verify_token'] ?? '');
        $verifyUrl = '/verify?token=' . urlencode($token);
        View::flash('success', 'Account created. Verify your email: <a href="' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '">Click to verify</a>');

        header('Location: /board');
        exit;
    }
}
