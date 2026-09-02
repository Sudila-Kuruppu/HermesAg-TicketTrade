<?php
/**
 * TicketTrade — Stable Error Code Registry
 *
 * Per AD-16. Stable machine-readable codes; user-facing copy comes from
 * the matching View template. i18n keys can be wired in later.
 */

declare(strict_types=1);

return [
    'E_VALIDATION'          => 'Validation failed.',
    'E_AUTH_INVALID'        => 'Email or password is incorrect.',
    'E_AUTH_BANNED'         => 'This account has been suspended.',
    'E_RATE_LIMIT'          => 'Too many attempts. Try again in 5 minutes.',
    'E_CSRF'                => 'CSRF token mismatch.',
    'E_NOT_FOUND'           => 'Not found.',
    'E_NICKNAME_TAKEN'      => 'Nickname taken. Pick another.',
    'E_AUTH_ALLOWLIST'      => 'Email or student ID not recognized. Check both and try again.',
    'E_TOKEN_INVALID'       => 'Token invalid or expired.',
    'E_PASSWORD_WEAK'       => 'Password must be at least 8 characters.',
    'E_PASSWORD_MISMATCH'   => 'Passwords do not match.',

    // Phase 3 additions (Plan 03-01).
    'E_IMAGE_INVALID'       => 'Image is invalid or failed validation.',
    'E_IMAGE_TOO_LARGE'     => 'Image exceeds the 5MB size limit.',
    'E_LISTING_NOT_FOUND'   => 'Listing not found.',
    'E_LISTING_FORBIDDEN'   => 'You do not have permission to modify this listing.',
    'E_LISTING_REVIEW_FLAG' => 'Edits to active listings are pending admin review.',
    'E_CATEGORY_NOT_FOUND'  => 'Category not found.',

    // Phase 4 additions (Plan 04-01).
    'E_TICKET_NOT_FOUND'      => 'Ticket not found.',
    'E_TICKET_FORBIDDEN'      => 'You do not have permission to access this ticket.',
    'E_TICKET_INVALID_STATE'  => 'Ticket is not in a state that allows this action.',
    'E_TICKET_CODE_COLLISION' => 'Could not generate a unique ticket code. Please retry.',
    'E_TICKET_SELF_PURCHASE'  => 'You cannot buy your own listing.',
    'E_TICKET_NOT_ACTIVE'     => 'Ticket is not active.',
    'E_LISTING_SOLD_OUT'      => 'This listing is sold out.',
    'E_DISPUTE_INVALID_REASON'=> 'Dispute reason is invalid.',
    'E_DISPUTE_TEXT_TOO_LONG' => 'Dispute text must be 200 characters or fewer.',
    'E_POINTS_FROZEN'         => 'Points operations are frozen for this user.',
    'E_POINTS_WRITE'          => 'Could not record points.',
];
