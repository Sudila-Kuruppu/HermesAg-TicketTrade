# Phase 2 Substrate — Developer Notes

This document covers the cross-cutting layer shipped in Phase 2 Plan 02-01.
Every later phase (02-02, 02-03, 3..9) assumes this substrate is in place.
Read it before adding a new state-changing Action or Support class.

## What's in this plan

- 7 SQL migrations (`migrations/001_initial.sql` through `migrations/007_cache_rate.sql`)
  and the `migrate.php` CLI runner.
- 8 Support classes (`src/Support/`): Db, Auth, Csrf, RateLimit, Crypto,
  ResponseHeaders, Error, View.
- 7 layout partials + the layout wrapper (`src/Support/View/`).
- 14 stub Action classes (Auth × 7, User × 3, Listing × 2, Ticket × 3,
  Admin × 6).
- 12 avatar SVGs (`public/assets/img/avatars/avatar-{1..12}.svg`).
- `Auth/Service/auth_service.php` with the real bcrypt writer.
- 6 Model files for the Auth and User contexts.
- 21 student routes (`config/routes.php`) and 6 admin routes
  (`admin/config/routes.php`).
- 6 config files (`auth.php`, `security_headers.php`, `rate_limits.php`,
  `error_codes.php`, `ranks.php`, `reserved_nicknames.php`).
- Wave 0 tests under `tests/Unit/Phase02/` and `tests/Integration/Phase02/`.

## Bootstrap ordering

`config/bootstrap.php` runs in this exact order. Do not reorder:

1. Define `APP_ROOT`.
2. Load composer autoload.
3. Set timezone to `Asia/Colombo` (AD-11).
4. Configure error reporting (display suppressed in prod).
5. Set `mb_internal_encoding('UTF-8')`.
6. Call `session_set_cookie_params(...)` and `ini_set(...)` for the
   AD-13 session config. Must run BEFORE `session_start()`.
7. Start the session unless `PHP_SAPI === 'cli'`.
8. `Support\ResponseHeaders::boot()` — sets the four AD-13 security
   headers. Must be first so the headers attach even on 302 responses.
9. `Support\Auth::boot()` — reads the session cookie, looks up
   `sessions` + `users`, sets `$GLOBALS['current_user']`.
10. `Support\Csrf::verify()` — fails fast on POST/PUT/PATCH/DELETE
    without a matching token.

Why this order:

- ResponseHeaders must run before any body output (CSP, X-Frame-Options,
  Referrer-Policy, X-Content-Type-Options must attach to every response,
  including 302 bounces from Auth).
- Auth::boot reads the session — so it must come AFTER session_start.
- Csrf::verify is the last gate so the request never reaches the
  Action handler without a valid token.

## The route map

`config/routes.php` is the student surface; `admin/config/routes.php`
is the admin surface. The format is:

```php
['METHOD PATH' => [ClassFQN, method, ['auth' => bool, 'admin' => bool, 'csrf' => bool, 'rate_limit' => string|null]]]
```

The Router consults the flags in this order:

1. `rate_limit` (if set) — calls `Support\RateLimit::hit(route, ip)`.
   On `allowed=false`: state-changing requests get a JSON `E_RATE_LIMIT`
   envelope; GETs render the form page with the inline error.
2. `csrf` — already enforced at bootstrap by `Csrf::verify()`.
3. `admin` — calls `Support\Auth::adminGuard(path)`. 404s non-admin
   access (D-10, AD-14).
4. `auth` — calls `Support\Auth::requireAuth(path)`. 302s to
   `/login?next=$path` (D-08).

Path-param routes (e.g. `GET /profile/{nickname}`) capture the matched
segment into `$GLOBALS['_tt_path_params']`.

## AD-18: bcrypt only in `Auth/Service/auth_service.php`

The `tests/Unit/Phase02/Support/PasswordHashTest::test_no_password_hash_outside_auth_service`
test greps `src/**/*.php` for `password_hash(` and asserts that exactly
one file matches: `src/Auth/Service/auth_service.php`. The Phase 9
phpcs `Custom\Sniffs\NoRawHash` sniff is the durable enforcement; this
unit test runs in CI now.

The same restriction covers `md5(`, `sha1(`, `crypt(`. `Support\Crypto`
is the only other file allowed to call these primitives.

To add a new bcrypt usage:

```php
$hash = \App\Auth\Service\auth_service::hashPassword($plain);   // do
if (\App\Auth\Service\auth_service::verifyPassword($plain, $hash)) { ... }   // do
password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);   // don't — use the Service
```

## How to add a new state-changing Action

1. Add the route to `config/routes.php`:
   ```php
   'POST /foo' => ['App\Bar\Action\FooAction', 'handlePost', ['auth' => true, 'admin' => false, 'csrf' => true, 'rate_limit' => 'foo']],
   ```
2. Create `src/Bar/Action/FooAction.php`:
   ```php
   declare(strict_types=1);
   namespace App\Bar\Action;
   class FooAction {
       public function handlePost(): void {
           // validate input
           // call Service
           // return envelope or render View
       }
   }
   ```
3. The Router auto-validates the class against `config/contexts.php`
   (a class outside the 9 contexts is rejected with 500).

## How to add a new Support class

1. Create `src/Support/Foo.php`:
   ```php
   declare(strict_types=1);
   namespace App\Support;
   class Foo {
       public static function bar(): void { ... }
   }
   ```
2. PSR-4 autoload picks it up — no manual `require`.
3. If it touches the DB, call `Support\Db::pdo()` not a new PDO.
4. Add a unit test in `tests/Unit/Phase02/Support/FooTest.php`.

## Common pitfalls

1. **Starting a session before `ResponseHeaders::boot()`** breaks CSP
   because headers are emitted before the body starts; the next
   `header()` call after output begins is a warning. Keep the bootstrap
   order intact.
2. **Calling `password_hash(` outside `auth_service.php`** is an AD-18
   violation. Use `\App\Auth\Service\auth_service::hashPassword()`.
3. **Forgetting to update `config/routes.php`** when adding an Action
   results in a 404, not a 500. Check the route map first when a new
   endpoint returns the generic Not Found page.
4. **Using `$_SESSION['session_id']` instead of `$_COOKIE[session_name()]`**
   in `Auth::boot()`. The cookie IS the session id; the `$_SESSION`
   array doesn't carry it. `Support\Auth::boot()` reads the cookie.
5. **Running `php migrate.php` against the dev DB while a PHPUnit
   suite is running against the test DB** races on `migrations/.applied`
   (the file is shared by both surfaces). Use `APP_ENV=test` for the
   test run so the runner reads `config/db.test.php` and the test DB.

## Quick reference

- Migrations: `php migrate.php` (first run) or `APP_ENV=test php migrate.php` (test DB).
- Dev server: `php -S 127.0.0.1:18001 -t public public/router.php`.
- Tests: `APP_ENV=test vendor/bin/phpunit --testsuite=phase-2`.
- Setup: `bash bin/dev-setup.sh` (idempotent).
- Lint: `vendor/bin/phpcs --standard=PSR12 src/`.

## What lands next

Plan 02-02 fills the stub Action bodies (login form, register form,
forgot password, reset password, verify, logout) and adds the matching
View files in `src/Auth/View/`. Plan 02-03 fills the profile read view
(`/profile/{nickname}`) and the public-facing profile summary header.
