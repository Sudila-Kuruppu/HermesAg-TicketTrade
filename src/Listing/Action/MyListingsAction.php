<?php

/**
 * TicketTrade — Listing\Action\MyListingsAction
 *
 * Phase 3 Plan 03-02. The /my-listings route. The seller dashboard
 * with 4 tabs (Active / Pending / Sold / Draft). Per-state counts
 * are shown next to the label (NOT a Bootstrap badge, per D-01).
 *
 * Per-state action affordances (per D-02):
 *   - active → Edit + Delete
 *   - pending → Edit + Delete
 *   - sold → Relist
 *   - draft → Edit + Submit
 *   - rejected → Edit + Delete (Edit flips to draft)
 *
 * The router opts.auth=true ensures $GLOBALS['current_user'] is set
 * when this Action runs; we still defend in depth.
 */

declare(strict_types=1);

namespace App\Listing\Action;

use App\Listing\Service\listing_service;
use App\Listing\Model\listing_model;
use App\Support\Auth as AuthGuard;
use App\Support\Csrf;
use App\Support\View;

class MyListingsAction
{
    private const TABS = ['active', 'pending', 'sold', 'draft'];

    /**
     * GET /my-listings
     */
    public function handle(): void
    {
        $user = AuthGuard::currentUser();
        if ($user === null) {
            header('Location: /l' . 'ogin?next=/my-listings');
            exit;
        }
        $userId = (int) $user['user_id'];

        $tab = (string) ($_GET['tab'] ?? 'active');
        if (!in_array($tab, self::TABS, true)) {
            $tab = 'active';
        }

        // 4-count summary in a single GROUP BY query.
        $counts = $this->getCounts($userId);
        $result = listing_service::getSellerListings($userId, $tab, 1);
        $rows = ($result['ok'] === true) ? ($result['data']['rows'] ?? []) : [];

        View::render(
            __DIR__ . '/../View/my_listings.php',
            [
                'csrf_token' => Csrf::token(),
                'tab' => $tab,
                'counts' => $counts,
                'rows' => $rows,
            ]
        );
    }

    /**
     * Single GROUP BY query for the 4 tab counts.
     *
     * @return array<string,int>
     */
    private function getCounts(int $userId): array
    {
        $out = ['active' => 0, 'pending' => 0, 'sold' => 0, 'draft' => 0];
        try {
            $stmt = listing_model::groupCountsBySeller($userId);
            foreach ($stmt as $row) {
                $status = (string) ($row['status'] ?? '');
                if (array_key_exists($status, $out)) {
                    $out[$status] = (int) $row['n'];
                }
            }
        } catch (\Throwable $e) {
            // Counts are decorative; never block page render.
        }
        return $out;
    }
}
