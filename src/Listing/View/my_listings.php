<?php

/**
 * TicketTrade — Listing\View\my_listings
 *
 * Phase 3 Plan 03-02. The seller dashboard. Tabs at the top, the
 * filtered list (3-column rows) below. Per-state empty-state copy
 * from EXPERIENCE.md.
 *
 * Vars from MyListingsAction:
 *   csrf_token (string)
 *   tab        (string)  active|pending|sold|draft
 *   counts     (array)   4 status counts
 *   rows       (array)   Filtered listing rows
 */

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$csrfToken = (string) ($__vars['csrf_token'] ?? '');
$tab = (string) ($__vars['tab'] ?? 'active');
$counts = $__vars['counts'] ?? [];
$rows = $__vars['rows'] ?? [];

$emptyCopy = [
    'active' => [
        'title' => 'No active listings yet',
        'body' => 'Your first listing is one click away',
        'cta_label' => 'Create your first listing',
        'cta_href' => '/listings/create',
    ],
    'pending' => [
        'title' => 'No pending listings',
        'body' => 'All your listings have been reviewed',
        'cta_label' => '',
        'cta_href' => '',
    ],
    'sold' => [
        'title' => 'No sold listings yet',
        'body' => 'When a buyer redeems a ticket, it lands here',
        'cta_label' => '',
        'cta_href' => '',
    ],
    'draft' => [
        'title' => 'Submit a draft to make it live',
        'body' => 'Drafts are private to you until you submit them for review',
        'cta_label' => '',
        'cta_href' => '',
    ],
];

$copy = $emptyCopy[$tab] ?? $emptyCopy['active'];

function actionButtons(array $row, string $tab, string $csrfToken): void {
    $id = (int) ($row['id'] ?? 0);
    $status = (string) ($row['status'] ?? '');
    $reviewFlag = !empty($row['review_flag']);
    ?>
    <div class="d-flex gap-2 align-items-center">
    <?php if (in_array($status, ['active', 'pending', 'draft', 'rejected'], true)) : ?>
    <a href="/listings/<?= $id ?>/edit" class="btn btn-outline-secondary btn-sm">Edit</a>
    <?php endif; ?>
    <?php if (in_array($status, ['active', 'pending', 'rejected'], true)) : ?>
    <form method="POST" action="/listings/<?= $id ?>/delete" class="d-inline" onsubmit="return confirm('Remove this listing?');">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
    </form>
    <?php endif; ?>
    <?php if ($status === 'sold') : ?>
    <form method="POST" action="/listings/<?= $id ?>/relist" class="d-inline">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit" class="btn btn-primary btn-sm">Relist</button>
    </form>
    <?php endif; ?>
    <?php if ($status === 'draft') : ?>
    <form method="POST" action="/listings/<?= $id ?>/submit" class="d-inline">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit" class="btn btn-primary btn-sm">Submit</button>
    </form>
    <?php endif; ?>
    </div>
    <?php
}
?>
<section class="container py-4 my-listings-shell">
<h1 class="headline-md mb-4">My listings</h1>

<?= \App\Support\View::partial('seller_dashboard_tabs', ['tab' => $tab, 'counts' => $counts, 'csrf_token' => $csrfToken]) ?>

<?php if (empty($rows)) : ?>
<?= \App\Support\View::partial('empty_state', $copy) ?>
<?php else : ?>
<ul class="list-group my-listings-list">
<?php foreach ($rows as $row) :
    $id = (int) ($row['id'] ?? 0);
    $title = (string) ($row['title'] ?? '');
    $priceCents = (int) ($row['price_cents'] ?? 0);
    $priceLkr = number_format($priceCents / 100, 2);
    $quantity = (int) ($row['quantity'] ?? 1);
    $quantitySold = (int) ($row['quantity_sold'] ?? 0);
    $status = (string) ($row['status'] ?? '');
    $reviewFlag = !empty($row['review_flag']);
    ?>
<li class="list-group-item d-flex align-items-center gap-3 my-listings-row" data-listing-id="<?= $id ?>">
<img src="/img/<?= $id ?>/thumb" class="rounded flex-shrink-0" width="64" height="64" alt="">
<div class="flex-grow-1">
<h2 class="h5 mb-1"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
<div class="d-flex gap-3 text-muted small align-items-center flex-wrap">
<span>LKR <?= htmlspecialchars($priceLkr, ENT_QUOTES, 'UTF-8') ?></span>
<?= \App\Support\View::partial('listing_status_pill', ['status' => $status, 'review_flag' => $reviewFlag]) ?>
<span><?= $quantitySold ?> / <?= $quantity ?> sold</span>
</div>
</div>
<?php actionButtons($row, $tab, $csrfToken); ?>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</section>
