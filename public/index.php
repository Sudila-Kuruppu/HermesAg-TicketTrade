<?php
/**
 * TicketTrade — Student Front Controller
 *
 * Per AD-3: this is the ONLY entry point for student routes. The
 * bootstrap (config/bootstrap.php) handles session start, security
 * headers, auth guard, and CSRF verification. The Router consults
 * $GLOBALS['current_user'] and the route map for dispatch.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$surface = 'student';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

try {
    \App\Support\Router::dispatch($surface, $requestPath);
} catch (\Throwable $e) {
    \App\Support\Error::server_error($e->getMessage());
}
