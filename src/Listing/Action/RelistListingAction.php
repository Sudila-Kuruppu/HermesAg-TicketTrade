<?php

/**
 * TicketTrade — Listing\Action\RelistListingAction
 *
 * Phase 3 Plan 03-02. The /listings/{id}/relist endpoint. One-click
 * relist (D-14): copies the sold listing into a fresh draft, resets
 * quantity_sold=0, sets source_listing_id for the approved-content
 * fast-track. Redirects to /listings/{new_id}/edit.
 *
 * Non-owner → 404. Non-sold source → re-render the referer page with
 * an inline error.
 */

declare(strict_types=1);

namespace App\Listing\Action;

use App\Listing\Service\listing_service;
use App\Support\Auth as AuthGuard;
use App\Support\Error;
use App\Support\View;

class RelistListingAction
{
    /**
     * POST /listings/{id}/relist
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

        $result = listing_service::relist($listingId, (int) $user['user_id']);
        if ($result['ok'] === false) {
            // Per D-14: same response as unknown route.
            Error::not_found();
        }

        $newId = (int) ($result['data']['id'] ?? 0);
        if ($newId <= 0) {
            Error::server_error('Relist succeeded but no new id.');
        }

        View::flash('success', 'Relisted as draft - edit and submit when ready');
        header('Location: /listings/' . $newId . '/edit');
        exit;
    }
}
