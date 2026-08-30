<?php
/**
 * TicketTrade — Student Front Controller
 * 
 * Single entry point for all student-facing routes. Loads the bootstrap,
 * determines the surface context, and dispatches via Support\Router.
 *
 * Per AD-3: this is the ONLY entry point for student routes.
 * Per AD-13: Support\ResponseHeaders is a Phase 1 no-op stub;
 *            real security headers land in Phase 9.
 */

declare(strict_types=1);

// Bootstrap: config + autoload + session + timezone + auth guard (Phase 2+)
require_once __DIR__ . '/../config/bootstrap.php';

$surface = 'student';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

try {
    \App\Support\Router::dispatch($surface, $requestPath);
} catch (\RuntimeException $e) {
    // Surface a minimal 500 to the client without leaking stack traces.
    // Production error logging lands in Phase 9.
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<title>Server Error</title></head><body>'
        . '<main id="main" tabindex="-1"><h1>Server Error</h1>'
        . '<p>The application could not complete your request.</p>'
        . '</main></body></html>';
}
