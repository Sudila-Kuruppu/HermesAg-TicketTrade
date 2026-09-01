<?php

/**
 * TicketTrade — Support\View\partials\empty_state
 *
 * The named empty-state component used on My Listings, board, and
 * ticket surfaces. Uses EXPERIENCE.md copy verbatim where applicable.
 *
 * Vars:
 *   title     (string, required)  Bold one-line heading
 *   body      (string, required)  Supporting explanation
 *   cta_label (string, optional)  Primary CTA label; required if cta_href set
 *   cta_href  (string, optional)  Primary CTA link
 */

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$title = (string) ($__vars['title'] ?? '');
$body = (string) ($__vars['body'] ?? '');
$ctaLabel = (string) ($__vars['cta_label'] ?? '');
$ctaHref = (string) ($__vars['cta_href'] ?? '');
?>
<div class="text-center py-5 empty-state" data-component="empty-state">
<h2 class="h4 text-on-surface-variant empty-state-title">
<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
</h2>
<?php if ($body !== '') : ?>
<p class="text-on-surface-variant empty-state-body">
    <?= htmlspecialchars($body, ENT_QUOTES, 'UTF-8') ?>
</p>
<?php endif; ?>
<?php if ($ctaLabel !== '' && $ctaHref !== '') : ?>
<a class="btn btn-primary empty-state-cta" href="<?= htmlspecialchars($ctaHref, ENT_QUOTES, 'UTF-8') ?>">
    <?= htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8') ?>
</a>
<?php endif; ?>
</div>
