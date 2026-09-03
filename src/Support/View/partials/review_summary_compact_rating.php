<?php

/**
 * TicketTrade — Support\View\partials\review_summary_compact_rating
 *
 * Phase 5 Plan 05-02. The compact "★ 4.8 (23 reviews)" row used on
 * the listing modal between the seller nickname and the tier badge.
 * Per D-09 + the BLOCKER review note: the rating row renders ONLY
 * when rating_count > 0 (no row at all when 0 — the listing modal
 * is information-dense; absence is signal).
 *
 * Independent of the dispute fragment — a seller with 0 reviews but
 * 2 upheld disputes still gets the "· 2 disputes" suffix rendered by
 * the dispute fragment, even though the rating fragment renders
 * nothing.
 *
 * Vars:
 *   summary  array  The review_service::getSummaryForUser return value.
 */

declare(strict_types=1);

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$summary = is_array($__vars['summary'] ?? null) ? $__vars['summary'] : [];
$ratingAvg = (float) ($summary['rating_avg'] ?? 0.0);
$ratingCount = (int) ($summary['rating_count'] ?? 0);

if ($ratingCount <= 0) {
    return;
}
?>
<span class="review-summary review-summary--compact"
      data-testid="listing-modal-rating">
  <span aria-hidden="true">&#9733;</span>
  <strong><?= htmlspecialchars(number_format($ratingAvg, 1), ENT_QUOTES, 'UTF-8') ?></strong>
  <span class="caption">(<?= (int) $ratingCount ?> <?= $ratingCount === 1 ? 'review' : 'reviews' ?>)</span>
</span>