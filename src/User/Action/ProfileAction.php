<?php

/**
 * TicketTrade — User\Action\ProfileAction
 *
 * Phase 2 Plan 02-02.
 *
 * The /profile route (auth-required per D-08). The owner edits their
 * full_name, bio, whatsapp, avatar_id. Per D-15 the nickname field is
 * NOT in the form (locked at registration).
 *
 * The /profile/{nickname} public read view is owned by PublicProfileAction
 * (Plan 02-03).
 */

declare(strict_types=1);

namespace App\User\Action;

use App\Support\Auth as AuthGuard;
use App\Support\Csrf;
use App\Support\View;
use App\User\Service\user_service;

class ProfileAction
{
    public function handle(): void
    {
        $user = AuthGuard::currentUser();
        // Router already ran requireAuth(), but defend in depth.
        if ($user === null) {
            header('Location: /login?next=/profile');
            exit;
        }
        $profile = user_service::getById((int) $user['user_id']);
        if ($profile === null) {
            View::flash('error', 'Profile not found.');
            header('Location: /board');
            exit;
        }
        $GLOBALS['_tt_form_error'] = null;
        View::render(
            __DIR__ . '/../View/profile_edit.php',
            [
                'csrf_token' => Csrf::token(),
                'profile' => $profile,
                'values' => $profile,
            ]
        );
    }

    public function handlePost(): void
    {
        $user = AuthGuard::currentUser();
        if ($user === null) {
            header('Location: /login?next=/profile');
            exit;
        }
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $bio = (string) ($_POST['bio'] ?? '');
        $whatsapp = (string) ($_POST['whatsapp'] ?? '');
        $avatarId = (int) ($_POST['avatar_id'] ?? 1);

        $fieldErrors = [];
        if ($fullName === '') {
            $fieldErrors['full_name'] = 'Full name is required.';
        }
        if (strlen($bio) > 500) {
            $fieldErrors['bio'] = 'Bio must be 500 characters or fewer.';
        }
        if (!empty($fieldErrors)) {
            $GLOBALS['_tt_form_error'] = [
                'code' => 'E_VALIDATION',
                'message' => 'Please fix the highlighted fields.',
                'fields' => $fieldErrors,
            ];
            View::render(
                __DIR__ . '/../View/profile_edit.php',
                [
                    'csrf_token' => Csrf::token(),
                    'profile' => $user,
                    'values' => [
                        'full_name' => $fullName,
                        'bio' => $bio,
                        'whatsapp' => $whatsapp,
                        'avatar_id' => $avatarId,
                    ],
                ]
            );
            return;
        }

        $result = user_service::updateProfile(
            (int) $user['user_id'],
            [
                'full_name' => $fullName,
                'bio' => $bio,
                'whatsapp' => $whatsapp,
                'avatar_id' => $avatarId,
            ]
        );
        if (!$result['ok']) {
            $GLOBALS['_tt_form_error'] = $result['error'];
            View::render(
                __DIR__ . '/../View/profile_edit.php',
                [
                    'csrf_token' => Csrf::token(),
                    'profile' => $user,
                    'values' => [
                        'full_name' => $fullName,
                        'bio' => $bio,
                        'whatsapp' => $whatsapp,
                        'avatar_id' => $avatarId,
                    ],
                ]
            );
            return;
        }
        View::flash('success', 'Profile updated.');
        header('Location: /profile');
        exit;
    }
}
