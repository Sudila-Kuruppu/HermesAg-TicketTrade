<?php

/**
 * TicketTrade — Support\View\partials\velocity_flag_pill
 *
 * Phase 6 Plan 06-01. Single small "Earning paused — admin review"
 * pill on Profile when users.points_frozen=TRUE (D-02 — gentler
 * variant of UX-DR-16).
 *
 * Renders ONLY when $isFrozen === true. Pill links nowhere in
 * Phase 6 (admin Phase 7/8 wires the resolution flow).
 *
 * Reads from $GLOBALS['_tt_view_vars']:
 *   @var bool $isFrozen
 */

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$isFrozen = (bool) ($__vars['isFrozen'] ?? false);
if (!$isFrozen) {
    return;
}
$copy = 'Earning paused — admin review';
?>
<span
  class="velocity-flag-pill"
  data-component="velocity-flag-pill"
  aria-label="<?= htmlspecialchars($copy, ENT_QUOTES, 'UTF-8') ?>"
><?= htmlspecialchars($copy, ENT_QUOTES, 'UTF-8') ?></span>