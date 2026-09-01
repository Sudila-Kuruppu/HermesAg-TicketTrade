<?php
/**
 * TicketTrade — Auth/View/forgot_password
 *
 * Centered card with a single email field. The hint copy matches
 * the D-07 anti-enumeration promise.
 */
$csrf = $csrf_token ?? '';
$values = $values ?? [];
$error = $GLOBALS['_tt_form_error'] ?? null;
$fields = $error['fields'] ?? null;
$errMsg = ($error !== null && empty($fields)) ? ($error['message'] ?? null) : null;
?>
<section class="container forgot-shell" style="padding-top: var(--space-8, 48px);">
<div class="row justify-content-center">
<div class="col-12 col-sm-10 col-md-8 col-lg-5">
<div class="card surface-container shadow-sm">
<div class="card-body p-4 p-md-5">
<h1 class="headline-md mb-2">Forgot password</h1>
<p class="body-sm text-muted mb-4">If that email is registered, a reset link is in your inbox.</p>

<?php if ($errMsg !== null && $errMsg !== ''): ?>
<div class="alert alert-danger" role="alert"><?= htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form method="post" action="/forgot-password" novalidate>
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

<div class="mb-3">
<label class="form-label" for="email">Email</label>
<input type="email" name="email" id="email" required
class="form-control <?= isset($fields['email']) ? 'is-invalid' : '' ?>"
value="<?= htmlspecialchars((string) ($values['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
autocomplete="email" inputmode="email">
<?php if (isset($fields['email'])): ?>
<div class="invalid-feedback d-block"><?= htmlspecialchars($fields['email'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
</div>

<button type="submit" class="btn btn-primary w-100">Send reset link</button>
</form>

<p class="body-sm text-center text-muted mt-3 mb-0">
<a href="/login">Back to sign in</a>
</p>
</div>
</div>
</div>
</div>
</section>
