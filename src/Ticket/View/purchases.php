<?php

/**
 * TicketTrade — Purchases View
 *
 * Phase 4 Plan 04-02. Chronological purchase history: a table on
 * desktop / stacked rows on mobile (Bootstrap 5 responsive classes).
 * The `Leave review` affordance is NOT in Phase 4 (Phase 5).
 *
 * Columns: Code (masked + reveal), Status (status badge), Listing
 * (title), Price (Rs X.XX), Seller (nickname), Date (Asia/Colombo).
 */

declare(strict_types=1);

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$tickets = (array) ($__vars['tickets'] ?? []);

$dateFmt = new \IntlDateFormatter(
    'en_US@calendar=gregorian',
    \IntlDateFormatter::MEDIUM,
    \IntlDateFormatter::SHORT,
    'Asia/Colombo',
    \IntlDateFormatter::GREGORIAN,
    'd MMM y, HH:mm'
);
?>
<section class="container purchases-shell" style="padding-top: var(--space-8, 48px); padding-bottom: var(--space-8, 48px);">
  <header class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="headline-md mb-0">Purchase History</h1>
  </header>

  <?php if (empty($tickets)) : ?>
    <div class="my-4">
        <?= \App\Support\View::partial('empty_state', [
          'title' => 'No purchases yet. Your first purchase appears here.',
          'body' => '',
          'cta_label' => 'Browse Board',
          'cta_href' => '/board',
      ]) ?>
    </div>
  <?php else : ?>
    <!-- Desktop table (md+) -->
    <div class="table-responsive d-none d-md-block" data-component="purchases-table">
      <table class="table align-middle">
        <thead>
          <tr>
            <th scope="col">Code</th>
            <th scope="col">Status</th>
            <th scope="col">Listing</th>
            <th scope="col">Price</th>
            <th scope="col">Seller</th>
            <th scope="col">Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tickets as $t) :
                $tid = (int) $t['id'];
                $code = (string) $t['ticket_code'];
                $status = (string) $t['status'];
                $priceCents = (int) $t['price_cents'];
                $listingTitle = (string) $t['listing_title'];
                $sellerNick = (string) ($t['seller_nickname'] ?? 'seller');
                $createdAt = (string) ($t['created_at'] ?? '');
                try {
                    $ts = new \DateTime($createdAt, new \DateTimeZone('Asia/Colombo'));
                    $dateStr = $dateFmt->format($ts);
                } catch (\Throwable $e) {
                    $dateStr = $createdAt;
                }
                ?>
            <tr data-ticket-id="<?= (int) $tid ?>">
              <td>
                <?= \App\Support\View::partial('ticket_code_block', [
                    'code' => $code,
                    'seller_whatsapp' => '',
                ]) ?>
              </td>
              <td><?= \App\Support\View::partial('status_badge', ['status' => $status]) ?></td>
              <td><?= htmlspecialchars($listingTitle, ENT_QUOTES, 'UTF-8') ?></td>
              <td>Rs <?= htmlspecialchars(number_format($priceCents / 100, 2), ENT_QUOTES, 'UTF-8') ?></td>
              <td>@<?= htmlspecialchars($sellerNick, ENT_QUOTES, 'UTF-8') ?></td>
              <td class="caption"><?= htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobile stacked rows (<md) -->
    <div class="d-md-none" data-component="purchases-cards">
      <?php foreach ($tickets as $t) :
            $tid = (int) $t['id'];
            $code = (string) $t['ticket_code'];
            $status = (string) $t['status'];
            $priceCents = (int) $t['price_cents'];
            $listingTitle = (string) $t['listing_title'];
            $sellerNick = (string) ($t['seller_nickname'] ?? 'seller');
            $createdAt = (string) ($t['created_at'] ?? '');
            try {
                $ts = new \DateTime($createdAt, new \DateTimeZone('Asia/Colombo'));
                $dateStr = $dateFmt->format($ts);
            } catch (\Throwable $e) {
                $dateStr = $createdAt;
            }
            ?>
        <div class="card surface-container mb-2" data-ticket-id="<?= (int) $tid ?>">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
              <strong class="flex-grow-1"><?= htmlspecialchars($listingTitle, ENT_QUOTES, 'UTF-8') ?></strong>
              <?= \App\Support\View::partial('status_badge', ['status' => $status]) ?>
            </div>
            <p class="body-sm mb-2">@<?= htmlspecialchars($sellerNick, ENT_QUOTES, 'UTF-8') ?> &middot; Rs <?= htmlspecialchars(number_format($priceCents / 100, 2), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="mb-2">
              <?= \App\Support\View::partial('ticket_code_block', [
                  'code' => $code,
                  'seller_whatsapp' => '',
              ]) ?>
            </div>
            <p class="caption text-on-surface-variant mb-0"><?= htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
