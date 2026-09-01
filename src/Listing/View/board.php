<?php

/**
 * TicketTrade — Listing/View/board
 *
 * Phase 2 Plan 02-02. Public-browse placeholder per D-09. Real listings
 * data lands in Phase 3. The welcome message reads the current user's
 * nickname when logged in; guests see a generic welcome.
 *
 * The flash-toast container is rendered by the layout; this view does
 * not emit the toast markup itself.
 */

$nickname = $nickname ?? null;
?>
<section class="container board-shell" style="padding-top: var(--space-8, 48px);">
<div class="row justify-content-center">
<div class="col-12 col-md-10 col-lg-8">
<div class="card surface-container shadow-sm mb-4">
<div class="card-body p-4 p-md-5 text-center">
<h1 class="headline-md mb-2">
<?php if ($nickname !== null && $nickname !== '') : ?>
Welcome to the board, @<?= htmlspecialchars($nickname, ENT_QUOTES, 'UTF-8') ?>
<?php else : ?>
Welcome, guest
<?php endif; ?>
</h1>
<p class="body-md text-muted mb-0">Browse listings from verified NSBM students.</p>
</div>
</div>

<div class="row g-3">
<?php for ($i = 1; $i <= 3; $i++) : ?>
<div class="col-12 col-md-4">
<div class="card surface-container h-100">
<div class="card-body">
<span class="badge text-bg-secondary mb-2">Phase 3 placeholder</span>
<h2 class="h6 mb-2">Sample listing #<?= $i ?></h2>
<p class="body-sm text-muted mb-3">Real listings data lands in Phase 3.</p>
    <?php if ($nickname === null || $nickname === '') : ?>
<a href="/login?next=/board" class="btn btn-outline-primary btn-sm">Sign in to buy</a>
    <?php else : ?>
<button type="button" class="btn btn-outline-primary btn-sm" disabled>Buy (Phase 3)</button>
    <?php endif; ?>
</div>
</div>
</div>
<?php endfor; ?>
</div>
</div>
</div>
</section>
