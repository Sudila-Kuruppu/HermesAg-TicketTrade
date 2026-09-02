<?php
/**
 * Phase 4 — RouteGuardTicketTest
 *
 * Verifies the auth-required route guards for the Phase 4 ticket
 * surfaces:
 *   - GET /my-tickets  → auth=true
 *   - GET /sales       → auth=true
 *   - GET /purchases   → auth=true
 *
 * Plus the Action-level 302 redirect source: each Phase 4 Action
 * has a handle() that issues a 302 to /login?next=<path> when
 * $GLOBALS['current_user'] is null. We verify by reading the
 * source file directly (the redirect pattern is a contract).
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase04\Support;

use App\Tests\Integration\Phase04\Fixtures\Fixtures;

class RouteGuardTicketTest extends Fixtures
{
    public function test_my_tickets_route_requires_auth(): void
    {
        $routes = require APP_ROOT . '/config/routes.php';
        $opts = $routes['GET /my-tickets'][2];
        $this->assertTrue($opts['auth']);
        $this->assertFalse($opts['csrf']);
    }

    public function test_sales_route_requires_auth(): void
    {
        $routes = require APP_ROOT . '/config/routes.php';
        $opts = $routes['GET /sales'][2];
        $this->assertTrue($opts['auth']);
        $this->assertFalse($opts['csrf']);
    }

    public function test_purchases_route_requires_auth(): void
    {
        $routes = require APP_ROOT . '/config/routes.php';
        $opts = $routes['GET /purchases'][2];
        $this->assertTrue($opts['auth']);
        $this->assertFalse($opts['csrf']);
    }

    public function test_my_tickets_action_redirects_guests_to_login(): void
    {
        $src = file_get_contents(APP_ROOT . '/src/Ticket/Action/MyTicketsAction.php');
        $this->assertStringContainsString("'Location: /login?next=/my-tickets'", $src);
    }

    public function test_sales_action_redirects_guests_to_login(): void
    {
        $src = file_get_contents(APP_ROOT . '/src/Ticket/Action/SalesAction.php');
        $this->assertStringContainsString("'Location: /login?next=/sales'", $src);
    }

    public function test_purchases_action_redirects_guests_to_login(): void
    {
        $src = file_get_contents(APP_ROOT . '/src/Ticket/Action/PurchasesAction.php');
        $this->assertStringContainsString("'Location: /login?next=/purchases'", $src);
    }
}
