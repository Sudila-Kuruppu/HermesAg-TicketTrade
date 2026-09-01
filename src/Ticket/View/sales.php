<?php

/**
 * TicketTrade — My Sales placeholder
 *
 * Phase 2 Plan 02-02 ships the auth-required route guard + this
 * "coming soon" card. Phase 4 fills the real data.
 */

$phase_label = $phase_label ?? 'Phase 4';
?>
<section class="container placeholder-shell" style="padding-top: var(--space-8, 48px);">
<div class="row justify-content-center">
<div class="col-12 col-md-10 col-lg-8">
<div class="card surface-container shadow-sm">
<div class="card-body p-4 p-md-5 text-center">
<span class="badge text-bg-secondary mb-2">Coming soon</span>
<h1 class="headline-md mb-2"><?= htmlspecialchars($phase_label, ENT_QUOTES, 'UTF-8') ?></h1>
<p class="body-md text-muted mb-4">This page is wired by Plan 02-02 (auth guard + placeholder). The real data lands in <?= htmlspecialchars($phase_label, ENT_QUOTES, 'UTF-8') ?>.</p>
<a href="/board" class="btn btn-outline-primary">Back to board</a>
</div>
</div>
</div>
</div>
</section>
