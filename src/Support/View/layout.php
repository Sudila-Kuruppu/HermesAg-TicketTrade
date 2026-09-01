<?php
/**
 * TicketTrade — Layout Template
 *
 * Wraps every page with the design-token-driven chrome (head, flash
 * toast, skip link, content, bottom nav, toast container). The content
 * View is loaded from $GLOBALS['_tt_content_view'].
 *
 * @var array $_tt_view_vars    Variables passed by View::render
 * @var string $_tt_content_view Absolute path to the content view
 */

$surface = $GLOBALS['_tt_surface'] ?? 'student';
$vars = $GLOBALS['_tt_view_vars'] ?? [];
extract($vars, EXTR_SKIP);

$themeDefault = ($surface === 'admin' || $surface === 'public') ? 'light' : 'dark';
?><!DOCTYPE html>
<html lang="en" data-surface="<?= htmlspecialchars($surface, ENT_QUOTES, 'UTF-8') ?>" data-theme="<?= htmlspecialchars($themeDefault, ENT_QUOTES, 'UTF-8') ?>">
<?php require __DIR__ . '/partials/head.php'; ?>
<body class="surface-<?= htmlspecialchars($surface, ENT_QUOTES, 'UTF-8') ?>">
<?php require __DIR__ . '/partials/flash_toast.php'; ?>
<?php require __DIR__ . '/partials/skip_link.php'; ?>
<main id="main" tabindex="-1">
<?php
$contentView = $GLOBALS['_tt_content_view'] ?? '';
if ($contentView !== '' && is_file($contentView)) {
    require $contentView;
} else {
    echo '<p>Missing content view.</p>';
}
?>
</main>
<?php require __DIR__ . '/partials/bottom_nav.php'; ?>
<?php require __DIR__ . '/partials/toast_container.php'; ?>
</body>
</html>
