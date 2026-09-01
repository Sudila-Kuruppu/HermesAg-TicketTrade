<?php
/**
 * TicketTrade — User/View/settings
 *
 * Phase 2 Plan 02-02. Theme radios + a destructive-styled logout
 * button inside a Bootstrap confirm modal.
 */
$csrf = $csrf_token ?? '';
$profile = $profile ?? [];
?>
<section class="container settings-shell" style="padding-top: var(--space-8, 48px);">
<div class="row justify-content-center">
<div class="col-12 col-md-10 col-lg-7">
<div class="card surface-container shadow-sm">
<div class="card-body p-4 p-md-5">
<h1 class="headline-md mb-4">Settings</h1>

<div class="mb-4">
<h2 class="h6 mb-3">Theme</h2>
<form id="theme-form" data-component="theme-controller" data-theme-controller data-persist="localStorage" data-key="tickettrade-theme">
<div class="form-check">
<input class="form-check-input" type="radio" name="theme" id="theme-light" value="light">
<label class="form-check-label" for="theme-light">Light</label>
</div>
<div class="form-check">
<input class="form-check-input" type="radio" name="theme" id="theme-dark" value="dark">
<label class="form-check-label" for="theme-dark">Dark</label>
</div>
<div class="form-check">
<input class="form-check-input" type="radio" name="theme" id="theme-system" value="system">
<label class="form-check-label" for="theme-system">System</label>
</div>
<div class="form-text mt-2">Theme is stored in your browser. No server-side change.</div>
</form>
</div>

<hr class="my-4">

<div>
<h2 class="h6 mb-3 text-danger">Danger zone</h2>
<button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#logoutConfirmModal">
Log out
</button>
</div>
</div>
</div>
</div>
</div>
</section>

<div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-labelledby="logoutConfirmLabel" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<form method="post" action="/logout" id="logout-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
<div class="modal-header">
<h5 class="modal-title" id="logoutConfirmLabel">Log out?</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
You'll need to sign in again to access your profile, tickets, and listings.
</div>
<div class="modal-footer">
<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
<button type="submit" class="btn btn-danger">Log out</button>
</div>
</form>
</div>
</div>
</div>

<script>
// On load, populate the theme radio from localStorage so the UI matches
// the user's current choice.
(function () {
  try {
    var stored = localStorage.getItem('tickettrade-theme') || 'dark';
    var radio = document.getElementById('theme-' + stored);
    if (radio) radio.checked = true;
    // Save the choice on change.
    var form = document.getElementById('theme-form');
    if (form) {
      form.addEventListener('change', function (e) {
        var v = e.target.value;
        if (!v) return;
        if (v === 'system') {
          v = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        localStorage.setItem('tickettrade-theme', v);
        document.documentElement.setAttribute('data-theme', v);
        if (window.TicketTrade && window.TicketTrade.setTheme) {
          window.TicketTrade.setTheme(v);
        }
      });
    }
  } catch (e) {}
})();
</script>
