# Phase 2 Flows — Developer Notes

This document covers the user-facing flows shipped in Phase 2 Plan 02-02:
register, verify, login, logout, forgot-password, reset-password, profile
edit, and settings. Read it before adding a new state-changing Action.

## The auth_service surface

The `Auth\Service\auth_service` class is the single entry point for
every authentication concern. Per AD-18 it is the SOLE writer of
`password_hash()` and `password_verify()` in the codebase.

The full method surface:

| Method | Use case | Returns |
|--------|----------|---------|
| `hashPassword($plain)` | Hash a plaintext password at cost 12 | bcrypt hash string |
| `verifyPassword($plain, $hash)` | Constant-time verify | bool |
| `dummyHash()` | Sentinel hash for the missing-user case (Pitfall 3) | bcrypt hash string |
| `randomToken($bytes=32)` | Generate a hex token | 64-char hex |
| `hashToken($raw)` | SHA-256 of a raw token | 64-char hex |
| `sanitizeUser($row)` | Strip password_hash / is_admin / is_banned / points / points_frozen | array |
| `tierFromPoints($points)` | Resolve the rank tier for a balance | 'E' / 'D' / 'C' / 'B' / 'A' / 'S' |
| `nextRedirectIsSafe($next)` | Validate a post-login redirect target | safe path |
| `findUserForLogin($email)` | Normalized email lookup | user row or null |
| `register($email, $pw, $nick, $sid, $name, $avatar?)` | Allowlist + uniqueness + insert | `['ok' => bool, 'user_id' => int, 'verify_token' => string, 'error' => array]` |
| `verifyEmail($raw)` | Consume email_verification token, flip is_verified, award +50 | `['ok' => bool, 'user_id' => int, 'nickname' => string]` |
| `login($email, $password)` | Authenticate + start session | `['ok' => bool, 'user_id' => int, 'error' => array]` |
| `startSession($userId)` | session_regenerate_id + insert sessions row | void |
| `endSession($sid, $uid)` | DELETE FROM sessions WHERE sid = ? AND uid = ? | void |
| `requestPasswordReset($email)` | Insert a password_resets row (anti-enumeration) | `['ok' => bool, 'token' => ?string]` |
| `consumePasswordReset($raw, $newPw)` | Mark used, hash new password, start session | `['ok' => bool, 'user_id' => int]` |

All bcrypt calls go through `auth_service::hashPassword` (AD-18). The
Phase 9 phpcs `Custom\Sniffs\NoRawHash` sniff will catch any other
caller; the Phase 2.0 `tests/Unit/Phase02/Support/PasswordHashTest`
already enforces the invariant in CI.

## How to add a new state-changing form

1. Add the route to `config/routes.php`:
   ```php
   'POST /foo' => ['App\Bar\Action\FooAction', 'handlePost', ['auth' => true, 'admin' => false, 'csrf' => true, 'rate_limit' => 'foo']],
   ```
2. Create `src/Bar/Action/FooAction.php`:
   ```php
   declare(strict_types=1);
   namespace App\Bar\Action;
   use App\Support\Auth as AuthGuard;
   use App\Support\Csrf;
   use App\Support\View;
   class FooAction {
       public function handle(): void {
           $user = AuthGuard::currentUser();
           if ($user === null) { header('Location: /login?next=/foo'); exit; }
           View::render(__DIR__ . '/../View/foo.php', ['csrf_token' => Csrf::token()]);
       }
       public function handlePost(): void {
           $csrf = $_POST['csrf_token'] ?? '';
           // Csrf::verify() is called automatically at bootstrap.
           $result = \App\Bar\Service\foo_service::doThing($_POST);
           if (!$result['ok']) {
               $GLOBALS['_tt_form_error'] = $result['error'];
               View::render(__DIR__ . '/../View/foo.php', ['csrf_token' => Csrf::token()]);
               return;
           }
           View::flash('success', 'Thing done.');
           header('Location: /foo');
           exit;
       }
   }
   ```
3. The Router auto-validates the class against `config/contexts.php`
   (a class outside the 9 contexts is rejected with 500).

## The flash-toast pattern

Server-set flash messages survive a 302 redirect via a `$_SESSION`
carry. The bootstrap reads `$_SESSION['_tt_flash_toast']` on the next
request, copies it into `$GLOBALS['_tt_flash_toast']`, and unsets it.
`Support\View::flash($type, $message)` is the helper — use it before
any `header('Location: ...'); exit;`.

`Support\View\partials\flash_toast.php` renders the
`<div data-flash-toast="…">` markup. The Phase 1
`window.TicketTrade.toast.show()` JS picks up the attribute on
`DOMContentLoaded`.

Pattern:
```php
View::flash('success', 'Profile updated.');
header('Location: /profile');
exit;
```

Inline errors (form validation, wrong password) do NOT use the
flash-toast — they render as an `alert alert-danger` at the top of
the form so the user sees the failure next to the field.

## The +50 points stub

`Points\Service\points_service::awardVerificationBonus($userId)` is
the SOLE writer of `points_log` and the SOLE updater of `users.points`
and `users.tier` outside of Phase 6 (AD-10). The signature is the
Phase 6 contract:

```php
public static function awardVerificationBonus(int $userId): array
{
    // 1. BEGIN
    // 2. INSERT points_log (event_uuid = Uuid::uuid7()->toString(), reference_type='email_verification', delta=50, balance_after=50)
    // 3. UPDATE users SET points=50, tier='D'
    // 4. COMMIT
    return ['ok' => true, 'event_uuid' => $uuid];
}
```

Phase 6 will generalize via `auth_service::tierFromPoints($newPoints)`
instead of the literal `tier='D'`.

## The anti-enumeration error copy (D-13)

The combined copy is:
> Email or student ID not recognized. Check both and try again.

It is returned for ALL of: email not in allowlist, student ID not in
allowlist, email + student ID pair mismatched, email already registered
as a user. The code is `E_AUTH_ALLOWLIST` for ALL of these.

The two public cases:
- email format wrong → field-level: `Use your \`@students.nsbm.ac.lk\` email.`
- nickname taken → field-level: `Nickname taken. Pick another.`

The rationale: the email format and the nickname are public, so we can
tell the user "your email is wrong" or "this nickname is taken" without
helping an attacker. But the allowlist membership is sensitive — we
collapse it.

## Common pitfalls

A Phase 3+ implementer will trip on these. The plan documents them so
the same mistakes don't repeat.

1. **Echoing the raw error message without `View::h()`** (T-2-13).
   Every dynamic value rendered into a View MUST go through
   `View::h()` (or `htmlspecialchars` with `ENT_QUOTES, 'UTF-8'`).
   The Views in this plan already do this; copy the pattern.

2. **Returning a flash-toast for the login error instead of an inline
   `alert`** (Pitfall 15). Wrong-password / rate-limited errors render
   inline so the user sees the failure next to the password field.
   Successful flows (register → verify link, profile updated, password
   reset) use `View::flash()` so the message survives the redirect.

3. **Calling `password_hash(` outside `auth_service.php`** (AD-18).
   Use `\App\Auth\Service\auth_service::hashPassword()`. The
   `PasswordHashTest` will catch any other caller in CI; the Phase 9
   phpcs sniff is the durable enforcement.

4. **Accepting `?next=...` from the URL without `nextRedirectIsSafe`**
   (Pitfall 5). Open-redirect attacks come from a malicious link like
   `/login?next=//evil.com`. `nextRedirectIsSafe` rejects absolute
   URLs, protocol-relative URLs, and any path with a backslash.
   Always: `header('Location: ' . auth_service::nextRedirectIsSafe($next));`

5. **Returning the `email_not_in_allowlist` vs
   `student_id_not_in_allowlist` vs `email_already_registered` as three
   distinct errors** (D-13 + Pitfall 6). All three collapse to
   `E_AUTH_ALLOWLIST` with the same copy. The Action can set its own
   field-level errors for format/nickname/empty fields, but the
   anti-enumeration check at the Service level is a single combined
   result.

6. **Forgetting to use the dummy hash sentinel on the missing-user
   branch** (Pitfall 3). `auth_service::login` ALWAYS calls
   `password_verify` against the user's hash OR the dummy sentinel.
   Don't write your own login that short-circuits on missing-user —
   the timing leak is detectable in 100-iter benchmarks.

7. **Forgetting to call `session_regenerate_id(true)` on login**.
   Without it, session fixation attacks are possible. `startSession`
   handles this; if you write a custom session-start path, do not skip
   the call.

8. **Calling `points_log` writers outside `points_service`**. AD-10
   says only `points_service` writes to `points_log` and updates
   `users.points` / `users.tier`. The Phase 9 lock-step test will
   catch any other writer.

9. **Surfacing the raw reset token in the UI** (D-07). The token is
   only written to `error_log` in dev mode (`APP_ENV !== production`).
   The user always sees the same toast.

10. **Accepting the avatar_id from `$_POST` without clamping**
    (Pitfall 11). `user_service::validateAvatarId` clamps to 1..12.
    Do not write your own UPDATE without the clamp.

11. **Returning the nickname in the `/profile` edit form** (D-15).
    The nickname is locked at registration; the form does not include
    a `nickname` field. The service's `updateProfile` whitelist
    silently drops any key other than `full_name`, `bio`, `whatsapp`,
    `avatar_id`.

12. **Setting `$GLOBALS['_tt_flash_toast']` directly** in an Action.
    The globals are per-request and do NOT survive the 302. Use
    `View::flash($type, $msg)` which writes to `$_SESSION` and the
    bootstrap carries it to globals on the next request.
