<?php

/**
 * TicketTrade — Review/View/review_modal
 *
 * Phase 5 Plan 05-01. Self-contained review modal body. Includes:
 *   - Star rating input via the shared `star_rating_input` partial.
 *   - <textarea name="comment" maxlength="2000"> with a live char
 *     counter (data-review-counter) updated by the JS handler.
 *   - Submit button `disabled` until a rating is selected (CSS-only
 *     via :checked).
 *   - data-scrim-guard="2" matches the Phase 4 dispute modal pattern.
 *
 * Variables (set via $GLOBALS['_tt_view_vars']):
 *   ticket_id   - int, the ticket this modal is reviewing
 *   csrf_token  - string, the per-session CSRF token
 */

declare(strict_types=1);

$vars = $GLOBALS['_tt_view_vars'] ?? [];
$ticketId = (int) ($vars['ticket_id'] ?? 0);
$csrfToken = (string) ($vars['csrf_token'] ?? \App\Support\Csrf::token());
$commentMaxChars = (int) \App\Review\Service\review_service::COMMENT_MAX_CHARS;
?>
<div class="modal fade" id="review-modal-<?= (int) $ticketId ?>" tabindex="-1"
     aria-labelledby="review-modal-title-<?= (int) $ticketId ?>" aria-hidden="true"
     data-scrim-guard="2" data-component="review-modal">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="/tickets/<?= (int) $ticketId ?>/review" data-review-form>
        <div class="modal-header">
          <h5 class="modal-title" id="review-modal-title-<?= (int) $ticketId ?>">Leave a review</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
          <?= '' /* Star input partial below; no echo prefix needed */ ?>
          <?php \App\Support\View::partial('star_rating_input', [
              'name'         => 'rating',
              'current_value' => 0,
              'unique_id'    => (string) $ticketId,
          ]); ?>

          <label class="form-label mt-3" for="review-comment-<?= (int) $ticketId ?>">
            Comment (optional)
            <span class="form-text text-on-surface-variant">
              (<span data-review-counter><?= (int) $commentMaxChars ?></span> chars remaining)
            </span>
          </label>
          <textarea
            class="form-control"
            id="review-comment-<?= (int) $ticketId ?>"
            name="comment"
            maxlength="<?= (int) $commentMaxChars ?>"
            data-review-text
            placeholder="Share details (50+ chars to earn +10 points)"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" data-review-submit disabled>Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>