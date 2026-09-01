<?php

/**
 * TicketTrade — Listing\Action\BrowseAction
 *
 * Phase 3 Plan 03-03. The /board route. Renders the corkboard board
 * view (LND-07) with category tabs (D-15..D-19), search (D-15), and
 * pagination. The Board view also includes the full-screen listing
 * modal at the bottom of the page (D-20..D-24).
 *
 * Per D-09 (Phase 2): guests can browse, but the card CTA reads
 * "Sign in to buy" and links to /login?next=/board. Logged-in buyers
 * see "Buy Now" linking to /listings/{id}#buy (a placeholder until
 * Phase 4 wires the real purchase flow).
 *
 * Per the must_haves truths in the plan:
 *   - Guest: 200, up to 50 cards, rotation = crc32(id) % 5 - 2,
 *     red/blue pushpin (id % 2), aria-hidden on rotation/pin, CTA
 *     "Sign in to buy" → /login?next=/board.
 *   - Logged-in: same corkboard but CTA "Buy Now" → /listings/{id}#buy.
 *   - Filters compose: ?q, ?cat, ?page.
 *   - Empty state: named copy.
 */

declare(strict_types=1);

namespace App\Listing\Action;

use App\Category\Service\category_service;
use App\Listing\Service\listing_service;
use App\Support\Auth as AuthGuard;
use App\Support\View;

class BrowseAction
{
    /** Per-page listing cap per D-17. */
    private const PER_PAGE = 50;

    public function handle(): void
    {
        $user = AuthGuard::currentUser();
        $isGuest = ($user === null);

        // -------- Parse + coerce input (T-03-23, T-03-24 hardening) ----
        $q = isset($_GET['q']) ? mb_substr(trim((string) $_GET['q']), 0, 100) : null;
        if ($q === '') {
            $q = null;
        }

        $cat = null;
        if (isset($_GET['cat']) && $_GET['cat'] !== '') {
            $candidate = (int) $_GET['cat'];
            if ($candidate > 0) {
                $catCheck = category_service::getById($candidate);
                if ($catCheck['ok'] === true) {
                    $cat = $candidate;
                }
                // Non-existent or inactive category → null (All).
            }
        }

        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

        // -------- Load the result set --------------------------------
        $search = listing_service::getSearchResults($q, $cat, $page);
        $rows = [];
        $total = 0;
        $pages = 1;
        $effectivePage = $page;
        if ($search['ok'] === true) {
            $rows = $search['data']['rows'] ?? [];
            $total = (int) ($search['data']['total'] ?? 0);
            $pages = (int) ($search['data']['pages'] ?? 1);
            $effectivePage = (int) ($search['data']['page'] ?? $page);
        }

        // -------- Load the category tab strip ------------------------
        $cats = [];
        $catList = category_service::listActive();
        if ($catList['ok'] === true) {
            $cats = $catList['data'];
        }

        // -------- Resolve the active category label (for empty state) -
        $activeCatName = 'all categories';
        if ($cat !== null) {
            foreach ($cats as $c) {
                if ((int) $c['id'] === $cat) {
                    $activeCatName = (string) $c['name'];
                    break;
                }
            }
        }

        View::render(
            __DIR__ . '/../View/board.php',
            [
                'rows' => $rows,
                'total' => $total,
                'page' => $effectivePage,
                'pages' => $pages,
                'q' => $q,
                'cat' => $cat,
                'categories' => $cats,
                'is_guest' => $isGuest,
                'active_cat_name' => $activeCatName,
            ]
        );
    }
}
