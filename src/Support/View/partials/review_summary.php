<?php

/**
 * TicketTrade — Support\View\partials\review_summary
 *
 * Phase 5 Plan 05-02. The full + compact rating summary used on the
 * public profile stats row. Per D-09 + the BLOCKER review note:
 *
 *   - Default full variant renders the full distribution + dispute count.
 *     Used on the public profile stats row.
 *   - Compact fragments live in their own partials
 *     (review_summary_compact_rating, review_summary_compact_dispute).
 *     They are gated INDEPENDENTLY so the listing modal can show
 *     "· N disputes" even when rating_count === 0.
 *
 * Vars (full variant):
 *   summary  array  The review_service::getSummaryForUser return value:
 *                   ['rating_avg' => float, 'rating_count' => int,
 *                    'rating_distribution' => [1..5 => int],
 *                    'dispute_count' => int]
 *   prefix   string Optional override for the data-testid prefix.
 */

declare(strict_types=1);

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$summary = is_array($__vars['summary'] ?? null) ? $__vars['summary'] : [];
$prefix = (string) ($__vars['prefix'] ?? 'public-profile');
$showDispute = array_key_exists('show_dispute', $__vars)
    ? (bool) $__vars['show_dispute']
    : true;

$ratingAvg = (float) ($summary['rating_avg'] ?? 0.0);
$ratingCount = (int) ($summary['rating_count'] ?? 0);
$distribution = is_array($summary['rating_distribution'] ?? null)
    ? $summary['rating_distribution']
    : [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$disputeCount = (int) ($summary['dispute_count'] ?? 0);
?>
<div class="review-summary" data-testid="<?= htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8') ?>-reviews">
  <div class="review-summary__avg">
    <?php if ($ratingCount > 0) : ?>
      <span aria-hidden="true">&#9733;</span>
      <strong><?= htmlspecialchars(number_format($ratingAvg, 1), ENT_QUOTES, 'UTF-8') ?></strong>
    <?php else : ?>
      <span class="text-on-surface-variant">&mdash;</span>
    <?php endif; ?>
  </div>
  <div class="review-summary__count caption text-on-surface-variant">
    <?php if ($ratingCount > 0) : ?>
      (<?= (int) $ratingCount ?> <?= $ratingCount === 1 ? 'review' : 'reviews' ?>)
    <?php else : ?>
      No reviews yet
    <?php endif; ?>
  </div>
<?php if ($ratingCount > 0) : ?>
    <ul class="review-summary__distribution list-unstyled mb-2" aria-label="Rating distribution">
      <?php for ($bucket = 5; $bucket >= 1; $bucket--) :
            $bucketCount = (int) ($distribution[$bucket] ?? 0);
            $pct = $ratingCount > 0 ? (int) round(($bucketCount / $ratingCount) * 100) : 0;
            ?>
        <li class="review-summary__distribution-row d-flex-row">
          <span class="review-summary__distribution-label">
            <?= (int) $bucket ?> star<?= $bucket === 1 ? '' : 's' ?>
          </span>
          <span class="review-summary__distribution-bar" aria-hidden="true">
            <span class="review-summary__distribution-fill"
                  style="width: <?= (int) $pct ?>%;"></span>
          </span>
          <span class="review-summary__distribution-count"><?= (int) $bucketCount ?></span>
        </li>
      <?php endfor; ?>
    </ul>
<?php endif; ?>
  <?php if ($showDispute) : ?>
    <div class="review-summary__dispute caption text-on-surface-variant"
         data-testid="<?= htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8') ?>-disputes">
        <?php if ($disputeCount === 0) : ?>
        0 disputes
        <?php else : ?>
            <?= (int) $disputeCount ?> disputes on record
        <?php endif; ?>
    </div>
  <?php endif; ?>
</div>