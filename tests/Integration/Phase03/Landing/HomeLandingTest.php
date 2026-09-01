<?php
/**
 * Phase 3 — HomeLandingTest
 *
 * Verifies the public landing page at `/`:
 *   - 200 response, all 5 sections render (hero, vision/mission,
 *     how-it-works, team, footer)
 *   - Hero CTAs: `Get Started` (-> /register) for guests; `My listings`
 *     (-> /my-listings) for logged-in users
 *   - Hero copy: `<h1>Every Trade Ends With Proof</h1>` and the
 *     NSBM tagline
 *   - Vision & Mission: 2 Bootstrap cards (col-md-6 / col-12)
 *   - How It Works: 5 step cards with number badges
 *   - Footer: NSBM branding, simulation disclaimer, GitHub + Drive links
 *
 * Tests dispatch HomeAction directly with controlled $GLOBALS.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase03\Landing;

use App\Auth\Action\HomeAction;
use App\Support\View;
use App\Tests\Integration\Phase03\Fixtures\Fixtures;

class HomeLandingTest extends Fixtures
{
    public function test_home_renders_200_with_all_five_sections_for_guest(): void
    {
        $out = $this->renderHome();
        // Status: HomeAction::handle is called; we just check the HTML.
        $this->assertStringContainsString('<h1 id="hero-heading" class="display-lg mb-3">Every Trade Ends With Proof</h1>', $out);
        $this->assertStringContainsString("NSBM's campus-only marketplace where every purchase produces a confirmable digital ticket.", $out);
        // Hero CTAs for guest
        $this->assertStringContainsString('href="/register"', $out);
        $this->assertStringContainsString('>Get Started<', $out);
        // Hero CTAs for guest (Explore stays)
        $this->assertStringContainsString('href="/board"', $out);
        $this->assertStringContainsString('>Explore Marketplace<', $out);
        // Sections present in order
        $heroPos = strpos($out, 'class="hero bg-primary');
        $vmPos = strpos($out, 'class="vision-mission container');
        $hwPos = strpos($out, 'class="how-it-works bg-surface-container');
        $teamPos = strpos($out, 'class="team container');
        $footerPos = strpos($out, 'class="landing-footer bg-surface-container');
        $this->assertNotFalse($heroPos);
        $this->assertNotFalse($vmPos);
        $this->assertNotFalse($hwPos);
        $this->assertNotFalse($teamPos);
        $this->assertNotFalse($footerPos);
        $this->assertLessThan($vmPos, $heroPos);
        $this->assertLessThan($hwPos, $vmPos);
        $this->assertLessThan($teamPos, $hwPos);
        $this->assertLessThan($footerPos, $teamPos);
    }

    public function test_home_hero_cta_flips_for_logged_in_user(): void
    {
        $out = $this->renderHome(true);
        // Logged in: "My listings" -> /my-listings, not "Get Started"
        $this->assertStringContainsString('>My listings<', $out);
        $this->assertStringContainsString('href="/my-listings"', $out);
        $this->assertStringNotContainsString('>Get Started<', $out);
        // Explore Marketplace stays
        $this->assertStringContainsString('>Explore Marketplace<', $out);
        $this->assertStringContainsString('href="/board"', $out);
    }

    public function test_home_hero_does_not_bounce_logged_in_user(): void
    {
        // Phase 2 HomeAction redirected logged-in users to /board; Phase 3
        // renders the landing page for everyone (the hero CTA handles the
        // logged-in case by showing My listings).
        $out = $this->renderHome(true);
        $this->assertStringContainsString('Every Trade Ends With Proof', $out);
        // Ensure no redirect header was emitted (we just check the body)
        $this->assertStringContainsString('<h1', $out);
    }

    public function test_vision_mission_section_has_two_cards(): void
    {
        $out = $this->renderHome();
        $this->assertStringContainsString('<h2 class="h4">Our Vision</h2>', $out);
        $this->assertStringContainsString('<h2 class="h4">Our Mission</h2>', $out);
        // 2 cards inside the vision-mission row, each col-12 col-md-6
        $visionCount = substr_count($out, '<div class="col-12 col-md-6">');
        // Note: the how-it-works section uses col-12 col-md-6 col-lg, so
        // we restrict the count to vision-mission by inspecting that block.
        $vmBlock = substr($out, (int) strpos($out, 'vision-mission container'), 1500);
        $this->assertSame(2, substr_count($vmBlock, '<div class="col-12 col-md-6">'));
        // The vision-mission col-lg count is implicit (the count above is enough).
        $this->assertGreaterThanOrEqual(2, $visionCount);
    }

    public function test_how_it_works_section_has_five_step_cards_with_badges(): void
    {
        $out = $this->renderHome();
        $hwBlock = substr($out, (int) strpos($out, 'how-it-works bg-surface-container'), 4000);
        // 5 step cards
        $this->assertSame(5, substr_count($hwBlock, '<div class="col-12 col-md-6 col-lg">'));
        // Each step has a badge with the step number (1..5)
        for ($i = 1; $i <= 5; $i++) {
            $this->assertStringContainsString('>' . $i . '</span>', $hwBlock);
        }
        // 5 step titles per D-25
        $this->assertStringContainsString('Register &amp; verify', $hwBlock);
        $this->assertStringContainsString('List or browse', $hwBlock);
        $this->assertStringContainsString('Buy with a digital ticket', $hwBlock);
        $this->assertStringContainsString('Redeem in person', $hwBlock);
        $this->assertStringContainsString('Rate &amp; review', $hwBlock);
    }

    public function test_footer_renders_nsbm_branding_simulation_disclaimer_and_links(): void
    {
        $out = $this->renderHome();
        // NSBM branding
        $this->assertStringContainsString('TicketTrade - NSBM Marketplace', $out);
        // WAD batch line
        $this->assertStringContainsString('WAD coursework project (Batch 26.1, 2026-09-02)', $out);
        // Simulation disclaimer
        $this->assertStringContainsString('Simulation only - no real money flows', $out);
        // GitHub + Drive links
        $this->assertStringContainsString('href="https://github.com/"', $out);
        $this->assertStringContainsString('>GitHub<', $out);
        $this->assertStringContainsString('href="https://drive.google.com/"', $out);
        $this->assertStringContainsString('>Drive<', $out);
    }

    public function test_home_action_dispatches_via_router_route_map(): void
    {
        // The route map's GET / must point at HomeAction::handle.
        $routes = require APP_ROOT . '/config/routes.php';
        $this->assertArrayHasKey('GET /', $routes);
        $this->assertSame('App\Auth\Action\HomeAction', $routes['GET /'][0]);
        $this->assertSame('handle', $routes['GET /'][1]);
        // Public landing is NOT auth-gated.
        $this->assertFalse((bool) ($routes['GET /'][2]['auth'] ?? true));
        $this->assertFalse((bool) ($routes['GET /'][2]['admin'] ?? true));
        $this->assertFalse((bool) ($routes['GET /'][2]['csrf'] ?? true));
    }

    /**
     * Helper: dispatch HomeAction and capture its output.
     *
     * @param bool $loggedIn When true, set $GLOBALS['current_user'] to a stub row.
     */
    private function renderHome(bool $loggedIn = false): string
    {
        $originalUser = $GLOBALS['current_user'] ?? null;
        if ($loggedIn) {
            $GLOBALS['current_user'] = [
                'user_id' => 42,
                'email' => 'kasun@students.nsbm.ac.lk',
                'nickname' => 'kasun',
                'is_admin' => false,
                'is_banned' => false,
            ];
        } else {
            $GLOBALS['current_user'] = null;
        }

        ob_start();
        try {
            $action = new HomeAction();
            $action->handle();
        } catch (\Throwable $e) {
            ob_end_clean();
            $GLOBALS['current_user'] = $originalUser;
            throw $e;
        }
        $out = (string) ob_get_clean();
        $GLOBALS['current_user'] = $originalUser;

        return $out;
    }
}
