<?php

/**
 * TicketTrade — Listing\Action\ListingFragmentAction
 *
 * Phase 3 Plan 03-03. Returns a JSON envelope with the rendered modal
 * body for a given listing id, plus the prev/next ids the modal's
 * keyboard navigation needs. Used by /assets/js/listing_modal.js for
 * the prev/next AJAX swap (D-22).
 *
 * Response shape (success):
 *   { ok: true, listing_id: int, title: string, html: string,
 *     prev_id: int|null, next_id: int|null }
 *
 * Response shape (failure):
 *   { ok: false, listing_id: int|null, error: string }
 *
 * The endpoint is rate-limited via the standard rate-limit layer
 * (img_thumb, 60/min/IP) — public, low cost.
 */

declare(strict_types=1);

namespace App\Listing\Action;

use App\Listing\Model\listing_model;
use App\Listing\Service\listing_service;

class ListingFragmentAction
{
    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $pathParams = $GLOBALS['_tt_path_params'] ?? [];
        $id = isset($pathParams['id']) ? (int) $pathParams['id'] : 0;
        $nav = isset($_GET['nav']) ? (string) $_GET['nav'] : 'open';

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'bad_request']);
            return;
        }

        // Only serve active listings (board visibility rule).
        $row = listing_model::findById($id);
        if ($row === null || (string) ($row['status'] ?? '') !== 'active') {
            http_response_code(404);
            echo json_encode(['ok' => false, 'listing_id' => $id, 'error' => 'not_found']);
            return;
        }

        // If the caller asked for next/prev specifically, return just
        // those ids (a lightweight navigation probe). The modal's
        // JS uses this to walk the list without re-rendering.
        if ($nav === 'prev' || $nav === 'next') {
            $catId = (int) ($row['category_id'] ?? 0);
            $catIdOrNull = $catId > 0 ? $catId : null;
            $targetId = listing_model::getNextInCategory($id, $catIdOrNull, $nav);
            if ($targetId === null) {
                http_response_code(204);
                return;
            }
            echo json_encode([
                'ok' => true,
                'listing_id' => $targetId,
                'direction' => $nav,
            ]);
            return;
        }

        // Default: render the full modal body for the requested listing.
        $withImages = listing_service::getWithImages($id);
        if ($withImages === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'listing_id' => $id, 'error' => 'not_found']);
            return;
        }

        $catId = (int) ($row['category_id'] ?? 0);
        $catIdOrNull = $catId > 0 ? $catId : null;
        $prevId = listing_model::getNextInCategory($id, $catIdOrNull, 'prev');
        $nextId = listing_model::getNextInCategory($id, $catIdOrNull, 'next');

        // Render the listing_modal_carousel + details partial inline.
        $images = $withImages['images'] ?? [];
        $carouselImages = [];
        foreach ($images as $img) {
            if (($img['size'] ?? '') === 'full') {
                $carouselImages[] = $img;
            }
        }

        // Render via output buffering so we can emit it as JSON.
        $vars = [
            'listing_id' => $id,
            'title' => (string) $withImages['title'],
            'images' => $carouselImages,
            'id_prefix' => 'listingModalCarouselAjax' . $id,
        ];
        $GLOBALS['_tt_view_vars'] = $vars;
        ob_start();
        require __DIR__ . '/../../Support/View/partials/listing_modal_carousel.php';
        $carouselHtml = (string) ob_get_clean();

        $priceCents = (int) ($withImages['price_cents'] ?? 0);
        $priceStr = number_format($priceCents / 100, 2);
        $titleStr = (string) ($withImages['title'] ?? '');
        $descStr = (string) ($withImages['description'] ?? '');
        $nickname = (string) ($withImages['seller_nickname'] ?? 'seller');
        $tier = (string) ($withImages['seller_tier'] ?? 'E');
        $verified = !empty($withImages['seller_is_verified']);

        $html = '<div class="listing-modal__carousel-wrap" data-listing-id="'
            . htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') . '">'
            . $carouselHtml
            . '<div class="listing-modal__details p-3">'
            . '<p class="listing-modal__price h4 mb-2">LKR '
            . htmlspecialchars($priceStr, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p class="listing-modal__description body-md">'
            . nl2br(htmlspecialchars($descStr, ENT_QUOTES, 'UTF-8')) . '</p>'
            . '<div class="listing-modal__seller d-flex align-items-center gap-2 mt-3">'
            . '<span class="body-sm text-on-surface-variant">Sold by <strong>@'
            . htmlspecialchars($nickname, ENT_QUOTES, 'UTF-8') . '</strong>'
            . ($verified ? ' <span aria-label="Verified student">&#10003;</span>' : '')
            . '</span>'
            . '<span class="badge rank-badge rank-'
            . htmlspecialchars(strtolower($tier), ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($tier, ENT_QUOTES, 'UTF-8') . '</span>'
            . '</div>'
            . '<div class="listing-modal__actions mt-4">'
            . '<a href="/listings/' . (int) $id . '#buy" class="btn btn-primary listing-modal__buy">Buy now</a>'
            . '<a href="/listings/' . (int) $id . '/report" class="btn btn-link listing-modal__report">Report</a>'
            . '</div>'
            . '</div>'
            . '</div>';

        echo json_encode([
            'ok' => true,
            'listing_id' => $id,
            'title' => $titleStr,
            'html' => $html,
            'prev_id' => $prevId,
            'next_id' => $nextId,
        ], JSON_UNESCAPED_UNICODE);
    }
}
