<?php

/**
 * TicketTrade — Support\Csrf Token Management
 *
 * Per D-13 + AD-13:
 *   - Per-session token (not per-request) so a slow form submission
 *     doesn't 419 (Pitfall 1).
 *   - 32 random bytes from random_bytes() — 256 bits entropy, encoded
 *     to 64 lowercase hex chars.
 *   - hash_equals() constant-time compare on POST/PUT/PATCH/DELETE.
 *   - On mismatch: 400 + E_CSRF envelope, exit.
 */

declare(strict_types=1);

namespace App\Support;

class Csrf
{
    /**
     * Generate (or retrieve) the per-session CSRF token.
     */
    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify the CSRF token for state-changing requests.
     *
     * No-op for GET/HEAD/OPTIONS. On mismatch: 400 + E_CSRF JSON, exit.
     */
    public static function verify(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }
        $sent = $_POST['csrf_token'] ?? '';
        $stored = $_SESSION['csrf_token'] ?? '';
        if ($stored === '' || !hash_equals($stored, $sent)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'ok' => false,
                'error' => ['code' => 'E_CSRF', 'message' => 'CSRF token mismatch.'],
            ]);
            exit;
        }
    }
}
