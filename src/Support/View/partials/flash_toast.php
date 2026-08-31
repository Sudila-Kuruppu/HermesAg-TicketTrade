<?php
if (!empty($GLOBALS['_tt_flash_toast'])) {
    $flash = $GLOBALS['_tt_flash_toast'];
    $type = htmlspecialchars($flash['type'] ?? 'info', ENT_QUOTES, 'UTF-8');
    $msg = htmlspecialchars($flash['message'] ?? '', ENT_QUOTES, 'UTF-8');
    echo '<div data-flash-toast="' . $type . '" hidden>' . $msg . '</div>';
}
?>
