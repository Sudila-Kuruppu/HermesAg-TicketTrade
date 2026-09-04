<?php

/**
 * TicketTrade — Support\View\partials\leaderboard_row
 *
 * Phase 6 Plan 06-03. One row in any of the four leaderboards.
 * Per EXPERIENCE.md L150 + 06-UI-SPEC.md Copywriting Contract:
 *   rank number  (body-md, color secondary)
 *   nickname     (body-md)
 *   meta text    (body-sm, on-surface-variant) — for category leaders:
 *                the category name pill, otherwise program/year (not
 *                surfaced in this build — users table lacks program/year
 *                per Phase 6 deviation #4 in 06-01-SUMMARY.md; the meta
 *                cell renders empty until those columns land).
 *   tier badge   (rank_badge partial, right-aligned)
 *
 * No encouragement chrome (no 🔥, no streak-count badge per D-01).
 *
 * Reads from $GLOBALS['_tt_view_vars']:
 *   @var int    $rank         Row number (1-indexed)
 *   @var int    $userId
 *   @var string $nickname
 *   @var string $meta         Optional program/year or category name
 *   @var string $tier         E | D | C | B | A | S
 *   @var int    $score
 *   @var bool   $isSelf       Highlight via 2px primary left-border
 *   @var string $profileUrl   Defaults to /profile/{nickname}
 *   @var string $size         'sm' (leaderboard) or 'md' (modal)
 */

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$rank = (int) ($__vars['rank'] ?? 0);
$userId = (int) ($__vars['userId'] ?? 0);
$nickname = (string) ($__vars['nickname'] ?? '');
$meta = (string) ($__vars['meta'] ?? '');
$tier = (string) ($__vars['tier'] ?? 'E');
$score = (int) ($__vars['score'] ?? 0);
$isSelf = (bool) ($__vars['isSelf'] ?? false);
$size = (string) ($__vars['size'] ?? 'sm');
$badgeSize = $size === 'md' ? 28 : 20;

$profileUrl = (string) ($__vars['profileUrl'] ?? '/profile/' . rawurlencode($nickname));
?>
<li class="leaderboard-row list-group-item d-flex align-items-center gap-3<?= $isSelf ? ' leaderboard-row--self' : '' ?>"
    data-testid="leaderboard-row"
    data-rank="<?= $rank ?>"
    data-user-id="<?= $userId ?>">
  <span class="leaderboard-row__rank body-md fw-medium text-secondary"
        data-testid="leaderboard-row-rank"
        aria-label="Rank <?= $rank ?>"><?= $rank ?></span>
  <a href="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') ?>"
     class="leaderboard-row__name body-md text-decoration-none text-on-surface flex-grow-1 text-truncate"
     data-testid="leaderboard-row-name">
    <?= htmlspecialchars($nickname, ENT_QUOTES, 'UTF-8') ?>
  </a>
  <?php if ($meta !== '') : ?>
    <span class="leaderboard-row__meta body-sm text-on-surface-variant text-truncate"
          data-testid="leaderboard-row-meta">
        <?= htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') ?>
    </span>
  <?php endif; ?>
  <span class="leaderboard-row__score body-sm text-on-surface-variant"
        data-testid="leaderboard-row-score"
        aria-label="<?= $score ?> points"><?= $score ?></span>
  <?= \App\Support\View::partial('rank_badge', ['tier' => $tier, 'size' => $badgeSize]) ?>
</li>
