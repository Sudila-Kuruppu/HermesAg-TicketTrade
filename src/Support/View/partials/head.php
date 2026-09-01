<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title ?? 'TicketTrade', ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="/assets/css/tickettrade.css">
<script>
// Phase 1 FOUC-guard inline script (D-05 of Phase 1 CONTEXT).
// Reads stored theme BEFORE the CSS resolves so the page never flashes the wrong theme.
(function () {
  try {
    var stored = localStorage.getItem('tickettrade-theme');
    var theme = stored || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    var surface = document.documentElement.getAttribute('data-surface') || 'student';
    if (surface === 'admin') { theme = theme === 'dark' ? 'light' : theme; }
    document.documentElement.setAttribute('data-theme', theme);
  } catch (e) {}
})();
</script>
<script defer src="/assets/js/tickettrade.js"></script>
<script defer src="/assets/js/listing_modal.js"></script>
</head>
