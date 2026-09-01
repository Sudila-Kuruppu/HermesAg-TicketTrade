<?php
/**
 * Phase 2 — ProfileEditValidationTest
 *
 * Verifies user_service::validateWhatsApp() and validateAvatarId()
 * with boundary inputs.
 */

declare(strict_types=1);

namespace App\Tests\Unit\Phase02\User;

use App\User\Service\user_service;
use PHPUnit\Framework\TestCase;

class ProfileEditValidationTest extends TestCase
{
    public function test_validate_whatsapp_valid_inputs(): void
    {
        $this->assertSame('+94771234567', user_service::validateWhatsApp('+94771234567'));
        $this->assertSame('0771234567', user_service::validateWhatsApp('0771234567'));
        $this->assertSame('+94771234567', user_service::validateWhatsApp('  +94771234567  '));
    }

    public function test_validate_whatsapp_empty_returns_null(): void
    {
        $this->assertNull(user_service::validateWhatsApp(''));
        $this->assertNull(user_service::validateWhatsApp(null));
        $this->assertNull(user_service::validateWhatsApp('   '));
    }

    public function test_validate_whatsapp_invalid_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        user_service::validateWhatsApp('invalid');
    }

    public function test_validate_whatsapp_missing_plus_or_zero_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // 9 digits without prefix
        user_service::validateWhatsApp('771234567');
    }

    public function test_validate_whatsapp_wrong_country_code_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        user_service::validateWhatsApp('+1234567890');
    }

    public function test_validate_whatsapp_too_many_digits_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        user_service::validateWhatsApp('+947712345678');
    }

    public function test_validate_avatar_id_in_range(): void
    {
        $this->assertSame(1, user_service::validateAvatarId(1));
        $this->assertSame(7, user_service::validateAvatarId(7));
        $this->assertSame(12, user_service::validateAvatarId(12));
    }

    public function test_validate_avatar_id_clamps_out_of_range(): void
    {
        $this->assertSame(12, user_service::validateAvatarId(99));
        $this->assertSame(12, user_service::validateAvatarId(13));
        $this->assertSame(1, user_service::validateAvatarId(0));
        $this->assertSame(1, user_service::validateAvatarId(-5));
    }

    public function test_validate_avatar_id_coerces_types(): void
    {
        $this->assertSame(5, user_service::validateAvatarId('5'));
        $this->assertSame(5, user_service::validateAvatarId(5.7));
        $this->assertSame(1, user_service::validateAvatarId(null));
    }

    public function test_random_avatar_id_in_range(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $v = user_service::randomAvatarId();
            $this->assertGreaterThanOrEqual(1, $v);
            $this->assertLessThanOrEqual(12, $v);
        }
    }
}
