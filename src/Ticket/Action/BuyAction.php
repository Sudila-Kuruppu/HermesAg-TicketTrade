<?php

/**
 * TicketTrade — Ticket\Action\BuyAction
 *
 * Phase 4 Plan 04-01 + Plan 04-02. POST /listings/{id}/buy.
 *
 * Per AD-1: thin Action (validate → call Service → render View).
 *   - CSRF: enforced by Support\Csrf::verify() at bootstrap.
 *   - Rate limit: 'purchase' (10/hr/user per NFR-SEC-007) — checked
 *     here in the Action so a flood is short-circuited before the
 *     Service transaction begins.
 *   - Delegates to Ticket\Service\ticket_service::createTicket().
 *
 * On success: flash toast + 302 /my-tickets?new={ticket_id} so the
 * View can auto-focus the freshly-bought card (D-02).
 * On failure: flash error + 302 back to /board#{listing_id} so the
 * user sees the listing modal again with the error inline.
 */

declare(strict_types=1);

namespace App\Ticket\Action;

use App\Support\Auth as AuthGuard;
use App\Support\Csrf;
use App\Support\RateLimit;
use App\Support\View;
use App\Ticket\Service\ticket_service;

class BuyAction
{
    /**
     * POST /listings/{id}/buy
     */
    public function handlePost(): void
    {
        $user = AuthGuard::currentUser();
        if ($user === null) {
            header('Location: /login?next=/board');
            exit;
        }
        $userId = (int) $user['user_id'];
        $listingId = (int) ($GLOBALS['_tt_path_params']['id'] ?? 0);
        if ($listingId <= 0) {
            View::flash('error', 'Invalid listing.');
            header('Location: /board');
            exit;
        }

        // CSRF is enforced at bootstrap; just read the token for the form.
        Csrf::token();

        // Rate limit per NFR-SEC-007 (10/hr/user).
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $rl = RateLimit::hit('purchase', $ip, (string) $userId);
        if (!$rl['allowed']) {
            View::flash('error', 'Too many purchases. Try again later.');
            header('Location: /board#listing-' . $listingId);
            exit;
        }

        $result = ticket_service::createTicket($listingId, $userId);
        if ($result['ok'] === true) {
            $code = (string) ($result['data']['ticket_code'] ?? '');
            $ticketId = (int) ($result['data']['ticket_id'] ?? 0);
            View::flash('success', 'Ticket created. Code: ' . $code);
            header('Location: /my-tickets?new=' . $ticketId);
            exit;
        }

        // Failure: re-render the board with the listing modal showing
        // the error. The simplest path is to flash the error message
        // and 302 back to the board with the listing hash, then the
        // JS re-opens the modal. The listing modal's hidden self-owned
        // / sold-out paths keep us from rendering the form for those.
        View::flash('error', (string) ($result['error']['message'] ?? 'Could not complete purchase.'));
        header('Location: /board#listing-' . $listingId);
        exit;
    }
}
