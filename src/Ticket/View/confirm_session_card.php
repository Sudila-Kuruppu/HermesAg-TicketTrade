<?php

/**
 * TicketTrade — Ticket\View\confirm_session_card
 *
 * Phase 4 Plan 04-01. The inline card rendered next to the
 * in-progress service ticket on the Sales page (D-05). Posts to
 * /tickets/{id}/confirm-session.
 */

declare(strict_types=1);

$vars = $GLOBALS['_tt_view_vars'] ?? [];
$ticket = $vars['ticket'] ?? [];
$csrfToken = (string) ($vars['csrf_token'] ?? \App\Support\Csrf::token());
$ticketId = (int) ($ticket['id'] ?? 0);
if ($ticketId <= 0) {
    return;
}
$sessionNumber = (int) ($ticket['session_number'] ?? 1);
$totalSessions = (int) ($ticket['total_sessions'] ?? 1);
$isLastSession = ($sessionNumber >= $totalSessions);
$buttonLabel = $isLastSession ? 'Confirm final session' : 'Confirm next session';
?>
<div class="card confirm-session-card mb-2" data-ticket-id="<?= (int) $ticketId ?>">
  <div class="card-body d-flex gap-2 align-items-center">
    <?php \App\Support\View::partial('session_progress', [
      'session_number' => $sessionNumber,
      'total_sessions' => $totalSessions,
    ]); ?>
    <form method="POST" action="/tickets/<?= (int) $ticketId ?>/confirm-session" class="ms-auto">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <button type="submit" class="btn btn-primary"><?= htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8') ?></button>
    </form>
  </div>
</div>
