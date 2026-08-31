<?php
/**
 * TicketTrade — Admin Front Controller
 *
 * Per AD-3: this is the ONLY entry point for admin routes. Phase 2
 * ships an empty admin route map; Phase 8 populates it.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

$surface = 'admin';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

try {
    \App\Support\Router::dispatch($surface, $requestPath);
} catch (\Throwable $e) {
    \App\Support\Error::server_error($e->getMessage());
}
