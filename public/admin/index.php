<?php
/**
 * TicketTrade — Admin Front Controller
 *
 * Single entry point for all admin routes. Identical to the student
 * front controller except the surface context is 'admin', which
 * controls theme default (light) and any per-surface routing rules.
 *
 * Per AD-3: this is the ONLY entry point for admin routes.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

$surface = 'admin';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

try {
    \App\Support\Router::dispatch($surface, $requestPath);
} catch (\RuntimeException $e) {
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<title>Server Error</title></head><body>'
        . '<main id="main" tabindex="-1"><h1>Server Error</h1>'
        . '<p>The application could not complete your request.</p>'
        . '</main></body></html>';
}
