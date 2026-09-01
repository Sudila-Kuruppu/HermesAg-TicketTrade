<?php
/**
 * TicketTrade — Support\View\partials\listing_card_cork
 *
 * Corkboard variant: paper surface, deterministic +/-2 deg rotation,
 * pushpin graphic. Used on the board's corkboard default view.
 *
 * Per EXPERIENCE.md and CONTEXT D-21..D-23, the rotation and pin are
 * decorative-only (aria-hidden). All ranking data flows through list
 * order, NOT card rotation. prefers-reduced-motion is honored via CSS.
 *
 * Deterministic seed: crc32($listing['id']) % 5 - 2  →  -2..+2 deg.
 * Pin alternates red/blue by $listing['id'] % 2.
 */

$vars = $GLOBALS['_tt_view_vars'] ?? [];
$listing = $vars['listing'] ?? [];
$id = (int) ($listing['id'] ?? 0);
$title = (string) ($listing['title'] ?? '');
$priceCents = (int) ($listing['price_cents'] ?? 0);
$status = (string) ($listing['status'] ?? 'draft');
$quantity = (int) ($listing['quantity'] ?? 1);
$quantitySold = (int) ($listing['quantity_sold'] ?? 0);
$remaining = max(0, $quantity - $quantitySold);
$sellerNickname = (string) ($listing['seller_nickname'] ?? '');
$sellerTier = (string) ($listing['seller_tier'] ?? 'E');
$sellerVerified = (bool) ($listing['seller_is_verified'] ?? false);
$primarySha = (string) ($listing['primary_sha256'] ?? '');

// Deterministic rotation
$rotationSeed = ($id > 0) ? (crc32((string) $id) % 5) - 2 : 0;
$pinColor = ($id % 2 === 0) ? '#C62828' : '#1565C0'; // NSBM pin-red / info-blue

$priceStr = number_format($priceCents / 100, 2);
?>
<article class="listing-card-cork" data-listing-id="<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>" role="article">
  <span class="listing-card-cork__pin" aria-hidden="true" style="background: <?= htmlspecialchars($pinColor, ENT_QUOTES, 'UTF-8') ?>;"></span>
  <div class="listing-card-cork__paper" style="transform: rotate(<?= (int) $rotationSeed ?>deg);">
    <?php if ($primarySha !== '') : ?>
      <img src="/img/<?= htmlspecialchars((string) $id, ENT_QUOTES, 'UTF-8') ?>/thumb" class="listing-card-cork__img" alt="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
    <?php else : ?>
      <div class="listing-card-cork__img placeholder d-flex align-items-center justify-content-center">
        <span class="text-on-surface-variant body-sm">No image</span>
      </div>
    <?php endif; ?>
    <div class="listing-card-cork__body">
      <h2 class="h6 mb-1"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
      <p class="body-md fw-semibold mb-1">LKR <?= htmlspecialchars($priceStr, ENT_QUOTES, 'UTF-8') ?></p>
      <p class="body-sm text-on-surface-variant mb-1">
        <?= (int) $remaining ?> of <?= (int) $quantity ?> available
      </p>
      <?php if ($sellerNickname !== '') : ?>
        <p class="body-sm text-on-surface-variant mb-0">
          @<?= htmlspecialchars($sellerNickname, ENT_QUOTES, 'UTF-8') ?>
          <?php if ($sellerVerified) : ?>
            <span aria-label="Verified student" title="Verified student">&#10003;</span>
          <?php endif; ?>
        </p>
      <?php endif; ?>
    </div>
  </div>
</article>
