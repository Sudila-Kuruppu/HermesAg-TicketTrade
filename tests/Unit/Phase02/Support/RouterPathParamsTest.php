<?php
/**
 * Phase 2 — RouterPathParamsTest
 *
 * WR-002: locks the path-params naming to `_tt_path_params` (no
 * `_tt_route_params` may exist anywhere in src/ or public/).
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase02\Support;

use PHPUnit\Framework\TestCase;

class RouterPathParamsTest extends TestCase
{
    public function test_no_route_params_global_anywhere(): void
    {
        $root = dirname(__DIR__, 4);
        $patterns = [
            $root . '/src',
            $root . '/public',
        ];
        foreach ($patterns as $base) {
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iter as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $ext = $file->getExtension();
                if (!in_array($ext, ['php', 'js', 'html', 'css'], true)) {
                    continue;
                }
                $path = $file->getPathname();
                $contents = (string) file_get_contents($path);
                $this->assertStringNotContainsString(
                    '_tt_route_params',
                    $contents,
                    '_tt_route_params must not appear in ' . $path . ' (use _tt_path_params instead)'
                );
            }
        }
    }

    public function test_path_params_global_used_in_router(): void
    {
        $src = file_get_contents(dirname(__DIR__, 4) . '/src/Support/Router.php');
        $this->assertStringContainsString(
            '_tt_path_params',
            $src,
            'Router must expose route placeholders via $GLOBALS[\'_tt_path_params\']'
        );
    }
}