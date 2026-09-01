<?php

/**
 * TicketTrade — Support\View\partials\list_view_toggle
 *
 * Phase 3 Plan 03-03. A single button (corkboard view by default;
 * click flips to plain-grid). The Phase 1 listViewToggle component
 * (in public/assets/js/tickettrade.js) wires the sessionStorage
 * persistence + aria-pressed state. This partial just renders the
 * button. The board container class swap (corkboard <-> list-grid)
 * is done by the JS reading data-list-view on <html>.
 *
 * Vars: none. The component is self-contained.
 */

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$currentView = (string) ($__vars['current'] ?? 'cork');
?>
<div class="list-view-toggle d-inline-block" data-component="list-view-toggle" role="group" aria-label="Toggle board layout">
  <button type="button"
          class="btn btn-outline-secondary btn-sm list-view-toggle__btn"
          data-value="cork"
          aria-pressed="<?= $currentView === 'cork' ? 'true' : 'false' ?>"
          title="Corkboard view">
    <span aria-hidden="true">▦</span> Corkboard
  </button>
  <button type="button"
          class="btn btn-outline-secondary btn-sm list-view-toggle__btn"
          data-value="list"
          aria-pressed="<?= $currentView === 'list' ? 'true' : 'false' ?>"
          title="List view">
    <span aria-hidden="true">≡</span> List
  </button>
</div>
