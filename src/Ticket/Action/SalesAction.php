<?php

/**
 * TicketTrade — Ticket\Action\SalesAction
 *
 * Phase 4 Plan 04-02. GET /sales.
 *
 * Per AD-1: thin Action. Calls Ticket\Service\ticket_service::getGroupedSales()
 * and renders the Sales View. The page header carries the redemption
 * input form (POST /tickets/redeem) so the seller can paste a buyer's
 * code at any time.
 *
 * Per D-05 the View uses per-listing-group placement: each listing
 * renders as a card group with the listing title + per-listing
 * progress chip (only when total_sessions > 1) + ticket rows
 * (status badge + ticket-code-block with the buyer's nickname +
 * #N/M progress + "Confirm next session" button next to the
 * in-progress ticket).
 */

declare(strict_types=1);

namespace App\Ticket\Action;

use App\Support\Auth as AuthGuard;
use App\Support\Csrf;
use App\Support\View;
use App\Ticket\Service\ticket_service;

class SalesAction
{
    public function handle(): void
    {
        $user = AuthGuard::currentUser();
        if ($user === null) {
            header('Location: /login?next=/sales');
            exit;
        }
        $userId = (int) $user['user_id'];

        $groups = ticket_service::getGroupedSales($userId);

        // Per-ticket buyer info (nickname + tier) is needed for the
        // ticket-code-block's "buyer" hint + the row layout.
        $pdo = \App\Support\Db::pdo();
        $allTicketIds = [];
        foreach ($groups as $g) {
            foreach ($g['tickets'] as $t) {
                $allTicketIds[] = (int) $t['id'];
            }
        }
        $buyerMap = [];
        if (!empty($allTicketIds)) {
            $buyerIds = [];
            foreach ($groups as $g) {
                foreach ($g['tickets'] as $t) {
                    $buyerIds[(int) $t['buyer_id']] = true;
                }
            }
            $buyerIds = array_keys($buyerIds);
            if (!empty($buyerIds)) {
                $placeholders = implode(',', array_fill(0, count($buyerIds), '?'));
                $stmt = $pdo->prepare(
                    "SELECT user_id, nickname, tier, whatsapp "
                    . "FROM users WHERE user_id IN ($placeholders)"
                );
                $stmt->execute(array_values($buyerIds));
                foreach ($stmt->fetchAll() as $r) {
                    $buyerMap[(int) $r['user_id']] = $r;
                }
            }
            // Attach buyer info to each ticket.
            foreach ($groups as &$g) {
                foreach ($g['tickets'] as &$t) {
                    $bid = (int) $t['buyer_id'];
                    $t['buyer_nickname'] = (string) ($buyerMap[$bid]['nickname'] ?? 'buyer');
                    $t['buyer_tier'] = (string) ($buyerMap[$bid]['tier'] ?? 'E');
                    $t['buyer_whatsapp'] = (string) ($buyerMap[$bid]['whatsapp'] ?? '');
                }
                unset($t);
            }
            unset($g);
        }

        View::render(
            __DIR__ . '/../View/sales.php',
            [
                'groups' => $groups,
                'csrf_token' => Csrf::token(),
                'user' => AuthGuard::sanitizeUser($user),
            ]
        );
    }
}
