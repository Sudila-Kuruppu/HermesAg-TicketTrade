<?php
/**
 * TicketTrade — Support\View\partials\listing_card
 *
 * Plain-grid Bootstrap card used in:
 *   - the list-view toggle of the board (Phase 3 Plan 03-03)
 *   - My Listings (Phase 3 Plan 03-02)
 *
 * Visual: standard Bootstrap card surface, no rotation, used in dense
 * listings. The corkboard variant is listing_card_cork.
 *
 * Expected keys on $listing:
 *   id, title, price_cents, primary_sha256 (or NULL),
 *   seller_nickname, seller_tier, seller_is_verified,
 *   status, review_flag, quantity, quantity_sold, created_at
 */

$vars = $GLOBALS['_tt_view_vars'] ?? [];
$listing = $vars['listing'] ?? [];
$id = (int) ($listing['id'] ?? 0);
$title = (string) ($listing['title'] ?? '');
$priceCents = (int) ($listing['price_cents'] ?? 0);
$status = (string) ($listing['status'] ?? 'draft');
$reviewFlag = (bool) ($listing['review_flag'] ?? false);
$quantity = (int) ($listing['quantity'] ?? 1);
$quantitySold = (int) ($listing['quantity_sold'] ?? 0);
$remaining = max(0, $quantity - $quantitySold);
$sellerNickname = (string) ($listing['seller_nickname'] ?? '');
$sellerTier = (string) ($listing['seller_tier'] ?? 'E');
$sellerVerified = (bool) ($listing['seller_is_verified'] ?? false);
$primarySha = (string) ($listing['primary_sha256'] ?? '');

$priceStr = number_format($priceCents / 100, 2);
$statusLabel = [
    'draft' => 'Draft',
    'pending' => 'Pending',
    'active' => 'Active',
    'rejected' => 'Rejected',
    'sold' => 'Sold',
    'removed' => 'Removed',
][$status] ?? ucfirst($status);
$statusClass = [
    'draft' => 'surface-container-high',
    'pending' => 'bg-warning text-dark',
    'active' => 'bg-success',
    'rejected' => 'bg-danger',
    'sold' => 'surface-container-high',
    'removed' => 'bg-secondary',
][$status] ?? 'bg-secondary';
?>
<article class="card listing-card listing-card--plain h-100" data-listing-id="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>">
  <?php if ($primarySha !== '') : ?>
    <img src="/img/<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>/thumb" class="card-img-top" alt="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
  <?php else : ?>
    <div class="card-img-top bg-surface-container-high d-flex align align-items-center justify-content-center" style="height: 160px;">
      <span class="text-on-surface-variant body-sm">No image</span>
    </div>
  <?php endif; ?>
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start mb-2">
      <span class="badge <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span>
      <?php if ($reviewFlag) : ?>
        <span class="badge bg-warning text-dark" aria-label="Edits pending admin review">Under review</span>
      <?php endif; ?>
    </div>
    <h2 class="h6 mb-2"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
    <p class="body-md fw-semibold mb-1">LKR <?= htmlspecialchars($priceStr, ENT_QUOTES, 'UTF-8') ?></p>
    <p class="body-sm text-on-surface-variant mb-2">
      <?= (int) $remaining ?> of <?= (int) $quantity ?> available
    </p>
    <?php if ($sellerNickname !== '') : ?>
      <p class="body-sm text-on-surface-variant mb-0">
        @<?= htmlspecialchars($sellerNickname, ENT_QUOTES, 'UTF-8') ?>
        <?php if ($sellerVerified) : ?>
          <span aria-label="Verified student" title="Verified student">&#10003;</span>
        <?php endif; ?>
        &middot; <span class="text-on-surface-variant">Tier <?= htmlspecialchars($sellerTier, ENT_QUOTES, 'UTF-8') ?></span>
      </p>
    <?php endif; ?>
  </div>
</article>
