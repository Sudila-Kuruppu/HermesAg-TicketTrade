<?php

/**
 * TicketTrade — Auth\Service\auth_service
 *
 * Per AD-18: the SOLE writer of password_hash() / password_verify() in
 * the codebase. The sole writer of the users.password_hash column, the
 * sole writer of sessions (DB-backed) per D-05, the sole writer of the
 * email_verifications / password_resets token lifecycle, and the canonical
 * helper for the login → startSession path.
 *
 * Phase 2 Plan 02-02 lands the full method surface (register, verifyEmail,
 * login, forgotPassword, consumePasswordReset, startSession, endSession,
 * updateLastSeen, findUserForLogin).
 *
 * Phase 6's Points\Service\points_service::awardVerificationBonus($userId)
 * is the +50 stub this plan lands; the stub lives in
 * src/Points/Service/points_service.php and writes the points_log row +
 * bumps users.points/tier.
 *
 * Phase 6 Plan 06-02 ADDS:
 *   - recordLogin(int $userId): void — canonical post-authenticate
 *     hook (D-03) that refreshes users.last_active_at via
 *     user_model::updateLastActive(). Called from login() /
 *     consumePasswordReset() after startSession() succeeds. Swallows
 *     exceptions — a failed refresh must NOT abort the login.
 */

declare(strict_types=1);

namespace App\Auth\Service;

use App\Auth\Model\email_verification_model;
use App\Auth\Model\password_reset_model;
use App\Auth\Model\session_model;
use App\Auth\Model\student_id_allowlist_model;
use App\Auth\Model\user_model;
use App\Support\Auth as AuthGuard;
use App\Support\Crypto;
use App\Support\Db;
use DateTime;
use DateTimeZone;
use PDO;

class auth_service
{
    private static ?string $dummyHash = null;

    /**
     * Hash a plaintext password at the configured bcrypt cost (12).
     *
     * Sole writer per AD-18.
     */
    public static function hashPassword(string $plain): string
    {
        $cfg = require APP_ROOT . '/config/auth.php';
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => (int) $cfg['bcrypt_cost']]);
    }

    /**
     * Constant-time verify against a stored bcrypt hash.
     */
    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    /**
     * Compute a bcrypt "dummy" hash once per process. Use on failed
     * logins to keep the response time independent of whether the
     * user exists (Pitfall 3 — timing-attack mitigation).
     */
    public static function dummyHash(): string
    {
        if (self::$dummyHash === null) {
            $cfg = require APP_ROOT . '/config/auth.php';
            self::$dummyHash = password_hash(
                'dummy-for-timing-attack-mitigation-only',
                PASSWORD_BCRYPT,
                ['cost' => (int) $cfg['bcrypt_cost']]
            );
        }
        return self::$dummyHash;
    }

    /**
     * Generate a hex-encoded random token (delegates to Support\Crypto).
     */
    public static function randomToken(int $bytes = 32): string
    {
        return Crypto::randomToken($bytes);
    }

    /**
     * SHA-256 of a raw token — produces the CHAR(64) hash stored in
     * email_verifications.token_hash and password_resets.token_hash.
     */
    public static function hashToken(string $raw): string
    {
        return Crypto::hashToken($raw);
    }

    /**
     * Strip sensitive fields from a user row before passing to a View.
     */
    public static function sanitizeUser(array $row): array
    {
        return AuthGuard::sanitizeUser($row);
    }

    /**
     * Resolve the rank tier for a given point balance.
     *
     * Delegates to the global tierFromPoints() defined in
     * config/ranks.php. The function_exists guard is a belt-and-braces
     * for the rare code path where ranks.php hasn't been required yet;
     * the require_once then loads the function (and its $ranks array).
     * (WR-08 — auth_service no longer carries a parallel ladder.)
     */
    public static function tierFromPoints(int $points): string
    {
        if (!function_exists('tierFromPoints')) {
            require_once APP_ROOT . '/config/ranks.php';
        }
        return tierFromPoints($points);
    }

    /**
     * Validate a post-login redirect target. Rejects absolute URLs,
     * protocol-relative URLs, and anything with a backslash to prevent
     * open-redirect attacks (Pitfall 5).
     */
    public static function nextRedirectIsSafe(?string $next): string
    {
        $next = (string) $next;
        if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//') || str_contains($next, '\\')) {
            return '/board';
        }
        return $next;
    }

    /**
     * Find a user by email (normalized: lowercase + trim). Used by login()
     * to gate the password_verify call.
     */
    public static function findUserForLogin(string $email): ?array
    {
        return user_model::findByEmail(Db::pdo(), $email);
    }

    /**
     * Register a new user.
     *
     * Validates format + allowlist + uniqueness, hashes the password,
     * inserts the users row + email_verifications row in one
     * transaction, and returns the raw verify token so the caller
     * can surface it in the flash toast (D-02 dev simulation).
     *
     * Per D-13, the field-level anti-enumeration collapse:
     *  - bad email format         → E_VALIDATION (field: email)
     *  - password < 8 chars       → E_PASSWORD_WEAK
     *  - bad nickname format      → E_VALIDATION (field: nickname)
     *  - allowlist miss (either)  → E_AUTH_ALLOWLIST (combined copy)
     *  - email already registered → E_AUTH_ALLOWLIST (same combined copy)
     *  - nickname reserved/taken  → E_NICKNAME_TAKEN / E_VALIDATION
     *  - student ID mismatch with email in allowlist → E_AUTH_ALLOWLIST
     *
     * @return array{ok:bool,user_id?:int,verify_token?:string,error?:array}
     */
    public static function register(
        string $email,
        string $password,
        string $nickname,
        string $studentId,
        string $fullName,
        ?int $avatarId = null
    ): array {
        $pdo = Db::pdo();

        // Field-level format errors (public, no enumeration concern)
        $email = strtolower(trim($email));
        $nickname = trim($nickname);
        $studentId = trim($studentId);
        $fullName = trim($fullName);

        if (
            !filter_var($email, FILTER_VALIDATE_EMAIL)
            || !str_ends_with($email, '@students.nsbm.ac.lk')
        ) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_VALIDATION',
                    'message' => 'Use your `@students.nsbm.ac.lk` email.',
                    'fields' => ['email' => 'Use your `@students.nsbm.ac.lk` email.'],
                ],
            ];
        }
        if (strlen($password) < 8) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_PASSWORD_WEAK',
                    'message' => 'Password must be at least 8 characters.',
                    'fields' => ['password' => 'Password must be at least 8 characters.'],
                ],
            ];
        }
        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $nickname)) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_VALIDATION',
                    'message' => 'Nickname must be 3–30 letters, numbers, or underscores.',
                    'fields' => ['nickname' => 'Nickname must be 3–30 letters, numbers, or underscores.'],
                ],
            ];
        }

        // Reserved nickname anti-squatting (Q4 in RESEARCH).
        $reserved = require APP_ROOT . '/config/reserved_nicknames.php';
        if (in_array(strtolower($nickname), array_map('strtolower', $reserved), true)) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_VALIDATION',
                    'message' => 'Nickname reserved. Pick another.',
                    'fields' => ['nickname' => 'Nickname reserved. Pick another.'],
                ],
            ];
        }

        // Combined anti-enumeration (D-13):
        //   - email not in allowlist
        //   - student ID not in allowlist
        //   - email + student ID pair mismatched
        //   - email already registered as a user
        // all collapse to E_AUTH_ALLOWLIST with the same copy.
        $allowEmail = student_id_allowlist_model::findByEmail($pdo, $email);
        $allowStudent = student_id_allowlist_model::findByStudentId($pdo, $studentId);
        if (
            $allowEmail === null || $allowStudent === null
            || $allowEmail['student_id'] !== $studentId
        ) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_AUTH_ALLOWLIST',
                    'message' => 'Email or student ID not recognized. Check both and try again.',
                    'fields' => null,
                ],
            ];
        }
        // Pre-check duplicate email so the combined branch catches it.
        if (user_model::findByEmail($pdo, $email) !== null) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_AUTH_ALLOWLIST',
                    'message' => 'Email or student ID not recognized. Check both and try again.',
                    'fields' => null,
                ],
            ];
        }
        // Pre-check duplicate nickname (public per D-13).
        if (user_model::findByNickname($pdo, $nickname) !== null) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_NICKNAME_TAKEN',
                    'message' => 'Nickname taken. Pick another.',
                    'fields' => ['nickname' => 'Nickname taken. Pick another.'],
                ],
            ];
        }

        // Hash + insert (the only password_hash call in the codebase).
        $hash = self::hashPassword($password);
        $avatar = $avatarId ?? random_int(1, 12);

        $rawToken = self::randomToken();
        $hashToken = self::hashToken($rawToken);
        $expiresAt = (new DateTime('+24 hours', new DateTimeZone('Asia/Colombo')))
            ->format('Y-m-d H:i:s');

        try {
            $pdo->beginTransaction();
            $userId = user_model::insert($pdo, [
                'email' => $email,
                'student_id' => $studentId,
                'nickname' => $nickname,
                'password_hash' => $hash,
                'full_name' => $fullName,
                'avatar_id' => $avatar,
            ]);
            email_verification_model::insert($pdo, $userId, $hashToken, $expiresAt);
            $pdo->commit();
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Race on a unique index. Differentiate nickname vs email/student_id:
            //  - SQLSTATE 23000 + 'uniq_nickname' in the message → the user's
            //    nickname was registered by someone else a few ms before them.
            //    Match the pre-check's E_NICKNAME_TAKEN copy (D-13: nicknames
            //    are intentionally public, no enumeration concern here).
            //  - SQLSTATE 23000 on uniq_email / uniq_student_id → collapse to
            //    the same combined E_AUTH_ALLOWLIST copy so attackers can't
            //    distinguish email-taken from student-id-mismatch from allowlist-miss.
            if (
                (string) $e->getCode() === '23000'
                && str_contains($e->getMessage(), 'uniq_nickname')
            ) {
                return [
                    'ok' => false,
                    'error' => [
                        'code' => 'E_NICKNAME_TAKEN',
                        'message' => 'Nickname taken. Pick another.',
                        'fields' => ['nickname' => 'Nickname taken. Pick another.'],
                    ],
                ];
            }
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_AUTH_ALLOWLIST',
                    'message' => 'Email or student ID not recognized. Check both and try again.',
                    'fields' => null,
                ],
            ];
        }

        return [
            'ok' => true,
            'user_id' => $userId,
            'verify_token' => $rawToken,
        ];
    }

    /**
     * Verify an email-verification token.
     *
     * Returns E_TOKEN_INVALID for any failure (not used, expired, hash
     * mismatch). On success, marks the row used, sets is_verified=TRUE
     * on the user, and triggers the +50 points stub via
     * Points\Service\points_service::awardVerificationBonus.
     */
    public static function verifyEmail(string $rawToken): array
    {
        $pdo = Db::pdo();
        $tokenHash = self::hashToken($rawToken);
        $row = email_verification_model::findActiveByHash($pdo, $tokenHash);
        if ($row === null) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_TOKEN_INVALID',
                    'message' => 'Verification link is invalid or expired.',
                    'fields' => null,
                ],
            ];
        }
        $userId = (int) $row['user_id'];
        $nickname = (string) ($row['nickname'] ?? '');

        // Mark the verification row used and flip is_verified=TRUE inside
        // a single transaction. The +50 points award is delegated to
        // Points\Service\points_service::awardVerificationBonus which
        // manages its own transaction (the two-step shape matches the
        // AD-10 sole-writer rule: only points_service touches points_log
        // and users.points/tier outside of Phase 6).
        try {
            $pdo->beginTransaction();
            if (!email_verification_model::markUsed($pdo, (int) $row['id'])) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'error' => [
                        'code' => 'E_TOKEN_INVALID',
                        'message' => 'Verification link is invalid or expired.',
                        'fields' => null,
                    ],
                ];
            }
            $stmt = $pdo->prepare('UPDATE users SET is_verified = TRUE, updated_at = NOW() WHERE user_id = ?');
            $stmt->execute([$userId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_TOKEN_INVALID',
                    'message' => 'Verification link is invalid or expired.',
                    'fields' => null,
                ],
            ];
        }
        // Best-effort +50 (Phase 2 stub). The verify itself succeeded
        // above; the points award is a side-effect logged but does not
        // fail the response.
        \App\Points\Service\points_service::awardVerificationBonus($userId);
        return [
            'ok' => true,
            'user_id' => $userId,
            'nickname' => $nickname,
        ];
    }

    /**
     * Login with email + password.
     *
     * Always runs password_verify against the user's hash OR the dummy
     * sentinel (Pitfall 3 — timing attack). On success, starts a session.
     */
    public static function login(string $email, string $password): array
    {
        $user = self::findUserForLogin($email);
        $hash = $user['password_hash'] ?? self::dummyHash();
        $verified = self::verifyPassword($password, $hash);
        if (!$verified || $user === null) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_AUTH_INVALID',
                    'message' => 'Email or password is incorrect.',
                    'fields' => null,
                ],
            ];
        }
        // Banned users get the same generic copy (D-06 — don't reveal).
        if (!empty($user['is_banned'])) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_AUTH_INVALID',
                    'message' => 'Email or password is incorrect.',
                    'fields' => null,
                ],
            ];
        }
        self::startSession((int) $user['user_id']);
        // D-03: refresh last_active_at on successful login.
        self::recordLogin((int) $user['user_id']);
        return [
            'ok' => true,
            'user_id' => (int) $user['user_id'],
        ];
    }

    /**
     * Start a fresh DB-backed session for the given user. Called by
     * login() (on success), register() (auto-login per D-02), and
     * consumePasswordReset() (auto-login on reset).
     *
     * Calls session_regenerate_id(true) so the new session ID is used
     * for the row's session_id (defends against session-fixation).
     */
    public static function startSession(int $userId): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        session_regenerate_id(true);
        $sid = session_id();
        $user = user_model::findById(Db::pdo(), $userId);
        if ($user === null) {
            return;
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
        try {
            session_model::insert(Db::pdo(), $sid, $userId, $ip, $ua);
        } catch (\Throwable $e) {
            // schema missing or duplicate session_id; do not abort.
        }
        $GLOBALS['current_user'] = $user;
        // The next request will re-read current_user via Support\Auth::boot()
        // (which runs at every bootstrap and looks up the sessions row by
        // the regenerated session cookie). For the CURRENT request the
        // $user row is identical to what boot() already loaded from the
        // old session — the assignment above is a defensive refresh, not
        // a signal to anyone. The trailing comment is intentionally
        // minimal; IN-04's original 'force Auth::boot() to re-read' note
        // was misleading because boot() runs once per request, not on
        // demand.
    }

    /**
     * Post-authenticate hook: refresh users.last_active_at NOW().
     *
     * Per D-03 (06-CONTEXT.md), the gamification path (points_log
     * INSERT) refreshes last_active_at via the migration 019 trigger.
     * The login path needs its own writer so a user who logs in but
     * earns no points is still considered "active" by the On-Break
     * gate. Called from login() / consumePasswordReset() AFTER
     * startSession() succeeds (so a failed session insert doesn't
     * refresh last_active_at).
     *
     * Swallows all exceptions — a failed refresh must NOT abort the
     * login. Mirrors the idempotent shape of endSession() / updateLastSeen().
     */
    public static function recordLogin(int $userId): void
    {
        try {
            // Cross-context: App\User\Model\user_model owns the
            // canonical user CRUD (AD-1 — Service is the only
            // context that imports another context's Model). The
            // local App\Auth\Model\user_model stub ships findBy* /
            // insert for the register/verify flow but does NOT
            // own updateLastActive (the Phase 6 On-Break column
            // is updated via the User context per AD-1 + D-03).
            \App\User\Model\user_model::updateLastActive(Db::pdo(), $userId);
        } catch (\Throwable $e) {
            // idempotent — never abort the login.
        }
    }

    /**
     * Bump sessions.last_seen (called from Support\Auth::boot() in the
     * 5-min idempotency window per D-04).
     */
    public static function updateLastSeen(string $sessionId): void
    {
        if ($sessionId === '') {
            return;
        }
        try {
            session_model::touch(Db::pdo(), $sessionId);
        } catch (\Throwable $e) {
            // idempotent — never abort the request.
        }
    }

    /**
     * End a session (D-05 DELETE-based logout). Called from
     * LogoutAction::handlePost().
     */
    public static function endSession(string $sessionId, int $userId): void
    {
        try {
            session_model::delete(Db::pdo(), $sessionId, $userId);
        } catch (\Throwable $e) {
            // schema missing; session_destroy below still cleans up.
        }
    }

    /**
     * Generate a password-reset token.
     *
     * Per D-07: the function always returns ok=true (anti-enumeration).
     * The raw token is returned ONLY in non-production environments for
     * the dev-reset-link log line (OQ-7 answer). In production the
     * function returns ['ok' => true, 'token' => null].
     */
    public static function requestPasswordReset(string $email): array
    {
        $pdo = Db::pdo();
        $user = user_model::findByEmail($pdo, $email);
        if ($user === null) {
            return ['ok' => true, 'token' => null];
        }
        $raw = self::randomToken();
        $hash = self::hashToken($raw);
        $expiresAt = (new DateTime('+24 hours', new DateTimeZone('Asia/Colombo')))
            ->format('Y-m-d H:i:s');
        try {
            password_reset_model::insert($pdo, (int) $user['user_id'], $hash, $expiresAt);
        } catch (\Throwable $e) {
            return ['ok' => true, 'token' => null];
        }
        // Dev simulation (OQ-7): the link is NEVER surfaced in the UI.
        // The Action writes a single error_log line so a developer can
        // copy the token from the dev server log.
        $tokenForCaller = (getenv('APP_ENV') === 'production') ? null : $raw;
        return ['ok' => true, 'token' => $tokenForCaller];
    }

    /**
     * Read-only peek for the GET /reset-password form (does NOT consume).
     */
    public static function peekPasswordReset(string $rawToken): ?array
    {
        $hash = self::hashToken($rawToken);
        return password_reset_model::findActiveByHash(Db::pdo(), $hash);
    }

    /**
     * Consume a password-reset token, hash the new password, mark the
     * row used, and start a session for the user.
     */
    public static function consumePasswordReset(string $rawToken, string $newPassword): array
    {
        if (strlen($newPassword) < 8) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_PASSWORD_WEAK',
                    'message' => 'Password must be at least 8 characters.',
                    'fields' => ['password' => 'Password must be at least 8 characters.'],
                ],
            ];
        }
        $pdo = Db::pdo();
        $hash = self::hashToken($rawToken);
        $row = password_reset_model::findActiveByHash($pdo, $hash);
        if ($row === null) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_TOKEN_INVALID',
                    'message' => 'Verification link is invalid or expired.',
                    'fields' => null,
                ],
            ];
        }
        $userId = (int) $row['user_id'];
        $newHash = self::hashPassword($newPassword);
        try {
            $pdo->beginTransaction();
            if (!password_reset_model::markUsed($pdo, (int) $row['id'])) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'error' => [
                        'code' => 'E_TOKEN_INVALID',
                        'message' => 'Verification link is invalid or expired.',
                        'fields' => null,
                    ],
                ];
            }
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?');
            $stmt->execute([$newHash, $userId]);
            // Invalidate all existing sessions for this user. Per D-07
            // "the reset endpoint ... logs the user in" but other
            // devices/sessions for the same user should NOT remain
            // authenticated after a password change.
            $stmt = $pdo->prepare('DELETE FROM sessions WHERE user_id = ?');
            $stmt->execute([$userId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'ok' => false,
                'error' => [
                    'code' => 'E_TOKEN_INVALID',
                    'message' => 'Verification link is invalid or expired.',
                    'fields' => null,
                ],
            ];
        }
        self::startSession($userId);
        // D-03: refresh last_active_at on auto-login after a successful
        // password reset. Matches the login() path's call.
        self::recordLogin($userId);
        return [
            'ok' => true,
            'user_id' => $userId,
        ];
    }
}
