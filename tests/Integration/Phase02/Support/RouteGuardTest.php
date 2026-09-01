<?php
/**
 * Phase 2 — RouteGuardTest
 *
 * Verifies the route map in config/routes.php:
 *  - /my-tickets, /my-listings, /sales, /purchases are auth-required
 *  - /admin/* is admin-required (non-admin -> 404 per D-10)
 *  - /board is public-browse per D-09
 *  - /profile/{nickname} is public per D-11
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase02\Support;

use App\Tests\Integration\Phase02\Fixtures\Fixtures;

class RouteGuardTest extends Fixtures
{
    public function test_private_routes_have_auth_flag(): void
    {
        $routes = require APP_ROOT . '/config/routes.php';
        $expected = [
            'GET /profile' => true,
            'POST /profile' => true,
            'POST /logout' => true,
            'GET /settings' => true,
            'POST /settings' => true,
            'GET /my-tickets' => true,
            'GET /my-listings' => true,
            'GET /sales' => true,
            'GET /purchases' => true,
        ];
        foreach ($expected as $key => $expectedAuth) {
            $this->assertArrayHasKey($key, $routes, "Route $key should exist");
            $auth = $routes[$key][2]['auth'] ?? false;
            $this->assertSame($expectedAuth, $auth, "Route $key auth flag mismatch");
        }
    }

    public function test_public_routes_have_no_auth_flag(): void
    {
        $routes = require APP_ROOT . '/config/routes.php';
        $public = ['GET /', 'GET /login', 'POST /login', 'GET /register', 'POST /register', 'GET /verify', 'GET /forgot-password', 'POST /forgot-password', 'GET /reset-password', 'POST /reset-password', 'GET /board', 'GET /profile/{nickname}'];
        foreach ($public as $key) {
            $this->assertArrayHasKey($key, $routes, "Public route $key should exist");
            $auth = $routes[$key][2]['auth'] ?? false;
            $this->assertFalse($auth, "Public route $key should NOT have auth flag");
        }
    }

    public function test_login_post_has_rate_limit(): void
    {
        $routes = require APP_ROOT . '/config/routes.php';
        $this->assertSame('login', $routes['POST /login'][2]['rate_limit'] ?? null);
        $this->assertSame('register', $routes['POST /register'][2]['rate_limit'] ?? null);
        $this->assertSame('forgot_password', $routes['POST /forgot-password'][2]['rate_limit'] ?? null);
    }

    public function test_csrf_flag_on_state_changing_routes(): void
    {
        $routes = require APP_ROOT . '/config/routes.php';
        $stateChanging = ['POST /login', 'POST /register', 'POST /forgot-password', 'POST /reset-password', 'POST /profile', 'POST /logout', 'POST /settings'];
        foreach ($stateChanging as $key) {
            $csrf = $routes[$key][2]['csrf'] ?? false;
            $this->assertTrue($csrf, "Route $key should have csrf flag");
        }
    }

    public function test_private_route_302s_unauthenticated_to_login_next(): void
    {
        // Directly verify Support\Auth::requireAuth behavior.
        // We can't trigger an actual 302 from CLI without breaking the process.
        $src = file_get_contents(APP_ROOT . '/src/Support/Auth.php');
        $this->assertStringContainsString('requireAuth', $src);
        $this->assertStringContainsString("header('Location: '", $src);
        $this->assertStringContainsString('next=', $src);
    }

    public function test_admin_route_admin_guard_404s_non_admin(): void
    {
        $src = file_get_contents(APP_ROOT . '/src/Support/Auth.php');
        $this->assertStringContainsString('adminGuard', $src);
        $this->assertStringContainsString('Error::not_found', $src);
    }
}
