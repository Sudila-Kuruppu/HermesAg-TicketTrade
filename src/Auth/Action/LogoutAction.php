<?php

/**
 * TicketTrade — Auth\Action\LogoutAction
 *
 * Phase 2 Plan 02-02.
 *
 * Per D-05: logout = DELETE FROM sessions WHERE session_id = ? AND
 * user_id = ?. Also calls session_destroy() + clears the PHPSESSID
 * cookie (Pitfall 1: cookie clear is mandatory even when the DB row
 * is deleted, so a stale cookie can't be replayed).
 */

declare(strict_types=1);

namespace App\Auth\Action;

use App\Auth\Service\auth_service;
use App\Support\Auth as AuthGuard;

class LogoutAction
{
    public function handlePost(): void
    {
        $user = AuthGuard::currentUser();
        if ($user !== null && session_status() === PHP_SESSION_ACTIVE) {
            auth_service::endSession(session_id(), (int) $user['user_id']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
            unset($_COOKIE[session_name()]);
        }
        header('Location: /');
        exit;
    }
}
