<?php

/**
 * TicketTrade — Listing\Action\DeleteListingAction
 *
 * Phase 3 Plan 03-02. The /listings/{id}/delete endpoint.
 * Soft-delete for active/rejected/sold (kept in DB for audit),
 * hard-delete for draft/pending (no audit needed).
 *
 * Per D-14: non-owner access returns 404 (not 403), so listing
 * existence is not leaked.
 */

declare(strict_types=1);

namespace App\Listing\Action;

use App\Listing\Service\listing_service;
use App\Support\Auth as AuthGuard;
use App\Support\Error;
use App\Support\View;

class DeleteListingAction
{
    /**
     * POST /listings/{id}/delete
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

        // Look up current status to pick soft-delete vs hard-delete.
        $load = listing_service::loadForOwner($listingId, (int) $user['user_id']);
        if ($load['ok'] === false) {
            Error::not_found();
        }
        $status = (string) ($load['data']['status'] ?? '');
        $tab = in_array($status, ['active', 'pending', 'sold', 'draft'], true) ? $status : 'draft';

        $result = in_array($status, ['active', 'rejected', 'sold'], true)
            ? listing_service::softDelete($listingId, (int) $user['user_id'])
            : listing_service::hardDelete($listingId, (int) $user['user_id']);

        if ($result['ok'] === false) {
            Error::not_found();
        }

        View::flash('success', 'Listing removed');
        header('Location: /my-listings?tab=' . $tab);
        exit;
    }
}
