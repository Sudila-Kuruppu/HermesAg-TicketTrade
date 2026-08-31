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
     * Generic 500; logs internal message to error_log in non-production.
     */
    public static function server_error(string $internalMessage = ''): void
    {
        http_response_code(500);
        if ($internalMessage !== '' && getenv('APP_ENV') !== 'production') {
            error_log('[phase-2] ' . $internalMessage);
        }
        header('Content-Type: text/html; charset=utf-8');
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
