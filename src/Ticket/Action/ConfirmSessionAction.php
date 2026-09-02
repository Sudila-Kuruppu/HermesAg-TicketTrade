<?php

/**
 * TicketTrade — Ticket\Action\ConfirmSessionAction
 *
 * Phase 4 Plan 04-01. POST /tickets/{id}/confirm-session.
 *
 * Per AD-1: thin Action. CSRF is enforced at bootstrap.
 * The session_number is implicit (= current + 1); no client-supplied value.
 * Delegates to Ticket\Service\ticket_service::confirmSession().
 */

declare(strict_types=1);

namespace App\Ticket\Action;

use App\Support\Auth as AuthGuard;
use App\Support\Csrf;
use App\Support\View;
use App\Ticket\Service\ticket_service;

class ConfirmSessionAction
{
    /**
     * POST /tickets/{id}/confirm-session
     */
    public function handlePost(): void
    {
        $user = AuthGuard::currentUser();
        if ($user === null) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => ['code' => 'E_AUTH_REQUIRED', 'message' => 'Authentication required.'],
            ]);
            exit;
        }
        $userId = (int) $user['user_id'];
        $ticketId = (int) ($GLOBALS['_tt_path_params']['id'] ?? 0);
        if ($ticketId <= 0) {
            $this->redirectWithError('/sales', 'Invalid ticket.');
            return;
        }

        Csrf::token();

        $result = ticket_service::confirmSession($ticketId, $userId);
        if ($result['ok'] === true) {
            $isFinal = (bool) ($result['data']['is_final'] ?? false);
            if ($isFinal) {
                View::flash('success', 'Ticket redeemed. Handover complete.');
            } else {
                $sn = (int) ($result['data']['session_number'] ?? 0);
                $total = (int) ($result['data']['total_sessions'] ?? 0);
                View::flash('success', sprintf('Session %d of %d confirmed.', $sn, $total));
            }
            header('Location: /sales');
            exit;
        }

        $this->redirectWithError('/sales', (string) ($result['error']['message'] ?? 'Could not confirm session.'));
    }

    private function redirectWithError(string $path, string $message): void
    {
        View::flash('error', $message);
        header('Location: ' . $path);
        exit;
    }
}
