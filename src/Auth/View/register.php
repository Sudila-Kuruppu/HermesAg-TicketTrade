<?php
/**
 * TicketTrade — Auth/View/register
 *
 * Centered card, max-width 480px. Field-level errors render under each
 * input via .invalid-feedback.d-block. The combined anti-enumeration
 * error (D-13) renders as an alert alert-danger at the top of the form.
 */
$csrf = $csrf_token ?? '';
$values = $values ?? [];
$error = $GLOBALS['_tt_form_error'] ?? null;
$fields = $error['fields'] ?? null;
$combined = ($error !== null && (empty($fields) || $fields === null)) ? ($error['message'] ?? null) : null;
?>
<section class="container register-shell" style="padding-top: var(--space-8, 48px); padding-bottom: var(--space-8, 48px);">
<div class="row justify-content-center">
<div class="col-12 col-sm-10 col-md-8 col-lg-6">
<div class="card surface-container shadow-sm">
<div class="card-body">
<h1 class="headline-md mb-1">Create your account</h1>
<p class="body-sm text-muted mb-4">Verified NSBM students only. You'll get +50 points after email verification.</p>

<?php if ($combined !== null): ?>
<div class="alert alert-danger" role="alert"><?= htmlspecialchars($combined, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if (!empty($fields) && is_array($fields) && $combined === null): ?>
<div class="alert alert-warning" role="alert">Please fix the highlighted fields.</div>
<?php endif; ?>

<form method="post" action="/register" novalidate>
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

<div class="mb-3">
<label class="form-label" for="email">Email</label>
<input type="email" name="email" id="email" required
class="form-control <?= isset($fields['email']) ? 'is-invalid' : '' ?>"
value="<?= htmlspecialchars((string) ($values['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
autocomplete="email" inputmode="email">
<div class="form-text">Use your <code>@students.nsbm.ac.lk</code> address.</div>
<?php if (isset($fields['email'])): ?>
<div class="invalid-feedback d-block"><?= htmlspecialchars($fields['email'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
</div>

<div class="mb-3">
<label class="form-label" for="student_id">Student ID</label>
<input type="text" name="student_id" id="student_id" required
class="form-control <?= isset($fields['student_id']) ? 'is-invalid' : '' ?>"
value="<?= htmlspecialchars((string) ($values['student_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
placeholder="NSBM/2024/001" autocomplete="off">
<div class="form-text">Format: <code>NSBM/YYYY/NNN</code></div>
<?php if (isset($fields['student_id'])): ?>
<div class="invalid-feedback d-block"><?= htmlspecialchars($fields['student_id'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
</div>

<div class="mb-3">
<label class="form-label" for="full_name">Full name</label>
<input type="text" name="full_name" id="full_name" required
class="form-control <?= isset($fields['full_name']) ? 'is-invalid' : '' ?>"
value="<?= htmlspecialchars((string) ($values['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
autocomplete="name">
<?php if (isset($fields['full_name'])): ?>
<div class="invalid-feedback d-block"><?= htmlspecialchars($fields['full_name'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
</div>

<div class="mb-3">
<label class="form-label" for="nickname">Nickname</label>
<input type="text" name="nickname" id="nickname" required
class="form-control <?= isset($fields['nickname']) ? 'is-invalid' : '' ?>"
value="<?= htmlspecialchars((string) ($values['nickname'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
pattern="[A-Za-z0-9_]{3,30}" autocomplete="username">
<div class="form-text">3–30 letters, numbers, or underscores. Locked after registration.</div>
<?php if (isset($fields['nickname'])): ?>
<div class="invalid-feedback d-block"><?= htmlspecialchars($fields['nickname'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
</div>

<div class="mb-4">
<label class="form-label" for="password">Password</label>
<input type="password" name="password" id="password" required minlength="8"
class="form-control <?= isset($fields['password']) ? 'is-invalid' : '' ?>"
autocomplete="new-password">
<div class="form-text">At least 8 characters.</div>
<?php if (isset($fields['password'])): ?>
<div class="invalid-feedback d-block"><?= htmlspecialchars($fields['password'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
</div>

<button type="submit" class="btn btn-primary w-100">Register</button>
</form>

<p class="body-sm text-center text-muted mt-3 mb-0">
Already have an account? <a href="/login">Sign in</a>
</p>
</div>
</div>
</div>
</div>
</section>
