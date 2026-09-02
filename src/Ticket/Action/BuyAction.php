<?php

/**
 * TicketTrade — Ticket\Action\BuyAction
 *
 * Phase 4 Plan 04-01. POST /listings/{id}/buy.
 *
 * Per AD-1: thin Action (validate → call Service → render View).
 *   - CSRF: enforced by Support\Csrf::verify() at bootstrap.
 *   - Rate limit: 'purchase' (10/hr/user per NFR-SEC-007) — checked
 *     here in the Action so a flood is short-circuited before the
 *     Service transaction begins.
 *   - Delegates to Ticket\Service\ticket_service::createTicket().
 *
 * On success: flash toast + 302 /my-tickets. On failure: re-render
 * the listing modal View with the error inline (preserves the
 * buyer's context per EXPERIENCE.md).
 */

declare(strict_types=1);

namespace App\Ticket\Action;

use App\Listing\Service\listing_service;
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
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => ['code' => 'E_AUTH_REQUIRED', 'message' => 'Authentication required.'],
            ]);
            exit;
        }
        $userId = (int) $user['user_id'];
        $listingId = (int) ($GLOBALS['_tt_path_params']['id'] ?? 0);
        if ($listingId <= 0) {
            $this->renderListingModalError(null, ['code' => 'E_VALIDATION', 'message' => 'Invalid listing.']);
            return;
        }

        // CSRF is enforced at bootstrap; just read the token for the form.
        Csrf::token();

        // Rate limit per NFR-SEC-007 (10/hr/user).
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $rl = RateLimit::hit('purchase', $ip, (string) $userId);
        if (!$rl['allowed']) {
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => [
                    'code' => 'E_RATE_LIMIT',
                    'message' => 'Too many purchases. Try again later.',
                ],
            ]);
            exit;
        }

        $result = ticket_service::createTicket($listingId, $userId);
        if ($result['ok'] === true) {
            $code = (string) ($result['data']['ticket_code'] ?? '');
            View::flash('success', 'Ticket created. Code: ' . $code);
            header('Location: /my-tickets');
            exit;
        }

        $this->renderListingModalError($listingId, $result['error']);
    }

    /**
     * Internal: re-render the listing modal View with the error inline.
     */
    private function renderListingModalError(?int $listingId, array $error): void
    {
        // If we don't know the listing id, fall back to the board.
        if ($listingId === null || $listingId <= 0) {
            View::flash('error', (string) ($error['message'] ?? 'Could not complete purchase.'));
            header('Location: /board');
            exit;
        }

        $listing = listing_service::getWithImages($listingId);
        $GLOBALS['_tt_form_error'] = $error;
        View::render(
            __DIR__ . '/../../Listing/View/listing_modal.php',
            [
                'csrf_token' => Csrf::token(),
                'listing' => $listing,
                'listing_id' => $listingId,
                'page_message' => (string) ($error['message'] ?? 'Could not complete purchase.'),
                'page_title' => 'Listing',
            ]
        );
        exit;
    }
}
