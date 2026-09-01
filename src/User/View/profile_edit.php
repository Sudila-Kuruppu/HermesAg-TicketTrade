<?php
/**
 * TicketTrade — User/View/profile_edit
 *
 * Phase 2 Plan 02-02. The form has NO nickname field (D-15 — locked
 * at registration). Avatar picker uses the existing partial.
 */
$csrf = $csrf_token ?? '';
$values = $values ?? [];
$profile = $profile ?? [];
$error = $GLOBALS['_tt_form_error'] ?? null;
$fields = $error['fields'] ?? null;

$selected = (int) ($values['avatar_id'] ?? ($profile['avatar_id'] ?? 1));
$selected = max(1, min(12, $selected));
$whatsapp = (string) ($values['whatsapp'] ?? ($profile['whatsapp'] ?? ''));
$bio = (string) ($values['bio'] ?? ($profile['bio'] ?? ''));
$fullName = (string) ($values['full_name'] ?? ($profile['full_name'] ?? ''));
?>
<section class="container profile-shell" style="padding-top: var(--space-8, 48px);">
<div class="row justify-content-center">
<div class="col-12 col-md-10 col-lg-8">
<div class="card surface-container shadow-sm">
<div class="card-body p-4 p-md-5">
<h1 class="headline-md mb-2">Edit profile</h1>
<p class="body-sm text-muted mb-4">Update your public profile. Nickname is locked.</p>

<?php if ($error !== null && !empty($fields) && is_array($fields)): ?>
<div class="alert alert-warning" role="alert">Please fix the highlighted fields.</div>
<?php endif; ?>

<form method="post" action="/profile" novalidate>
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

<div class="mb-3">
<label class="form-label" for="full_name">Full name</label>
<input type="text" name="full_name" id="full_name" required
class="form-control <?= isset($fields['full_name']) ? 'is-invalid' : '' ?>"
value="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>"
autocomplete="name">
<?php if (isset($fields['full_name'])): ?>
<div class="invalid-feedback d-block"><?= htmlspecialchars($fields['full_name'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
</div>

<div class="mb-3">
<label class="form-label" for="bio">Bio</label>
<textarea name="bio" id="bio" maxlength="500" rows="3"
class="form-control <?= isset($fields['bio']) ? 'is-invalid' : '' ?>"
data-counter-target="bio-counter"
oninput="document.getElementById('bio-counter').textContent = this.value.length + '/500';"
><?= htmlspecialchars($bio, ENT_QUOTES, 'UTF-8') ?></textarea>
<div class="form-text"><span id="bio-counter"><?= strlen($bio) ?>/500</span></div>
<?php if (isset($fields['bio'])): ?>
<div class="invalid-feedback d-block"><?= htmlspecialchars($fields['bio'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
</div>

<div class="mb-3">
<label class="form-label" for="whatsapp">WhatsApp</label>
<input type="text" name="whatsapp" id="whatsapp"
class="form-control <?= isset($fields['whatsapp']) ? 'is-invalid' : '' ?>"
value="<?= htmlspecialchars($whatsapp, ENT_QUOTES, 'UTF-8') ?>"
placeholder="+94771234567 or 0771234567" autocomplete="tel">
<div class="form-text">Sri Lankan mobile. Format: <code>+94XXXXXXXXX</code> or <code>07XXXXXXXX</code>. Private — never shown on the public profile.</div>
<?php if (isset($fields['whatsapp'])): ?>
<div class="invalid-feedback d-block"><?= htmlspecialchars($fields['whatsapp'], ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
</div>

<div class="mb-4">
<?php
$GLOBALS['_tt_view_vars'] = ['selected' => $selected, 'name' => 'avatar_id'];
require __DIR__ . '/../../Support/View/partials/avatar_picker.php';
?>
</div>

<button type="submit" class="btn btn-primary">Save profile</button>
</form>
</div>
</div>
</div>
</div>
</section>
