<?php

/**
 * TicketTrade — Support\View\partials\star_rating_input
 *
 * Phase 5 Plan 05-01 Task 4. Reusable star-rating fieldset used by the
 * review compose modal (and any future review surface).
 *
 * Per D-01: 5 named radio inputs (1..5), visually hidden, with `<label>`
 * siblings showing 24px Bootstrap Icons (`bi-star` empty, `bi-star-fill`
 * filled). The fieldset's `<legend>` is `class="visually-hidden">Rating</legend>`.
 *
 * Per D-03 + EXPERIENCE.md L155:
 *   - Fieldset uses `display:inline-flex; flex-direction:row-reverse` so
 *     `:hover ~` and `:checked ~ label` flip the visual stacking order
 *     (highest star at the right, lowest at the left when rendered LTR).
 *   - On `:hover` and `:focus-within`, the visible label icons switch to
 *     `bi-star-fill` up to the hovered/focused value (handled by CSS).
 *   - Keyboard arrow keys cycle through radios (handled by the
 *     `star-rating` component in public/assets/js/tickettrade.js).
 *   - "Clear" link resets the selection to 0 (handled by JS).
 *   - The Submit button stays disabled until a rating is selected
 *     (CSS-only via :checked).
 *
 * Variables (set via $GLOBALS['_tt_view_vars']):
 *   name         string  Form field name (default 'rating').
 *   currentValue int     Pre-selected value 1..5 or 0 (default 0).
 *   unique_id    string  Per-instance id suffix (default 'global').
 *
 * The partial is self-contained: it does NOT render a wrapper <form> or
 * modal — the calling view owns those.
 */

declare(strict_types=1);

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$name = (string) ($__vars['name'] ?? 'rating');
$currentValue = (int) ($__vars['current_value'] ?? 0);
$uniqueId = (string) ($__vars['unique_id'] ?? 'global');

// Render radios 5..1 so the DOM order is descending; the fieldset is
// flex-row-reverse, so the visual stacking is 1..5 left-to-right.
?>
<fieldset class="star-rating-input"
        data-component="star-rating-input"
        name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
  <legend class="visually-hidden">Rating</legend>
  <?php for ($i = 5; $i >= 1; $i--) :
        $inputId = "rating-{$i}-{$uniqueId}";
        $checked = ($currentValue === $i) ? 'checked' : '';
        ?>
    <input type="radio"
           id="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>"
           name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
           value="<?= (int) $i ?>"
           class="visually-hidden"
           data-rating-value="<?= (int) $i ?>"
           required
           <?= $checked ?>>
    <label for="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>"
           class="star-rating-input__icon bi bi-star"
           aria-label="<?= (int) $i ?> of 5"
           data-rating-icon="<?= (int) $i ?>"></label>
  <?php endfor; ?>
  <a href="#"
     class="star-rating-input__clear"
     data-action="clear"
     data-target="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">Clear</a>
</fieldset>