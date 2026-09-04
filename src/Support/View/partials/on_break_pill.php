<?php

/**
 * TicketTrade — Support\View\partials\on_break_pill
 *
 * Phase 6 Plan 06-01. Small pill rendered next to a rank badge when
 * the user has been inactive for 14+ days (D-03 / EXPERIENCE.md L153).
 *
 * Renders ONLY when $lastActiveAt is provided and (NOW() - lastActiveAt)
 * >= 14 days. The pill itself is conditional; the partial caller is
 * expected to wrap the surrounding rank badge in a `.on-break` modifier
 * container (the grayed effect — opacity 0.6, grayscale 0.8 — applies
 * to the wrapped rank badge per 06-UI-SPEC.md).
 *
 * Tooltip text per EXPERIENCE.md L153 / 06-UI-SPEC.md Copywriting
 * Contract: "Inactive 14+ days — next action restores full badge".
 *
 * Reads from $GLOBALS['_tt_view_vars']:
 *   @var DateTimeInterface|string|null $lastActiveAt
 */

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$lastActiveAt = $__vars['lastActiveAt'] ?? null;
if ($lastActiveAt === null) {
    return; // no signal — partial renders nothing.
}
try {
    if ($lastActiveAt instanceof \DateTimeInterface) {
        $la = $lastActiveAt;
    } else {
        $la = new \DateTime((string) $lastActiveAt, new \DateTimeZone('Asia/Colombo'));
    }
    $now = new \DateTime('now', new \DateTimeZone('Asia/Colombo'));
    $diffDays = (int) floor(($now->getTimestamp() - $la->getTimestamp()) / 86400);
} catch (\Throwable $e) {
    return; // bad input — render nothing.
}
if ($diffDays < 14) {
    return; // active — no pill.
}
$tooltip = 'Inactive 14+ days — next action restores full badge';
?>
<span
  class="on-break-pill"
  data-component="on-break-pill"
  data-bs-toggle="tooltip"
  title="<?= htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') ?>"
  aria-label="<?= htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') ?>"
>On Break</span>