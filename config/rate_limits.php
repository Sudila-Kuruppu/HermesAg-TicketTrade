<?php
/**
 * TicketTrade — Rate Limit Configuration
 *
 * Per D-12 + D-13 + AD-13. The window is fixed per route (no sliding
 * window). Support\RateLimit::hit() reads this.
 *
 * Phase 3 adds:
 *   - listing_create (20/hr/user) per CONTEXT D-09
 *   - admin_cron (5/min/IP) per CONTEXT D-30
 *   - img_thumb (60/min/IP) per CONTEXT D-14 + AD-14 (thumb+medium share)
 *   - img_full (30/min/user) per CONTEXT D-14 + AD-14 (full-size auth-gated)
 *
 * Phase 4 ADDS:
 *   - purchase (10/hr/user) per NFR-SEC-007 (Plan 04-01)
 *   - redemption (5/hr/(ticket+user)) per NFR-SEC-007 (Plan 04-01)
 */

declare(strict_types=1);

return [
    'login' => ['max' => 5, 'window_minutes' => 5],
    'register' => ['max' => 5, 'window_minutes' => 60],
    'forgot_password' => ['max' => 3, 'window_minutes' => 60],
    'profile_edit' => ['max' => 30, 'window_minutes' => 60],
    'listing_create' => ['max' => 20, 'window_minutes' => 60],
    'admin_cron' => ['max' => 5, 'window_minutes' => 1],
    'img_thumb' => ['max' => 60, 'window_minutes' => 1],
    'img_full' => ['max' => 30, 'window_minutes' => 1],
    'purchase' => ['max' => 10, 'window_minutes' => 60],
    'redemption' => ['max' => 5, 'window_minutes' => 60],
];
