<?php
/**
 * TicketTrade - Team Roster
 *
 * Source of truth for the landing-page Team section (Phase 3 D-26) and
 * for the WAD rubric's "Project Report ... group member names + roles"
 * requirement (WAD_Batch26.md sec 4).
 *
 * Update this file with the Batch 26.1 students' real names and initials
 * before running the demo. The first row should be the team leader (the
 * designated submitter per WAD_Batch26.md sec 4). Order = display order.
 *
 * Fields:
 *   name      Full name as it should appear on the landing page and in the report.
 *   role      Functional role (matches the 6-person split in AGENTS.md and PROJECT.md).
 *   initials  Two letters used for the avatar tile (first letter of given + family name).
 *   bio       One-line bio shown on the team card. MAX 80 chars; no PII.
 *   github    Optional GitHub username; rendered as a small link on the card.
 *   is_leader TRUE for exactly one row (the LMS submitter).
 */

declare(strict_types=1);

return [
    [
        'name'      => 'Team Leader',
        'role'      => 'Team Leader / Backend Lead',
        'initials'  => 'TL',
        'bio'       => 'Designated submitter; owns Auth, support substrate, bcrypt policy.',
        'github'    => '',
        'is_leader' => true,
    ],
    [
        'name'      => 'Backend Member 2',
        'role'      => 'Backend (Listings, Tickets, Migrations)',
        'initials'  => 'B2',
        'bio'       => 'Owns Listing/Service, Ticket/Service, and migration runner.',
        'github'    => '',
        'is_leader' => false,
    ],
    [
        'name'      => 'Frontend Lead',
        'role'      => 'Frontend (Tokens, Layout, Modal)',
        'initials'  => 'FL',
        'bio'       => 'Owns design tokens, layout template, listing modal UX.',
        'github'    => '',
        'is_leader' => false,
    ],
    [
        'name'      => 'Frontend Member 2',
        'role'      => 'Frontend (Board, My Tickets, Profile)',
        'initials'  => 'F2',
        'bio'       => 'Owns board view, My Tickets, profile pages, and seller dashboard.',
        'github'    => '',
        'is_leader' => false,
    ],
    [
        'name'      => 'Database Engineer',
        'role'      => 'Database (Schema, FULLTEXT, Leaderboards)',
        'initials'  => 'DB',
        'bio'       => 'Owns schema design, FULLTEXT indexes, leaderboard summary tables.',
        'github'    => '',
        'is_leader' => false,
    ],
    [
        'name'      => 'QA + Docs Lead',
        'role'      => 'QA / Docs (PHPUnit, phpcs, Report, Video)',
        'initials'  => 'QA',
        'bio'       => 'Owns PHPUnit suites, phpcs cleanliness, project report, demo video.',
        'github'    => '',
        'is_leader' => false,
    ],
];
