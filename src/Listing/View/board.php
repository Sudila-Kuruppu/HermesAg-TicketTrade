<?php

/**
 * TicketTrade — Listing/View/board
 *
 * Phase 3 Plan 03-03. The /board route. The corkboard board view
 * (LND-07 + D-15..D-19). Composed of:
 *   - visually-hidden h1
 *   - search box + list-view toggle in the header
 *   - category tabs strip
 *   - corkboard (default) or plain-grid (list-view) container
 *   - the listing modal at the bottom of the page
 *
 * Per D-09 (Phase 2): the card CTA copy switches between
 * "Sign in to buy" (guest) and "Buy now" (logged-in). The cork-cell
 * wrapper has the rotation and pin per the spec; we delegate to the
 * listing_card_cork partial for the actual card body (which itself
 * applies rotation/pin per the must_haves truths).
 *
 * Per CONTEXT D-15..D-19:
 *   - q is preserved in the search input + in pagination/tab URLs
 *   - cat is preserved in the pagination URLs and the active tab
 *     carries aria-current="page"
 *   - The list-view toggle is a single button (or pair) backed by
 *     sessionStorage via the Phase 1 listViewToggle component.
 *
 * Per the must_haves:
 *   - Empty state: "No listings yet - check back soon" / "New
 *     listings appear here within 24 hours of submission"
 *   - No-matches: "No matches for <q> in <category>"
 */

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$rows = $__vars['rows'] ?? [];
$total = (int) ($__vars['total'] ?? 0);
$page = (int) ($__vars['page'] ?? 1);
$pages = (int) ($__vars['pages'] ?? 1);
$q = $__vars['q'] ?? null;
$cat = $__vars['cat'] ?? null;
$categories = $__vars['categories'] ?? [];
$isGuest = (bool) ($__vars['is_guest'] ?? true);
$activeCatName = (string) ($__vars['active_cat_name'] ?? 'all categories');

$hasFilters = ($q !== null && $q !== '') || ($cat !== null);

// Compute the first listing (used by the modal's initial state)
$firstListing = null;
$firstListingWithImages = null;
if (!empty($rows) && is_array($rows)) {
    $firstListing = $rows[0];
    // Load images for the first listing so the modal can pre-render.
    $withImages = \App\Listing\Service\listing_service::getWithImages((int) $firstListing['id']);
    if ($withImages !== null) {
        // Merge in the search-row data we already had (seller_nickname etc.)
        $firstListingWithImages = array_merge($withImages, $firstListing);
    }
}

// Compute prev/next for the modal's keyboard navigation (D-22)
$prevId = null;
$nextId = null;
if ($firstListing !== null) {
    $listingId = (int) $firstListing['id'];
    $catId = (int) ($firstListing['category_id'] ?? 0);
    $catIdOrNull = $catId > 0 ? $catId : null;
    $prevId = \App\Listing\Model\listing_model::getNextInCategory($listingId, $catIdOrNull, 'prev');
    $nextId = \App\Listing\Model\listing_model::getNextInCategory($listingId, $catIdOrNull, 'next');
}
?>
<section class="container board-shell" data-component="board-page" data-list-view="cork">
  <h1 class="visually-hidden">Marketplace board</h1>

  <div class="board-toolbar d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="board-toolbar__search flex-grow-1">
      <?= \App\Support\View::partial('search_box', ['q' => $q, 'cat' => $cat, 'page' => $page]) ?>
    </div>
    <div class="board-toolbar__view-toggle">
      <?= \App\Support\View::partial('list_view_toggle', ['current' => 'cork']) ?>
    </div>
  </div>

  <div class="board-tabs mb-3">
    <?= \App\Support\View::partial('category_tabs', ['cat' => $cat, 'categories' => $categories, 'q' => $q]) ?>
  </div>

  <?= \App\Support\View::partial('pagination', [
      'page' => $page, 'pages' => $pages, 'q' => $q, 'cat' => $cat, 'slot' => 'top',
  ]) ?>

  <?php if (empty($rows)) : ?>
        <?php
        if ($hasFilters) {
            $title = 'No matches';
            // CR-03: escape both the user-supplied query and the
            // category name (defense-in-depth) before echoing.
            $body = 'No matches for "' . htmlspecialchars((string) ($q ?? ''), ENT_QUOTES, 'UTF-8')
                . '" in ' . htmlspecialchars($activeCatName, ENT_QUOTES, 'UTF-8');
        } else {
            $title = 'No listings yet - check back soon';
            $body = 'New listings appear here within 24 hours of submission';
        }
        ?>
    <div data-list-view-empty>
        <?= \App\Support\View::partial('empty_state', ['title' => $title, 'body' => $body, 'cta_label' => null]) ?>
    </div>
  <?php else : ?>
    <div class="corkboard row g-3" data-component="corkboard" role="list" aria-label="Active listings">
      <?php foreach ($rows as $row) :
            $id = (int) ($row['id'] ?? 0);
            $rotation = ($id > 0) ? (crc32((string) $id) % 5) - 2 : 0;
            $pinClass = ($id % 2 === 0) ? 'pin-red' : 'pin-blue';
            $titleAttr = (string) ($row['title'] ?? '');
            $ctaHref = $isGuest
              ? '/login?next=/board'
              : '/listings/' . $id . '#buy';
            $ctaLabel = $isGuest ? 'Sign in to buy' : 'Buy now';
            ?>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3 cork-cell" role="listitem"
             data-listing-id="<?= (int) $id ?>"
             style="transform: rotate(<?= (int) $rotation ?>deg);"
             aria-hidden="true">
          <div class="cork-cell__pin <?= htmlspecialchars($pinClass, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></div>
            <?php if ($isGuest) : ?>
          <a href="<?= htmlspecialchars('/login?next=/board', ENT_QUOTES, 'UTF-8') ?>"
             class="listing-card-cork-link"
             data-listing-id="<?= (int) $id ?>"
             aria-label="Open listing: <?= htmlspecialchars($titleAttr, ENT_QUOTES, 'UTF-8') ?>">
            <?php else : ?>
          <a href="#listing-<?= (int) $id ?>"
             class="listing-card-cork-link"
             data-bs-toggle="modal"
             data-bs-target="#listingModal"
             data-listing-id="<?= (int) $id ?>"
             aria-label="Open listing: <?= htmlspecialchars($titleAttr, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
            <?= \App\Support\View::partial('listing_card_cork', [
                'listing' => $row,
                'is_guest' => $isGuest,
            ]) ?>
            <span class="cork-cell__cta btn btn-sm btn-primary mt-2">
              <?= htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8') ?>
            </span>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="list-grid row g-3 d-none" data-component="list-grid" role="list" aria-label="Active listings (list view)">
      <?php foreach ($rows as $row) :
            $id = (int) ($row['id'] ?? 0);
            $titleAttr = (string) ($row['title'] ?? '');
            $ctaHref = $isGuest
              ? '/login?next=/board'
              : '/listings/' . $id . '#buy';
            $ctaLabel = $isGuest ? 'Sign in to buy' : 'Buy now';
            ?>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3" role="listitem" data-listing-id="<?= (int) $id ?>">
          <a href="#listing-<?= (int) $id ?>"
             class="text-decoration-none text-reset"
             data-bs-toggle="modal"
             data-bs-target="#listingModal"
             data-listing-id="<?= (int) $id ?>"
             aria-label="Open listing: <?= htmlspecialchars($titleAttr, ENT_QUOTES, 'UTF-8') ?>">
            <?= \App\Support\View::partial('listing_card', [
                'listing' => $row,
                'is_guest' => $isGuest,
            ]) ?>
            <span class="cork-cell__cta btn btn-sm btn-primary mt-2">
              <?= htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8') ?>
            </span>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?= \App\Support\View::partial('pagination', [
      'page' => $page, 'pages' => $pages, 'q' => $q, 'cat' => $cat, 'slot' => 'bottom',
  ]) ?>

  <?php if (!empty($rows)) :
        $GLOBALS['_tt_view_vars'] = [
          'first_listing' => $firstListingWithImages ?? $firstListing,
          'prev_id' => $prevId,
          'next_id' => $nextId,
          'csrf_token' => \App\Support\Csrf::token(),
          'seller_summary' => $__vars['seller_summary'] ?? [],
        ];
        require __DIR__ . '/listing_modal.php';
  endif; ?>
</section>
