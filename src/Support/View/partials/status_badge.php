<?php

/**
 * TicketTrade — Support\View\partials\status_badge
 *
 * Phase 4 Plan 04-01. Renders the status-badge markup. The CSS
 * class `.status-badge.status-{active|redeemed|expired|disputed}`
 * ships from Phase 1 / Plan 01-02 (CSS rules in
 * tickettrade.components.css).
 *
 * Expected vars: status (string).
 */

declare(strict_types=1);

$vars = $GLOBALS['_tt_view_vars'] ?? [];
$status = (string) ($vars['status'] ?? 'active');
$label = [
    'active' => 'Active',
    'redeemed' => 'Redeemed',
    'expired' => 'Expired',
    'disputed' => 'Disputed',
][$status] ?? ucfirst($status);
?>
<span class="status-badge status-<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" role="status">
  <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
</span>
