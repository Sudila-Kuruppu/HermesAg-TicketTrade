<?php

/**
 * TicketTrade — My Tickets View
 *
 * Phase 4 Plan 04-02. Renders the My Tickets page with 5 tabs
 * (All / Active / Redeemed / Expired / Disputed), each ticket
 * card with the listing title + status badge + price + seller info
 * row + ticket-code-block + per-session progress (for service tickets)
 * + dispute button when eligible.
 *
 * Tab counts render as inline bg-secondary badges; the active tab
 * carries aria-current='page'. Tab clicks reload with ?tab={name}.
 *
 * The view does NOT write to the DB. The Action passed the data in.
 *
 * Vars:
 *   tickets          (array) Tickets for the active tab.
 *   tab              (string) Active tab key (default 'active').
 *   tab_counts       (array) Counts per tab key.
 *   csrf_token       (string) CSRF token for the dispute form.
 *   new_ticket_id    (int) Ticket id to focus after a buy (?new=).
 *   user             (array) Sanitized current user row.
 */

declare(strict_types=1);

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$tickets = $__vars['tickets'] ?? [];
$tab = (string) ($__vars['tab'] ?? 'active');
$tabCounts = (array) ($__vars['tab_counts'] ?? []);
$csrfToken = (string) ($__vars['csrf_token'] ?? '');
$newTicketId = (int) ($__vars['new_ticket_id'] ?? 0);

$tabs = [
    'all'      => 'All',
    'active'   => 'Active',
    'redeemed' => 'Redeemed',
    'expired'  => 'Expired',
    'disputed' => 'Disputed',
];

$dateFmt = new \IntlDateFormatter(
    'en_US@calendar=gregorian',
    \IntlDateFormatter::MEDIUM,
    \IntlDateFormatter::SHORT,
    'Asia/Colombo',
    \IntlDateFormatter::GREGORIAN,
    'd MMM y, HH:mm'
);
?>
<section class="container my-tickets-shell" style="padding-top: var(--space-8, 48px); padding-bottom: var(--space-8, 48px);">
  <header class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="headline-md mb-0">My Tickets</h1>
  </header>

  <!-- 5-tab nav strip (All / Active / Redeemed / Expired / Disputed) -->
  <nav class="mb-4" aria-label="Ticket status filters" data-component="my-tickets-tabs">
    <ul class="nav nav-pills nav-fill flex-nowrap overflow-auto" role="tablist">
      <?php foreach ($tabs as $key => $label) :
            $isActive = ($tab === $key);
            $count = (int) ($tabCounts[$key] ?? 0);
            $href = ($key === 'active') ? '/my-tickets' : '/my-tickets?tab=' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            ?>
        <li class="nav-item" role="presentation">
          <a
            class="nav-link <?= $isActive ? 'active' : '' ?>"
            id="tab-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
            href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
            role="tab"
            <?= $isActive ? 'aria-current="page"' : '' ?>
            aria-selected="<?= $isActive ? 'true' : 'false' ?>"
          >
            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            <span class="badge bg-secondary ms-1" aria-label="<?= (int) $count ?> ticket<?= $count === 1 ? '' : 's' ?>"><?= (int) $count ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </nav>

  <?php if (empty($tickets)) : ?>
    <div class="my-4">
        <?= \App\Support\View::partial('empty_state', [
          'title' => 'No tickets yet. Buy your first item.',
          'body' => '',
          'cta_label' => 'Browse Board',
          'cta_href' => '/board',
      ]) ?>
    </div>
  <?php else : ?>
    <div class="row g-3" data-component="my-tickets-list">
      <?php foreach ($tickets as $t) :
            $tid = (int) $t['id'];
            $code = (string) $t['ticket_code'];
            $status = (string) $t['status'];
            $disputeStatus = (string) $t['dispute_status'];
            $priceCents = (int) $t['price_cents'];
            $sessionNumber = (int) $t['session_number'];
            $totalSessions = (int) $t['total_sessions'];
            $listingTitle = (string) $t['listing_title'];
            $sellerNick = (string) ($t['seller_nickname'] ?? 'seller');
            $sellerTier = (string) ($t['seller_tier'] ?? 'E');
            $sellerVerified = (bool) ($t['seller_is_verified'] ?? false);
            $sellerWhatsapp = (string) ($t['seller_whatsapp'] ?? '');
            $createdAt = (string) ($t['created_at'] ?? '');
            $rotationSeed = ($tid > 0) ? (crc32((string) $tid) % 5) - 2 : 0;
            $isFocusTarget = ($newTicketId > 0 && $tid === $newTicketId);
            $eligibleForDispute = ($disputeStatus === 'none' && in_array($status, ['active', 'redeemed'], true));
            ?>
        <div class="col-12 col-md-6">
          <article
            class="listing-card listing-card--ticket h-100"
            data-ticket-id="<?= (int) $tid ?>"
            data-component="ticket-card"
            tabindex="<?= $isFocusTarget ? '0' : '-1' ?>"
            style="--rot: <?= (int) $rotationSeed ?>deg;"
          >
            <span class="listing-card-cork__pin" aria-hidden="true"></span>
            <div class="listing-card-cork__paper" aria-hidden="false">
              <div class="card-body">
                <header class="d-flex align-items-start justify-content-between gap-2 mb-2">
                  <h2 class="h6 mb-0 flex-grow-1">
                    <?= htmlspecialchars($listingTitle, ENT_QUOTES, 'UTF-8') ?>
                  </h2>
                  <?= \App\Support\View::partial('status_badge', ['status' => $status]) ?>
                </header>

                <p class="body-md fw-semibold mb-2">
                  Rs <?= htmlspecialchars(number_format($priceCents / 100, 2), ENT_QUOTES, 'UTF-8') ?>
                </p>

                <p class="body-sm text-on-surface-variant mb-2 d-flex align-items-center gap-2">
                  <span>Seller:</span>
                  <strong>@<?= htmlspecialchars($sellerNick, ENT_QUOTES, 'UTF-8') ?></strong>
                  <?php if ($sellerVerified) : ?>
                    <span aria-label="Verified student" title="Verified student">&#10003;</span>
                  <?php endif; ?>
                  <span class="badge rank-badge rank-<?= htmlspecialchars(strtolower($sellerTier), ENT_QUOTES, 'UTF-8') ?>"
                        aria-label="Rank tier <?= htmlspecialchars($sellerTier, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($sellerTier, ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </p>

                <?php if ($totalSessions > 1) : ?>
                  <div class="mb-2">
                    <?= \App\Support\View::partial('session_progress', [
                        'session_number' => $sessionNumber,
                        'total_sessions' => $totalSessions,
                    ]) ?>
                  </div>
                <?php endif; ?>

                <div class="mb-2">
                  <?= \App\Support\View::partial('ticket_code_block', [
                      'code' => $code,
                      'seller_whatsapp' => $sellerWhatsapp,
                  ]) ?>
                </div>

                <p class="caption text-on-surface-variant mb-3">
                  Bought <?= htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') ?>
                </p>

                <?php if ($eligibleForDispute) : ?>
                  <div class="d-flex justify-content-end">
                    <button
                      type="button"
                      class="btn btn-outline-danger btn-sm"
                      data-bs-toggle="modal"
                      data-bs-target="#dispute-modal-<?= (int) $tid ?>"
                      aria-label="File dispute on ticket <?= (int) $tid ?>"
                    >Dispute</button>
                  </div>
                    <?= \App\Support\View::partial('dispute_modal', [
                      'ticket_id' => $tid,
                      'csrf_token' => $csrfToken,
                  ]) ?>
                <?php endif; ?>
              </div>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($newTicketId > 0) : ?>
<!-- D-02: inline auto-focus on the freshly-bought ticket card. -->
<script>
  (function () {
    setTimeout(function () {
      var el = document.querySelector('[data-ticket-id="<?= (int) $newTicketId ?>"]');
      if (el && typeof el.focus === 'function') {
        el.focus();
      }
    }, 50);
  })();
</script>
<?php endif; ?>
