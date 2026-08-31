<?php
/**
 * TicketTrade — Reserved Nicknames
 *
 * Hardcoded starting set for anti-squatting of privileged/system terms.
 * Phase 8's admin UI extends this list. auth_service::register() reads
 * this and rejects any case-insensitive match.
 */

declare(strict_types=1);

return [
    'admin',
    'nsbm',
    'support',
    'system',
    'root',
    'moderator',
    'mod',
    'staff',
    'faculty',
    'help',
];
