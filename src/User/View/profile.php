<?php

/**
 * TicketTrade — User\View\profile (Plan 06-03)
 *
 * The owner Profile view. Mirrors public_profile.php for the header
 * (avatar, verified checkmark, rank badge, points, join date) PLUS the
 * Phase 6 gamification surface:
 *   - tier_progress partial below the header
 *   - on_break_pill next to the rank badge (when 14+ days inactive)
 *   - velocity_flag_pill below the points row (when points_frozen=TRUE)
 *   - "Recent activity" section after the stats row (D-07) — last 5
 *     points_log rows: delta + reason label + relative time.
 *
 * Reads from $GLOBALS['_tt_view_vars']:
 *   @var array<string,mixed> $profile
 *   @var bool                $is_owner
 *   @var int                 $points
 *   @var string              $tier
 *   @var bool                $points_frozen
 *   @var string|null         $last_active_at
 *   @var array<int,array<string,mixed>> $recent_activity
 *   @var int                 $current_streak  (display only — not surfaced per D-01)
 */

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$profile = $__vars['profile'] ?? [];
$is_owner = (bool) ($__vars['is_owner'] ?? false);
$points = (int) ($__vars['points'] ?? 0);
$tier = (string) ($__vars['tier'] ?? 'E');
$pointsFrozen = (bool) ($__vars['points_frozen'] ?? false);
$lastActiveAt = $__vars['last_active_at'] ?? null;
$recentActivity = is_array($__vars['recent_activity'] ?? null) ? $__vars['recent_activity'] : [];

$avatarId = (int) max(1, min(12, (int) ($profile['avatar_id'] ?? 1)));
$avatarSrc = '/assets/img/avatars/avatar-' . $avatarId . '.svg';

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

/**
 * Reason labels per the locked copywriting contract. The mapping table
 * is local to this view — Phase 6 ships these labels; future events
 * extend the map.
 */
$reasonLabels = [
    'email_verification' => 'Email verified',
    'final_session' => 'Sale completed',
    'transaction' => 'Transaction',
    'redemption' => 'Redemption',
    'review' => 'Detailed review',
    'listing_approval' => 'Listing approved',
    'report_validated' => 'Report validated',
    'streak_7day' => '7-day streak',
    'streak_30day' => '30-day streak',
    'void' => 'Points adjusted',
];

/**
 * Render an event_at timestamp as "Xh ago" / "Xd ago" / "just now".
 */
function profile_relative_time(string $eventAt): string
{
    try {
        $then = new DateTime($eventAt, new DateTimeZone('Asia/Colombo'));
        $now = new DateTime('now', new DateTimeZone('Asia/Colombo'));
        $diffSec = $now->getTimestamp() - $then->getTimestamp();
    } catch (\Throwable $e) {
        return '';
    }
    if ($diffSec < 60) {
        return 'just now';
    }
    if ($diffSec < 3600) {
        $m = (int) floor($diffSec / 60);
        return $m . 'm ago';
    }
    if ($diffSec < 86400) {
        $h = (int) floor($diffSec / 3600);
        return $h . 'h ago';
    }
    $d = (int) floor($diffSec / 86400);
    return $d . 'd ago';
}
?>
<div class="container py-4">
  <div class="card surface-raised p-4" data-testid="profile-owner-card">
    <div class="d-flex flex-column flex-md-row align-items-center align-items-md-start gap-4">
      <img src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8') ?>"
           alt="Avatar" width="96" height="96"
           class="rounded-circle flex-shrink-0"
           data-testid="profile-avatar">
      <div class="flex-grow-1 text-center text-md-start">
        <h1 class="display-lg mb-1" data-testid="profile-name">
          <?= htmlspecialchars((string) ($profile['full_name'] ?? $profile['nickname'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
          <?php if (!empty($profile['is_verified'])) : ?>
            <i class="bi bi-patch-check-fill text-primary align-baseline"
               data-bs-toggle="tooltip" data-bs-placement="top"
               title="Verified student"
               aria-label="Verified student"
               data-testid="profile-verified"></i>
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
          <?= \App\Support\View::partial('rank_badge', ['tier' => $tier, 'size' => 32]) ?>
          <?= \App\Support\View::partial('on_break_pill', ['lastActiveAt' => $lastActiveAt]) ?>
          <span class="body-sm" data-testid="profile-points"><strong><?= $points ?></strong> points</span>
          <?php if ($createdAtFormatted !== '') : ?>
            <span class="body-sm text-on-surface-variant" data-testid="profile-joined">Joined <?= htmlspecialchars($createdAtFormatted, ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
        </div>
        <?= \App\Support\View::partial('velocity_flag_pill', ['isFrozen' => $pointsFrozen]) ?>
      </div>
      <div class="d-flex flex-column gap-2 flex-shrink-0">
        <a href="/profile/edit" class="btn btn-primary"
           data-bs-toggle="tooltip" title="Edit your profile"
           data-testid="profile-edit-btn">Edit profile</a>
      </div>
    </div>

    <div class="profile-gamification">
      <?= \App\Support\View::partial('tier_progress', ['userId' => (int) ($profile['user_id'] ?? 0), 'points' => $points, 'tier' => $tier]) ?>
    </div>
  </div>

  <section class="card surface-raised p-4 mt-4" data-testid="profile-recent-activity">
    <h2 class="h5 mb-3">Recent activity</h2>
    <?php if (empty($recentActivity)) : ?>
      <p class="text-on-surface-variant mb-0"
         data-testid="profile-recent-activity-empty">
        No activity yet. Earn your first points by listing an item or completing a transaction.
      </p>
    <?php else : ?>
      <ul class="recent-activity-list" data-testid="profile-recent-activity-list">
        <?php foreach ($recentActivity as $row) :
            $delta = (int) ($row['delta'] ?? 0);
            $ref = (string) ($row['reference_type'] ?? '');
            $eventAt = (string) ($row['event_at'] ?? '');
            $meta = is_array($row['metadata'] ?? null) ? $row['metadata'] : [];
            $reason = $reasonLabels[$ref] ?? ucwords(str_replace('_', ' ', $ref));
            $isCapHit = !empty($meta['velocity_cap_hit']) || !empty($meta['pair_cap_hit']);
            $sign = $delta >= 0 ? '+' : '';
            $isZero = $delta === 0;
            ?>
          <li class="recent-activity-row"
              data-testid="profile-recent-activity-row">
            <span class="recent-activity-row__delta<?= $isZero ? ' recent-activity-row__delta--zero' : '' ?>"
                  aria-label="<?= $sign . $delta ?> points">
              <?= $sign . $delta ?>
            </span>
            <span class="recent-activity-row__reason"><?= htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($isCapHit) : ?>
              <span class="recent-activity-row__cap-meta">counted as 0</span>
            <?php endif; ?>
            <span class="recent-activity-row__time"><?= htmlspecialchars(profile_relative_time($eventAt), ENT_QUOTES, 'UTF-8') ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>