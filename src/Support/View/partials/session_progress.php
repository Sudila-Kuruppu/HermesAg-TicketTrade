<?php

/**
 * TicketTrade — Support\View\partials\session_progress
 *
 * Phase 4 Plan 04-01. Renders `N/M` + a horizontal bar fill. Pure
 * CSS; the JS does not touch it.
 *
 * Expected vars: session_number (int), total_sessions (int).
 */

declare(strict_types=1);

$vars = $GLOBALS['_tt_view_vars'] ?? [];
$n = max(0, (int) ($vars['session_number'] ?? 1));
$m = max(1, (int) ($vars['total_sessions'] ?? 1));
$pct = (int) round(($n / $m) * 100);
$pct = max(0, min(100, $pct));
?>
<div class="session-progress" aria-label="Sessions used">
  <span class="session-progress__count"><?= (int) $n ?>/<?= (int) $m ?></span>
  <span class="session-progress__bar" aria-hidden="true">
    <span class="session-progress__fill" style="width: <?= (int) $pct ?>%"></span>
  </span>
</div>
