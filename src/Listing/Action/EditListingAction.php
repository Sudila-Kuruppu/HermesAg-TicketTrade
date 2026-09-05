<?php

/**
 * TicketTrade — Listing\Action\EditListingAction
 *
 * Phase 3 Plan 03-02. The /listings/{id}/edit route pair:
 * - GET  → loads the listing for the owner; flips rejected → draft on
 *          page load (D-04); sets review_flag warning if the listing
 *          was active (D-09).
 * - POST → calls ListingService::saveDraft. If the listing was active,
 *          the Service appends a listing_revisions snapshot AND sets
 *          review_flag=1 (D-09). On validation failure, re-renders
 *          with field errors + preserved values.
 *
 * Non-owner access returns 404 (per D-14: same as unknown route, never
 * 403, so listing existence is not leaked).
 */

declare(strict_types=1);

namespace App\Listing\Action;

use App\Category\Service\category_service;
use App\Listing\Model\listing_image_model;
use App\Listing\Service\listing_service;
use App\Support\Auth as AuthGuard;
use App\Support\Csrf;
use App\Support\Error;
use App\Support\View;

class EditListingAction
{
    /**
     * GET /listings/{id}/edit
     */
    public function handle(): void
    {
        AuthGuard::requireAuth('/listings/' . $this->currentId() . '/edit');

        $user = AuthGuard::currentUser();
        // WR-06: requireAuth() exits for guests, so reaching here
        // with $user === null indicates a refactor regression. Throw
        // rather than emit a misleading 404 (which would mask the bug).
        if ($user === null) {
            throw new \LogicException('EditListingAction::handle: requireAuth should have exited for guests');
        }

        $listingId = $this->currentId();
        $result = listing_service::loadForOwner($listingId, (int) $user['user_id']);
        if ($result['ok'] === false) {
            Error::not_found();
        }
        $listing = $result['data'];

        // D-04: rejected listings flip to draft on edit page load. This is
        // intentional (documented in D-04 + Phase 3 SUMMARY must-haves)
        // and gated by the `=== 'rejected'` check — once flipped, the
        // next GET sees `status='draft'` and skips the write, so a
        // browser refresh or crawler pre-fetch does NOT keep bumping
        // `updated_at`. WR-03 noted the GET-mutates-DB pattern; the
        // one-shot gate is the minimum fix. A future phase may move
        // the flip to a POST button per the reviewer's recommendation.
        if (($listing['status'] ?? '') === 'rejected') {
            listing_service::saveDraft($listingId, (int) $user['user_id'], $listing);
            $listing['status'] = 'draft';
        }

        $images = listing_image_model::findByListingId($listingId);

        View::render(
            __DIR__ . '/../View/edit.php',
            [
                'csrf_token' => Csrf::token(),
                'listing' => $listing,
                'images' => $images,
                'errors' => [],
                'values' => $listing,
            ]
        );
    }

    /**
     * POST /listings/{id}/edit
     */
    public function handlePost(): void
    {
        AuthGuard::requireAuth('/listings/' . $this->currentId() . '/edit');

        $user = AuthGuard::currentUser();
        // WR-06: same invariant — requireAuth exits for guests.
        if ($user === null) {
            throw new \LogicException('EditListingAction::handlePost: requireAuth should have exited for guests');
        }

        $listingId = $this->currentId();
        $result = listing_service::saveDraft($listingId, (int) $user['user_id'], $_POST);
        if ($result['ok'] === false) {
            $this->renderForm($listingId, $result['error'] ?? ['code' => 'E_VALIDATION', 'message' => '', 'fields' => []]);
            return;
        }

        $reviewFlagged = !empty($result['data']['review_flagged']);

        // Optional image upload on edit.
        if (!empty($_FILES['images']) && (int) ($_FILES['images']['error'][0] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            listing_service::uploadImages($listingId, (int) $user['user_id'], $_FILES['images']);
        }

        if ($reviewFlagged) {
            View::flash(
                'warning',
                'Listing saved. Edits to active listings are reviewed by an admin before they go live'
            );
        } else {
            View::flash('success', 'Listing saved.');
        }
        header('Location: /my-listings?tab=' . $this->tabForStatus($result['data']['listing']['status'] ?? 'draft'));
        exit;
    }

    /**
     * Re-render the edit form on validation failure.
     */
    private function renderForm(int $listingId, array $errorEnvelope): void
    {
        $user = AuthGuard::currentUser();
        $loadResult = listing_service::loadForOwner($listingId, (int) $user['user_id']);
        $listing = ($loadResult['ok'] === true) ? $loadResult['data'] : [];
        $images = listing_image_model::findByListingId($listingId);

        $categories = category_service::listActive();
        View::render(
            __DIR__ . '/../View/edit.php',
            [
                'csrf_token' => Csrf::token(),
                'listing' => $listing,
                'images' => $images,
                'categories' => ($categories['ok'] === true) ? $categories['data'] : [],
                'errors' => $errorEnvelope['fields'] ?? [],
                'values' => $_POST,
                'top_error' => $errorEnvelope['message'] ?? '',
            ]
        );
    }

    private function currentId(): int
    {
        $params = $GLOBALS['_tt_path_params'] ?? [];
        return (int) ($params['id'] ?? 0);
    }

    private function tabForStatus(string $status): string
    {
        return in_array($status, ['active', 'pending', 'sold', 'draft'], true) ? $status : 'draft';
    }
}
