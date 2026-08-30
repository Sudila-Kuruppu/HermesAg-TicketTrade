<?php
/**
 * TicketTrade — Phase 1 Stub Landing Page
 *
 * Minimal HTML proving the routing layer is wired and the front
 * controllers can return HTTP 200 with the expected data-surface.
 * Phase 2 replaces this with real dispatch and content.
 *
 * @var string $_tt_surface  'student' or 'admin' — set by Router
 */

$surface = $GLOBALS['_tt_surface'] ?? 'student';
$themeDefault = $surface === 'admin' ? 'light' : 'dark';
?><!DOCTYPE html>
<html lang="en" data-surface="<?= htmlspecialchars($surface, ENT_QUOTES, 'UTF-8') ?>" data-theme="<?= htmlspecialchars($themeDefault, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TicketTrade — Phase 1 UX Foundation</title>
  <link rel="stylesheet" href="/assets/css/tickettrade.css">
</head>
<body class="surface-<?= htmlspecialchars($surface, ENT_QUOTES, 'UTF-8') ?>">
  <main id="main" tabindex="-1">
    <div class="container" style="padding-top: var(--space-8, 48px);">
      <h1 class="headline-md">TicketTrade — Phase 1 UX Foundation</h1>
      <p class="body-md">
        Design tokens, theme persistence, and three mockup surfaces are live. Routes land in Phase 2.
      </p>
    </div>
  </main>
</body>
</html>
