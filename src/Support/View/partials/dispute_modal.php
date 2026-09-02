<?php

/**
 * TicketTrade — Support\View\partipr dispute_modal wrapper
 *
 * The full dispute modal lives at src/Ticket/View/dispute_modal.php
 * (Phase 4 Plan 04-01). The My Tickets View calls this partial,
 * which sets the expected view vars and requires the modal file.
 */

declare(strict_types=1);

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$ticketId = (int) ($__vars['ticket_id'] ?? 0);
$csrfToken = (string) ($__vars['csrf_token'] ?? \App\Support\Csrf::token());

$GLOBALS['_tt_view_vars'] = [
    'ticket_id' => $ticketId,
    'csrf_token' => $csrfToken,
];
require __DIR__ . '/../../../Ticket/View/dispute_modal.php';
