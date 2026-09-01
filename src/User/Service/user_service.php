<?php

/**
 * TicketTrade — User\Service\user_service
 *
 * Plan 02-02 owns the write surface (validateWhatsApp, validateAvatarId,
 * updateProfile, randomAvatarId). Plan 02-03 owns the public read view
 * (getByNicknameForPublicProfile, getPublicProfile). getByNickname
 * (case-insensitive) is shared and used by the owner-only edit flow.
 *
 * Per D-15: nickname is locked at registration. updateProfile() accepts
 * ONLY the 4 whitelisted fields (full_name, bio, whatsapp, avatar_id);
 * any other key in $fields is silently dropped.
 *
 * Per SEC-08: WhatsApp regex is `^(\+94|0)7[0-9]{8}$` — the canonical
 * Sri Lankan mobile pattern.
 *
 * Per Pitfall 11: avatar_id is (int) cast + clamped 1..12.
 */

declare(strict_types=1);

namespace App\User\Service;

use App\Auth\Service\auth_service;
use App\Support\Db;
use InvalidArgumentException;

class user_service
{
    /**
     * Plan 02-02's case-insensitive nickname lookup for owner-only flows.
     */
    public static function getByNickname(string $nickname): ?array
    {
        $row = \App\User\Model\user_model::findByNickname(Db::pdo(), $nickname);
        if ($row === null) {
            return null;
        }
        return auth_service::sanitizeUser($row);
    }

    /**
     * Plan 02-03's case-sensitive public-profile lookup.
     */
    public static function getByNicknameForPublicProfile(string $nickname): ?array
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            'SELECT user_id, nickname, full_name, bio, avatar_id, tier, '
            . 'points, is_verified, created_at '
            . 'FROM users '
            . 'WHERE BINARY nickname = ? AND is_banned = FALSE '
            . 'LIMIT 1'
        );
        $stmt->execute([$nickname]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $publicPoints = (int) $row['points'];
        $publicVerified = (bool) $row['is_verified'];
        $row = auth_service::sanitizeUser($row);
        $row['points'] = $publicPoints;
        $row['is_verified'] = $publicVerified;
        return $row;
    }

    /**
     * Alias for getByNicknameForPublicProfile.
     */
    public static function getPublicProfile(string $nickname): ?array
    {
        return self::getByNicknameForPublicProfile($nickname);
    }

    /**
     * Plan 02-02's owner profile lookup (by user_id).
     *
     * @return array<string,mixed>|null
     */
    public static function getById(int $userId): ?array
    {
        $row = \App\User\Model\user_model::findById(Db::pdo(), $userId);
        if ($row === null) {
            return null;
        }
        return auth_service::sanitizeUser($row);
    }

    /**
     * Plan 02-02's WhatsApp validator.
     *
     * Regex: `^(\+94|0)7[0-9]{8}$` (SEC-08, AGENTS.md Constraints).
     * Returns the canonicalized value if valid.
     * Returns null if empty (whatsapp is optional in the profile).
     * Throws InvalidArgumentException if the format is invalid.
     */
    public static function validateWhatsApp(?string $s): ?string
    {
        if ($s === null) {
            return null;
        }
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        if (!preg_match('/^(\+94|0)7[0-9]{8}$/', $s)) {
            throw new InvalidArgumentException(
                'Use a Sri Lankan mobile (e.g., +94771234567 or 0771234567).'
            );
        }
        return $s;
    }

    /**
     * Plan 02-02's avatar_id validator + clamp.
     *
     * Per Pitfall 11: (int) cast + clamp 1..12. Out-of-range values
     * are clamped to the nearest valid value, NOT rejected.
     */
    public static function validateAvatarId(mixed $v): int
    {
        return max(1, min(12, (int) $v));
    }

    /**
     * Plan 02-02's random avatar_id generator (D-19).
     */
    public static function randomAvatarId(): int
    {
        return random_int(1, 12);
    }

    /**
     * Plan 02-02's profile-update with strict field whitelist.
     *
     * Accepts ONLY full_name, bio, whatsapp, avatar_id. Any other key
     * in $fields is silently dropped (whitelist at the Service layer,
     * not the View).
     *
     * @param int $userId
     * @param array<string,mixed> $fields
     * @return array{ok:bool,updated?:array<string,mixed>,error?:array}
     */
    public static function updateProfile(int $userId, array $fields): array
    {
        $allowed = ['full_name', 'bio', 'whatsapp', 'avatar_id'];
        $clean = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $fields)) {
                $clean[$k] = $fields[$k];
            }
        }
        if (empty($clean)) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_VALIDATION',
                    'message' => 'No editable fields provided.',
                ],
            ];
        }

        // Validate WhatsApp if present
        if (array_key_exists('whatsapp', $clean)) {
            try {
                $clean['whatsapp'] = self::validateWhatsApp($clean['whatsapp']);
            } catch (InvalidArgumentException $e) {
                return [
                    'ok' => false,
                    'error' => [
                        'code' => 'E_VALIDATION',
                        'message' => $e->getMessage(),
                        'fields' => ['whatsapp' => $e->getMessage()],
                    ],
                ];
            }
        }
        // Clamp avatar_id if present
        if (array_key_exists('avatar_id', $clean)) {
            $clean['avatar_id'] = self::validateAvatarId($clean['avatar_id']);
        }
        // full_name: non-empty
        if (array_key_exists('full_name', $clean) && trim((string) $clean['full_name']) === '') {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_VALIDATION',
                    'message' => 'Full name is required.',
                    'fields' => ['full_name' => 'Full name is required.'],
                ],
            ];
        }
        // bio: ≤ 500 chars
        if (array_key_exists('bio', $clean) && strlen((string) $clean['bio']) > 500) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_VALIDATION',
                    'message' => 'Bio must be 500 characters or fewer.',
                    'fields' => ['bio' => 'Bio must be 500 characters or fewer.'],
                ],
            ];
        }

        $ok = \App\User\Model\user_model::updateProfile(Db::pdo(), $userId, $clean);
        if (!$ok) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_NOT_FOUND',
                    'message' => 'User not found.',
                ],
            ];
        }
        return ['ok' => true, 'updated' => $clean];
    }
}
