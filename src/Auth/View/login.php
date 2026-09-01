<?php
/**
 * TicketTrade — Auth/View/login
 *
 * Centered card, max-width 400px. The inline alert-danger renders
 * ABOVE the form fields (per D-12: not a flash toast).
 */
$csrf = $csrf_token ?? '';
$next = $next ?? '';
$values = $values ?? [];
$error = $GLOBALS['_tt_form_error'] ?? null;
$errMsg = $error['message'] ?? null;
$errCode = $error['code'] ?? null;
?>
<section class="container login-shell" style="padding-top: var(--space-8, 48px); padding-bottom: var(--space-8, 48px);">
<div class="row justify-content-center">
<div class="col-12 col-sm-10 col-md-8 col-lg-5">
<div class="card surface-container shadow-sm">
<div class="card-body p-4 p-md-5">
<h1 class="headline-md text-center mb-4">Sign in</h1>

<?php if ($errMsg !== null && $errMsg !== ''): ?>
<div class="alert alert-danger" role="alert"><?= htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form method="post" action="/login" novalidate>
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">

<div class="mb-3">
<label class="form-label" for="email">Email</label>
<input type="email" name="email" id="email" required
class="form-control"
value="<?= htmlspecialchars((string) ($values['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
autocomplete="email" inputmode="email">
</div>

<div class="mb-4">
<label class="form-label" for="password">Password</label>
<input type="password" name="password" id="password" required
class="form-control" autocomplete="current-password">
</div>

<button type="submit" class="btn btn-primary w-100 mb-3">Sign in</button>

<div class="d-flex justify-content-between body-sm">
<a href="/register">Register</a>
<a href="/forgot-password">Forgot password?</a>
</div>
</form>
</div>
</div>
</div>
</div>
</section>
