<?php

/**
 * TicketTrade — Support\View\partials\seller_dashboard_tabs
 *
 * The 4-tab nav on /my-listings. Tabs are links (?tab=...) so they
 * work without JS. The active tab carries aria-current="page".
 * Per D-01: the count is a plain inline span next to the label,
 * NOT a Bootstrap badge (which would compete with the status pills).
 *
 * Vars:
 *   tab    (string)  Current tab key: active|pending|sold|draft
 *   counts (array)   ['active' => N, 'pending' => N, 'sold' => N, 'draft' => N]
 *   csrf_token (string)  Unused here but passed-through for parity with other tabs.
 */

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$tab = (string) ($__vars['tab'] ?? 'active');
$counts = $__vars['counts'] ?? [];
$order = ['active', 'pending', 'sold', 'draft'];
$labels = [
    'active' => 'Active',
    'pending' => 'Pending',
    'sold' => 'Sold',
    'draft' => 'Draft',
];
?>
<nav class="nav nav-tabs mb-4" role="tablist" aria-label="My listings">
<?php foreach ($order as $key) :
    $isActive = ($tab === $key);
    $count = (int) ($counts[$key] ?? 0);
    ?>
<a class="nav-link <?= $isActive ? 'active' : '' ?>"
   href="/my-listings?tab=<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
   role="tab"
   aria-current="<?= $isActive ? 'page' : 'false' ?>"
   data-tab="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
    <?= htmlspecialchars($labels[$key], ENT_QUOTES, 'UTF-8') ?>
<span class="text-muted ms-1">(<?= $count ?>)</span>
</a>
<?php endforeach; ?>
</nav>
