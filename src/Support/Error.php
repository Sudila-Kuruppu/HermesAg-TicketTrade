<?php
/**
 * TicketTrade — Support\Error
 *
 * Per AD-16. Canonical 404/405/500 pages plus the failure envelope.
 * The 404 page is the same for unknown routes AND non-admin /admin/*
 * access (D-10, AD-14 — don't reveal the resource exists).
 */

declare(strict_types=1);

namespace App\Support;

class Error
{
    /**
     * Generic 404 — same page for unknown routes and /admin/* non-admin.
     */
    public static function not_found(): void
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            . '<title>Not Found</title></head><body><main id="main" tabindex="-1">'
            . '<h1>Not Found</h1><p>The page you requested does not exist.</p>'
            . '</main></body></html>';
        exit;
    }

    /**
     * Generic 405 with Allow header.
     */
    public static function method_not_allowed(): void
    {
        http_response_code(405);
        header('Allow: GET, POST');
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            . '<title>Method Not Allowed</title></head><body><main id="main" tabindex="-1">'
            . '<h1>Method Not Allowed</h1>'
            . '</main></body></html>';
        exit;
    }

    /**
     * Generic 500; ALWAYS logs the internal message server-side, but
     * only echoes it to the client in an explicit APP_ENV=development
     * context (safe-by-default per CR-004). In production the client
     * sees a generic page so table names, paths, and class names don't
     * leak.
     */
    public static function server_error(string $internalMessage = ''): void
    {
        http_response_code(500);
        // Always log server-side so operators can diagnose even when
        // APP_ENV is unset (which we now treat as production by default).
        if ($internalMessage !== '') {
            error_log('[server_error] ' . $internalMessage);
        }
        // Safe-by-default: only echo the message in an explicit dev env.
        $isDev = getenv('APP_ENV') !== false && getenv('APP_ENV') === 'development';
        header('Content-Type: text/html; charset=utf-8');
        if ($isDev && $internalMessage !== '') {
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
                . '<title>Server Error</title></head><body><main id="main" tabindex="-1">'
                . '<h1>Server Error</h1><pre>' . htmlspecialchars($internalMessage, ENT_QUOTES, 'UTF-8') . '</pre>'
                . '</main></body></html>';
            exit;
        }
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            . '<title>Server Error</title></head><body><main id="main" tabindex="-1">'
            . '<h1>Server Error</h1><p>The application could not complete your request.</p>'
            . '</main></body></html>';
        exit;
    }

    /**
     * AD-16 failure envelope.
     *
     * @param bool $ok
     * @param mixed $data
     * @param array|null $error [code, message, fields?]
     * @return array
     */
    public static function envelope(bool $ok, $data = null, ?array $error = null): array
    {
        return [
            'ok' => $ok,
            'data' => $data,
            'error' => $error,
        ];
    }
}
