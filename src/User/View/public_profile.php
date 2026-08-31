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
 * @var array<string,mixed> $profile  The sanitized user row + re-injected
 *                                    `points` and `is_verified` from
 *                                    User\Service\user_service::getByNicknameForPublicProfile
 * @var bool $is_owner                 Whether the current user is the
 *                                    profile owner (Phase 2 placeholder
 *                                    — the Edit button is the same for
 *                                    owner and guest until later phases
 *                                    decide otherwise)
 */

$profile = $profile ?? [];
$is_owner = $is_owner ?? false;
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
?><div class="container py-4">
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
          <span class="body-sm" data-testid="public-profile-points"><strong><?= (int) ($profile['points'] ?? 0) ?></strong> points</span>
          <?php if ($createdAtFormatted !== '') : ?>
            <span class="body-sm text-on-surface-variant" data-testid="public-profile-joined">Joined <?= htmlspecialchars($createdAtFormatted, ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="d-flex flex-column gap-2 flex-shrink-0">
        <?php if ($is_owner) : ?>
          <a href="/profile" class="btn btn-primary" data-bs-toggle="tooltip" title="Edit your profile">Edit profile</a>
        <?php endif; ?>
        <a href="#" class="btn btn-outline-secondary disabled"
           aria-disabled="true"
           data-bs-toggle="tooltip" data-bs-placement="top"
           title="Coming soon"
           data-testid="public-profile-report-user">Report user</a>
      </div>
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
        <div class="display-sm">—</div>
        <div class="caption text-on-surface-variant">No reviews yet</div>
      </div>
      <div class="col-6 col-md-3">
        <div class="display-sm">0</div>
        <div class="caption text-on-surface-variant">Disputes</div>
      </div>
    </div>
    <?php /* Phase 2: NO tab navigation. The 5 tabs (My Listings / My Tickets / Purchase History / Sales History / Reviews) land in Phases 3/4/5. */ ?>
  </div>
</div>
