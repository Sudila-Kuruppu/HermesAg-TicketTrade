<?php

/**
 * TicketTrade — Support\View\partials\search_box
 *
 * Phase 3 Plan 03-03. The board's search form. Submits a GET to
 * /board; cat + page are preserved via hidden inputs.
 *
 * Vars:
 *   $q   string|null  Current query (used to pre-fill the input)
 *   $cat int|null     Current category (preserved as hidden input)
 *   $page int         Current page (preserved as hidden input)
 */

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$q = $__vars['q'] ?? null;
$cat = $__vars['cat'] ?? null;
$page = (int) ($__vars['page'] ?? 1);
?>
<form method="GET" action="/board" role="search" class="board-search d-flex" data-component="board-search">
  <?php if ($cat !== null) : ?>
    <input type="hidden" name="cat" value="<?= (int) $cat ?>">
  <?php endif; ?>
  <?php if ($page > 1) : ?>
    <input type="hidden" name="page" value="<?= (int) $page ?>">
  <?php endif; ?>
  <input type="search" name="q" class="form-control board-search__input"
         placeholder="Search by title or description"
         value="<?= htmlspecialchars((string) $q, ENT_QUOTES, 'UTF-8') ?>"
         aria-label="Search listings" aria-describedby="board-search-help"
         minlength="1" maxlength="100">
  <button type="submit" class="btn btn-primary ms-2 board-search__submit">Search</button>
</form>
<small id="board-search-help" class="text-muted board-search__help">Type a keyword and press Enter</small>
