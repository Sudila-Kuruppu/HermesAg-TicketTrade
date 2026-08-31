<?php
$note = $note ?? 'Coming soon.';
?><section class="placeholder-card container" style="padding-top: var(--space-8, 48px);">
<h1 class="headline-md">Phase 2 Substrate</h1>
<p class="body-md"><?= htmlspecialchars($note, ENT_QUOTES, 'UTF-8') ?></p>
<p class="body-sm text-muted">This page is wired by Plan 02-01; the Action body lands in Plan 02-02.</p>
</section>
