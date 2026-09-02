<?php

/**
 * TicketTrade — Ticket\Action\RedeemAction
 *
 * Phase 4 Plan 04-01. POST /tickets/redeem.
 *
 * Per AD-1: thin Action. CSRF is enforced at bootstrap.
 * Rate limit: 'redemption' (5/hr/(ticket+user) per NFR-SEC-007 + D-08).
 *   The 3rd RateLimit key param scopes the bucket per ticket+user
 *   so wrong-code attempts on ticket A don't count against ticket B.
 *
 * The code input is normalized server-side (strip whitespace +
 * dashes, then re-dashed) so users can paste the code with or
 * without dashes. The canonical dashed form is what the Service
 * matches against (D-01).
 */

declare(strict_types=1);

namespace App\Ticket\Action;

use App\Support\Auth as AuthGuard;
use App\Support\Csrf;
use App\Support\RateLimit;
use App\Support\View;
use App\Ticket\Model\ticket_model;
use App\Ticket\Service\ticket_service;
use App\Support\Db;

class RedeemAction
{
    /**
     * POST /tickets/redeem
     */
    public function handlePost(): void
    {
        $user = AuthGuard::currentUser();
        if ($user === null) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => ['code' => 'E_AUTH_REQUIRED', 'message' => 'Authentication required.'],
            ]);
            exit;
        }
        $userId = (int) $user['user_id'];

        Csrf::token();

        // Read the code from POST. The user pastes the buyer's code.
        $raw = (string) ($_POST['ticket_code'] ?? '');
        $code = self::normalizeCode($raw);
        if ($code === '') {
            $this->renderSalesError(['code' => 'E_VALIDATION', 'message' => 'Ticket code is required.']);
            return;
        }

        // Rate-limit bucket scope: ticket:<ticket_id>:<user_id> when
        // we can resolve the ticket from the code. If the code is
        // unknown, fall back to the (user) only — D-08 only protects
        // legitimate flows.
        $ticketId = 0;
        try {
            $pdo = Db::pdo();
            $existing = ticket_model::findByCode($pdo, $code);
            if ($existing !== null) {
                $ticketId = (int) $existing['id'];
            }
        } catch (\Throwable $e) {
            // ignore — bucket key fallback
        }
        $bucketKey = $ticketId > 0 ? ('ticket:' . $ticketId . ':' . $userId) : ('user:' . $userId);

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $rl = RateLimit::hit('redemption', $ip, $bucketKey);
        if (!$rl['allowed']) {
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => [
                    'code' => 'E_RATE_LIMIT',
                    'message' => 'Too many redemptions. Try again later.',
                ],
            ]);
            exit;
        }

        $result = ticket_service::redeemTicket($code, $userId);
        if ($result['ok'] === true) {
            View::flash('success', 'Ticket redeemed. Handover complete.');
            header('Location: /sales');
            exit;
        }

        $this->renderSalesError($result['error']);
    }

    /**
     * Normalize the pasted ticket code to the canonical dashed form
     * `TK-XXXX-XXXX-XXXX-XXXX-XXXX`. Strips whitespace + dashes,
     * then re-dashes into the canonical 6-group form. Per D-01 the
     * dashed form is the canonical stored form so the lookup matches
     * it directly.
     */
    public static function normalizeCode(string $raw): string
    {
        $stripped = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');
        if (strpos($stripped, 'TK') === 0) {
            $stripped = substr($stripped, 2);
        }
        // Now we expect 24 chars (6 groups * 4). If not, the input
        // is malformed.
        if (strlen($stripped) !== 24) {
            return '';
        }
        $groups = str_split($stripped, 4);
        return 'TK-' . implode('-', $groups);
    }

    /**
     * Internal: re-render the Sales page with the error inline.
     */
    private function renderSalesError(array $error): void
    {
        View::flash('error', (string) ($error['message'] ?? 'Could not redeem ticket.'));
        header('Location: /sales');
        exit;
    }
}
