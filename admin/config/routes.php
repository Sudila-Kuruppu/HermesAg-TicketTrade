<?php
/**
 * TicketTrade — Admin Route Map
 *
 * Phase 2 ships a minimal admin route map: every admin/* path that
 * the user might hit (including /admin/users) is registered with
 * admin => true so Support\Auth::adminGuard() 404s non-admin access
 * (D-10, AD-14 — same page as unknown routes). Phase 8 populates the
 * real admin surface.
 */

declare(strict_types=1);

return [
    'GET /admin'              => ['App\Admin\Action\DashboardAction', 'handle', ['auth' => true, 'admin' => true, 'csrf' => false, 'rate_limit' => null]],
    'GET /admin/users'        => ['App\Admin\Action\UsersAction', 'handle', ['auth' => true, 'admin' => true, 'csrf' => false, 'rate_limit' => null]],
    'GET /admin/listings'     => ['App\Admin\Action\ListingsAction', 'handle', ['auth' => true, 'admin' => true, 'csrf' => false, 'rate_limit' => null]],
    'GET /admin/reports'      => ['App\Admin\Action\ReportsAction', 'handle', ['auth' => true, 'admin' => true, 'csrf' => false, 'rate_limit' => null]],
    'GET /admin/cron'         => ['App\Admin\Action\CronAction', 'handle', ['auth' => true, 'admin' => true, 'csrf' => false, 'rate_limit' => null]],
    'GET /admin/audit'        => ['App\Admin\Action\AuditAction', 'handle', ['auth' => true, 'admin' => true, 'csrf' => false, 'rate_limit' => null]],
    'POST /admin/cron/ticket-expiry' => ['App\Admin\Action\CronAction', 'handle', ['auth' => true, 'admin' => true, 'csrf' => true, 'rate_limit' => 'admin_cron']],
    'POST /admin/cron/daily' => ['App\Admin\Action\CronAction', 'handleDaily', ['auth' => true, 'admin' => true, 'csrf' => true, 'rate_limit' => 'admin_cron']],
];
