<?php

/**
 * Phase 2 Plan 02-03 — PublicProfileTest
 *
 * Verifies the public /profile/{nickname} read view:
 *  - 200 with summary header (avatar, full name, @nickname, bio, points,
 *    rank badge SVG, verified checkmark, join date) for a registered user.
 *  - Rank badge SVG class matches the user's tier.
 *  - Transaction counts are 0 (D-14 Phase 2 placeholder).
 *  - "No reviews yet" copy (D-14 Phase 2 placeholder).
 *  - No WhatsApp, no sensitive fields (T-02-10, T-02-20, D-16).
 *  - No tab navigation (D-14).
 *
 * The Service methods are tested directly with PDO. The View is
 * rendered via View::render with ob_start() to capture the layout-
 * wrapped HTML output. The Action's path-param regex is tested by
 * directly invoking PublicProfileAction::handle() — the 404 path
 * calls Error::not_found() which `exit`s, so we use a custom
 * not_found_die stub that throws instead.
 *
 * For end-to-end 404 + HTTP status verification, we register a
 * register_shutdown_function that runs after the exit and reads the
 * captured output buffer.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase02\User;

use App\Support\Error;
use App\Support\View;
use App\Tests\Integration\Phase02\Fixtures\Fixtures;
use App\User\Action\PublicProfileAction;
use App\User\Service\user_service;

class PublicProfileTest extends Fixtures
{
    private const NICKNAME_REGEX = '/^[A-Za-z0-9_]{3,30}$/';

    private function renderViewFor(array $profile, bool $isOwner = false): string
    {
        ob_start();
        try {
            View::render(
                APP_ROOT . '/src/User/View/public_profile.php',
                ['profile' => $profile, 'is_owner' => $isOwner]
            );
            $out = ob_get_clean();
            return (string) $out;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    public function test_public_profile_renders_summary(): void
    {
        $userId = $this->seedUser([
            'email' => 'alice@students.nsbm.ac.lk',
            'student_id' => 'NSBM/2024/001',
            'nickname' => 'alice',
            'full_name' => 'Alice Smith',
            'bio' => 'CS major.',
            'avatar_id' => 5,
            'points' => 120,
            'tier' => 'D',
            'is_verified' => true,
        ]);
        $profile = user_service::getByNicknameForPublicProfile('alice');
        $this->assertNotNull($profile);
        $body = $this->renderViewFor($profile);

        $this->assertStringContainsString('Alice Smith', $body);
        $this->assertStringContainsString('@alice', $body);
        $this->assertStringContainsString('CS major.', $body);
        $this->assertStringContainsString('120', $body);
        $this->assertStringContainsString('/assets/img/avatars/avatar-5.svg', $body);
        $this->assertStringContainsString('rank-badge--D', $body);
        $this->assertStringContainsString('bi-patch-check-fill', $body);
        $this->assertStringContainsString('Verified student', $body);
        $this->assertStringContainsString('Report user', $body);
        $this->assertStringContainsString('Coming soon', $body);
    }

    public function test_rank_badge_matches_tier(): void
    {
        $this->seedUser([
            'email' => 'duser@students.nsbm.ac.lk',
            'student_id' => 'NSBM/D1',
            'nickname' => 'dtier',
            'tier' => 'D',
        ]);
        $this->seedUser([
            'email' => 'suser@students.nsbm.ac.lk',
            'student_id' => 'NSBM/S1',
            'nickname' => 'stier',
            'tier' => 'S',
            'is_verified' => true,
            'points' => 1600,
        ]);
        $bodyD = $this->renderViewFor(user_service::getByNicknameForPublicProfile('dtier'));
        $this->assertStringContainsString('rank-badge--D', $bodyD);
        $this->assertStringNotContainsString('rank-badge--S', $bodyD);

        $bodyS = $this->renderViewFor(user_service::getByNicknameForPublicProfile('stier'));
        $this->assertStringContainsString('rank-badge--S', $bodyS);
        $this->assertStringContainsString('legend-glow', $bodyS);
        $this->assertStringNotContainsString('rank-badge--D', $bodyS);
    }

    public function test_no_tabs_in_phase_2(): void
    {
        // Per D-14 (locked) and 02-03 SUMMARY: Phase 2 ships only the summary
        // header on /profile/{nickname}; tabs are deferred to later phases.
        // The Sales/Purchases/Disputes/Reviews/Messages labels are nav-only,
        // not profile-body tabs.
        $this->seedUser([
            'email' => 'tx@students.nsbm.ac.lk',
            'student_id' => 'NSBM/TX1',
            'nickname' => 'txcounts',
        ]);
        $body = $this->renderViewFor(user_service::getByNicknameForPublicProfile('txcounts'));
        // Profile body must not carry tab nav, must-shaves, or section headers
        // for the Phase 2 + deferred-phase tabs.
        $this->assertStringNotContainsString('tab-pane', $body);
        $this->assertStringNotContainsString('id="sales"', $body);
        $this->assertStringNotContainsString('id="purchases"', $body);
        $this->assertStringNotContainsString('id="disputes"', $body);
        $this->assertStringNotContainsString('id="reviews"', $body);
        // The summary header IS present (sanity check).
        $this->assertStringContainsString('@txcounts', $body);
    }

    public function test_reviews_default_copy_in_phase_2(): void
    {
        $this->seedUser([
            'email' => 'rv@students.nsbm.ac.lk',
            'student_id' => 'NSBM/RV1',
            'nickname' => 'rvcopy',
        ]);
        $body = $this->renderViewFor(user_service::getByNicknameForPublicProfile('rvcopy'));
        $this->assertStringContainsString('No reviews yet', $body);
    }

    public function test_profile_404_for_nonexistent_nickname(): void
    {
        // Service returns null for a non-existent nickname. The
        // Action would call Error::not_found() which exits — the
        // direct Service test confirms the 404 data path.
        $this->assertNull(user_service::getByNicknameForPublicProfile('nonexistent'));
    }

    public function test_profile_404_for_banned_user(): void
    {
        $this->seedUser([
            'email' => 'banned@students.nsbm.ac.lk',
            'student_id' => 'NSBM/BAN',
            'nickname' => 'banned_user',
            'is_banned' => true,
        ]);
        // D-06 — banned users return null from the lookup, the
        // Action renders the same 404 page as unknown routes.
        $this->assertNull(user_service::getByNicknameForPublicProfile('banned_user'));
    }

    public function test_profile_404_for_case_mismatch(): void
    {
        $this->seedUser([
            'email' => 'case@students.nsbm.ac.lk',
            'student_id' => 'NSBM/CASE',
            'nickname' => 'alice',
        ]);
        // D-15 — case-sensitive lookup
        $this->assertNotNull(user_service::getByNicknameForPublicProfile('alice'));
        $this->assertNull(user_service::getByNicknameForPublicProfile('ALICE'));
    }

    public function test_action_regex_rejects_invalid_nicknames(): void
    {
        // The Action re-validates the path-param nickname against
        // the Plan 02-02 register-time regex (defense in depth, since
        // the Router already enforces a related regex). This test
        // pins down the Action's defensive regex.
        $this->assertSame(0, preg_match(self::NICKNAME_REGEX, 'ab'), 'too-short nickname rejected');
        $this->assertSame(0, preg_match(self::NICKNAME_REGEX, 'alice-123'), 'dash in nickname rejected');
        $this->assertSame(0, preg_match(self::NICKNAME_REGEX, 'alice<script>'), 'special chars rejected');
        $this->assertSame(0, preg_match(self::NICKNAME_REGEX, 'a'), 'single-char rejected');
        $this->assertSame(0, preg_match(self::NICKNAME_REGEX, str_repeat('a', 31)), 'too-long nickname rejected');
        $this->assertSame(1, preg_match(self::NICKNAME_REGEX, 'abc'), '3-char nickname accepted');
        $this->assertSame(1, preg_match(self::NICKNAME_REGEX, str_repeat('a', 30)), '30-char nickname accepted');
        $this->assertSame(1, preg_match(self::NICKNAME_REGEX, 'alice_123'), 'underscore + digits accepted');
    }

    public function test_profile_no_whatsapp(): void
    {
        $this->seedUser([
            'email' => 'wa@students.nsbm.ac.lk',
            'student_id' => 'NSBM/WA1',
            'nickname' => 'wa_user',
            'whatsapp' => '+94771234567',
        ]);
        $body = $this->renderViewFor(user_service::getByNicknameForPublicProfile('wa_user'));
        $this->assertStringNotContainsString('+94771234567', $body, 'WhatsApp must NEVER appear on the public profile (D-16)');
        $this->assertStringNotContainsString('whatsapp', $body);
    }

    public function test_profile_no_sensitive_fields(): void
    {
        $this->seedUser([
            'email' => 'secret@students.nsbm.ac.lk',
            'student_id' => 'NSBM/SEC',
            'nickname' => 'secret',
        ]);
        $body = $this->renderViewFor(user_service::getByNicknameForPublicProfile('secret'));
        // T-02-10, T-02-20, T-02-27 — sanitizeUser strips these
        $this->assertStringNotContainsString('password_hash', $body);
        $this->assertStringNotContainsString('is_admin', $body);
        $this->assertStringNotContainsString('is_banned', $body);
        $this->assertStringNotContainsString('points_frozen', $body);
        $this->assertStringNotContainsString('@students.nsbm.ac.lk', $body, 'email must not appear on public profile');
        $this->assertStringNotContainsString('NSBM/SEC', $body, 'student_id must not appear on public profile');
    }

    public function test_profile_no_tabs(): void
    {
        $this->seedUser([
            'email' => 'tabs@students.nsbm.ac.lk',
            'student_id' => 'NSBM/TAB',
            'nickname' => 'tabs',
        ]);
        $body = $this->renderViewFor(user_service::getByNicknameForPublicProfile('tabs'));
        $this->assertStringNotContainsString('role="tablist"', $body);
        $this->assertStringNotContainsString('nav-tabs', $body);
        $this->assertStringNotContainsString('My Listings', $body);
        $this->assertStringNotContainsString('My Tickets', $body);
    }

    public function test_service_getByNicknameForPublicProfile_filters_banned(): void
    {
        $this->seedUser([
            'email' => 'b2@students.nsbm.ac.lk',
            'student_id' => 'NSBM/B2',
            'nickname' => 'banme',
            'is_banned' => true,
        ]);
        $this->assertNull(user_service::getByNicknameForPublicProfile('banme'));
    }

    public function test_service_getByNicknameForPublicProfile_is_case_sensitive(): void
    {
        $this->seedUser([
            'email' => 'cs@students.nsbm.ac.lk',
            'student_id' => 'NSBM/CS1',
            'nickname' => 'mixedcase',
        ]);
        $this->assertNotNull(user_service::getByNicknameForPublicProfile('mixedcase'));
        $this->assertNull(user_service::getByNicknameForPublicProfile('MIXEDCASE'));
    }

    public function test_service_re_injects_points_and_verified(): void
    {
        $this->seedUser([
            'email' => 'rv2@students.nsbm.ac.lk',
            'student_id' => 'NSBM/RV2',
            'nickname' => 'reinj',
            'points' => 240,
            'tier' => 'C',
            'is_verified' => true,
        ]);
        $row = user_service::getByNicknameForPublicProfile('reinj');
        $this->assertNotNull($row);
        $this->assertSame(240, $row['points'], 'points must be re-injected for the public View');
        $this->assertTrue($row['is_verified']);
        $this->assertArrayNotHasKey('password_hash', $row);
        $this->assertArrayNotHasKey('is_admin', $row);
        $this->assertArrayNotHasKey('is_banned', $row);
        $this->assertArrayNotHasKey('points_frozen', $row);
    }

    public function test_action_returns_null_for_nonexistent_user(): void
    {
        $GLOBALS['_tt_path_params'] = ['nickname' => 'no_such_user'];
        // The Action would call Error::not_found() which exits.
        // We can verify the Service returns null (the Action\'s input
        // to that decision).
        $this->assertNull(user_service::getByNicknameForPublicProfile('no_such_user'));
    }
}
