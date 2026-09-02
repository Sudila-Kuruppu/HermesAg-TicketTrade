<?php

/**
 * TicketTrade — Support\View\partials\ticket_code_block
 *
 * Phase 4 Plan 04-01. Renders the ticket-code-block with mask/reveal
 * toggle, copy-to-clipboard, and WhatsApp share. The mask default is
 * `TK-****-****-****-****-****`. The JS handler in
 * public/assets/js/tickettrade.js reads `data-code-value` and
 * `data-seller-whatsapp` to build the WhatsApp share URL.
 *
 * Expected vars: code (string), seller_whatsapp (string|null).
 */

declare(strict_types=1);

$vars = $GLOBALS['_tt_view_vars'] ?? [];
$code = (string) ($vars['code'] ?? '');
$sellerWhatsapp = (string) ($vars['seller_whatsapp'] ?? '');

// Build the masked display (only the prefix stays visible).
$masked = 'TK-****-****-****-****-****';

// Build WhatsApp share URL.
$shareText = 'My ticket code: ' . $code;
$waUrl = '';
if ($sellerWhatsapp !== '') {
    // Normalize whatsapp (Sri Lankan mobile; strip non-digits).
    $num = preg_replace('/[^0-9]/', '', $sellerWhatsapp);
    $waUrl = 'https://wa.me/' . $num . '?text=' . urlencode($shareText);
} else {
    $waUrl = 'https://wa.me/?text=' . urlencode($shareText);
}
?>
<div
  class="ticket-code-block ticket-code-block--masked"
  data-component="ticket-code-block"
  data-code-value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"
  data-seller-whatsapp="<?= htmlspecialchars($sellerWhatsapp, ENT_QUOTES, 'UTF-8') ?>"
>
  <code class="ticket-code-block__code" data-role="code"><?= htmlspecialchars($masked, ENT_QUOTES, 'UTF-8') ?></code>
  <button
    type="button"
    class="ticket-code-block__toggle"
    aria-label="Reveal ticket code"
    aria-pressed="false"
    data-role="toggle"
  >Reveal</button>
  <button
    type="button"
    class="ticket-code-block__copy"
    aria-label="Copy ticket code"
    data-role="copy"
    hidden
  >Copy</button>
  <a
    class="ticket-code-block__share"
    href="<?= htmlspecialchars($waUrl, ENT_QUOTES, 'UTF-8') ?>"
    aria-label="Share via WhatsApp"
    target="_blank"
    rel="noopener"
    data-role="share"
  >WhatsApp</a>
  <span
    class="visually-hidden"
    aria-live="polite"
    data-role="confirmation"
  ></span>
</div>
