<?php

/**
 * TicketTrade — Auth/View/home
 *
 * Phase 3 Plan 03-04. The public landing page. Composes the five
 * sections (hero, vision/mission, how-it-works, team, footer) as
 * View partials. The Action passes:
 *   - $is_logged_in (bool)  controls the hero CTA
 *   - $team         (array) the 6-card team roster from config/team.php
 */

$is_logged_in = $is_logged_in ?? false;
$team = $team ?? [];
$partialsDir = __DIR__ . '/partials';
?>
<?php require $partialsDir . '/hero.php'; ?>
<?php require $partialsDir . '/vision_mission.php'; ?>
<?php require $partialsDir . '/how_it_works.php'; ?>
<?php require $partialsDir . '/team_section.php'; ?>
<?php require $partialsDir . '/landing_footer.php'; ?>
