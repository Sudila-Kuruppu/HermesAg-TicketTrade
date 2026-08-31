<?php
/**
 * Phase 2 — PdoOnlyTest
 *
 * Locks in AD-5: no string-interpolated SQL in src/. Every query uses
 * prepared statements via Support\Db::pdo(). This is the unit-test
 * equivalent of the Phase 9 phpcs Custom\Sniffs\NoRawHash sniff.
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase02\Support;

use PHPUnit\Framework\TestCase;

class PdoOnlyTest extends TestCase
{
    public function test_no_string_interpolated_sql(): void
    {
        $srcDir = realpath(__DIR__ . '/../../../../src');
        $files = $this->collectPhpFiles($srcDir);
        $issues = [];
        foreach ($files as $f) {
            $contents = file_get_contents($f);
            // Pattern 1: double-quoted strings with $var passed to query/exec
            $dqRegex = '/->(query|exec)\(\s*"[^"]*\$[A-Za-z_][A-Za-z0-9_]*[^"]*"/';
            if (preg_match_all($dqRegex, $contents, $m)) {
                foreach ($m[0] as $hit) {
                    $issues[] = str_replace($srcDir . '/', '', $f) . ': ' . $hit;
                }
            }
            // Pattern 2: single-quoted strings with $var passed to query/exec
            $sqRegex = "/->(query|exec)\(\s*'[^']*\$[A-Za-z_][A-Za-z0-9_]*[^']*'/";
            if (preg_match_all($sqRegex, $contents, $m)) {
                foreach ($m[0] as $hit) {
                    $issues[] = str_replace($srcDir . '/', '', $f) . ': ' . $hit;
                }
            }
        }
        $this->assertSame([], $issues, 'String-interpolated SQL found in src/');
    }

    private function collectPhpFiles(string $dir): array
    {
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $out = [];
        foreach ($rii as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $out[] = $f->getPathname();
            }
        }
        return $out;
    }
}
