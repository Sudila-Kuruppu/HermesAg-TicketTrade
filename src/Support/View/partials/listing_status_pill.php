<?php

/**
 * TicketTrade — Support\View\partials\listing_status_pill
 *
 * Maps a listing status to a Bootstrap pill per CONTEXT specifics.
 * The review_flag adds an inline "Under review" badge so the seller
 * sees their edit is queued for admin review (D-09).
 *
 * Usage:
 *   <?= \App\Support\View::partial('listing_status_pill', [
 *       'status' => $row['status'],
 *       'review_flag' => $row['review_flag'],
 *   ]) ?>
 */

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$status = (string) ($__vars['status'] ?? 'draft');
$reviewFlag = !empty($__vars['review_flag']);

$labels = [
    'draft' => 'Draft',
    'pending' => 'Pending',
    'active' => 'Active',
    'rejected' => 'Rejected',
    'sold' => 'Sold',
    'removed' => 'Removed',
];

$classes = [
    'draft' => 'surface-container-high text-on-surface-variant listing-status-pill--dashed',
    'pending' => 'bg-warning text-dark',
    'active' => 'bg-success',
    'rejected' => 'bg-error-fill text-on-error',
    'sold' => 'surface-container-high text-on-surface-variant',
    'removed' => 'bg-error-fill text-on-error',
];

$label = $labels[$status] ?? ucfirst($status);
$class = $classes[$status] ?? 'surface-container-high text-on-surface-variant';
?>
<span class="badge <?= htmlspecialchars($class, ENT_QUOTES, 'UTF-8') ?> listing-status-pill" data-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
</span>
<?php if ($reviewFlag) : ?>
<span class="badge bg-warning text-dark ms-1" aria-label="Edits pending admin review" title="Edits pending admin review">Under review</span>
<?php endif; ?>
