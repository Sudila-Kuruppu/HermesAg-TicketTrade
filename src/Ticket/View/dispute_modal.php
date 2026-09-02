<?php

/**
 * TicketTrade — Ticket\View\dispute_modal
 *
 * Phase 4 Plan 04-01. Self-contained dispute modal (D-03). 4-value
 * reason dropdown + 200-char text + Cancel/Submit. data-scrim-guard
 * suppresses backdrop clicks for 2 seconds after open.
 */

declare(strict_types=1);

$vars = $GLOBALS['_tt_view_vars'] ?? [];
$ticketId = (int) ($vars['ticket_id'] ?? 0);
$csrfToken = (string) ($vars['csrf_token'] ?? \App\Support\Csrf::token());
?>
<div class="modal fade" id="dispute-modal-<?= (int) $ticketId ?>" tabindex="-1" aria-labelledby="dispute-modal-title-<?= (int) $ticketId ?>" aria-hidden="true" data-scrim-guard="2">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="/tickets/<?= (int) $ticketId ?>/dispute" data-dispute-form>
        <div class="modal-header">
          <h5 class="modal-title" id="dispute-modal-title-<?= (int) $ticketId ?>">File dispute</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
          <label class="form-label" for="dispute-reason-<?= (int) $ticketId ?>">Reason</label>
          <select class="form-select" id="dispute-reason-<?= (int) $ticketId ?>" name="reason" required data-dispute-reason>
            <option value="">Select reason</option>
            <option value="seller_unresponsive">Seller unresponsive</option>
            <option value="item_not_as_described">Item not as described</option>
            <option value="buyer_unresponsive">Buyer unresponsive</option>
            <option value="other">Other</option>
          </select>
          <label class="form-label mt-3" for="dispute-text-<?= (int) $ticketId ?>">
            Details
            <span class="form-text text-on-surface-variant">
              (<span data-dispute-counter><?= (int) ticket_service::DISPUTE_TEXT_MAX ?></span> chars remaining)
            </span>
          </label>
          <textarea
            class="form-control"
            id="dispute-text-<?= (int) $ticketId ?>"
            name="text"
            maxlength="<?= (int) ticket_service::DISPUTE_TEXT_MAX ?>"
            minlength="1"
            required
            data-dispute-text
          ></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger" data-dispute-submit disabled>Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>
