<?php

/**
 * TicketTrade — Support\ResponseHeaders
 *
 * Per D-20 + D-21 + AD-13. Replaces the Phase 1 eval stub in
 * config/bootstrap.php. Sets the four AD-13 security headers plus
 * any extras from config/security_headers.php['extra'].
 */

declare(strict_types=1);

namespace App\Support;

class ResponseHeaders
{
    private static bool $booted = false;

    /**
     * Set security headers before any body output.
     *
     * Idempotent: a second call is a no-op. Defensive headers_sent()
     * check avoids warnings when output has already started.
     */
    public static function boot(): void
    {
        if (self::$booted || headers_sent()) {
            return;
        }
        self::$booted = true;
        $csp = require APP_ROOT . '/config/security_headers.php';
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Content-Security-Policy: ' . $csp['csp']);
        foreach ($csp['extra'] ?? [] as $name => $value) {
            header($name . ': ' . $value);
        }
    }
}
