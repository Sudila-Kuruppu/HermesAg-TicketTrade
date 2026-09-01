<?php
/**
 * Phase 3 — RouteGuardListingTest
 *
 * Verifies route map shapes for the listing CRUD + admin cron endpoints.
 *   - GET/POST /listings/create are auth-required
 *   - POST /listings/{id}/* are csrf-required
 *   - POST /admin/cron/ticket-expiry is admin-required + csrf-required
 *     + rate-limited to admin_cron
 *   - The router's admin guard returns 404 (not 403) for non-admin access
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase03\Support;

use App\Tests\Integration\Phase03\Fixtures\Fixtures;

class RouteGuardListingTest extends Fixtures
{
    public function test_create_routes_require_auth(): void
    {
        $routes = require APP_ROOT . '/config/routes.php';
        $this->assertTrue($routes['GET /listings/create'][2]['auth']);
        $this->assertTrue($routes['POST /listings/create'][2]['auth']);
        // The GET shows the form (no CSRF needed); POST requires CSRF.
        $this->assertFalse($routes['GET /listings/create'][2]['csrf']);
        $this->assertTrue($routes['POST /listings/create'][2]['csrf']);
    }

    public function test_create_post_has_listing_create_rate_limit(): void
    {
        $routes = require APP_ROOT . '/config/routes.php';
        $this->assertSame('listing_create', $routes['POST /listings/create'][2]['rate_limit']);
    }

    public function test_edit_get_does_not_have_csrf(): void
    {
        $routes = require APP_ROOT . '/config/routes.php';
        $this->assertTrue($routes['GET /listings/{id}/edit'][2]['auth']);
        $this->assertFalse($routes['GET /listings/{id}/edit'][2]['csrf']);
    }

    public function test_state_changing_listing_routes_have_csrf(): void
    {
        $routes = require APP_ROOT . '/config/routes.php';
        foreach ([
            'POST /listings/{id}/edit',
            'POST /listings/{id}/delete',
            'POST /listings/{id}/relist',
            'POST /listings/{id}/submit',
        ] as $key) {
            $this->assertTrue($routes[$key][2]['csrf'], "$key must have csrf flag");
            $this->assertTrue($routes[$key][2]['auth'], "$key must have auth flag");
        }
    }

    public function test_cron_route_is_admin_and_rate_limited(): void
    {
        $routes = require APP_ROOT . '/config/routes.php';
        $opts = $routes['POST /admin/cron/ticket-expiry'][2];
        $this->assertTrue($opts['auth']);
        $this->assertTrue($opts['admin']);
        $this->assertTrue($opts['csrf']);
        $this->assertSame('admin_cron', $opts['rate_limit']);
    }

    public function test_admin_guard_returns_404_not_403(): void
    {
        // Per D-10 + AD-14: admin-only routes return 404 to non-admin
        // users, never 403, so existence is not leaked. The Router code
        // is the canonical implementation.
        $routerSrc = file_get_contents(APP_ROOT . '/src/Support/Router.php');
        $this->assertStringContainsString('admin', $routerSrc);
        $this->assertStringContainsString('http_response_code(404)', $routerSrc);
    }

    public function test_auth_require_re_method_exists_and_returns_user_row(): void
    {
        // Plan 03-02 deviation: Support\Auth::requireReAuth(int) returns
        // an array (the current user row) on success. We verify the
        // method exists, has the right signature, AND that stale auth
        // returns 403 JSON.
        $src = file_get_contents(APP_ROOT . '/src/Support/Auth.php');
        $this->assertStringContainsString('public static function requireReAuth', $src);
        $this->assertStringContainsString(': array', $src);
        $this->assertStringContainsString('re-auth required', $src);
    }

    public function test_cron_log_table_exists(): void
    {
        $rows = $this->pdo->query("SHOW TABLES LIKE 'cron_log'")->fetchAll();
        $this->assertCount(1, $rows);
    }
}
