<?php
/**
 * Phase 2 — ComposerTest
 *
 * Locks in:
 *  - composer.json require contains only `php` and `ramsey/uuid`.
 *  - composer.json require-dev contains only phpcs and phpunit.
 *  - Phase 2 adds no new runtime or dev deps.
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase02\Support;

use PHPUnit\Framework\TestCase;

class ComposerTest extends TestCase
{
    public function test_require_only_locked_deps(): void
    {
        $composer = json_decode(file_get_contents(__DIR__ . '/../../../../composer.json'), true);
        $this->assertIsArray($composer);
        $this->assertArrayHasKey('require', $composer);
        $this->assertSame(['php', 'ramsey/uuid'], array_keys($composer['require']));
    }

    public function test_require_dev_only_locked_deps(): void
    {
        $composer = json_decode(file_get_contents(__DIR__ . '/../../../../composer.json'), true);
        $this->assertIsArray($composer['require-dev']);
        $devKeys = array_keys($composer['require-dev']);
        sort($devKeys);
        $expected = ['phpunit/phpunit', 'squizlabs/php_codesniffer'];
        sort($expected);
        $this->assertSame($expected, $devKeys);
    }

    public function test_php_version_constraint(): void
    {
        $composer = json_decode(file_get_contents(__DIR__ . '/../../../../composer.json'), true);
        $this->assertStringStartsWith('>=', $composer['require']['php']);
    }
}
