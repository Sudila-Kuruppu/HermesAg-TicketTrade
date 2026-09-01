<?php

/**
 * TicketTrade — Listing\View\edit
 *
 * Phase 3 Plan 03-02. The /listings/{id}/edit form. Mirrors create.php
 * markup but pre-populates values from the existing listing row.
 *
 * On load:
 *   - rejected → flip to draft (D-04); show the rejection reason banner.
 *   - active   → set review_flag warning in vars; the Service sets
 *                review_flag=1 on POST.
 *
 * Vars:
 *   csrf_token     (string)
 *   listing        (array)     Current listing row
 *   images         (array)     Existing listing_images rows
 *   errors         (array)     Field-level error map (re-render path)
 *   values         (array)     Submitted values (preserve on error)
 *   top_error      (string)    Optional top-of-form message
 *   categories     (array)     Active categories (for re-render)
 */

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$csrfToken = (string) ($__vars['csrf_token'] ?? '');
$listing = $__vars['listing'] ?? [];
$images = $__vars['images'] ?? [];
$errors = $__vars['errors'] ?? [];
$values = $__vars['values'] ?? $listing;
$topError = (string) ($__vars['top_error'] ?? '');
$categories = $__vars['categories'] ?? [];

$rejectionReason = (string) ($listing['rejection_reason'] ?? '');
$reviewFlag = !empty($listing['review_flag']);
$status = (string) ($listing['status'] ?? 'draft');
$selectedType = (string) ($values['type'] ?? $listing['type'] ?? 'product');
$selectedCategory = (int) ($values['category_id'] ?? $listing['category_id'] ?? 0);
$hasError = static fn(string $k) => isset($errors[$k]);
$val = static fn(string $k, $d = '') => htmlspecialchars((string) ($values[$k] ?? $d), ENT_QUOTES, 'UTF-8');

$listingId = (int) ($listing['id'] ?? 0);
?>
<section class="container py-4 edit-listing-shell">
<div class="row justify-content-center">
<div class="col-12 col-md-10 col-lg-8">

<h1 class="headline-md mb-4">Edit listing</h1>

<?php if ($rejectionReason !== '') : ?>
<div class="alert alert-danger" role="alert" aria-live="polite">
<strong>Rejection note:</strong> <?= htmlspecialchars($rejectionReason, ENT_QUOTES, 'UTF-8') ?>
<p class="mb-0 mt-2">This listing has been flipped back to draft. Edit and resubmit when ready.</p>
</div>
<?php endif; ?>

<?php if ($reviewFlag) : ?>
<div class="alert alert-warning" role="alert" aria-live="polite">
Edits to active listings are pending admin review.
</div>
<?php endif; ?>

<?php if ($topError !== '') : ?>
<div class="alert alert-danger" role="alert" aria-live="polite">
<?= htmlspecialchars($topError, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<?php if (!empty($images)) : ?>
<div class="mb-4">
<h2 class="h6">Existing images</h2>
<div class="d-flex flex-wrap gap-2">
<?php foreach ($images as $img) :
    $sha = (string) ($img['sha256'] ?? '');
    $sz = (string) ($img['size'] ?? 'thumb');
    ?>
<img src="/img/<?= $listingId ?>/thumb" class="rounded" width="80" height="80" alt="">
<?php endforeach; ?>
</div>
<small class="text-muted"><?= count($images) ?> image<?= count($images) === 1 ? '' : 's' ?> attached.</small>
</div>
<?php endif; ?>

<form method="POST" action="/listings/<?= $listingId ?>/edit" enctype="multipart/form-data" class="row g-3" novalidate>
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
$selectedCondition = (string) ($values['condition'] ?? $listing['condition'] ?? '');
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
</div>

<div class="col-md-6 type-service-field" <?= $selectedType !== 'service' ? 'hidden' : '' ?>>
<label for="delivery-input" class="form-label">Delivery method</label>
<select id="delivery-input" name="delivery_method"
        class="form-select <?= $hasError('delivery_method') ? 'is-invalid' : '' ?>">
<option value="">Select delivery method</option>
<?php
$dm = ['in_person' => 'In person', 'online' => 'Online', 'hybrid' => 'Hybrid'];
$selectedDm = (string) ($values['delivery_method'] ?? $listing['delivery_method'] ?? '');
foreach ($dm as $k => $label) :
    $sel = ($k === $selectedDm) ? ' selected' : '';
    ?>
<option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"<?= $sel ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-12 type-service-field" <?= $selectedType !== 'service' ? 'hidden' : '' ?>>
<label for="availability-input" class="form-label">Availability</label>
<textarea id="availability-input" name="availability" maxlength="500" rows="2"
        class="form-control <?= $hasError('availability') ? 'is-invalid' : '' ?>"><?= $val('availability') ?></textarea>
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
<label for="images-input" class="form-label">Add images</label>
<input type="file" id="images-input" name="images[]" multiple accept="image/*"
       class="form-control">
<small class="text-muted">New images will be added to existing ones (max 8 total).</small>
</div>

<div class="col-12 d-flex gap-2 justify-content-end">
<?php if ($status === 'rejected' || $status === 'draft') : ?>
<button type="submit" name="action" value="save_draft" class="btn btn-outline-secondary">Save as draft</button>
<button type="submit" name="action" value="submit" class="btn btn-primary">Resubmit for review</button>
<?php else : ?>
<button type="submit" class="btn btn-primary">Save changes</button>
<?php endif; ?>
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
