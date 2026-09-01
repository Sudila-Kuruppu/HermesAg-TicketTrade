<?php

/**
 * TicketTrade — User\Action\PublicProfileAction
 *
 * Per D-11: GET /profile/{nickname} is a public route (no auth flag in
 * config/routes.php). Per D-14 / D-15 / D-16: the response is the
 * summary header for any registered user; banned, non-existent,
 * case-mismatched, and invalid-character URLs all return the same
 * generic 404 page (D-10, AD-14 — don't reveal the resource exists).
 *
 * The Router captures the path-param `nickname` into
 * $GLOBALS['_tt_path_params']['nickname'] per the Phase 2 Plan 02-01
 * Router implementation. The Action re-validates it against the
 * Plan 02-02 register-time regex (^[A-Za-z0-9_]{3,30}$) as a
 * defense-in-depth check; the Router already enforces the same regex
 * via its path-param matching, so this branch is unreachable in
 * practice but is the canonical hardening if the regex is ever loosened.
 */

declare(strict_types=1);

namespace App\User\Action;

use App\Support\Error;
use App\Support\View;
use App\User\Service\user_service;

class PublicProfileAction
{
    /**
     * GET /profile/{nickname}
     */
    public function handle(): void
    {
        $nickname = (string) ($GLOBALS['_tt_path_params']['nickname'] ?? '');
        error_log("[ACTION] nickname='$nickname' path_params=" . var_export($GLOBALS['_tt_path_params'] ?? null, true));
        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $nickname)) {
            Error::not_found();
            return;
        }

        $profile = user_service::getByNicknameForPublicProfile($nickname);
        if ($profile === null) {
            Error::not_found();
            return;
        }

        // is_owner toggles the Edit profile button. Guests see the same
        // header minus the button; authed users see the button when
        // they are looking at their own profile. Phase 2 placeholder
        // — the button is gated to the owner only, never to other
        // authed users.
        $currentUser = $GLOBALS['current_user'] ?? null;
        $isOwner = $currentUser !== null
            && isset($profile['user_id'], $currentUser['user_id'])
            && (int) $profile['user_id'] === (int) $currentUser['user_id'];

        View::render(
            __DIR__ . '/../View/public_profile.php',
            [
                'profile' => $profile,
                'is_owner' => $isOwner,
            ]
        );
    }
}
