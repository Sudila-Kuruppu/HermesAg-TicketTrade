<?php

/**
 * TicketTrade — Support\View\partials\tier_progress
 *
 * Phase 6 Plan 06-01. Horizontal tier progress bar for the Profile
 * page. 8px tall, rounded-full, track var(--color-surface-container),
 * fill uses var(--color-rank-{tier}). Tooltip text per 06-CONTEXT.md
 * D-15 / 06-UI-SPEC.md Copywriting Contract:
 *   E..A: "{X} of {Y} toward {next tier name}" — e.g. "60 of 100
 *         toward Operative (C)". X = points - currentTierMin,
 *         Y = nextTierMin - currentTierMin.
 *   S:   "Top tier reached" + 100% fill.
 *
 * Data component attribute `data-component="tier-progress"` wires the
 * Bootstrap tooltip via the existing stock `data-bs-toggle="tooltip"`
 * hook (no extra JS needed beyond the Phase 1 stock init).
 *
 * Reads from $GLOBALS['_tt_view_vars'] (View::partial convention).
 * Variables:
 *   @var int      $userId
 *   @var int      $points
 *   @var string   $tier   Tier letter in [E, D, C, B, A, S]
 */

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$userId = isset($__vars['userId']) ? (int) $__vars['userId'] : 0;
$points = isset($__vars['points']) ? (int) $__vars['points'] : 0;
$tier = isset($__vars['tier']) ? (string) $__vars['tier'] : 'E';

$ladder = require APP_ROOT . '/config/ranks.php';
$tier = isset($ladder[$tier]) ? $tier : 'E';
$tierOrder = array_keys($ladder);
$tierIdx = array_search($tier, $tierOrder, true);
$currentMin = (int) $ladder[$tier]['min_points'];
$nextTier = $tierOrder[$tierIdx + 1] ?? null;
if ($nextTier === null) {
    // Top tier (S) — full bar, "Top tier reached".
    $fillPct = 100;
    $tooltipText = 'Top tier reached';
} else {
    $nextMin = (int) $ladder[$nextTier]['min_points'];
    $span = max(1, $nextMin - $currentMin); // avoid div-by-zero
    $x = max(0, $points - $currentMin);
    $y = $span;
    $fillPct = (int) min(100, max(0, (int) round(($x / $span) * 100)));
    $nextName = $ladder[$nextTier]['name'];
    $tooltipText = sprintf('%d of %d toward %s (%s)', $x, $y, $nextName, $nextTier);
}
?>
<div class="tier-progress" role="group" aria-label="Tier progress">
  <div
    class="tier-progress__fill tier-progress__fill--<?= htmlspecialchars($tier, ENT_QUOTES, 'UTF-8') ?>"
    style="width: <?= $fillPct ?>%;"
    data-component="tier-progress"
    data-bs-toggle="tooltip"
    title="<?= htmlspecialchars($tooltipText, ENT_QUOTES, 'UTF-8') ?>"
    aria-label="<?= htmlspecialchars($tooltipText, ENT_QUOTES, 'UTF-8') ?>"
  ></div>
</div>