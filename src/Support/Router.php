<?php

/**
 * TicketTrade — Support\Router
 *
 * Phase 2 dispatch: lookup the route map, run admin/auth/rate_limit guards,
 * invoke the handler. The route map format is
 *   ['METHOD PATH' => [ClassName, methodName, opts]]
 * where opts is [auth => bool, admin => bool, csrf => bool, rate_limit => string|null].
 * Path placeholders like {nickname} are matched and exposed via
 * $GLOBALS['_tt_path_params'] as a [name => capturedValue] map.
 */

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

class Router
{
    /**
     * Dispatch a request to the matching handler.
     */
    public static function dispatch(string $surface, string $path): void
    {
        $surface = self::validateSurface($surface);
        $routes = self::loadRoutes($surface);
        if (empty($routes)) {
            self::renderStubLanding($surface);
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $GLOBALS['_tt_surface'] = $surface;
        error_log("[ROUTER] dispatch surface=$surface method=$method path=$path routes_count=" . count($routes) . " key={$method} {$path}");

        // Resolve the route: exact match first, then placeholder match.
        $key = $method . ' ' . $path;
        $route = $routes[$key] ?? null;
        $routeParams = [];
        if ($route === null) {
            error_log("[ROUTER] trying placeholders, count=" . count($routes));
            foreach ($routes as $routeKey => $routeEntry) {
                $hasPlaceholder = strpos($routeKey, '{');
                if ($hasPlaceholder === false) {
                    continue;
                }
                $spacePos = strpos($routeKey, ' ');
                if ($spacePos === false) {
                    continue;
                }
                if (substr($routeKey, 0, $spacePos) !== $method) {
                    continue;
                }
                $rkPath = substr($routeKey, $spacePos + 1);
                $pattern = preg_replace('#\\{[^}]+\\}#', '([^/]+)', $rkPath);
                if (!is_string($pattern)) {
                    continue;
                }
                $fullPattern = '#^' . $pattern . '$#';
                if (preg_match($fullPattern, $path, $m) === 1) {
                    $route = $routeEntry;
                    $names = [];
                    $nameCount = preg_match_all('#\\{([^}]+)\\}#', $rkPath, $names);
                    if ($nameCount && !empty($names[1])) {
                        foreach ($names[1] as $idx => $name) {
                            $routeParams[$name] = $m[$idx + 1] ?? '';
                        }
                    }
                    break;
                }
            }
        }

        error_log("[ROUTER] final route=" . var_export($route, true));
        if ($route === null) {
            http_response_code(404);
            self::renderGenericError('Not Found', 'The page you requested does not exist.');
            return;
        }
        if (!empty($routeParams)) {
            $GLOBALS['_tt_path_params'] = $routeParams;
        }

        $class = $route[0] ?? null;
        $methodName = $route[1] ?? null;
        $opts = $route[2] ?? [];
        if (!is_string($class) || !is_string($methodName)) {
            throw new \RuntimeException("Bad route entry for {$key}");
        }
        error_log("[ROUTER] class=$class method=$methodName admin_guard=" . var_export(!empty($opts['admin']), true));
        if (!class_exists($class)) {
            throw new \RuntimeException("Handler class not found: {$class}");
        }

        // Admin guard runs BEFORE the auth guard (D-10). An unauthenticated
        // access to /admin/* returns 404, not a 302 to /login, so the
        // route's existence is not leaked.
        if (
            !empty($opts['admin']) && (($GLOBALS['current_user'] ?? null) === null
            || !($GLOBALS['current_user']['is_admin'] ?? false))
        ) {
            http_response_code(404);
            self::renderGenericError('Not Found', 'The page you requested does not exist.');
            return;
        }

        // Route-level auth guard (D-08): unauthenticated GETs get a 302
        // to /login?next=<current>. For POSTs/PUT/PATCH/DELETE, CSRF has
        // already been verified at bootstrap. If auth is required and
        // the user is not logged in, we render a 401 envelope.
        if (!empty($opts['auth']) && ($GLOBALS['current_user'] ?? null) === null) {
            if ($method === 'GET') {
                $next = urlencode($path);
                header('Location: /login?next=' . $next);
                exit;
            }
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => ['code' => 'E_AUTH_REQUIRED', 'message' => 'Authentication required.'],
            ]);
            exit;
        }

        // Per-route rate limit (D-12, D-13). Runs before the handler so
        // the cost of password verification is skipped on a flood.
        if (!empty($opts['rate_limit'])) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $result = RateLimit::hit((string) $opts['rate_limit'], $ip);
            if (!$result['allowed']) {
                $GLOBALS['_tt_form_error'] = [
                    'code' => 'E_RATE_LIMIT',
                    'message' => 'Too many attempts. Try again in a few minutes.',
                ];
                if ($method === 'GET') {
                    self::renderRateLimited((string) $opts['rate_limit']);
                    return;
                }
                http_response_code(429);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok' => false,
                    'error' => [
                        'code' => 'E_RATE_LIMIT',
                        'message' => 'Too many attempts. Try again in a few minutes.',
                    ],
                ]);
                exit;
            }
        }
        (new $class())->$methodName();
    }

    private static function renderGenericError(string $title, string $message): void
    {
        $layout = __DIR__ . '/View/layout.php';
        if (is_file($layout)) {
            $GLOBALS['_tt_view_vars'] = ['page_title' => $title, 'page_message' => $message];
            $GLOBALS['_tt_content_view'] = null;
            require $layout;
            return;
        }
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            . '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title></head><body>'
            . '<main id="main" tabindex="-1"><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></main></body></html>';
    }

    private static function renderRateLimited(string $route): void
    {
        $placeholder = __DIR__ . '/../Auth/View/placeholder.php';
        $GLOBALS['_tt_view_vars'] = ['rate_limited' => true, 'route' => $route];
        $GLOBALS['_tt_content_view'] = $placeholder;
        require __DIR__ . '/View/layout.php';
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
     */
    private static function renderStubLanding(string $surface): void
    {
        http_response_code(200);
        $viewPath = __DIR__ . '/View/landing.php';
        if (is_file($viewPath)) {
            $GLOBALS['_tt_surface'] = $surface;
            require $viewPath;
        } else {
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
                . '<title>TicketTrade</title></head><body><main id="main" tabindex="-1">'
                . '<h1>TicketTrade</h1></main></body></html>';
        }
    }
}
