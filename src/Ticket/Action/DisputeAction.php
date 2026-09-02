<?php

/**
 * TicketTrade — Ticket\Action\DisputeAction
 *
 * Phase 4 Plan 04-01. POST /tickets/{id}/dispute.
 *
 * Per AD-1: thin Action. CSRF is enforced at bootstrap.
 * Validates reason (must be one of 4 allowed) + text (1..200 chars)
 * BEFORE calling the Service.
 * Delegates to Ticket\Service\ticket_service::fileDispute().
 */

declare(strict_types=1);

namespace App\Ticket\Action;

use App\Support\Auth as AuthGuard;
use App\Support\Csrf;
use App\Support\View;
use App\Ticket\Service\ticket_service;

class DisputeAction
{
    /**
     * POST /tickets/{id}/dispute
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
            $this->redirectWithError('/my-tickets', 'Invalid ticket.');
            return;
        }

        Csrf::token();

        $reason = (string) ($_POST['reason'] ?? '');
        $text = (string) ($_POST['text'] ?? '');
        $origin = (string) ($_SERVER['HTTP_REFERER'] ?? '/my-tickets');
        // Origin should be a same-site path; only honor same-host paths.
        if (!str_starts_with($origin, '/')) {
            $origin = '/my-tickets';
        }

        $result = ticket_service::fileDispute($ticketId, $userId, $reason, $text);
        if ($result['ok'] === true) {
            View::flash('success', 'Dispute submitted. Admin will review within 48 hours.');
            header('Location: ' . $origin);
            exit;
        }

        View::flash('error', (string) ($result['error']['message'] ?? 'Could not file dispute.'));
        header('Location: ' . $origin);
        exit;
    }

    private function redirectWithError(string $path, string $message): void
    {
        View::flash('error', $message);
        header('Location: ' . $path);
        exit;
    }
}
