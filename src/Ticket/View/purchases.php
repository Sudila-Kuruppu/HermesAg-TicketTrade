<?php

/**
 * TicketTrade — Purchases View
 *
 * Phase 4 Plan 04-02 + Phase 5 Plan 05-01. Chronological purchase
 * history: a table on desktop / stacked rows on mobile (Bootstrap 5
 * responsive classes). Phase 5 adds the `Leave review` button +
 * per-row review modal for redeemed tickets within the 14-day window.
 *
 * Columns: Code (masked + reveal), Status (status badge), Listing
 * (title), Price (Rs X.XX), Seller (nickname), Date (Asia/Colombo),
 * Actions (Leave review button when eligible).
 */

declare(strict_types=1);

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$tickets = (array) ($__vars['tickets'] ?? []);

// 14-day review window cutoff — D-03. Computed once per render.
$reviewCutoff = (new \DateTime('-14 days', new \DateTimeZone('Asia/Colombo')))
    ->format('Y-m-d H:i:s');

$dateFmt = new \IntlDateFormatter(
    'en_US@calendar=gregorian',
    \IntlDateFormatter::MEDIUM,
    \IntlDateFormatter::SHORT,
    'Asia/Colombo',
    \IntlDateFormatter::GREGORIAN,
    'd MMM y, HH:mm'
);

/**
 * Helper: is the row eligible for review? Per D-03 + AD-15 the row must
 * be `redeemed` AND `redeemed_at >= NOW() - 14 days`. The 14-day
 * eligibility flag is exposed for tests / View integration.
 */
$canReview = static function (array $row) use ($reviewCutoff): bool {
    $status = (string) ($row['status'] ?? '');
    if ($status !== 'redeemed') {
        return false;
    }
    $redeemedAt = (string) ($row['redeemed_at'] ?? '');
    if ($redeemedAt === '') {
        return false;
    }
    return $redeemedAt >= $reviewCutoff;
};
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
            <th scope="col">Actions</th>
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
                $reviewEligible = $canReview((array) $t);
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
              <td>
                <?php if ($reviewEligible) : ?>
                  <button type="button"
                          class="btn btn-primary btn-sm"
                          data-bs-toggle="modal"
                          data-bs-target="#review-modal-<?= (int) $tid ?>"
                          data-action="leave-review">
                    Leave review
                  </button>
                <?php endif; ?>
              </td>
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
            $reviewEligible = $canReview((array) $t);
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
            <p class="caption text-on-surface-variant mb-2"><?= htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8') ?></p>
            <?php if ($reviewEligible) : ?>
              <button type="button"
                      class="btn btn-primary btn-sm w-100"
                      data-bs-toggle="modal"
                      data-bs-target="#review-modal-<?= (int) $tid ?>"
                      data-action="leave-review">
                Leave review
              </button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Per-row review modals (one per eligible ticket). Rendered in a
         hidden container at the end of the page so the DOM weight is
         acceptable (Phase 5 cap: ≤50 rows). The modal file reads
         $GLOBALS['_tt_view_vars'] directly, so we set the vars and
         require the file (no layout wrapping). -->
      <?php foreach ($tickets as $t) :
            $tid = (int) $t['id'];
            if (!$canReview((array) $t)) {
                continue;
            }
            $GLOBALS['_tt_view_vars'] = [
              'ticket_id'  => $tid,
              'csrf_token' => \App\Support\Csrf::token(),
            ];
            require __DIR__ . '/../../Review/View/review_modal.php';
      endforeach; ?>
  <?php endif; ?>
</section>