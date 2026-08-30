<?php

declare(strict_types=1);

namespace App\Tests\Smoke\Smoke_01_02;

use PHPUnit\Framework\TestCase;

/**
 * Empty-state smoke test for Phase 1 / Plan 01-02 Task 3.
 *
 * Asserts:
 *   - named-copy contract (UX-DR-34): no banned generic strings
 *   - retry button attribute: error-state partial has a <button data-error-state>
 *     with the literal text "Tap to retry"
 */
final class EmptyStateTest extends TestCase
{
    private string $emptyStatePath;
    private string $errorStatePath;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 3);
        $this->emptyStatePath = $root . '/public/mockups/_partials/empty-state.html';
        $this->errorStatePath = $root . '/public/mockups/_partials/error-state.html';
    }

    /**
     * The partial must declare the empty-state structure (illustration +
     * title + description + optional CTA). The body text must NOT be a
     * generic banned phrase (UX-DR-34 named-copy contract).
     */
    public function test_named_copy_contract(): void
    {
        $empty = (string) file_get_contents($this->emptyStatePath);
        $this->assertNotEmpty($empty, 'empty-state.html must be readable');

        // Must contain the structural contract
        $this->assertStringContainsString('class="empty-state"', $empty);
        $this->assertStringContainsString('data-empty-state', $empty);
        $this->assertStringContainsString('empty-state__title', $empty);
        $this->assertStringContainsString('empty-state__description', $empty);

        // Extract the title text. The default title is "No tickets yet".
        preg_match('/<h2 class="empty-state__title">(.*?)<\/h2>/s', $empty, $m);
        $this->assertNotEmpty($m, 'empty-state.html must declare a title');
        $title = trim(strip_tags($m[1]));

        // Banned phrases per UX-DR-34
        $banned = ['Oops!', 'Something went wrong', 'Error', 'Empty', 'No data'];
        foreach ($banned as $phrase) {
            $this->assertNotSame(
                $phrase,
                $title,
                sprintf('empty-state title "%s" matches banned generic string', $title)
            );
        }

        // Title should be a noun phrase describing the surface state.
        // The default title "No tickets yet" passes.
        $this->assertNotEmpty($title, 'empty-state title must not be empty');

        // Verify the my-tickets mockup uses a different named copy for its
        // empty state (the Redeemed pane).
        $root = dirname(__DIR__, 3);
        $myTickets = (string) file_get_contents($root . '/public/mockups/my-tickets.html');
        $this->assertStringContainsString("You haven't redeemed any tickets", $myTickets);
    }

    /**
     * The error-state partial must have a <button data-error-state>
     * with the literal text "Tap to retry" (UX-DR-34).
     */
    public function test_retry_button_attribute(): void
    {
        $error = (string) file_get_contents($this->errorStatePath);
        $this->assertNotEmpty($error, 'error-state.html must be readable');

        // Must declare the structural contract
        $this->assertStringContainsString('class="error-state"', $error);
        $this->assertStringContainsString('data-error-state', $error);
        $this->assertStringContainsString('error-state__retry', $error);

        // Must declare the retry button with the literal text
        $this->assertMatchesRegularExpression(
            '/<button[^>]*data-error-state[^>]*>Tap to retry<\/button>/',
            $error,
            'error-state.html must declare a <button data-error-state>Tap to retry</button>'
        );
    }
}
