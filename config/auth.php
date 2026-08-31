<?php
/**
 * TicketTrade — Auth Configuration
 *
 * Per D-04 + AD-18:
 *   - bcrypt cost >= 12 (sole writer: Auth/Service/auth_service.php)
 *   - 7-day session lifetime, refresh-on-activity
 *   - 5-minute idempotency window for sessions.last_seen UPDATE
 */

declare(strict_types=1);

return [
    'bcrypt_cost' => 12,
    'session_lifetime_seconds' => 7 * 24 * 60 * 60,
    'last_seen_idempotency_seconds' => 5 * 60,
];
