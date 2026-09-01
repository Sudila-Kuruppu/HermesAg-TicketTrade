<?php

/**
 * TicketTrade — Auth/View/reset_password
 *
 * Password + confirm form. The token is a hidden input. The
 * "invalid" flag renders the "Verification link is invalid" card.
 */

$csrf = $csrf_token ?? '';
$token = $token ?? '';
$invalid = $invalid ?? false;
$error = $GLOBALS['_tt_form_error'] ?? null;
$fields = $error['fields'] ?? null;
?>
<section class="container reset-shell" style="padding-top: var(--space-8, 48px);">
<div class="row justify-content-center">
<div class="col-12 col-sm-10 col-md-8 col-lg-5">
<div class="card surface-container shadow-sm">
<div class="card-body p-4 p-md-5">
<?php if ($invalid) : ?>
<svg viewBox="0 0 24 24" width="48" height="48" class="text-danger mb-3" aria-hidden="true">
<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>
<line x1="9" y1="9" x2="15" y2="15" stroke="currentColor" stroke-width="2"/>
<line x1="15" y1="9" x2="9" y2="15" stroke="currentColor" stroke-width="2"/>
</svg>
<h1 class="headline-md">Verification link invalid</h1>
<p class="body-md text-muted">This reset link is invalid, expired, or has already been used. Try requesting a new one.</p>
<a href="/forgot-password" class="btn btn-primary mt-3">Request a new link</a>
<?php else : ?>
<h1 class="headline-md mb-3">Reset your password</h1>
<p class="body-sm text-muted mb-4">Enter a new password (8+ characters). You'll be signed in automatically.</p>

    <?php if ($error !== null && !empty($fields) && is_array($fields)) : ?>
<div class="alert alert-warning" role="alert">Please fix the highlighted fields.</div>
    <?php endif; ?>

<form method="post" action="/reset-password" novalidate>
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

<div class="mb-3">
<label class="form-label" for="password">New password</label>
<input type="password" name="password" id="password" required minlength="8"
class="form-control <?= isset($fields['password']) ? 'is-invalid' : '' ?>"
autocomplete="new-password">
<div class="form-text">At least 8 characters.</div>
    <?php if (isset($fields['password'])) : ?>
<div class="invalid-feedback d-block"><?= htmlspecialchars($fields['password'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
</div>

<div class="mb-4">
<label class="form-label" for="password_confirm">Confirm new password</label>
<input type="password" name="password_confirm" id="password_confirm" required minlength="8"
class="form-control <?= isset($fields['password_confirm']) ? 'is-invalid' : '' ?>"
autocomplete="new-password">
    <?php if (isset($fields['password_confirm'])) : ?>
<div class="invalid-feedback d-block"><?= htmlspecialchars($fields['password_confirm'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
</div>

<button type="submit" class="btn btn-primary w-100">Reset password</button>
</form>
<?php endif; ?>
</div>
</div>
</div>
</div>
</section>
