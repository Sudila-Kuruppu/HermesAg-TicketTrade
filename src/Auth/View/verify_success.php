<?php
/**
 * TicketTrade — Auth/View/verify_success
 *
 * Centered Bootstrap-style modal for the email-verified confirmation.
 * data-component="modal" hook lets the layout JS auto-open it.
 */
$nickname = $nickname ?? '';
$tier = $tier ?? 'D';
$error = $error ?? null;
?>
<section class="container verify-success-shell" style="padding-top: var(--space-8, 48px);">
<div class="row justify-content-center">
<div class="col-12 col-md-8 col-lg-6">
<div class="card surface-container shadow-sm">
<div class="card-body text-center p-5">
<?php if ($error !== null && $error !== ''): ?>
<svg viewBox="0 0 24 24" width="56" height="56" class="text-danger mb-3" aria-hidden="true">
<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>
<line x1="9" y1="9" x2="15" y2="15" stroke="currentColor" stroke-width="2"/>
<line x1="15" y1="9" x2="9" y2="15" stroke="currentColor" stroke-width="2"/>
</svg>
<h1 class="headline-md">Verification link invalid</h1>
<p class="body-md text-muted"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p>
<a href="/register" class="btn btn-outline-primary mt-3">Register again</a>
<?php else: ?>
<svg viewBox="0 0 24 24" width="56" height="56" class="text-success mb-3" aria-hidden="true">
<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>
<polyline points="8 12 11 15 16 9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
<h1 class="headline-md">Email verified! +50 points</h1>
<p class="body-md text-muted mb-4">
Welcome aboard, <strong>@<?= htmlspecialchars((string) $nickname, ENT_QUOTES, 'UTF-8') ?></strong>.
</p>
<div class="mb-4">
<?php $GLOBALS['_tt_view_vars'] = ['tier' => $tier]; require __DIR__ . '/../../Support/View/partials/rank_badge.php'; ?>
</div>
<a href="/board" class="btn btn-primary">Continue to board</a>
<?php endif; ?>
</div>
</div>
</div>
</div>
</section>
