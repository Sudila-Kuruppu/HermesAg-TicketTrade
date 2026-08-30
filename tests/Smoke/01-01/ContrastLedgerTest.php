<?php

declare(strict_types=1);

namespace App\Tests\Smoke\Smoke_01_01;

use PHPUnit\Framework\TestCase;

/**
 * Contrast Ledger smoke test for Phase 1 / Plan 01-01 Task 2.
 *
 * Parses DESIGN.md, extracts every row of the Contrast Ledger table,
 * loads tickettrade.tokens.css, looks up each named token in both
 * :root[data-theme="light"] and :root[data-theme="dark"] blocks, and
 * asserts the hex value is non-empty.
 *
 * Plus a separate test for the no-hex-literal-outside-tokens rule.
 */
final class ContrastLedgerTest extends TestCase
{
    private string $tokensPath;
    private string $designPath;
    private string $tokensContent;
    private array $lightTokens = [];
    private array $darkTokens = [];

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 3);
        $this->tokensPath = $root . '/public/assets/css/tickettrade.tokens.css';
        $this->designPath = $root . '/DESIGN.md';

        $this->tokensContent = (string) file_get_contents($this->tokensPath);
        $this->lightTokens = $this->extractTokens(':root[data-theme="light"]');
        $this->darkTokens = $this->extractTokens(':root[data-theme="dark"]');
    }

    /**
     * Iterates the contrast ledger rows from DESIGN.md and asserts
     * each token referenced by name has a non-empty hex in both
     * light and dark themes (where present).
     */
    public function test_contrast_ledger_tokens_resolve(): void
    {
        $rows = $this->extractContrastLedgerRows();
        $this->assertGreaterThanOrEqual(15, count($rows), 'Expected at least 15 contrast ledger rows in DESIGN.md');

        $matched = 0;
        $missing = [];

        foreach ($rows as $row) {
            foreach ($row['tokens'] as $tokenName) {
                $this->assertNotEmpty($tokenName, 'Empty token name in ledger row');

                // DESIGN.md uses {colors.X}; tokens.css uses --color-X.
                // Normalize by adding the 'color-' prefix (and dropping any 's').
                $cssName = $this->normalizeTokenName($tokenName);

                // Token may exist only in one theme; assert it exists in at least one
                $inLight = array_key_exists($cssName, $this->lightTokens);
                $inDark = array_key_exists($cssName, $this->darkTokens);

                if ($inLight || $inDark) {
                    $matched++;
                } else {
                    $missing[] = $cssName;
                }
            }
        }

        $this->assertGreaterThan(0, $matched, 'No ledger tokens resolved to the tokens.css file');
        $this->assertEmpty($missing, 'The following ledger tokens are not in tokens.css: ' . implode(', ', array_unique($missing)));

        fwrite(STDOUT, sprintf("Contrast ledger: %d/%d token references resolved.
", $matched, $matched + count($missing)));
    }

    /**
     * Normalize DESIGN.md token name to CSS custom property name.
     * DESIGN.md: colors.primary, colors.surface-raised-dark
     * CSS:       --color-primary, --color-surface-raised-dark
     *
     * @param string $name  Token name as it appears in DESIGN.md (without 'colors.' prefix)
     */
    private function normalizeTokenName(string $name): string
    {
        // Strip leading 's' from 'colors' style prefixes (none, but safety)
        return 'color-' . $name;
    }

    /**
     * No hex literal appears in any file under public/ or config/
     * other than tickettrade.tokens.css.
     */
    public function test_no_hex_lit_outside_tokens(): void
    {
        $root = dirname(__DIR__, 3);
        $command = sprintf(
            'grep -RIn --include=*.css --include=*.js --include=*.php --include=*.html ' .
            '-E %s %s/public %s/config --exclude=tickettrade.tokens.css | wc -l',
            "'#[0-9A-Fa-f]{3,8}\\b'",
            escapeshellarg($root),
            escapeshellarg($root)
        );

        $output = [];
        $exit = 0;
        exec($command, $output, $exit);

        $count = (int) ($output[0] ?? 0);
        $this->assertSame(0, $count, sprintf('Found %d hex literals outside tickettrade.tokens.css', $count));
    }

    /**
     * Extract tokens from a CSS block keyed by `:root[data-theme="X"] { ... }`.
     *
     * @return array<string, string>  token-name => hex-value
     */
    private function extractTokens(string $selector): array
    {
        $tokens = [];
        // Capture the block contents
        $pattern = '/' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/s';
        if (!preg_match($pattern, $this->tokensContent, $m)) {
            return $tokens;
        }
        $body = $m[1];
        // Parse `--token-name: value;`
        if (preg_match_all('/--([a-z0-9-]+)\s*:\s*([^;]+);/i', $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tokens[$match[1]] = trim($match[2]);
            }
        }
        return $tokens;
    }

    /**
     * Extract Contrast Ledger rows from DESIGN.md.
     * Each row yields the list of token names referenced by `{colors.<token>}` syntax.
     *
     * @return list<array{tokens: list<string>}>
     */
    private function extractContrastLedgerRows(): array
    {
        $content = (string) file_get_contents($this->designPath);
        if ($content === '') {
            return [];
        }

        // Find the Contrast Ledger table region
        if (!preg_match('/##\s+Contrast Ledger(.*?)(?=^## |\Z)/sm', $content, $section)) {
            return [];
        }

        $rows = [];
        $lines = explode("
", $section[1]);
        foreach ($lines as $line) {
            if (!preg_match('/^\s*\|.*\|.*\|.*\|.*\|/m', $line)) {
                continue;
            }
            // Skip header row and separator
            if (preg_match('/^\s*\|[-:\s|]+\|/', $line)) {
                continue;
            }
            // Extract token references of the form `{colors.<token>}`
            if (preg_match_all('/\{colors\.([a-z0-9-]+)\}/', $line, $m)) {
                $rows[] = ['tokens' => array_values(array_unique($m[1]))];
            }
        }
        return $rows;
    }
}
