<?php

/**
 * TicketTrade — User\View\public_profile
 *
 * The Phase 2 public profile summary header (D-14). Renders inside the
 * layout's <main>. NO tab navigation in Phase 2 — tabs (My Listings,
 * My Tickets, Purchase History, Sales History, Reviews) land in
 * Phases 3/4/5. The summary header shape is locked here; later phases
 * ADD tabs below this header without modifying it.
 *
 * Per D-16: WhatsApp is NEVER sent to this View. Per D-17/D-18: avatar
 * src is the 1..12 SVG with a hard clamp. Per PROF-04: the verified
 * checkmark renders when users.is_verified = TRUE.
 *
 * Phase 5 Plan 05-02: replaces the placeholder Reviews / Disputes cells
 * with the real `review_summary` partial, and ADDS the Reviews tab
 * content section (paginated list of reviews received). Per D-09 the
 * dispute count is always shown on the public profile (so the column's
 * existence is signalled) even when 0.
 *
 * @var array<string,mixed> $profile  The sanitized user row + re-injected
 *                                    `points` and `is_verified` from
 *                                    User\Service\user_service::getByNicknameForPublicProfile
 * @var bool $is_owner                 Whether the current user is the
 *                                    profile owner
 * @var array<string,mixed> $summary   review_service::getSummaryForUser return
 * @var array<int,array<string,mixed>> $reviews  listReviewsForUser rows (received)
 * @var int $reviews_total             Total count (for Prev/Next + empty state)
 * @var int $reviews_offset            Pagination offset (clamped 0..1000)
 * @var int $reviews_per_page          Page size (10)
 */

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$profile = $__vars['profile'] ?? [];
$is_owner = (bool) ($__vars['is_owner'] ?? false);
$summary = is_array($__vars['summary'] ?? null) ? $__vars['summary'] : [];
$reviews = is_array($__vars['reviews'] ?? null) ? $__vars['reviews'] : [];
$reviewsTotal = (int) ($__vars['reviews_total'] ?? 0);
$reviewsOffset = (int) ($__vars['reviews_offset'] ?? 0);
$reviewsPerPage = (int) ($__vars['reviews_per_page'] ?? 10);

$avatarId = (int) max(1, min(12, (int) ($profile['avatar_id'] ?? 1)));
$avatarSrc = '/assets/img/avatars/avatar-' . $avatarId . '.svg';

// Format created_at in Asia/Colombo (ARCHITECTURE-SPINE Conventions).
$createdAtFormatted = '';
if (!empty($profile['created_at'])) {
    try {
        $createdAtFormatted = (new DateTime((string) $profile['created_at'], new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('Asia/Colombo'))
            ->format('d M Y');
    } catch (\Throwable $e) {
        $createdAtFormatted = (string) $profile['created_at'];
    }
}
?>
<div class="container py-4">
  <div class="card surface-raised p-4">
    <div class="d-flex flex-column flex-md-row align-items-center align-items-md-start gap-4">
      <img src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8') ?>"
           alt="Avatar" width="96" height="96"
           class="rounded-circle flex-shrink-0"
           data-testid="public-profile-avatar">
      <div class="flex-grow-1 text-center text-md-start">
        <h1 class="display-lg mb-1" data-testid="public-profile-name">
          <?= htmlspecialchars((string) ($profile['full_name'] ?? $profile['nickname'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
          <?php if (!empty($profile['is_verified'])) : ?>
            <i class="bi bi-patch-check-fill text-primary align-baseline"
               data-bs-toggle="tooltip" data-bs-placement="top"
               title="Verified student"
               aria-label="Verified student"
               data-testid="public-profile-verified"></i>
          <?php endif; ?>
        </h1>
        <p class="body-md text-on-surface-variant mb-2">
          @<?= htmlspecialchars((string) ($profile['nickname'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <?php
          $bio = trim((string) ($profile['bio'] ?? ''));
        if ($bio === '') : ?>
            <p class="body-md mb-3 text-on-surface-variant">No bio yet.</p>
        <?php else : ?>
            <p class="body-md mb-3"><?= nl2br(htmlspecialchars($bio, ENT_QUOTES, 'UTF-8')) ?></p>
        <?php endif; ?>
        <div class="d-flex flex-wrap align-items-center gap-3">
          <?= \App\Support\View::partial('rank_badge', ['tier' => $profile['tier'] ?? 'E', 'size' => 32]) ?>
          <?php
            // On-Break pill on public profiles too — the rank badge
            // wrapping is the grayed-out signal. Velocity flag is
            // OWNER-only per PTS-09 privacy (T-06-19).
            $laPublic = $profile['last_active_at'] ?? null;
            ?>
          <?= \App\Support\View::partial('on_break_pill', ['lastActiveAt' => $laPublic]) ?>
          <span class="body-sm" data-testid="public-profile-points"><strong><?= (int) ($profile['points'] ?? 0) ?></strong> points</span>
          <?php if ($createdAtFormatted !== '') : ?>
            <span class="body-sm text-on-surface-variant" data-testid="public-profile-joined">Joined <?= htmlspecialchars($createdAtFormatted, ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="d-flex flex-column gap-2 flex-shrink-0">
        <?php if ($is_owner) : ?>
          <a href="/profile/edit" class="btn btn-primary" data-bs-toggle="tooltip" title="Edit your profile">Edit profile</a>
        <?php endif; ?>
        <a href="#" class="btn btn-outline-secondary disabled"
           aria-disabled="true"
           data-bs-toggle="tooltip" data-bs-placement="top"
           title="Coming soon"
           data-testid="public-profile-report-user">Report user</a>
      </div>
    </div>
    <div class="profile-gamification">
      <?= \App\Support\View::partial('tier_progress', ['userId' => (int) ($profile['user_id'] ?? 0), 'points' => (int) ($profile['points'] ?? 0), 'tier' => (string) ($profile['tier'] ?? 'E')]) ?>
    </div>
    <hr class="my-4">
    <div class="row g-3 text-center" data-testid="public-profile-stats">
      <div class="col-6 col-md-3">
        <div class="display-sm">0</div>
        <div class="caption text-on-surface-variant">Sales</div>
      </div>
      <div class="col-6 col-md-3">
        <div class="display-sm">0</div>
        <div class="caption text-on-surface-variant">Purchases</div>
      </div>
      <div class="col-6 col-md-3">
        <?= \App\Support\View::partial('review_summary', ['summary' => $summary, 'prefix' => 'public-profile', 'show_dispute' => false]) ?>
      </div>
      <div class="col-6 col-md-3">
        <div class="display-sm"><?= (int) ($summary['dispute_count'] ?? 0) ?></div>
        <div class="caption text-on-surface-variant">
          <?php $dc = (int) ($summary['dispute_count'] ?? 0); ?>
          <?php if ($dc === 0) : ?>
            0 disputes
          <?php else : ?>
              <?= (int) $dc ?> disputes on record
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Reviews tab content section (Phase 5 Plan 05-02, D-08 + D-09) -->
  <section class="card surface-raised p-4 mt-4" data-testid="public-profile-reviews-tab">
    <h2 class="h5 mb-3">Reviews</h2>
    <?php if ($reviewsTotal <= 0) : ?>
      <p class="text-on-surface-variant mb-0" data-testid="public-profile-reviews-empty">
        No reviews yet. Reviews appear after transactions complete.
      </p>
    <?php else : ?>
      <div class="review-card-list d-flex flex-column gap-3" data-testid="public-profile-reviews-list">
        <?php foreach ($reviews as $review) : ?>
            <?= \App\Support\View::partial('review_card', ['review' => $review]) ?>
        <?php endforeach; ?>
      </div>
        <?php
        // Prev / Next (D-08): render when offset > 0 or more pages remain.
        $hasPrev = $reviewsOffset > 0;
        $hasNext = $reviewsTotal > $reviewsOffset + $reviewsPerPage;
        ?>
        <?php if ($hasPrev || $hasNext) : ?>
        <nav class="review-pagination mt-3 d-flex justify-content-between"
             aria-label="Reviews pagination"
            data-testid="public-profile-reviews-pagination">
            <?php if ($hasPrev) : ?>
            <a class="btn btn-outline-secondary btn-sm"
               href="?offset=<?= (int) max(0, $reviewsOffset - $reviewsPerPage) ?>"
               aria-label="Previous reviews page">Prev</a>
            <?php else : ?>
            <span></span>
            <?php endif; ?>
          <span class="caption text-on-surface-variant align-self-center">
            Page <?= (int) (floor($reviewsOffset / max(1, $reviewsPerPage)) + 1) ?>
            of <?= (int) ceil($reviewsTotal / max(1, $reviewsPerPage)) ?>
          </span>
            <?php if ($hasNext) : ?>
            <a class="btn btn-outline-secondary btn-sm"
               href="?offset=<?= (int) ($reviewsOffset + $reviewsPerPage) ?>"
               aria-label="Next reviews page">Next</a>
            <?php else : ?>
            <span></span>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
  </section>
</div>