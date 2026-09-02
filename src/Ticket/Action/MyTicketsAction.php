<?php

/**
 * TicketTrade — Ticket\Action\MyTicketsAction
 *
 * Phase 4 Plan 04-02. GET /my-tickets.
 *
 * Per AD-1: thin Action. Reads the ?tab query string, calls
 * Ticket\Service\ticket_service::getTicketsForBuyer(), and renders
 * the My Tickets view with the per-tab counts.
 *
 * The active tab carries aria-current='page'; tab counts render as
 * inline bg-secondary badges next to each tab label (per mockup +
 * CONTEXT D-05).
 */

declare(strict_types=1);

namespace App\Ticket\Action;

use App\Auth\Service\auth_service;
use App\Support\Auth as AuthGuard;
use App\Support\Csrf;
use App\Support\View;
use App\Ticket\Service\ticket_service;

class MyTicketsAction
{
    public const TABS = ['all', 'active', 'redeemed', 'expired', 'disputed'];

    public function handle(): void
    {
        $user = AuthGuard::currentUser();
        if ($user === null) {
            header('Location: /login?next=/my-tickets');
            exit;
        }
        $userId = (int) $user['user_id'];

        // Read the tab from the query string. Default to 'active'.
        $tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'active';
        if (!in_array($tab, self::TABS, true)) {
            $tab = 'active';
        }

        // Read the optional ?new={ticket_id} for auto-focus after a buy.
        $newTicketId = isset($_GET['new']) ? max(0, (int) $_GET['new']) : 0;

        $result = ticket_service::getTicketsForBuyer($userId, $tab);

        // Per-ticket seller info (nickname + tier) is needed for the
        // ticket cards' seller info row. We do a single join query.
        $pdo = \App\Support\Db::pdo();
        $tickets = $result['tickets'];
        if (!empty($tickets)) {
            $sellerIds = array_unique(array_map(fn($t) => (int) $t['seller_id'], $tickets));
            $placeholders = implode(',', array_fill(0, count($sellerIds), '?'));
            $stmt = $pdo->prepare(
                "SELECT user_id, nickname, tier, points, is_verified, whatsapp "
                . "FROM users WHERE user_id IN ($placeholders)"
            );
            $stmt->execute(array_values($sellerIds));
            $sellerMap = [];
            foreach ($stmt->fetchAll() as $r) {
                $sellerMap[(int) $r['user_id']] = $r;
            }
            foreach ($tickets as &$t) {
                $sid = (int) $t['seller_id'];
                $t['seller_nickname'] = (string) ($sellerMap[$sid]['nickname'] ?? 'seller');
                $t['seller_tier'] = (string) ($sellerMap[$sid]['tier'] ?? 'E');
                $t['seller_whatsapp'] = (string) ($sellerMap[$sid]['whatsapp'] ?? '');
                $t['seller_points'] = (int) ($sellerMap[$sid]['points'] ?? 0);
                $t['seller_is_verified'] = (bool) ($sellerMap[$sid]['is_verified'] ?? false);
                $t['seller_rank_name'] = auth_service::tierFromPoints((int) ($sellerMap[$sid]['points'] ?? 0));
            }
            unset($t);
        }

        View::render(
            __DIR__ . '/../View/my_tickets.php',
            [
                'tickets' => $tickets,
                'tab' => $result['tab'],
                'tab_counts' => $result['tab_counts'],
                'csrf_token' => Csrf::token(),
                'new_ticket_id' => $newTicketId,
                'user' => AuthGuard::sanitizeUser($user),
            ]
        );
    }
}
