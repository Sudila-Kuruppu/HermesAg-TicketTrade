<?php
/**
 * TicketTrade — Minimum Viable Router
 *
 * Phase 1 deliverable: when the route map is empty (which it is for
 * Phase 1 student AND admin surfaces), renders the stub landing page.
 * The front controllers in public/ call Router::dispatch($surface).
 *
 * Phase 2 extends this with real dispatch against the route map,
 * context validation against config/contexts.php, CSRF check, and
 * the auth guard.
 */

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

class Router
{
    /**
     * Dispatch a request to the matching handler.
     *
     * @param string $surface   'student' or 'admin'
     * @param string $path      Request path (already parsed)
     */
    public static function dispatch(string $surface, string $path): void
    {
        $surface = self::validateSurface($surface);
        $routes = self::loadRoutes($surface);

        // Phase 1: empty route map renders the stub landing page
        if (empty($routes)) {
            self::renderStubLanding($surface);
            return;
        }

        // Phase 2+: lookup and dispatch
        // $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        // $key = $method . ' ' . $path;
        // if (!isset($routes[$key])) {
        //     self::render404();
        //     return;
        // }
        // $handler = $routes[$key];
        // [$class, $method] = explode('::', $handler);
        // (new $class())->$method();
    }

    private static function validateSurface(string $surface): string
    {
        if (!in_array($surface, ['student', 'admin'], true)) {
            throw new RuntimeException("Invalid surface: {$surface}");
        }
        return $surface;
    }

    /**
     * Load the route map for the given surface.
     */
    private static function loadRoutes(string $surface): array
    {
        $path = $surface === 'admin'
            ? (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2)) . '/admin/config/routes.php'
            : (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2)) . '/config/routes.php';

        if (!is_file($path)) {
            return [];
        }

        $routes = require $path;
        return is_array($routes) ? $routes : [];
    }

    /**
     * Render the Phase 1 stub landing page (HTTP 200).
     *
     * The stub is the minimum HTML needed to prove the routing layer is
     * wired and to satisfy the verify command in Plan 01-01 Task 1.
     * Phase 2 replaces this with real dispatch.
     */
    private static function renderStubLanding(string $surface): void
    {
        http_response_code(200);
        $viewPath = __DIR__ . '/View/landing.php';
        if (is_file($viewPath)) {
            // Surface is consumed by the view to set data-surface attr
            $GLOBALS['_tt_surface'] = $surface;
            require $viewPath;
        } else {
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
                . '<title>TicketTrade</title></head><body><main id="main" tabindex="-1">'
                . '<h1>TicketTrade</h1></main></body></html>';
        }
    }

    private static function render404(): void
    {
        http_response_code(404);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            . '<title>Not Found</title></head><body>'
            . '<main id="main" tabindex="-1"><h1>Not Found</h1>'
            . '<p>The page you requested does not exist.</p>'
            . '</main></body></html>';
    }
}
