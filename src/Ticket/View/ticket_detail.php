<?php

/**
 * TicketTrade — Ticket\View\ticket_detail
 *
 * Phase 4 Plan 04-01. The optional GET /tickets/{id} detail page
 * (D-05). Renders the ticket-code-block + status badge + listing
 * title + seller info. The dispute action is rendered ONLY when
 * dispute_status='none' AND status IN ('active','redeemed').
 */

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\View;

$vars = $GLOBALS['_tt_view_vars'] ?? [];
$ticket = $vars['ticket'] ?? null;
if ($ticket === null) {
    echo '<p>Ticket not found.</p>';
    return;
}
$status = (string) ($ticket['status'] ?? '');
$disputeStatus = (string) ($ticket['dispute_status'] ?? '');
$canDispute = ($disputeStatus === 'none') && in_array($status, ['active', 'redeemed'], true);
$code = (string) ($ticket['ticket_code'] ?? '');
$listingTitle = (string) ($ticket['listing_title'] ?? '');
$sessionNumber = (int) ($ticket['session_number'] ?? 1);
$totalSessions = (int) ($ticket['total_sessions'] ?? 1);
$ticketId = (int) ($ticket['id'] ?? 0);
$sellerWhatsapp = (string) ($ticket['seller_whatsapp'] ?? '');
$sellerNickname = (string) ($ticket['seller_nickname'] ?? 'unknown');
?>
<main id="main" tabindex="-1" class="container py-4">
  <h1 class="h4 mb-3">Ticket #<?= htmlspecialchars((string) $ticketId, ENT_QUOTES, 'UTF-8') ?></h1>

  <div class="d-flex gap-2 align-items-center mb-3">
    <?php View::partial('status_badge', ['status' => $status]); ?>
    <?php if ($totalSessions > 1) : ?>
        <?php View::partial('session_progress', [
        'session_number' => $sessionNumber,
        'total_sessions' => $totalSessions,
      ]); ?>
    <?php endif; ?>
  </div>

  <p class="body-md mb-3">
    For listing: <strong><?= htmlspecialchars($listingTitle, ENT_QUOTES, 'UTF-8') ?></strong>
  </p>
  <p class="body-sm text-on-surface-variant mb-3">
    Seller: @<?= htmlspecialchars($sellerNickname, ENT_QUOTES, 'UTF-8') ?>
  </p>

  <?php View::partial('ticket_code_block', [
    'code' => $code,
    'seller_whatsapp' => $sellerWhatsapp,
  ]); ?>

  <?php if ($canDispute) : ?>
    <div class="mt-4">
      <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal"
              data-bs-target="#dispute-modal-<?= (int) $ticketId ?>">
        File dispute
      </button>
    </div>
        <?php
        $GLOBALS['_tt_view_vars'] = [
        'ticket_id' => $ticketId,
        'csrf_token' => Csrf::token(),
        ];
        require __DIR__ . '/dispute_modal.php';
        ?>
  <?php endif; ?>
</main>
