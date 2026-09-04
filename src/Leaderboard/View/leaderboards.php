<?php

/**
 * TicketTrade — Leaderboard\View\leaderboards
 *
 * Plan 06-03. Public leaderboards page (auth=false, csrf=false, no rate
 * limit). Four board cards in a 2x2 CSS grid on >=768px / stacked on
 * mobile. Each card has:
 *   - the locked title (06-UI-SPEC.md Copywriting Contract)
 *   - skeleton shimmer on cold load (10 rows per board)
 *   - the row list OR per-board empty state
 *
 * Reads JSON cache files written by the daily cron; falls back to a
 * direct summary-table read on cache miss (cold start before the first
 * daily cron).
 *
 * Per PTS-09 privacy: the row data only carries nickname, tier, score,
 * and metadata. NEVER student_id, full_name, email, or whatsapp.
 *
 * @var array<string, array{generated_at:string, rows: array}> $boards
 *  Map of slug => cached payload. When a slug is missing, the View
 *  renders the cold-load skeleton (10 placeholder rows).
 */

$__vars = $GLOBALS['_tt_view_vars'] ?? [];
$boards = is_array($__vars['boards'] ?? null) ? $__vars['boards'] : [];

$boardMeta = [
    'campus_legends' => [
        'title' => 'Campus Legends Wall',
        'empty' => 'No Legends yet. Reach 1500 points to enter.',
    ],
    'weekly_risers' => [
        'title' => 'Weekly Risers',
        'empty' => 'No weekly risers yet. Earn 50+ points this week to appear.',
    ],
    'category_leaders' => [
        'title' => 'Category Leaders',
        'empty' => 'No category sales yet. Complete a sale to appear.',
        'group_by_category' => true,
    ],
    'streak_kings' => [
        'title' => 'Streak Kings',
        'empty' => 'No active streaks yet. Streaks build as you log in daily.',
    ],
];

$currentUser = $GLOBALS['current_user'] ?? null;
$currentUserId = $currentUser !== null ? (int) ($currentUser['user_id'] ?? 0) : 0;
?>
<div class="container py-4" data-testid="leaderboards-page">
  <h1 class="display-md mb-4" data-testid="leaderboards-title">Leaderboards</h1>
  <div class="leaderboards-grid">
    <?php foreach ($boardMeta as $slug => $meta) :
        $cache = $boards[$slug] ?? null;
        $hasRows = $cache !== null && !empty($cache['rows']);
        ?>
      <section class="leaderboard-card" data-testid="leaderboard-card-<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>">
        <h2 class="leaderboard-card__title"><?= htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8') ?></h2>
        <?php if (!$hasRows) : ?>
            <?php for ($i = 0; $i < 10; $i++) : ?>
            <div class="leaderboard-skeleton-row skeleton" aria-hidden="true"
                 data-skeleton></div>
            <?php endfor; ?>
          <p class="leaderboard-empty mb-0"
             data-testid="leaderboard-empty-<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($meta['empty'], ENT_QUOTES, 'UTF-8') ?>
          </p>
        <?php else :
            // Category Leaders renders grouped by category_id; the others
            // are flat lists.
            if (!empty($meta['group_by_category'])) :
                $grouped = [];
                foreach ($cache['rows'] as $r) {
                    $catId = (int) ($r['category_id'] ?? 0);
                    $grouped[$catId][] = $r;
                }
                ?>
                <?php foreach ($grouped as $catId => $rows) :
                    $catName = $rows[0]['category_name'] ?? ('Category ' . $catId);
                    ?>
                <h3 class="h6 mt-3 mb-2"
                    data-testid="leaderboard-category-heading">
                    <?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <ul class="leaderboard-row-list list-group list-group-flush">
                    <?php foreach ($rows as $r) :
                        $isSelf = $currentUserId !== 0 && (int) ($r['user_id'] ?? 0) === $currentUserId;
                        $metaText = (string) ($r['score'] ?? 0) . ' sales';
                        ?>
                        <?= \App\Support\View::partial('leaderboard_row', [
                        'rank' => (int) ($r['rank'] ?? 0),
                        'userId' => (int) ($r['user_id'] ?? 0),
                        'nickname' => (string) ($r['nickname'] ?? ''),
                        'meta' => $metaText,
                        'tier' => (string) ($r['tier'] ?? 'E'),
                        'score' => (int) ($r['score'] ?? 0),
                        'isSelf' => $isSelf,
                    ]) ?>
                    <?php endforeach; ?>
                </ul>
                <?php endforeach; ?>
            <?php else : ?>
              <ul class="leaderboard-row-list list-group list-group-flush">
                <?php foreach ($cache['rows'] as $r) :
                        $isSelf = $currentUserId !== 0 && (int) ($r['user_id'] ?? 0) === $currentUserId;
                    ?>
                    <?= \App\Support\View::partial('leaderboard_row', [
                      'rank' => (int) ($r['rank'] ?? 0),
                      'userId' => (int) ($r['user_id'] ?? 0),
                      'nickname' => (string) ($r['nickname'] ?? ''),
                      'meta' => '',
                      'tier' => (string) ($r['tier'] ?? 'E'),
                      'score' => (int) ($r['score'] ?? 0),
                      'isSelf' => $isSelf,
                  ]) ?>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>
</div>