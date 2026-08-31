<?php
$tier = $tier ?? 'E';
$ladder = require APP_ROOT . '/config/ranks.php';
$def = $ladder[$tier] ?? $ladder['E'];
$cls = $def['badge_class'];
$isLegend = $tier === 'S';
?>
<span class="rank-badge <?= htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') ?> <?= $isLegend ? 'legend-glow' : '' ?>" data-tier="<?= htmlspecialchars($tier, ENT_QUOTES, 'UTF-8') ?>">
  <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="currentColor" opacity="0.18"/><circle cx="12" cy="12" r="5" fill="currentColor"/></svg>
  <?= htmlspecialchars($def['name'], ENT_QUOTES, 'UTF-8') ?>
</span>
