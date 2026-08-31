<?php
/**
 * TicketTrade — Rank / Tier Ladder
 *
 * Per D-23 (the stub is config/ranks.php). Phase 6 expands this with
 * animations and the legend-glow class. The auth_service::tierFromPoints()
 * helper reads this and returns the matching tier.
 */

declare(strict_types=1);

$ranks = [
    'E' => ['name' => 'Recruit',    'min_points' => 0,    'badge_class' => 'bg-secondary'],
    'D' => ['name' => 'Rookie',     'min_points' => 50,   'badge_class' => 'bg-primary'],
    'C' => ['name' => 'Operative',  'min_points' => 150,  'badge_class' => 'bg-success'],
    'B' => ['name' => 'Specialist', 'min_points' => 400,  'badge_class' => 'bg-warning text-dark'],
    'A' => ['name' => 'Elite',      'min_points' => 800,  'badge_class' => 'bg-danger'],
    'S' => ['name' => 'Legend',     'min_points' => 1500, 'badge_class' => 'bg-dark'],
];

if (!function_exists('tierFromPoints')) {
    /**
     * Resolve the rank tier for a given point balance.
     *
     * Iterates the ladder in declaration order; the highest tier whose
     * min_points threshold the balance meets wins.
     *
     * @param int $points Current users.points balance
     * @return string Tier letter in [E, D, C, B, A, S]
     */
    function tierFromPoints(int $points): string
    {
        $ladder = require __DIR__ . '/ranks.php';
        $current = 'E';
        foreach ($ladder as $tier => $def) {
            if ($points >= $def['min_points']) {
                $current = $tier;
            }
        }
        return $current;
    }
}

return $ranks;
