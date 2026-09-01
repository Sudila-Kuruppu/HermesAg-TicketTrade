<?php

/**
 * TicketTrade — Support\View\partials\category_tabs
 *
 * Phase 3 Plan 03-03. Renders the 8-tab strip (All + 7 categories) on
 * the board view. Active tab carries aria-current="page" + the active
 * Bootstrap class. The strip is horizontally scrollable on mobile
 * (<md) with the active tab snapped into view via a small inline
 * script that calls scrollIntoView.
 *
 * Vars:
 *   $cat         int|null The currently-active category id (null = All)
 *   $categories  array    The list of category rows (id, name, sort_order)
 *   $q           string|null The current search query (preserved in href)
 */

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$cat = $__vars['cat'] ?? null;
$categories = $__vars['categories'] ?? [];
$q = $__vars['q'] ?? null;

$buildHref = function (?int $catId) use ($q): string {
    $parts = [];
    if ($catId !== null) {
        $parts[] = 'cat=' . $catId;
    }
    if ($q !== null && $q !== '') {
        $parts[] = 'q=' . urlencode($q);
    }
    return '/board' . (empty($parts) ? '' : '?' . implode('&', $parts));
};
?>
<nav class="category-tabs" aria-label="Filter by category">
  <div class="d-flex flex-nowrap overflow-auto category-tabs__strip" data-component="category-tabs">
    <a class="nav-link category-tab <?= $cat === null ? 'active' : '' ?>"
       href="<?= htmlspecialchars($buildHref(null), ENT_QUOTES, 'UTF-8') ?>"
       aria-current="<?= $cat === null ? 'page' : 'false' ?>">All</a>
    <?php foreach ($categories as $c) :
        $cid = (int) ($c['id'] ?? 0);
        $cname = (string) ($c['name'] ?? '');
        $isActive = ($cat === $cid);
    ?>
      <a class="nav-link category-tab <?= $isActive ? 'active' : '' ?>"
         href="<?= htmlspecialchars($buildHref($cid), ENT_QUOTES, 'UTF-8') ?>"
         aria-current="<?= $isActive ? 'page' : 'false' ?>"><?= htmlspecialchars($cname, ENT_QUOTES, 'UTF-8') ?></a>
    <?php endforeach; ?>
  </div>
</nav>
<script>
  // Snap the active tab into view on load so the strip is centered.
  (function () {
    try {
      var active = document.querySelector('.category-tabs__strip .category-tab.active');
      if (active && typeof active.scrollIntoView === 'function') {
        active.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'auto' });
      }
    } catch (e) { /* non-fatal */ }
  })();
</script>
