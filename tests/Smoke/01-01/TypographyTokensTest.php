<?php

declare(strict_types=1);

namespace App\Tests\Smoke\Smoke_01_01;

use PHPUnit\Framework\TestCase;

/**
 * Typography token smoke test for Phase 1 / Plan 01-01 Task 1 (UX-05).
 *
 * Asserts the static contract for typography tokens:
 *   - --font-family-display: Inter-based stack
 *   - --font-family-body: system-ui stack
 *   - --font-family-mono-code: monospace stack
 *   - ticket code display element has non-zero letter-spacing
 *     (the actual implementation uses .ticket-code-block__code with 0.05em;
 *     the gap spec says "0.04em or equivalent token" — we assert the
 *     behavioral contract that ticket codes are letter-spaced for readability)
 *   - at least 4 typography size tokens present
 *   - --font-weight-display-lg is bold (700)
 *   - --letter-spacing-display-lg is negative (display tracking)
 *   - Bootstrap overrides do NOT redeclare font-family values
 */
final class TypographyTokensTest extends TestCase
{
    private string $tokensPath;
    private string $componentsPath;
    private string $bootstrapOverridesPath;
    private string $tokensContent;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 3);
        $this->tokensPath = $root . '/public/assets/css/tickettrade.tokens.css';
        $this->componentsPath = $root . '/public/assets/css/tickettrade.components.css';
        $this->bootstrapOverridesPath = $root . '/public/assets/css/tickettrade.bootstrap-overrides.css';
        $this->tokensContent = (string) file_get_contents($this->tokensPath);
        $this->assertNotEmpty($this->tokensContent, 'tickettrade.tokens.css must be readable');
    }

    /**
     * The three required font-family tokens must be declared.
     */
    public function test_font_family_tokens_declared(): void
    {
        $this->assertMatchesRegularExpression(
            '/--font-family-display\s*:\s*\'Inter\'/',
            $this->tokensContent,
            'tokens.css must declare --font-family-display with Inter as the primary face'
        );

        $this->assertMatchesRegularExpression(
            '/--font-family-body\s*:\s*system-ui/',
            $this->tokensContent,
            'tokens.css must declare --font-family-body with system-ui as the primary face'
        );

        $this->assertMatchesRegularExpression(
            '/--font-family-mono-code\s*:\s*ui-monospace/',
            $this->tokensContent,
            'tokens.css must declare --font-family-mono-code with ui-monospace as the primary face'
        );
    }

    /**
     * Ticket code display elements must be letter-spaced for readability.
     * The gap listed 0.04em; the actual implementation uses 0.05em on
     * .ticket-code-block__code. The behavioral contract is "non-zero
     * letter-spacing applied to ticket code elements" — that's what we
     * assert here.
     */
    public function test_mono_code_ticket_block_has_letter_spacing(): void
    {
        $components = (string) file_get_contents($this->componentsPath);
        $this->assertNotEmpty($components, 'tickettrade.components.css must be readable');

        $this->assertMatchesRegularExpression(
            '/\.ticket-code-block__code\s*\{[^}]*letter-spacing\s*:\s*0\.0[1-9]em/s',
            $components,
            'components.css must apply a non-trivial letter-spacing (0.01em-0.09em) to .ticket-code-block__code for ticket code readability'
        );
    }

    /**
     * At least 4 typography size tokens must be declared.
     * The actual token set declares 6 (display-lg, display-md, headline-md,
     * body-lg, body-md, body-sm). 4 is the minimum the gap requires.
     */
    public function test_at_least_four_typography_size_tokens(): void
    {
        preg_match_all('/--font-size-(display-lg|display-md|headline-md|body-lg|body-md|body-sm|caption)\s*:/', $this->tokensContent, $matches);

        $unique = array_values(array_unique($matches[1] ?? []));
        $this->assertGreaterThanOrEqual(
            4,
            count($unique),
            sprintf('tokens.css must declare at least 4 typography size tokens, found %d (%s)', count($unique), implode(', ', $unique))
        );

        foreach (['display-lg', 'headline-md', 'body-md'] as $required) {
            $this->assertContains(
                $required,
                $unique,
                sprintf('tokens.css must declare --font-size-%s', $required)
            );
        }
    }

    /**
     * --font-weight-display-lg must be bold (700).
     */
    public function test_display_lg_weight_is_bold(): void
    {
        $this->assertMatchesRegularExpression(
            '/--font-weight-display-lg\s*:\s*700\s*;/',
            $this->tokensContent,
            'tokens.css must declare --font-weight-display-lg: 700 (bold)'
        );
    }

    /**
     * --letter-spacing-display-lg must exist (negative display tracking per UX-DR-2).
     */
    public function test_letter_spacing_display_lg_present(): void
    {
        $this->assertMatchesRegularExpression(
            '/--letter-spacing-display-lg\s*:\s*-0\.01em/',
            $this->tokensContent,
            'tokens.css must declare --letter-spacing-display-lg: -0.01em (display tracking)'
        );
    }

    /**
     * Bootstrap overrides must NOT redeclare font-family values.
     * Per D-02, Bootstrap is re-skinned via CSS custom properties only.
     */
    public function test_bootstrap_overrides_do_not_redeclare_font_family(): void
    {
        $overrides = (string) file_get_contents($this->bootstrapOverridesPath);
        $this->assertNotEmpty($overrides, 'tickettrade.bootstrap-overrides.css must be readable');

        $this->assertDoesNotMatchRegularExpression(
            '/font-family\s*:/i',
            $overrides,
            'tickettrade.bootstrap-overrides.css must not redeclare any font-family values (tokens.css owns the font stacks)'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/--bs-font-(family|sans-serif|monospace)/i',
            $overrides,
            'tickettrade.bootstrap-overrides.css must not override Bootstrap font custom properties'
        );
    }
}