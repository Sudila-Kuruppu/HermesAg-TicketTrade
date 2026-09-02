<?php

/**
 * TicketTrade — Ticket\Action\TicketDetailAction
 *
 * Phase 4 Plan 04-01. GET /tickets/{id}.
 *
 * Optional detail page (D-05). Renders the ticket-code-block +
 * status badge + listing title + seller info + dispute button (if
 * eligible). Non-buyer/non-seller non-admin viewers get a 404
 * (T-04-15 — don't leak ticket existence).
 */

declare(strict_types=1);

namespace App\Ticket\Action;

use App\Support\Auth as AuthGuard;
use App\Support\Db;
use App\Support\View;
use App\Ticket\Service\ticket_service;

class TicketDetailAction
{
    /**
     * GET /tickets/{id}
     */
    public function handle(): void
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
        $ticketId = (int) ($GLOBALS['_tt_path_params']['id'] ?? 0);
        if ($ticketId <= 0) {
            header('HTTP/1.1 404 Not Found');
            echo 'Not Found';
            exit;
        }

        $ticket = ticket_service::getTicketForViewer($ticketId, $user);
        if ($ticket === null) {
            // T-04-15: 404, not 403, to avoid leaking ticket existence.
            header('HTTP/1.1 404 Not Found');
            echo 'Not Found';
            exit;
        }

        // Hydrate listing title + seller row.
        $pdo = Db::pdo();
        $lst = $pdo->prepare('SELECT title FROM listings WHERE id = ? LIMIT 1');
        $lst->execute([(int) $ticket['listing_id']]);
        $listing = $lst->fetch();
        $ticket['listing_title'] = $listing['title'] ?? '';

        $sel = $pdo->prepare(
            'SELECT nickname, whatsapp FROM users WHERE user_id = ? LIMIT 1'
        );
        $sel->execute([(int) $ticket['seller_id']]);
        $seller = $sel->fetch();
        $ticket['seller_nickname'] = $seller['nickname'] ?? '';
        $ticket['seller_whatsapp'] = $seller['whatsapp'] ?? '';

        View::render(
            __DIR__ . '/../View/ticket_detail.php',
            ['ticket' => $ticket]
        );
    }
}
