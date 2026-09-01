<?php

/**
 * TicketTrade — Support\View\partials\pagination
 *
 * Phase 3 Plan 03-03. Numbered pagination control for the board view.
 * Preserves q + cat in every URL. Renders nothing when there is only
 * one page. Two slots are supported: the top copy (mobile-hidden) and
 * the bottom copy (always rendered).
 *
 * Vars:
 *   $page   int  Current page (1-based)
 *   $pages  int  Total pages (>=1)
 *   $q      string|null Current search query
 *   $cat    int|null Current category id
 *   $slot   string 'top' (mobile-hidden via d-none d-md-block) or
 *                    'bottom' (default; always rendered)
 */

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$page = (int) ($__vars['page'] ?? 1);
$pages = (int) ($__vars['pages'] ?? 1);
$q = $__vars['q'] ?? null;
$cat = $__vars['cat'] ?? null;
$slot = (string) ($__vars['slot'] ?? 'bottom');

if ($pages <= 1) {
    return;
}

$buildHref = function (int $targetPage) use ($q, $cat): string {
    $parts = ['page=' . max(1, $targetPage)];
    if ($cat !== null) {
        $parts[] = 'cat=' . (int) $cat;
    }
    if ($q !== null && $q !== '') {
        $parts[] = 'q=' . urlencode($q);
    }
    return '/board?' . implode('&', $parts);
};

$slotClass = ($slot === 'top') ? 'd-none d-md-block mb-3' : 'mt-4';
?>
<nav class="board-pagination <?= htmlspecialchars($slotClass, ENT_QUOTES, 'UTF-8') ?>" aria-label="Page navigation">
  <ul class="pagination justify-content-center mb-0">
    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= htmlspecialchars($buildHref($page - 1), ENT_QUOTES, 'UTF-8') ?>"
         aria-label="Previous page" <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Prev</a>
    </li>
    <?php for ($i = 1; $i <= $pages; $i++) : ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>" <?= $i === $page ? 'aria-current="page"' : '' ?>>
        <a class="page-link" href="<?= htmlspecialchars($buildHref($i), ENT_QUOTES, 'UTF-8') ?>"><?= (int) $i ?></a>
      </li>
    <?php endfor; ?>
    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= htmlspecialchars($buildHref($page + 1), ENT_QUOTES, 'UTF-8') ?>"
         aria-label="Next page" <?= $page >= $pages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Next</a>
    </li>
  </ul>
</nav>
