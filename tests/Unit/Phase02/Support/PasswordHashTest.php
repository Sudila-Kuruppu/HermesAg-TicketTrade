<?php
/**
 * Phase 2 — PasswordHashTest
 *
 * Locks in:
 *  - bcrypt cost 12 from Auth\Service\auth_service::hashPassword()
 *  - AD-18 sole-writer rule: only src/Auth/Service/auth_service.php
 *    contains a `password_hash(` call. Other files must use the Service.
 *  - sanitizeUser strips sensitive fields.
 *  - tierFromPoints returns the correct tier for each points boundary.
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase02\Support;

use PHPUnit\Framework\TestCase;
use App\Auth\Service\auth_service;

class PasswordHashTest extends TestCase
{
    public function test_hash_uses_cost_12(): void
    {
        $hash = auth_service::hashPassword('hunter2');
        $info = password_get_info($hash);
        $this->assertSame('bcrypt', $info['algoName']);
        $this->assertSame(12, $info['options']['cost']);
    }

    public function test_verify_round_trip(): void
    {
        $hash = auth_service::hashPassword('hunter2');
        $this->assertTrue(auth_service::verifyPassword('hunter2', $hash));
        $this->assertFalse(auth_service::verifyPassword('wrong', $hash));
    }

    public function test_dummy_hash_is_bcrypt_cost_12(): void
    {
        $hash = auth_service::dummyHash();
        $info = password_get_info($hash);
        $this->assertSame('bcrypt', $info['algoName']);
        $this->assertSame(12, $info['options']['cost']);
        // Verify is functionally a valid bcrypt hash
        $this->assertTrue(password_verify('dummy-for-timing-attack-mitigation-only', $hash));
    }

    public function test_no_password_hash_outside_auth_service(): void
    {
        $srcDir = realpath(__DIR__ . '/../../../../src');
        $files = $this->collectPhpFiles($srcDir);
        $matchers = [];
        foreach ($files as $f) {
            $contents = file_get_contents($f);
            // Match only standalone password_hash( calls (not part of password_hash_get_info or password_hash_needs_rehash).
            if (preg_match('/(?<![A-Za-z0-9_])password_hash\s*\(/', $contents, $m, PREG_OFFSET_CAPTURE)) {
                $matchers[] = str_replace($srcDir . '/', '', $f);
            }
        }
        $this->assertSame(['Auth/Service/auth_service.php'], $matchers, 'password_hash( call exists outside auth_service.php');
    }

    public function test_sanitize_strips_sensitive_fields(): void
    {
        $row = [
            'password_hash' => 'x',
            'is_admin' => 1,
            'is_banned' => 0,
            'points' => 100,
            'points_frozen' => false,
            'tier' => 'A',
            'nickname' => 'kasun',
            'email' => 'kasun@students.nsbm.ac.lk',
        ];
        $out = auth_service::sanitizeUser($row);
        $this->assertArrayNotHasKey('password_hash', $out);
        $this->assertArrayNotHasKey('is_admin', $out);
        $this->assertArrayNotHasKey('is_banned', $out);
        $this->assertArrayNotHasKey('points', $out);
        $this->assertArrayNotHasKey('points_frozen', $out);
        // tier may stay (it's the rank badge label, displayed publicly)
        $this->assertArrayHasKey('nickname', $out);
        $this->assertArrayHasKey('email', $out);
    }

    public function test_tier_from_points_boundaries(): void
    {
        $this->assertSame('E', auth_service::tierFromPoints(0));
        $this->assertSame('E', auth_service::tierFromPoints(49));
        $this->assertSame('D', auth_service::tierFromPoints(50));
        $this->assertSame('D', auth_service::tierFromPoints(149));
        $this->assertSame('C', auth_service::tierFromPoints(150));
        $this->assertSame('C', auth_service::tierFromPoints(399));
        $this->assertSame('B', auth_service::tierFromPoints(400));
        $this->assertSame('B', auth_service::tierFromPoints(799));
        $this->assertSame('A', auth_service::tierFromPoints(800));
        $this->assertSame('A', auth_service::tierFromPoints(1499));
        $this->assertSame('S', auth_service::tierFromPoints(1500));
        $this->assertSame('S', auth_service::tierFromPoints(99999));
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
