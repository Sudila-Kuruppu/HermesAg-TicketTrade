<?php
/**
 * TicketTrade — Rate Limit Configuration
 *
 * Per D-12 + D-13 + AD-13. The window is fixed per route (no sliding
 * window). Support\RateLimit::hit() reads this.
 */

declare(strict_types=1);

return [
    'login' => ['max' => 5, 'window_minutes' => 5],
    'register' => ['max' => 5, 'window_minutes' => 60],
    'forgot_password' => ['max' => 3, 'window_minutes' => 60],
    'profile_edit' => ['max' => 30, 'window_minutes' => 60],
];
