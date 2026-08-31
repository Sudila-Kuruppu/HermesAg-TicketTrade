<?php
$selected = isset($selected) ? (int) $selected : 1;
$selected = max(1, min(12, $selected));
$name = $name ?? 'avatar_id';
?>
<fieldset class="avatar-picker" aria-label="Choose an avatar">
<legend class="form-label">Avatar</legend>
<div class="avatar-grid">
<?php for ($i = 1; $i <= 12; $i++): ?>
  <label class="avatar-tile <?= $i === $selected ? 'is-selected' : '' ?>" aria-pressed="<?= $i === $selected ? 'true' : 'false' ?>">
    <input type="radio" name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" value="<?= $i ?>" <?= $i === $selected ? 'checked' : '' ?>>
    <img src="/assets/img/avatars/avatar-<?= $i ?>.svg" alt="Avatar {{$i}}">
  </label>
<?php endfor; ?>
</div>
</fieldset>
