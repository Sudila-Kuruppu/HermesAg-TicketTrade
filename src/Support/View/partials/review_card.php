<?php

/**
 * TicketTrade — Support\View\partials\review_card
 *
 * Phase 5 Plan 05-02. Renders a single review row in the Reviews
 * tab on /profile and /profile/{nickname}. Per D-02 + D-04 + FR-RAT-003:
 *
 *   - Reviewer NICKNAME only (never full_name). The nickname comes
 *     from the JOIN in review_model::listForReviewee.
 *   - Role badge ("Buyer" or "Seller") — Phase 5 ships a single
 *     unified list; a "Buyers rate this X / Sellers rate this Y"
 *     split is deferred to v2 per PLAT-03.
 *   - 5 stars rendered as filled + empty Bootstrap icons. Filled
 *     count = $review['rating'].
 *   - Comment text, or "Rating only — no comment." when NULL.
 *   - Relative timestamp ("2 days ago", "3 weeks ago", etc.)
 *     computed via PHP DateTime + diff() with Asia/Colombo TZ.
 *
 * Vars:
 *   review  array  One row from listReviewsForUser containing at
 *                  minimum: reviewer_nickname, reviewer_role,
 *                  rating (int 1..5), comment (?string),
 *                  created_at (string DATETIME in server TZ).
 */

declare(strict_types=1);

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$review = is_array($__vars['review'] ?? null) ? $__vars['review'] : [];

$nickname = (string) ($review['reviewer_nickname'] ?? 'user');
$role = (string) ($review['reviewer_role'] ?? 'buyer');
$stars = (int) ($review['rating'] ?? 0);
$comment = $review['comment'] ?? null;
$createdAt = (string) ($review['created_at'] ?? '');

// Relative time ("2 days ago").
$relative = '';
if ($createdAt !== '') {
    try {
        $tz = new DateTimeZone('Asia/Colombo');
        // MySQL DATETIME is treated as the server TZ (Asia/Colombo,
        // set in config/bootstrap.php).
        $then = new DateTime($createdAt, $tz);
        $now = new DateTime('now', $tz);
        $diff = $now->diff($then);
        if ($diff->y > 0) {
            $relative = $diff->y . ' year' . ($diff->y === 1 ? '' : 's') . ' ago';
        } elseif ($diff->m > 0) {
            $relative = $diff->m . ' month' . ($diff->m === 1 ? '' : 's') . ' ago';
        } elseif ($diff->d >= 7) {
            $weeks = (int) floor($diff->d / 7);
            $relative = $weeks . ' week' . ($weeks === 1 ? '' : 's') . ' ago';
        } elseif ($diff->d > 0) {
            $relative = $diff->d . ' day' . ($diff->d === 1 ? '' : 's') . ' ago';
        } elseif ($diff->h > 0) {
            $relative = $diff->h . ' hour' . ($diff->h === 1 ? '' : 's') . ' ago';
        } elseif ($diff->i > 0) {
            $relative = $diff->i . ' minute' . ($diff->i === 1 ? '' : 's') . ' ago';
        } else {
            $relative = 'just now';
        }
    } catch (Throwable $e) {
        $relative = '';
    }
}

$roleLabel = $role === 'seller' ? 'Seller' : 'Buyer';
$roleBg = $role === 'seller' ? 'bg-info' : 'bg-secondary';
?>
<article class="review-card" data-testid="review-card">
  <header class="review-card__header d-flex flex-wrap align-items-center gap-2">
    <span class="review-card__reviewer fw-semibold">
      @<?= htmlspecialchars($nickname, ENT_QUOTES, 'UTF-8') ?>
    </span>
    <span class="review-card__role badge <?= htmlspecialchars($roleBg, ENT_QUOTES, 'UTF-8') ?>"
          aria-label="<?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?> reviewer">
      <?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?>
    </span>
    <span class="review-card__rating" aria-label="<?= (int) $stars ?> of 5 stars">
      <?php for ($i = 1; $i <= 5; $i++) :
            $filled = $i <= $stars; ?>
        <span aria-hidden="true" class="bi <?= $filled ? 'bi-star-fill' : 'bi-star' ?>"></span>
      <?php endfor; ?>
    </span>
    <?php if ($relative !== '') : ?>
      <time class="review-card__time caption text-on-surface-variant ms-auto"
            datetime="<?= htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($relative, ENT_QUOTES, 'UTF-8') ?>
      </time>
    <?php endif; ?>
  </header>
  <div class="review-card__comment body-md mt-2">
    <?php if ($comment === null || $comment === '') : ?>
      <span class="text-on-surface-variant">Rating only &mdash; no comment.</span>
    <?php else : ?>
        <?php echo nl2br(htmlspecialchars((string) $comment, ENT_QUOTES, 'UTF-8')); ?>
    <?php endif; ?>
  </div>
</article>