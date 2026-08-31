<?php

/**
 * Phase 2 Plan 02-03 — PublicProfileRenderTest
 *
 * Unit-style coverage of the View rendering directly (without the HTTP
 * layer) for three behaviors the plan pins:
 *  - Verified badge appears ONLY when users.is_verified = TRUE (PROF-04).
 *  - The "Report user" link is rendered with the disabled + tooltip
 *    attributes (D-16).
 *  - The avatar src clamps avatar_id to the canonical 1..12 range
 *    (Pitfall 11 + D-18 — defense against a future schema change).
 *
 * Renders the View directly via View::render with a minimal $profile
 * array; captures the output via ob_start. No DB hits — these tests
 * verify the View's rendering logic only.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase02\User;

use App\Support\View;
use App\Tests\Integration\Phase02\Fixtures\Fixtures;

class PublicProfileRenderTest extends Fixtures
{
    /**
     * Render the View with the given profile + owner flag, capture output.
     *
     * @param array<string,mixed> $profile
     */
    private function renderView(array $profile, bool $isOwner = false): string
    {
        ob_start();
        try {
            View::render(
                APP_ROOT . '/src/User/View/public_profile.php',
                ['profile' => $profile, 'is_owner' => $isOwner]
            );
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    private function baseProfile(): array
    {
        return [
            'user_id' => 1,
            'nickname' => 'tester',
            'full_name' => 'Test User',
            'bio' => '',
            'avatar_id' => 1,
            'tier' => 'E',
            'points' => 0,
            'is_verified' => false,
            'created_at' => '2026-08-31 00:00:00',
        ];
    }

    public function test_verified_badge_visible_after_verify(): void
    {
        // Not verified
        $body = $this->renderView($this->baseProfile());
        $this->assertStringNotContainsString(
            'bi-patch-check-fill',
            $body,
            'verified checkmark must NOT render when is_verified is false (PROF-04)'
        );

        // Verified
        $profile = $this->baseProfile();
        $profile['is_verified'] = true;
        $body2 = $this->renderView($profile);
        $this->assertStringContainsString(
            'bi-patch-check-fill',
            $body2,
            'verified checkmark MUST render when is_verified is true (PROF-04)'
        );
        $this->assertStringContainsString('Verified student', $body2);
    }

    public function test_report_user_link_disabled(): void
    {
        $body = $this->renderView($this->baseProfile());
        // D-16 — the link is rendered disabled with the "Coming soon"
        // tooltip and aria-disabled for screen readers / keyboard users.
        $this->assertStringContainsString('aria-disabled="true"', $body);
        $this->assertStringContainsString('disabled', $body);
        $this->assertStringContainsString('Coming soon', $body);
        $this->assertStringContainsString('data-bs-toggle="tooltip"', $body);
        $this->assertStringContainsString('Report user', $body);
    }

    public function test_avatar_src_is_clamped(): void
    {
        // The View\'s `(int) max(1, min(12, $avatar_id))` clamp must
        // produce values in [1, 12] for any input.
        foreach ([0, 1, 5, 12, 13, 99, -5, 256] as $inputId) {
            $profile = $this->baseProfile();
            $profile['avatar_id'] = $inputId;
            $body = $this->renderView($profile);
            preg_match('#/assets/img/avatars/avatar-(\d+)\.svg#', $body, $m);
            $this->assertArrayHasKey(1, $m, "no avatar src found for avatar_id=$inputId");
            $rendered = (int) $m[1];
            $this->assertGreaterThanOrEqual(1, $rendered, "avatar_id=$inputId must clamp to >=1");
            $this->assertLessThanOrEqual(12, $rendered, "avatar_id=$inputId must clamp to <=12");
        }
    }

    public function test_no_bio_renders_muted_copy(): void
    {
        $profile = $this->baseProfile();
        $profile['bio'] = '';
        $body = $this->renderView($profile);
        $this->assertStringContainsString('No bio yet.', $body);
    }

    public function test_bio_with_newlines_uses_nl2br(): void
    {
        $profile = $this->baseProfile();
        $profile['bio'] = "Line 1\nLine 2";
        $body = $this->renderView($profile);
        $this->assertStringContainsString('Line 1<br', $body);
        $this->assertStringContainsString('Line 2', $body);
    }

    public function test_join_date_uses_asia_colombo(): void
    {
        $profile = $this->baseProfile();
        $profile['created_at'] = '2026-08-31 18:00:00'; // UTC midnight in Colombo = +5:30 = 23:30 Aug 31
        $body = $this->renderView($profile);
        // Colombo is UTC+5:30, so 18:00 UTC → 23:30 Colombo same day
        $this->assertStringContainsString('31 Aug 2026', $body);
    }
}
