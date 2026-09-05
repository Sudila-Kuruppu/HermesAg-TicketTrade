<?php

/**
 * TicketTrade — Listing/View/listing_modal
 *
 * Phase 3 Plan 03-03 + Phase 4 Plan 04-02 + Phase 5 Plan 05-02.
 *
 * The full-screen listing modal at the bottom of the board page.
 * Single modal, content swapped in by JS (per D-22: the JS reads
 * /listings/{id}?fragment=1 to fetch new content; for Phase 3 the
 * initial modal HTML is pre-rendered with the first visible listing's
 * data to keep the demo fast and avoid a fetch roundtrip on first
 * click).
 *
 * Phase 4 changes:
 *   - The "Buy now" button is now a <form method="POST" action=
 *     "/listings/{id}/buy"> carrying the CSRF token. The Action
 *     runs the rate-limit + ticket creation transaction and 302s
 *     to /my-tickets on success.
 *   - The button is HIDDEN when the current user is the seller
 *     (per EXPERIENCE.md L196-197) OR when the listing is sold out.
 *   - Guests still see the "Sign in to buy" link per D-09 of
 *     Phase 3.
 *
 * Phase 5 Plan 05-02: INSERTS a compact "★ 4.8 (23 reviews)" row
 * between the seller nickname and the tier badge (D-09 + BLOCKER
 * review note). The rating row renders ONLY when rating_count > 0
 * (no row when 0 — the listing modal is information-dense; absence
 * is signal). The dispute suffix ("· N disputes") is gated
 * INDEPENDENTLY so a seller with 0 reviews but 2 upheld disputes
 * still gets the suffix. The compact fragments live in
 * review_summary_compact_rating / review_summary_compact_dispute.
 *
 * The data-component="listingModal" attribute hooks the JS component.
 */

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
// $rows, $page, $q, $cat, $is_guest are available on the board view.
// $firstListing is the listing the JS uses for the initial modal state.
$firstListing = $__vars['first_listing'] ?? null;
$nextId = $__vars['next_id'] ?? null;
$prevId = $__vars['prev_id'] ?? null;
$sellerSummary = is_array($__vars['seller_summary'] ?? null)
    ? $__vars['seller_summary']
    : [];
$currentUser = $GLOBALS['current_user'] ?? null;
$currentUserId = $currentUser !== null ? (int) ($currentUser['user_id'] ?? 0) : 0;
$isGuest = ($currentUser === null);
$csrfToken = (string) ($__vars['csrf_token'] ?? \App\Support\Csrf::token());
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
            $listingId = (int) $firstListing['id'];
            $listingSellerId = (int) ($firstListing['seller_id'] ?? 0);
            $listingQuantity = (int) ($firstListing['quantity'] ?? 1);
            $listingQuantitySold = (int) ($firstListing['quantity_sold'] ?? 0);
            $isSoldOut = $listingQuantitySold >= $listingQuantity;
            $isOwnListing = $currentUserId > 0 && $listingSellerId === $currentUserId;
            ?>
          <div class="listing-modal__carousel-wrap" data-listing-id="<?= (int) $listingId ?>">
            <?= \App\Support\View::partial('listing_modal_carousel', [
                'listing_id' => $listingId,
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
              <div class="listing-modal__seller d-flex flex-wrap align-items-center gap-2 mt-3">
                <span class="body-sm text-on-surface-variant">
                  Sold by
                  <strong>@<?= htmlspecialchars((string) ($firstListing['seller_nickname'] ?? 'seller'), ENT_QUOTES, 'UTF-8') ?></strong>
                  <?php if (!empty($firstListing['seller_is_verified'])) : ?>
                    <span aria-label="Verified student" title="Verified student">&#10003;</span>
                  <?php endif; ?>
                </span>
                <?php
                  // Phase 5 Plan 05-02: compact rating + dispute
                  // fragments (D-09 + BLOCKER review note). Gated
                  // INDEPENDENTLY — a seller with 0 reviews but 2
                  // upheld disputes still shows "· 2 disputes".
                  \App\Support\View::partial(
                      'review_summary_compact_rating',
                      ['summary' => $sellerSummary]
                  );
                  \App\Support\View::partial(
                      'review_summary_compact_dispute',
                      ['summary' => $sellerSummary]
                  );
                ?>
                <span class="badge rank-badge rank-<?= htmlspecialchars(strtolower((string) ($firstListing['seller_tier'] ?? 'e')), ENT_QUOTES, 'UTF-8') ?>"
                      aria-label="Rank tier <?= htmlspecialchars((string) ($firstListing['seller_tier'] ?? 'E'), ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars((string) ($firstListing['seller_tier'] ?? 'E'), ENT_QUOTES, 'UTF-8') ?>
                </span>
              </div>
              <div class="listing-modal__actions mt-4" data-listing-actions>
                <?php if ($isGuest) : ?>
                  <!-- Guest: Sign in to buy (D-09 Phase 3) -->
                  <a href="/login?next=/board" class="btn btn-primary listing-modal__buy">
                    Sign in to buy
                  </a>
                <?php elseif ($isOwnListing) : ?>
                  <!-- Self-owned listing (EXPERIENCE.md L196) -->
                  <span class="badge surface-container-high px-3 py-2 listing-modal__self-owned">
                    This is your listing.
                  </span>
                <?php elseif ($isSoldOut) : ?>
                  <!-- Sold-out listing (EXPERIENCE.md L196) -->
                  <span class="badge bg-secondary px-3 py-2 listing-modal__sold-out" aria-label="Out of stock">
                    Out of stock
                  </span>
                <?php else : ?>
                  <!-- Logged-in buyer, not own listing, in stock: real Buy now POST.
                       Phase 4 Plan 04-02 ROADMAP #1: the button opens a Bootstrap
                       confirmation modal (data-scrim-guard="2" suppresses backdrop
                       click for 2s). The Confirm button submits the underlying
                       form via JS (form id="buy-form-{id}"). The form keeps
                       its method/action/inputs so BuyAction::handlePost is
                       unchanged — only the user-facing flow gains a confirm step. -->
                  <form id="buy-form-<?= (int) $listingId ?>" method="POST" action="/listings/<?= (int) $listingId ?>/buy" class="listing-modal__buy-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="button"
                            class="btn btn-primary listing-modal__buy"
                            data-bs-toggle="modal"
                            data-bs-target="#buy-confirm-modal-<?= (int) $listingId ?>"
                            data-action="buy-now">
                      Buy now
                    </button>
                  </form>
                  <div class="modal fade buy-confirm-modal"
                       id="buy-confirm-modal-<?= (int) $listingId ?>"
                       tabindex="-1"
                       aria-labelledby="buy-confirm-title-<?= (int) $listingId ?>"
                       aria-hidden="true"
                       data-scrim-guard="2"
                       data-component="buy-confirm-modal"
                       data-buy-form-id="buy-form-<?= (int) $listingId ?>">
                    <div class="modal-dialog modal-dialog-centered">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h2 class="modal-title h5 mb-0" id="buy-confirm-title-<?= (int) $listingId ?>">
                            Confirm purchase?
                          </h2>
                          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                          <p class="mb-0">
                            This reserves the item with a digital ticket
                            (a reservation, not payment).
                          </p>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                          <button type="button"
                                  class="btn btn-primary"
                                  data-action="buy-confirm"
                                  data-buy-form-id="buy-form-<?= (int) $listingId ?>">
                            Confirm
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>
                <a href="/listings/<?= (int) $listingId ?>/report"
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
