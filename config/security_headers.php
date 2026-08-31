<?php
/**
 * TicketTrade — Security Headers Configuration
 *
 * Per D-20 + D-21. The CSP string keeps `unsafe-inline` for script-src
 * to allow the Phase 1 FOUC-guard inline script. Future hardening
 * (nonces or an external FOUC-guard file) is deferred.
 *
 * Support\ResponseHeaders::boot() reads this and sets the four
 * AD-13 headers plus any extras.
 */

declare(strict_types=1);

return [
    'csp' => "default-src 'self'; script-src 'self' cdn.jsdelivr.net 'unsafe-inline'; style-src 'self' cdn.jsdelivr.net 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'",
    'extra' => [],
];
