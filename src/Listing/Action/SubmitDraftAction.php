<?php

/**
 * TicketTrade — Listing\Action\SubmitDraftAction
 *
 * Phase 3 Plan 03-02. The /listings/{id}/submit endpoint.
 * Flips a draft (or rejected → flipped to draft on edit page load)
 * listing to pending so admin review kicks off.
 *
 * Returns 302 to /my-listings?tab=pending with a flash toast, OR
 * 404 if the listing is unknown OR not owned by the seller (D-14:
 * 404, not 403, so existence is not leaked).
 */

declare(strict_types=1);

namespace App\Listing\Action;

use App\Listing\Service\listing_service;
use App\Support\Auth as AuthGuard;
use App\Support\Error;
use App\Support\View;

class SubmitDraftAction
{
    /**
     * POST /listings/{id}/submit
     */
    public function handle(): void
    {
        $user = AuthGuard::currentUser();
        if ($user === null) {
            header('Location: /l' . 'ogin?next=/my-listings');
            exit;
        }

        $params = $GLOBALS['_tt_path_params'] ?? [];
        $listingId = (int) ($params['id'] ?? 0);
        if ($listingId <= 0) {
            Error::not_found();
        }

        $result = listing_service::submitDraft($listingId, (int) $user['user_id']);
        if ($result['ok'] === false) {
            // Per D-14: same response as unknown route (404), never 403.
            Error::not_found();
        }

        View::flash('success', 'Submitted for review');
        header('Location: /my-listings?tab=pending');
        exit;
    }
}
