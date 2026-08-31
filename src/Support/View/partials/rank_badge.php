<?php

/**
 * TicketTrade — Support\View\partials\rank_badge
 *
 * The 6-tier rank badge SVG (D-14 + DESIGN.md colors). Each tier renders
 * a different shield/crown shape with the matching fill from the design
 * token palette. The `legend-glow` class on the S tier is a hook for
 * the Phase 6 animation; the static SVG is shipped here.
 *
 * Usage from a View:
 *   <?= \App\Support\View::partial('rank_badge.php', ['tier' => $profile['tier'] ?? 'E', 'size' => 32]) ?>
 *
 * Reads config/ranks.php for the tier name and the canonical 6-tier set.
 * The $tier input is clamped to the canonical set; an unknown tier
 * falls back to 'E' (the Recruit / gray shield).
 *
 * @var string $tier  One of E, D, C, B, A, S
 * @var int    $size  Pixel size (default 32)
 */

// View::partial() does not extract() the $vars into local scope, so
// we read from $GLOBALS['_tt_view_vars'] for the partial signature.
$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$tier = isset($__vars['tier']) ? (string) $__vars['tier'] : 'E';
$size = isset($__vars['size']) ? (int) $__vars['size'] : 32;
if ($size < 16) {
    $size = 16;
} elseif ($size > 96) {
    $size = 96;
}
$ladder = require APP_ROOT . '/config/ranks.php';
$tier = isset($ladder[$tier]) ? $tier : 'E';
$def = $ladder[$tier];
$isLegend = $tier === 'S';

// Tier-specific colors via the design-token palette (DESIGN.md row 32-43).
// The SVG fill is bound via inline `style` so the partial ships without
// needing new CSS additions in Phase 2.
$tierColor = [
    'E' => '#9E9E9E',  // rank-e
    'D' => '#2196F3',  // rank-d
    'C' => '#2E7D32',  // rank-c
    'B' => '#F9A825',  // rank-b
    'A' => '#EF6C00',  // rank-a
    'S' => '#C62828',  // rank-s
][$tier];

// 6 distinct shapes — each tier gets a unique silhouette.
if ($tier === 'S') : ?>
  <span class="rank-badge rank-badge--S legend-glow" data-tier="S" data-bs-toggle="tooltip" title="<?= htmlspecialchars($def['name'], ENT_QUOTES, 'UTF-8') ?>">
    <svg viewBox="0 0 32 32" width="<?= $size ?>" height="<?= $size ?>" aria-hidden="true">
      <path d="M4 20 L8 10 L12 18 L16 6 L20 18 L24 10 L28 20 L24 26 L8 26 Z" fill="<?= $tierColor ?>" stroke="#B71C1C" stroke-width="1"/>
      <circle cx="16" cy="14" r="2.5" fill="#FFEA00"/>
    </svg>
    <span class="visually-hidden"><?= htmlspecialchars($def['name'], ENT_QUOTES, 'UTF-8') ?></span>
  </span>
<?php elseif ($tier === 'A') : ?>
  <span class="rank-badge rank-badge--A" data-tier="A" data-bs-toggle="tooltip" title="<?= htmlspecialchars($def['name'], ENT_QUOTES, 'UTF-8') ?>">
    <svg viewBox="0 0 32 32" width="<?= $size ?>" height="<?= $size ?>" aria-hidden="true">
      <path d="M16 3 L29 12 L24 28 L8 28 L3 12 Z" fill="<?= $tierColor ?>" stroke="#E65100" stroke-width="1"/>
      <path d="M16 9 L23 14 L20 23 L12 23 L9 14 Z" fill="#FFE0B2" opacity="0.4"/>
    </svg>
    <span class="visually-hidden"><?= htmlspecialchars($def['name'], ENT_QUOTES, 'UTF-8') ?></span>
  </span>
<?php elseif ($tier === 'B') : ?>
  <span class="rank-badge rank-badge--B" data-tier="B" data-bs-toggle="tooltip" title="<?= htmlspecialchars($def['name'], ENT_QUOTES, 'UTF-8') ?>">
    <svg viewBox="0 0 32 32" width="<?= $size ?>" height="<?= $size ?>" aria-hidden="true">
      <path d="M16 3 L26 8 L26 18 L16 28 L6 18 L6 8 Z" fill="<?= $tierColor ?>" stroke="#F57F17" stroke-width="1"/>
      <path d="M11 12 L21 12 L21 20 L11 20 Z" fill="#FFF59D" opacity="0.5"/>
    </svg>
    <span class="visually-hidden"><?= htmlspecialchars($def['name'], ENT_QUOTES, 'UTF-8') ?></span>
  </span>
<?php elseif ($tier === 'C') : ?>
  <span class="rank-badge rank-badge--C" data-tier="C" data-bs-toggle="tooltip" title="<?= htmlspecialchars($def['name'], ENT_QUOTES, 'UTF-8') ?>">
    <svg viewBox="0 0 32 32" width="<?= $size ?>" height="<?= $size ?>" aria-hidden="true">
      <path d="M16 2 L28 7 L28 18 L16 30 L4 18 L4 7 Z" fill="<?= $tierColor ?>" stroke="#1B5E20" stroke-width="1"/>
      <path d="M16 8 L22 11 L22 18 L16 24 L10 18 L10 11 Z" fill="#A5D6A7" opacity="0.5"/>
    </svg>
    <span class="visually-hidden"><?= htmlspecialchars($def['name'], ENT_QUOTES, 'UTF-8') ?></span>
  </span>
<?php elseif ($tier === 'D') : ?>
  <span class="rank-badge rank-badge--D" data-tier="D" data-bs-toggle="tooltip" title="<?= htmlspecialchars($def['name'], ENT_QUOTES, 'UTF-8') ?>">
    <svg viewBox="0 0 32 32" width="<?= $size ?>" height="<?= $size ?>" aria-hidden="true">
      <path d="M4 6 L28 6 L28 22 L16 30 L4 22 Z" fill="<?= $tierColor ?>" stroke="#0D47A1" stroke-width="1"/>
      <path d="M9 11 L23 11 L23 19 L16 24 L9 19 Z" fill="#BBDEFB" opacity="0.4"/>
    </svg>
    <span class="visually-hidden"><?= htmlspecialchars($def['name'], ENT_QUOTES, 'UTF-8') ?></span>
  </span>
<?php else : // E (Recruit) ?>
  <span class="rank-badge rank-badge--E" data-tier="E" data-bs-toggle="tooltip" title="<?= htmlspecialchars($def['name'], ENT_QUOTES, 'UTF-8') ?>">
    <svg viewBox="0 0 32 32" width="<?= $size ?>" height="<?= $size ?>" aria-hidden="true">
      <path d="M6 6 L26 6 L26 24 L16 28 L6 24 Z" fill="<?= $tierColor ?>" stroke="#616161" stroke-width="1"/>
      <circle cx="16" cy="16" r="4" fill="#E0E0E0" opacity="0.5"/>
    </svg>
    <span class="visually-hidden"><?= htmlspecialchars($def['name'], ENT_QUOTES, 'UTF-8') ?></span>
  </span>
<?php endif;
