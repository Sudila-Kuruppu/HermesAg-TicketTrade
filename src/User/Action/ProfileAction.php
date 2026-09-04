<?php

/**
 * TicketTrade — User\Action\ProfileAction
 *
 * Phase 2 Plan 02-02 ships handle() (owner profile edit) + handlePost().
 *
 * Phase 6 Plan 06-03 RESHAPES the routing:
 *   - handle() is RENAMED to handleEdit() and renders profile_edit.php
 *     (the owner edit form) — wired to /profile/edit via the route map.
 *   - A NEW handle() renders profile.php — the owner Profile page with
 *     rank badge + tier progress + on_break_pill + velocity_flag_pill +
 *     Recent activity (D-07). Wired to /profile (the canonical owner
 *     view). The "Edit profile" affordance on the public profile still
 *     links to /profile/edit.
 *
 * The GET /profile/{nickname} public view is owned by PublicProfileAction
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
    /**
     * GET /profile (Plan 06-03: owner Profile view).
     *
     * Renders profile.php with the Phase 6 gamification surface:
     *   - profile (sanitized user row)
     *   - is_owner = true
     *   - points_frozen (bool) — drives velocity_flag_pill
     *   - last_active_at (string) — drives on_break_pill
     *   - recent_activity (5 points_log rows) — drives Recent activity section
     */
    public function handle(): void
    {
        $user = AuthGuard::currentUser();
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
        // Pull the full row (with points_frozen, last_active_at, points,
        // tier) — sanitizeUser strips these for the View, but the partials
        // need them.
        $pdo = \App\Support\Db::pdo();
        $stmt = $pdo->prepare(
            'SELECT points, points_frozen, tier, last_active_at, current_streak '
            . 'FROM users WHERE user_id = ?'
        );
        $stmt->execute([(int) $user['user_id']]);
        $gamification = (array) $stmt->fetch();

        $recentActivity = user_service::getRecentActivityForProfile(
            (int) $user['user_id'],
            5
        );

        $GLOBALS['_tt_form_error'] = null;
        View::render(
            __DIR__ . '/../View/profile.php',
            [
                'csrf_token' => Csrf::token(),
                'profile' => $profile,
                'points' => (int) ($gamification['points'] ?? 0),
                'tier' => (string) ($gamification['tier'] ?? 'E'),
                'points_frozen' => (bool) ($gamification['points_frozen'] ?? false),
                'last_active_at' => $gamification['last_active_at'] ?? null,
                'current_streak' => (int) ($gamification['current_streak'] ?? 0),
                'recent_activity' => $recentActivity,
                'is_owner' => true,
            ]
        );
    }

    /**
     * GET /profile/edit (Plan 06-03: previously was handle()).
     *
     * Renders profile_edit.php — the owner edit form. Renamed from
     * handle() so the route map can have a clean separation between
     * /profile (view) and /profile/edit (form).
     */
    public function handleEdit(): void
    {
        $user = AuthGuard::currentUser();
        if ($user === null) {
            header('Location: /login?next=/profile/edit');
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

    /**
     * POST /profile/edit (Plan 06-03: previously was handlePost()).
     */
    public function handleEditPost(): void
    {
        $user = AuthGuard::currentUser();
        if ($user === null) {
            header('Location: /login?next=/profile/edit');
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
