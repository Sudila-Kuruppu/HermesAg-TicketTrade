<?php

/**
 * TicketTrade — Support\View\partials\review_summary_compact_dispute
 *
 * Phase 5 Plan 05-02. The "· N disputes" suffix used on the listing
 * modal. Per D-09 + the BLOCKER review note: the dispute suffix
 * renders ONLY when dispute_count > 0, and INDEPENDENTLY of the
 * rating fragment. A seller with 0 reviews but 2 upheld disputes
 * still gets the suffix even when the rating fragment renders
 * nothing.
 *
 * Vars:
 *   summary  array  The review_service::getSummaryForUser return value.
 */

declare(strict_types=1);

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$summary = is_array($__vars['summary'] ?? null) ? $__vars['summary'] : [];
$disputeCount = (int) ($summary['dispute_count'] ?? 0);

if ($disputeCount <= 0) {
    return;
}
?>
<span class="review-summary__dispute caption text-on-surface-variant"
      data-testid="listing-modal-dispute">
  &middot; <?= (int) $disputeCount ?> dispute<?= $disputeCount === 1 ? '' : 's' ?>
</span>