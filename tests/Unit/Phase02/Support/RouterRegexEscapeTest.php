<?php
/**
 * Phase 2 — RouterRegexEscapeTest
 *
 * WR-003: the placeholder-substitution regex must quote literal regex
 * metacharacters in the route path. Otherwise a route '/items/{id}.json'
 * would have its trailing '.' interpreted as "any character", matching
 * '/items/abcXjson' (wrong).
 *
 * We can't dispatch a synthetic route through Router (the route map is
 * require'd from a fixed path), but we can verify the source contains
 * preg_quote before the placeholder swap, and we can stand up the same
 * regex pipeline in isolation and feed it a dotted-literal route.
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase02\Support;

use PHPUnit\Framework\TestCase;

class RouterRegexEscapeTest extends TestCase
{
    public function test_router_source_uses_preg_quote(): void
    {
        $src = file_get_contents(dirname(__DIR__, 4) . '/src/Support/Router.php');
        $this->assertStringContainsString(
            'preg_quote($rkPath, \'#\')',
            $src,
            'Router must preg_quote the literal part of the route pattern'
        );
    }

    /**
     * Replicate the Router regex pipeline and confirm a route literal
     * containing regex metacharacters is escaped so it matches only
     * itself, not any character.
     */
    public function test_dotted_literal_is_escaped(): void
    {
        $rkPath = '/items/{id}.json';
        $quoted = preg_quote($rkPath, '#');
        $pattern = preg_replace('#\\\\{[^}]+\\\\}#', '([^/]+)', $quoted);
        $full = '#^' . $pattern . '$#';

        $this->assertSame(
            1,
            preg_match($full, '/items/abc.json', $m),
            'exact-match should still hit'
        );
        $this->assertSame('abc', $m[1] ?? null);

        // Without escaping, '.' would match 'X' too. With escaping, this
        // should fail.
        $this->assertSame(
            0,
            preg_match($full, '/items/abcXjson', $m2),
            'dotted literal must be quoted so the trailing .json is literal, not regex wildcard'
        );
    }
}