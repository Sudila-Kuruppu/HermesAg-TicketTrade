<?php

/**
 * TicketTrade — Listing/View/listing_modal
 *
 * Phase 3 Plan 03-03. The full-screen listing modal at the bottom of
 * the board page. Single modal, content swapped in by JS (per D-22:
 * the JS reads /listings/{id}?fragment=1 to fetch new content; for
 * Phase 3 the initial modal HTML is pre-rendered with the first
 * visible listing's data to keep the demo fast and avoid a fetch
 * roundtrip on first click).
 *
 * The modal:
 *   - Bootstrap 5.3: modal-fullscreen-sm-down, modal-dialog-centered
 *   - Image carousel: data-bs-ride="false", no auto-advance
 *   - Prev/Next buttons in the modal header walk listings in the
 *     same category (created_at DESC) — wraps at end-of-list
 *   - Keyboard: ←/→ prev/next, Esc close + focus return
 *   - Mobile swipe: touchstart/touchend with 50px threshold
 *   - URL hash: /board#listing-{id} on open, removed on close
 *
 * The data-component="listingModal" attribute hooks the JS component.
 */

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
// $rows, $page, $q, $cat, $is_guest are available on the board view.
// $firstListing is the listing the JS uses for the initial modal state.
$firstListing = $__vars['first_listing'] ?? null;
$nextId = $__vars['next_id'] ?? null;
$prevId = $__vars['prev_id'] ?? null;
?>
<div class="modal fade listing-modal" id="listingModal" tabindex="-1"
     aria-labelledby="listingModalTitle" aria-hidden="true"
     data-component="listingModal"
     data-prev-id="<?= $prevId !== null ? (int) $prevId : '' ?>"
     data-next-id="<?= $nextId !== null ? (int) $nextId : '' ?>">
  <div class="modal-dialog modal-fullscreen-sm-down modal-dialog-centered">
    <div class="modal-content listing-modal__content">
      <div class="modal-header listing-modal__header">
        <button type="button" class="btn btn-link listing-modal__nav listing-modal__nav--prev"
                aria-label="Previous listing" data-listing-nav="prev">
          <span aria-hidden="true">&larr;</span>
        </button>
        <h2 class="modal-title h5 mb-0 listing-modal__title" id="listingModalTitle">
          <?= $firstListing !== null ? htmlspecialchars((string) $firstListing['title'], ENT_QUOTES, 'UTF-8') : 'Listing' ?>
        </h2>
        <button type="button" class="btn btn-link listing-modal__nav listing-modal__nav--next"
                aria-label="Next listing" data-listing-nav="next">
          <span aria-hidden="true">&rarr;</span>
        </button>
        <button type="button" class="btn-close listing-modal__close" data-bs-dismiss="modal"
                aria-label="Close"></button>
      </div>
      <div class="modal-body listing-modal__body" data-listing-modal-body>
        <?php if ($firstListing !== null) :
            $images = $firstListing['images'] ?? [];
            $carouselImages = [];
            foreach ($images as $img) {
                if (($img['size'] ?? '') === 'full') {
                    $carouselImages[] = $img;
                }
            }
            ?>
          <div class="listing-modal__carousel-wrap" data-listing-id="<?= (int) $firstListing['id'] ?>">
            <?= \App\Support\View::partial('listing_modal_carousel', [
                'listing_id' => (int) $firstListing['id'],
                'title' => (string) $firstListing['title'],
                'images' => $carouselImages,
                'id_prefix' => 'listingModalCarouselInitial',
            ]) ?>
            <div class="listing-modal__details p-3">
              <p class="listing-modal__price h4 mb-2">
                LKR <?= htmlspecialchars(number_format(((int) $firstListing['price_cents']) / 100, 2), ENT_QUOTES, 'UTF-8') ?>
              </p>
              <p class="listing-modal__description body-md">
                <?= nl2br(htmlspecialchars((string) $firstListing['description'], ENT_QUOTES, 'UTF-8')) ?>
              </p>
              <div class="listing-modal__seller d-flex align-items-center gap-2 mt-3">
                <span class="body-sm text-on-surface-variant">
                  Sold by
                  <strong>@<?= htmlspecialchars((string) ($firstListing['seller_nickname'] ?? 'seller'), ENT_QUOTES, 'UTF-8') ?></strong>
                  <?php if (!empty($firstListing['seller_is_verified'])) : ?>
                    <span aria-label="Verified student" title="Verified student">&#10003;</span>
                  <?php endif; ?>
                </span>
                <span class="badge rank-badge rank-<?= htmlspecialchars(strtolower((string) ($firstListing['seller_tier'] ?? 'e')), ENT_QUOTES, 'UTF-8') ?>"
                      aria-label="Rank tier <?= htmlspecialchars((string) ($firstListing['seller_tier'] ?? 'E'), ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars((string) ($firstListing['seller_tier'] ?? 'E'), ENT_QUOTES, 'UTF-8') ?>
                </span>
              </div>
              <div class="listing-modal__actions mt-4">
                <a href="/listings/<?= (int) $firstListing['id'] ?>#buy"
                   class="btn btn-primary listing-modal__buy">
                  Buy now
                </a>
                <a href="/listings/<?= (int) $firstListing['id'] ?>/report"
                   class="btn btn-link listing-modal__report">Report</a>
              </div>
            </div>
          </div>
        <?php else : ?>
          <p class="text-center text-on-surface-variant py-5">Select a listing to see details.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
