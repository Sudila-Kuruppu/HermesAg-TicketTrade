<?php

/**
 * TicketTrade — Auth\Action\VerifyAction
 *
 * Phase 2 Plan 02-02. GET /verify?token=… consumes the email-verification
 * token and renders the +50 success modal. Bad/expired/used tokens return
 * 400 + the E_TOKEN_INVALID card.
 */

declare(strict_types=1);

namespace App\Auth\Action;

use App\Auth\Model\user_model;
use App\Auth\Service\auth_service;
use App\Support\Db;
use App\Support\View;

class VerifyAction
{
    public function handle(): void
    {
        $rawToken = (string) ($_GET['token'] ?? '');
        if ($rawToken === '') {
            http_response_code(400);
            View::render(
                __DIR__ . '/../View/register.php',
                [
                    'csrf_token' => \App\Support\Csrf::token(),
                    'values' => [],
                    'verify_error' => 'Verification link is missing the token.',
                ]
            );
            return;
        }

        $result = auth_service::verifyEmail($rawToken);
        if (!$result['ok']) {
            http_response_code(400);
            $GLOBALS['_tt_form_error'] = $result['error'];
            View::render(
                __DIR__ . '/../View/verify_success.php',
                [
                    'error' => $result['error']['message'],
                    'nickname' => null,
                    'tier' => null,
                ]
            );
            return;
        }

        // Pull the freshly-updated user row to render the tier badge.
        $user = user_model::findById(Db::pdo(), (int) $result['user_id']);
        $nickname = $user['nickname'] ?? ($result['nickname'] ?? '');
        $tier = $user['tier'] ?? 'D';
        // Auto-login so the user lands logged-in after verify.
        auth_service::startSession((int) $result['user_id']);
        View::flash('success', 'Email verified! +50 points — welcome to the D tier.');
        View::render(
            __DIR__ . '/../View/verify_success.php',
            [
                'error' => null,
                'nickname' => (string) $nickname,
                'tier' => (string) $tier,
            ]
        );
    }
}
