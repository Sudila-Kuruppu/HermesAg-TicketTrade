<?php

/**
 * TicketTrade — Listing\Action\CreateListingAction
 *
 * Phase 3 Plan 03-02. The /listings/create route pair:
 * - GET  → renders the new-listing form (title, description, price,
 *          category, type, condition/service fields, images).
 * - POST → calls ListingService::createDraft, optionally uploads
 *          images, redirects with a flash toast, OR re-renders the
 *          form with field errors preserved.
 *
 * The Action is a thin controller (validate input via Service → render
 * View). Per AD-1 it never writes to the DB directly.
 */

declare(strict_types=1);

namespace App\Listing\Action;

use App\Category\Service\category_service;
use App\Listing\Service\listing_service;
use App\Support\Auth as AuthGuard;
use App\Support\Csrf;
use App\Support\ImageUpload;
use App\Support\View;

class CreateListingAction
{
    /**
     * GET /listings/create
     */
    public function handle(): void
    {
        AuthGuard::requireAuth('/listings/create');

        $categories = category_service::listActive();
        $vars = [
            'csrf_token' => Csrf::token(),
            'categories' => ($categories['ok'] === true) ? $categories['data'] : [],
            'errors' => [],
            'values' => [],
            'upload_errors' => [],
        ];

        View::render(__DIR__ . '/../View/create.php', $vars);
    }

    /**
     * POST /listings/create
     */
    public function handlePost(): void
    {
        AuthGuard::requireAuth('/listings/create');

        // Two-button submit: action=save_draft OR action=submit.
        $button = (string) ($_POST['action'] ?? 'submit');
        // CR-02: price translation is centralized in the Service
        // (validateListingData accepts both `price_rupees` and
        // `price_cents`). No translation needed here.
        $result = listing_service::createDraft(
            (int) AuthGuard::currentUser()['user_id'],
            $_POST
        );

        if ($result['ok'] === false) {
            $this->renderForm($result['error'] ?? ['code' => 'E_VALIDATION', 'message' => '', 'fields' => []], []);
            return;
        }

        $listing = $result['data'];
        $listingId = (int) ($listing['id'] ?? 0);

        // Optional image upload (even on save_draft; the seller can attach
        // images later via Edit).
        $uploadErrors = [];
        if ($listingId > 0 && !empty($_FILES['images']) && (int) ($_FILES['images']['error'][0] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $upload = listing_service::uploadImages($listingId, (int) AuthGuard::currentUser()['user_id'], $_FILES['images']);
            if ($upload['ok'] === true && !empty($upload['data']['errors'])) {
                $uploadErrors = $upload['data']['errors'];
            }
        }

        if ($button === 'save_draft') {
            View::flash('success', 'Draft saved.');
            header('Location: /my-listings?tab=draft');
            exit;
        }

        // Default = submit for review. Flip status to pending.
        listing_service::submitDraft($listingId, (int) AuthGuard::currentUser()['user_id']);

        $msg = 'Listing created - pending admin approval';
        if (!empty($uploadErrors)) {
            $msg .= ' (some images failed to upload)';
        }
        View::flash('success', $msg);
        header('Location: /my-listings?tab=pending');
        exit;
    }

    /**
     * Internal: re-render the form on validation failure.
     *
     * @param array $errorEnvelope The AD-16 error envelope from the Service.
     * @param array $uploadErrors  Optional list of per-image upload errors.
     */
    private function renderForm(array $errorEnvelope, array $uploadErrors): void
    {
        $categories = category_service::listActive();
        $fields = $errorEnvelope['fields'] ?? [];
        View::render(
            __DIR__ . '/../View/create.php',
            [
                'csrf_token' => Csrf::token(),
                'categories' => ($categories['ok'] === true) ? $categories['data'] : [],
                'errors' => $fields,
                'values' => $_POST,
                'upload_errors' => $uploadErrors,
                'top_error' => $errorEnvelope['message'] ?? '',
            ]
        );
    }
}
