<?php

/**
 * TicketTrade — Auth/View/partials/team_section
 *
 * Phase 3 Plan 03-04. Six Bootstrap cards from `config/team.php`.
 * Each card has an initials avatar (2 letters), full name, role, and
 * a one-line bio. Editing the team config re-renders on next page
 * load.
 *
 * Threat T-03-30: htmlspecialchars on every member field before render.
 *
 * Variables (from the parent View):
 *   - $team (array)  the loaded team config
 */

$team = $team ?? [];
?>
<section class="team container py-5" aria-labelledby="team-heading">
  <h2 id="team-heading" class="h3 text-center mb-4">Meet the Team</h2>
  <p class="text-center text-on-surface-variant mb-4">A six-person WAD Batch 26.1 team from NSBM Green University.</p>
  <div class="row g-4">
    <?php foreach ($team as $member):
      $name = htmlspecialchars((string) ($member['name'] ?? ''), ENT_QUOTES, 'UTF-8');
      $role = htmlspecialchars((string) ($member['role'] ?? ''), ENT_QUOTES, 'UTF-8');
      $initials = htmlspecialchars((string) ($member['initials'] ?? ''), ENT_QUOTES, 'UTF-8');
      $bio = htmlspecialchars((string) ($member['bio'] ?? ''), ENT_QUOTES, 'UTF-8');
      $isLeader = !empty($member['is_leader']);
    ?>
      <div class="col-12 col-sm-6 col-md-4 col-lg-2">
        <div class="card h-100 text-center">
          <div class="card-body">
            <div class="avatar rounded-circle bg-surface-container-high text-on-surface-variant d-flex align-items-center justify-content-center mx-auto mb-3" style="width:64px;height:64px;font-weight:600" aria-hidden="true"><?= $initials ?></div>
            <h3 class="h6 mb-1"><?= $name ?><?php if ($isLeader): ?> <span class="visually-hidden">(team leader)</span><?php endif; ?></h3>
            <p class="small text-on-surface-variant mb-1"><?= $role ?></p>
            <p class="small mb-0"><?= $bio ?></p>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
