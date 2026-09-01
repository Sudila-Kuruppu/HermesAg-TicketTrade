<?php

/**
 * TicketTrade — Listing\View\create
 *
 * Phase 3 Plan 03-02. The /listings/create form. POSTs to itself
 * with two submit buttons: action=save_draft OR action=submit. Field
 * errors are surfaced via Bootstrap's `is-invalid` + `invalid-feedback`
 * pair; the previously-submitted values are preserved (UX-DR-XX).
 *
 * Vars from CreateListingAction:
 *   csrf_token     (string)
 *   categories     (array)     List of active categories
 *   errors         (array)     Field-level error map (key → message)
 *   values         (array)     Submitted values (preserve on error)
 *   upload_errors  (array)     Optional per-image upload errors
 *   top_error      (string)    Optional top-of-form message
 */

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$csrfToken = (string) ($__vars['csrf_token'] ?? '');
$categories = $__vars['categories'] ?? [];
$errors = $__vars['errors'] ?? [];
$values = $__vars['values'] ?? [];
$uploadErrors = $__vars['upload_errors'] ?? [];
$topError = (string) ($__vars['top_error'] ?? '');

$selectedType = (string) ($values['type'] ?? 'product');
$selectedCategory = (int) ($values['category_id'] ?? 0);
$hasError = static fn(string $k) => isset($errors[$k]);
$val = static fn(string $k, $d = '') => htmlspecialchars((string) ($values[$k] ?? $d), ENT_QUOTES, 'UTF-8');
?>
<section class="container py-4 create-listing-shell">
<div class="row justify-content-center">
<div class="col-12 col-md-10 col-lg-8">

<h1 class="headline-md mb-4">Create a listing</h1>

<?php if ($topError !== '') : ?>
<div class="alert alert-danger" role="alert" aria-live="polite">
    <?= htmlspecialchars($topError, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<?php if (!empty($uploadErrors)) : ?>
<div class="alert alert-warning" role="alert" aria-live="polite">
<p class="mb-1"><strong>Some images failed to upload:</strong></p>
<ul class="mb-0">
    <?php foreach ($uploadErrors as $ue) : ?>
<li><?= htmlspecialchars((string) ($ue['message'] ?? 'upload error'), ENT_QUOTES, 'UTF-8') ?></li>
    <?php endforeach; ?>
</ul>
</div>
<?php endif; ?>

<form method="POST" action="/listings/create" enctype="multipart/form-data" class="row g-3" novalidate>
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

<div class="col-12">
<label for="title-input" class="form-label">Title</label>
<input type="text" id="title-input" name="title" maxlength="80"
       class="form-control <?= $hasError('title') ? 'is-invalid' : '' ?>"
       aria-describedby="title-err"
       value="<?= $val('title') ?>" required>
<?php if ($hasError('title')) : ?>
<div id="title-err" class="invalid-feedback">
    <?= htmlspecialchars((string) $errors['title'], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>
</div>

<div class="col-12">
<label for="description-input" class="form-label">Description</label>
<textarea id="description-input" name="description" maxlength="2000" rows="4"
          class="form-control <?= $hasError('description') ? 'is-invalid' : '' ?>"
          aria-describedby="description-err" required><?= $val('description') ?></textarea>
<?php if ($hasError('description')) : ?>
<div id="description-err" class="invalid-feedback">
    <?= htmlspecialchars((string) $errors['description'], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>
</div>

<div class="col-md-6">
<label for="price-input" class="form-label">Price</label>
<div class="input-group">
<span class="input-group-text">LKR</span>
<input type="number" id="price-input" name="price_rupees" min="1" max="100000"
       class="form-control <?= $hasError('price_cents') ? 'is-invalid' : '' ?>"
       aria-describedby="price-err"
       value="<?= $val('price_rupees') ?>" required>
</div>
<input type="hidden" name="price_cents" value="<?= $val('price_cents') ?>">
<?php if ($hasError('price_cents')) : ?>
<div id="price-err" class="invalid-feedback">
    <?= htmlspecialchars((string) $errors['price_cents'], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>
</div>

<div class="col-md-6">
<label for="category-input" class="form-label">Category</label>
<select id="category-input" name="category_id"
        class="form-select <?= $hasError('category_id') ? 'is-invalid' : '' ?>"
        aria-describedby="category-err" required>
<option value="">Select a category</option>
<?php foreach ($categories as $cat) :
    $cid = (int) ($cat['id'] ?? 0);
    $sel = ($cid === $selectedCategory) ? ' selected' : '';
    ?>
<option value="<?= $cid ?>"<?= $sel ?>><?= htmlspecialchars((string) ($cat['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
</select>
<?php if ($hasError('category_id')) : ?>
<div id="category-err" class="invalid-feedback">
    <?= htmlspecialchars((string) $errors['category_id'], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>
</div>

<fieldset class="col-12">
<legend class="form-label">Type</legend>
<div class="d-flex gap-3">
<div class="form-check">
<input class="form-check-input" type="radio" name="type" id="type-product" value="product"
       <?= $selectedType === 'product' ? 'checked' : '' ?> required>
<label class="form-check-label" for="type-product">Product</label>
</div>
<div class="form-check">
<input class="form-check-input" type="radio" name="type" id="type-service" value="service"
       <?= $selectedType === 'service' ? 'checked' : '' ?>>
<label class="form-check-label" for="type-service">Service</label>
</div>
</div>
<?php if ($hasError('type')) : ?>
<div class="invalid-feedback d-block">
    <?= htmlspecialchars((string) $errors['type'], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>
</fieldset>

<div class="col-md-6 type-product-field" <?= $selectedType !== 'product' ? 'hidden' : '' ?>>
<label for="condition-input" class="form-label">Condition</label>
<select id="condition-input" name="condition"
        class="form-select <?= $hasError('condition') ? 'is-invalid' : '' ?>">
<option value="">Select condition</option>
<?php
$conditions = ['new' => 'New', 'like_new' => 'Like new', 'good' => 'Good', 'fair' => 'Fair'];
$selectedCondition = (string) ($values['condition'] ?? '');
foreach ($conditions as $k => $label) :
    $sel = ($k === $selectedCondition) ? ' selected' : '';
    ?>
<option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"<?= $sel ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
</select>
<?php if ($hasError('condition')) : ?>
<div class="invalid-feedback d-block">
    <?= htmlspecialchars((string) $errors['condition'], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>
</div>

<div class="col-md-6 type-service-field" <?= $selectedType !== 'service' ? 'hidden' : '' ?>>
<label for="duration-input" class="form-label">Duration (minutes)</label>
<input type="number" id="duration-input" name="duration_minutes" min="1" max="600"
       class="form-control <?= $hasError('duration_minutes') ? 'is-invalid' : '' ?>"
       value="<?= $val('duration_minutes') ?>">
<?php if ($hasError('duration_minutes')) : ?>
<div class="invalid-feedback d-block">
    <?= htmlspecialchars((string) $errors['duration_minutes'], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>
</div>

<div class="col-md-6 type-service-field" <?= $selectedType !== 'service' ? 'hidden' : '' ?>>
<label for="delivery-input" class="form-label">Delivery method</label>
<select id="delivery-input" name="delivery_method"
        class="form-select <?= $hasError('delivery_method') ? 'is-invalid' : '' ?>">
<option value="">Select delivery method</option>
<?php
$dm = ['in_person' => 'In person', 'online' => 'Online', 'hybrid' => 'Hybrid'];
$selectedDm = (string) ($values['delivery_method'] ?? '');
foreach ($dm as $k => $label) :
    $sel = ($k === $selectedDm) ? ' selected' : '';
    ?>
<option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"<?= $sel ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
</select>
<?php if ($hasError('delivery_method')) : ?>
<div class="invalid-feedback d-block">
    <?= htmlspecialchars((string) $errors['delivery_method'], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>
</div>

<div class="col-12 type-service-field" <?= $selectedType !== 'service' ? 'hidden' : '' ?>>
<label for="availability-input" class="form-label">Availability</label>
<textarea id="availability-input" name="availability" maxlength="500" rows="2"
        class="form-control <?= $hasError('availability') ? 'is-invalid' : '' ?>"><?= $val('availability') ?></textarea>
<?php if ($hasError('availability')) : ?>
<div class="invalid-feedback d-block">
    <?= htmlspecialchars((string) $errors['availability'], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>
</div>

<div class="col-md-6">
<label for="quantity-input" class="form-label">Quantity</label>
<input type="number" id="quantity-input" name="quantity" min="1" max="999"
       class="form-control <?= $hasError('quantity') ? 'is-invalid' : '' ?>"
       aria-describedby="quantity-err"
       value="<?= $val('quantity', '1') ?>" required>
<?php if ($hasError('quantity')) : ?>
<div id="quantity-err" class="invalid-feedback">
    <?= htmlspecialchars((string) $errors['quantity'], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>
</div>

<div class="col-12">
<label for="images-input" class="form-label">Images</label>
<input type="file" id="images-input" name="images[]" multiple accept="image/*"
       class="form-control <?= $hasError('images') ? 'is-invalid' : '' ?>">
<small class="text-muted">Up to 8 images, max 5MB each, formats: JPG, PNG, WebP</small>
<?php if ($hasError('images')) : ?>
<div class="invalid-feedback d-block">
    <?= htmlspecialchars((string) $errors['images'], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>
</div>

<div class="col-12 d-flex gap-2 justify-content-end">
<button type="submit" name="action" value="save_draft" class="btn btn-outline-secondary">Save as draft</button>
<button type="submit" name="action" value="submit" class="btn btn-primary">Submit for review</button>
</div>
</form>
</div>
</div>
</section>

<script>
(function () {
  var productFields = document.querySelectorAll('.type-product-field');
  var serviceFields = document.querySelectorAll('.type-service-field');
  function applyTypeVisibility() {
    var t = (document.querySelector('input[name="type"]:checked') || {}).value || 'product';
    productFields.forEach(function (el) { el.hidden = (t !== 'product'); });
    serviceFields.forEach(function (el) { el.hidden = (t !== 'service'); });
  }
  document.querySelectorAll('input[name="type"]').forEach(function (el) {
    el.addEventListener('change', applyTypeVisibility);
  });
  applyTypeVisibility();
})();
</script>
