<?php

/**
 * TicketTrade — Sales View
 *
 * Phase 4 Plan 04-02. Renders the Sales page with per-listing-group
 * placement (D-05). Page header carries the redemption input form
 * (POST /tickets/redeem) so the seller can paste a buyer's code at
 * any time.
 *
 * Each listing group shows:
 *   - Listing title (with sold-out badge when applicable)
 *   - Per-listing-group progress chip (only when total_sessions > 1)
 *   - Ticket rows: status badge + ticket-code-block (with buyer
 *     nickname) + #N/M session progress + "Confirm next session"
 *     button next to the in-progress ticket.
 */

declare(strict_types=1);

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$groups = (array) ($__vars['groups'] ?? []);
$csrfToken = (string) ($__vars['csrf_token'] ?? '');

$dateFmt = new \IntlDateFormatter(
    'en_US@calendar=gregorian',
    \IntlDateFormatter::MEDIUM,
    \IntlDateFormatter::SHORT,
    'Asia/Colombo',
    \IntlDateFormatter::GREGORIAN,
    'd MMM y, HH:mm'
);
?>
<section class="container sales-shell" style="padding-top: var(--space-8, 48px); padding-bottom: var(--space-8, 48px);">
  <header class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <h1 class="headline-md mb-0">Sales</h1>
  </header>

  <!-- Redemption input: paste a buyer's ticket code here. Always visible per D-05 + CONTEXT. -->
  <div class="card surface-container mb-4" data-component="sales-redemption">
    <div class="card-body">
      <h2 class="h6 mb-2">Redeem a ticket</h2>
      <p class="caption text-on-surface-variant mb-3">
        Paste the buyer's <code>TK-XXXX-XXXX-XXXX-XXXX-XXXX</code> code.
      </p>
      <form method="POST" action="/tickets/redeem" class="d-flex flex-column flex-sm-row gap-2">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input
          type="text"
          name="ticket_code"
          class="form-control flex-grow-1"
          placeholder="TK-XXXX-XXXX-XXXX-XXXX-XXXX"
          autocomplete="off"
          required
          aria-label="Ticket code"
          data-role="sales-redeem-input"
        >
        <button type="submit" class="btn btn-primary">Redeem</button>
      </form>
    </div>
  </div>

  <?php if (empty($groups)) : ?>
    <div class="my-4">
        <?= \App\Support\View::partial('empty_state', [
          'title' => 'No sales yet. Your first sale happens when someone buys one of your listings.',
          'body' => '',
          'cta_label' => 'View your listings',
          'cta_href' => '/my-listings',
      ]) ?>
    </div>
  <?php else : ?>
    <div data-component="sales-groups">
      <?php foreach ($groups as $group) :
            $listingId = (int) $group['listing_id'];
            $listingTitle = (string) $group['listing_title'];
            $listingType = (string) ($group['listing_type'] ?? 'product');
            $tickets = (array) ($group['tickets'] ?? []);

          // Compute per-listing-group progress chip when total_sessions > 1.
            $hasService = false;
            $totalSessions = 0;
            $sessionNumber = 0;
            foreach ($tickets as $t) {
                if ((int) $t['total_sessions'] > 1) {
                    $hasService = true;
                    $totalSessions = (int) $t['total_sessions'];
                    $sessionNumber = (int) $t['session_number'];
                }
            }
          // For multi-ticket groups (same listing, multiple buyers), the
          // progress chip is the MAX session number across active tickets.
            $maxSession = 0;
            $maxTotalSessions = 0;
            foreach ($tickets as $t) {
                if ((int) $t['total_sessions'] > 1) {
                    if ((int) $t['session_number'] > $maxSession) {
                        $maxSession = (int) $t['session_number'];
                        $maxTotalSessions = (int) $t['total_sessions'];
                    }
                }
            }

          // Find the in-progress ticket (highest session_number for
          // tickets with status='active' AND total_sessions > 1).
            $inProgress = null;
            $maxSessionSeen = -1;
            foreach ($tickets as $t) {
                if (
                    (string) $t['status'] === 'active'
                    && (int) $t['total_sessions'] > 1
                    && (int) $t['session_number'] < (int) $t['total_sessions']
                    && (int) $t['session_number'] > $maxSessionSeen
                ) {
                    $maxSessionSeen = (int) $t['session_number'];
                    $inProgress = $t;
                }
            }
            ?>
        <div class="card surface-container mb-3" data-listing-id="<?= (int) $listingId ?>" data-component="sales-group">
          <div class="card-body">
            <header class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
              <h2 class="h6 mb-0 flex-grow-1">
                <?= htmlspecialchars($listingTitle, ENT_QUOTES, 'UTF-8') ?>
              </h2>
              <?php if ($maxTotalSessions > 1 && $maxSession > 0) : ?>
                <span class="badge bg-secondary caption" aria-label="Sessions confirmed">
                    <?= (int) $maxSession ?>/<?= (int) $maxTotalSessions ?> sessions confirmed
                </span>
              <?php endif; ?>
            </header>

            <ul class="list-unstyled mb-0">
              <?php foreach ($tickets as $t) :
                    $tid = (int) $t['id'];
                    $code = (string) $t['ticket_code'];
                    $status = (string) $t['status'];
                    $snN = (int) $t['session_number'];
                    $snM = (int) $t['total_sessions'];
                    $buyerNick = (string) ($t['buyer_nickname'] ?? 'buyer');
                    $buyerWhatsapp = (string) ($t['buyer_whatsapp'] ?? '');
                    $isInProgress = ($inProgress !== null && (int) $inProgress['id'] === $tid);
                    ?>
                <li class="d-flex flex-column flex-md-row gap-2 align-items-md-center py-2 sales-row" data-ticket-id="<?= (int) $tid ?>">
                  <div class="flex-grow-1">
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                      <?= \App\Support\View::partial('status_badge', ['status' => $status]) ?>
                      <span class="body-sm text-on-surface-variant">
                        Buyer: <strong>@<?= htmlspecialchars($buyerNick, ENT_QUOTES, 'UTF-8') ?></strong>
                      </span>
                    </div>
                    <?php if ($snM > 1) : ?>
                      <div class="mb-1">
                        <?= \App\Support\View::partial('session_progress', [
                            'session_number' => $snN,
                            'total_sessions' => $snM,
                        ]) ?>
                      </div>
                    <?php endif; ?>
                    <div>
                      <?= \App\Support\View::partial('ticket_code_block', [
                          'code' => $code,
                          'seller_whatsapp' => $buyerWhatsapp,
                      ]) ?>
                    </div>
                  </div>
                    <?php if ($isInProgress) : ?>
                    <form method="POST" action="/tickets/<?= (int) $tid ?>/confirm-session" class="sales-confirm-form">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                      <button type="submit" class="btn btn-primary btn-sm">
                        Confirm next session
                      </button>
                    </form>
                    <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
