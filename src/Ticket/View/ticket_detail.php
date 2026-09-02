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
    <?php \App\Support\View::partial('status_badge', ['status' => $status]); ?>
    <?php if ($totalSessions > 1): ?>
      <?php \App\Support\View::partial('session_progress', [
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

  <?php \App\Support\View::partial('ticket_code_block', [
    'code' => $code,
    'seller_whatsapp' => $sellerWhatsapp,
  ]); ?>

  <?php if ($canDispute): ?>
    <div class="mt-4">
      <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#disputeModal">
        File dispute
      </button>
    </div>
    <?php \App\Ticket\View\dispute_modal_stub($ticketId); ?>
  <?php endif; ?>
</main>
<?php

/**
 * Helper: inline modal stub so we don't need a separate file. Real
 * production deploys include the dispute_modal.php from the View
 * directory directly.
 */
function dispute_modal_stub(int $ticketId): void {
    ?>
    <div class="modal fade" id="disputeModal" tabindex="-1" data-scrim-guard="2">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST" action="/tickets/<?= (int) $ticketId ?>/dispute">
            <div class="modal-header">
              <h5 class="modal-title">File dispute</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Support\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
              <label class="form-label" for="dispute-reason-<?= (int) $ticketId ?>">Reason</label>
              <select class="form-select" id="dispute-reason-<?= (int) $ticketId ?>" name="reason" required>
                <option value="seller_unresponsive">Seller unresponsive</option>
                <option value="item_not_as_described">Item not as described</option>
                <option value="buyer_unresponsive">Buyer unresponsive</option>
                <option value="other">Other</option>
              </select>
              <label class="form-label mt-3" for="dispute-text-<?= (int) $ticketId ?>">Details (max 200 chars)</label>
              <textarea class="form-control" id="dispute-text-<?= (int) $ticketId ?>" name="text" maxlength="200" required></textarea>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-danger">Submit</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php
}
