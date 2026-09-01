<?php
/**
 * Phase 2 — ProfileEditTest
 *
 * Verifies user_service::updateProfile() with the field whitelist.
 *  - full_name, bio, whatsapp, avatar_id update correctly
 *  - nickname, is_admin, is_banned, points, tier are NOT touched
 *  - out-of-range avatar_id is clamped (1..12)
 *  - invalid whatsapp returns E_VALIDATION with the field error
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase02\User;

use App\Tests\Integration\Phase02\Fixtures\Fixtures;
use App\User\Service\user_service;

class ProfileEditTest extends Fixtures
{
    public function test_update_profile_with_valid_fields(): void
    {
        $uid = $this->seedUser([
            'email' => 'profile@students.nsbm.ac.lk',
            'nickname' => 'profileuser',
            'student_id' => 'NSBM/2024/PE1',
            'full_name' => 'Old Name',
        ]);
        $r = user_service::updateProfile($uid, [
            'full_name' => 'New Name',
            'bio' => 'Hello world',
            'whatsapp' => '+94771234567',
            'avatar_id' => 7,
        ]);
        $this->assertTrue($r['ok']);

        $stmt = $this->pdo->prepare('SELECT full_name, bio, whatsapp, avatar_id, nickname, is_admin, is_banned, points, tier FROM users WHERE user_id = ?');
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        $this->assertSame('New Name', $row['full_name']);
        $this->assertSame('Hello world', $row['bio']);
        $this->assertSame('+94771234567', $row['whatsapp']);
        $this->assertSame(7, (int) $row['avatar_id']);
        // The locked / protected fields are untouched.
        $this->assertSame('profileuser', $row['nickname']);
        $this->assertSame(0, (int) $row['is_admin']);
        $this->assertSame(0, (int) $row['is_banned']);
        $this->assertSame(0, (int) $row['points']);
        $this->assertSame('E', $row['tier']);
    }

    public function test_update_profile_drops_non_whitelisted_keys(): void
    {
        $uid = $this->seedUser([
            'email' => 'whitelist@students.nsbm.ac.lk',
            'nickname' => 'whitelistuser',
            'student_id' => 'NSBM/2024/PE2',
        ]);
        $r = user_service::updateProfile($uid, [
            'full_name' => 'Updated',
            'nickname' => 'should-be-ignored',
            'is_admin' => true, // attack attempt
            'points' => 9999, // attack attempt
            'tier' => 'S', // attack attempt
        ]);
        $this->assertTrue($r['ok']);
        $stmt = $this->pdo->prepare('SELECT nickname, is_admin, points, tier FROM users WHERE user_id = ?');
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        $this->assertSame('whitelistuser', $row['nickname']);
        $this->assertSame(0, (int) $row['is_admin']);
        $this->assertSame(0, (int) $row['points']);
        $this->assertSame('E', $row['tier']);
    }

    public function test_update_profile_clamps_avatar_id(): void
    {
        $uid = $this->seedUser([
            'email' => 'clamp@students.nsbm.ac.lk',
            'nickname' => 'clampuser',
            'student_id' => 'NSBM/2024/PE3',
        ]);
        user_service::updateProfile($uid, ['avatar_id' => 99]);
        $stmt = $this->pdo->prepare('SELECT avatar_id FROM users WHERE user_id = ?');
        $stmt->execute([$uid]);
        $this->assertSame(12, (int) $stmt->fetch()['avatar_id']);

        user_service::updateProfile($uid, ['avatar_id' => 0]);
        $stmt->execute([$uid]);
        $this->assertSame(1, (int) $stmt->fetch()['avatar_id']);
    }

    public function test_update_profile_invalid_whatsapp_returns_error(): void
    {
        $uid = $this->seedUser([
            'email' => 'badwp@students.nsbm.ac.lk',
            'nickname' => 'badwpuser',
            'student_id' => 'NSBM/2024/PE4',
        ]);
        $r = user_service::updateProfile($uid, ['whatsapp' => 'invalid']);
        $this->assertFalse($r['ok']);
        $this->assertSame('E_VALIDATION', $r['error']['code']);
        $this->assertArrayHasKey('whatsapp', $r['error']['fields']);
    }

    public function test_update_profile_bio_too_long(): void
    {
        $uid = $this->seedUser([
            'email' => 'longbio@students.nsbm.ac.lk',
            'nickname' => 'longbiouser',
            'student_id' => 'NSBM/2024/PE5',
        ]);
        $r = user_service::updateProfile($uid, ['bio' => str_repeat('x', 501)]);
        $this->assertFalse($r['ok']);
        $this->assertSame('E_VALIDATION', $r['error']['code']);
    }
}
