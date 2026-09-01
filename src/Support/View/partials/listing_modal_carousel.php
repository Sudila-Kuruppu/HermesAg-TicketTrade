<?php

/**
 * TicketTrade — Support\View\partials\listing_modal_carousel
 *
 * Phase 3 Plan 03-03. Bootstrap 5 carousel for the listing modal.
 * - data-bs-ride="false" + data-bs-interval="false" (no auto-advance).
 * - Indicators (dots) and prev/next arrows render ONLY when more
 *   than 1 image exists.
 * - Each slide is a /img/{id}/full URL (ImageProxy auth-gates this
 *   size; the seller/ticket holder/admin can fetch).
 *
 * Vars:
 *   $listing_id  int    Listing id (for the /img/{id}/full path)
 *   $title       string Listing title (for img alt)
 *   $images      array  List of listing_images rows (sha256, size, etc.)
 *                        — only 'full' size rows are needed here.
 *   $id_prefix   string DOM id prefix to keep multiple carousels unique
 */

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$listingId = (int) ($__vars['listing_id'] ?? 0);
$title = (string) ($__vars['title'] ?? '');
$images = $__vars['images'] ?? [];
$idPrefix = (string) ($__vars['id_prefix'] ?? 'listingModalCarousel');

$count = is_array($images) ? count($images) : 0;
?>
<?php if ($count === 0) : ?>
  <div class="listing-modal__no-image text-center py-5">
    <span class="text-on-surface-variant body-md">No images available</span>
  </div>
<?php else : ?>
  <div id="<?= htmlspecialchars($idPrefix, ENT_QUOTES, 'UTF-8') ?>"
       class="carousel slide listing-modal__carousel"
       data-bs-ride="false"
       data-bs-interval="false"
       data-bs-touch="true">
    <?php if ($count > 1) : ?>
      <ol class="carousel-indicators" aria-label="Image carousel pagination">
        <?php for ($i = 0; $i < $count; $i++) : ?>
          <li data-bs-target="#<?= htmlspecialchars($idPrefix, ENT_QUOTES, 'UTF-8') ?>"
              data-bs-slide-to="<?= (int) $i ?>"
              <?= $i === 0 ? 'class="active" aria-current="true"' : '' ?>
              aria-label="Slide <?= (int) ($i + 1) ?>"></li>
        <?php endfor; ?>
      </ol>
    <?php endif; ?>
    <div class="carousel-inner">
      <?php foreach ($images as $idx => $img) :
          $sha = (string) ($img['sha256'] ?? '');
          $isFirst = ($idx === 0);
      ?>
        <div class="carousel-item <?= $isFirst ? 'active' : '' ?>">
          <img src="/img/<?= (int) $listingId ?>/full"
               class="d-block w-100 listing-modal__img"
               alt="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
               loading="lazy">
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($count > 1) : ?>
      <button class="carousel-control-prev" type="button"
              data-bs-target="#<?= htmlspecialchars($idPrefix, ENT_QUOTES, 'UTF-8') ?>"
              data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous image</span>
      </button>
      <button class="carousel-control-next" type="button"
              data-bs-target="#<?= htmlspecialchars($idPrefix, ENT_QUOTES, 'UTF-8') ?>"
              data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next image</span>
      </button>
    <?php endif; ?>
  </div>
<?php endif; ?>
