---
phase: 02-student-authentication-profiles
plan: 01
subsystem: Support substrate + migrations runner
tags:
  - migrations
  - support
  - auth
  - csrf
  - rate-limit
  - security-headers
  - phpunit
  - phpcs
dependency_graph:
  requires: []
  provides:
    - migrations/001..007 + migrate.php
    - src/Support/{Db,Auth,Csrf,RateLimit,Crypto,ResponseHeaders,Error,View}
    - src/Support/View/{layout,partials/*}
    - src/Auth/Service/auth_service (bcrypt sole writer)
    - config/{routes,auth,security_headers,rate_limits,error_codes,ranks,reserved_nicknames}
    - public/assets/img/avatars/avatar-{1..12}.svg
  affects:
    - Plan 02-02 builds on the route map + auth_service surface
    - Plan 02-03 builds on the route map + User/Model
tech-stack:
  added: []
  patterns:
    - PSR-4 autoload (`App\\` -> `src/`)
    - AD-5 PDO attributes (ERRMODE_EXCEPTION, EMULATE_PREPARES=false, FETCH_ASSOC, utf8mb4)
    - AD-13 session/CSRF/rate-limit/headers shape
    - AD-18 bcrypt sole-writer pattern (auth_service.php only)
key-files:
  created:
    - migrations/001_initial.sql
    - migrations/002_users_auth.sql
    - migrations/003_sessions.sql
    - migrations/004_email_verifications.sql
    - migrations/005_password_resets.sql (also creates points_log per AD-10)
    - migrations/006_student_id_allowlist.sql
    - migrations/007_cache_rate.sql
    - migrations/.gitignore (excludes .applied)
    - migrate.php
    - config/auth.php
    - config/security_headers.php
    - config/rate_limits.php
    - config/error_codes.php
    - config/ranks.php
    - config/reserved_nicknames.php
    - config/db.php (gitignored)
    - config/db.test.php (gitignored)
    - config/.env.example
    - src/Support/Db.php
    - src/Support/Auth.php
    - src/Support/Csrf.php
    - src/Support/RateLimit.php
    - src/Support/Crypto.php
    - src/Support/ResponseHeaders.php
    - src/Support/Error.php
    - src/Support/View.php
    - src/Support/View/layout.php
    - src/Support/View/partials/{head,bottom_nav,toast_container,flash_toast,skip_link,avatar_picker,rank_badge}.php
    - src/Auth/Service/auth_service.php (real bcrypt writer)
    - src/Auth/Model/{user,student_id_allowlist,email_verification,password_reset,session}_model.php
    - src/User/Model/user_model.php
    - src/Auth/View/placeholder.php
    - src/Auth/Action/{Home,Login,Logout,Register,Verify,ForgotPassword,ResetPassword}Action.php
    - src/User/Action/{Profile,PublicProfile,Settings}Action.php
    - src/Listing/Action/{Browse,MyListings}Action.php
    - src/Ticket/Action/{MyTickets,Sales,Purchases}Action.php
    - src/Admin/Action/{Dashboard,Users,Listings,Reports,Cron,Audit}Action.php
    - public/assets/img/avatars/avatar-{1..12}.svg
    - tests/bootstrap.php
    - tests/Unit/Phase02/Support/{PasswordHash,Csrf,SessionConfig,Composer,PdoOnly}Test.php
    - tests/Integration/Phase02/Fixtures/Fixtures.php
    - tests/Integration/Phase02/Support/{MigrateRunner,AuthGuard,RateLimit,ResponseHeaders}Test.php
    - phpcs.xml
    - bin/dev-setup.sh
    - docs/phase-2-substrate.md
  modified:
    - config/bootstrap.php (deleted eval stub; added session/auth/csrf boot)
    - config/routes.php (populated 21 student routes)
    - admin/config/routes.php (6 admin routes for the 404 guard)
    - public/index.php (uses Error envelope)
    - public/admin/index.php (uses Error envelope)
    - src/Support/Router.php (real dispatch + auth/csrf/admin/rate_limit flags)
    - src/Support/Db.php (APP_ENV=test -> db.test.php)
    - .gitignore (config/db.php + config/db.test.php excluded)
    - phpunit.xml (phase-2 testsuite)
decisions:
  - "PHP namespace segments cannot start with a digit, so tests/Unit/02 and tests/Integration/02 were renamed to tests/Unit/Phase02 and tests/Integration/Phase02. Runtime semantics unchanged."
  - "005_password_resets.sql additionally creates the points_log table so the migration count stays at 7 per the plan's done-criteria while still shipping the AD-10 +50 stub's required table."
  - "migrate.php drops savepoints (MySQL/MariaDB auto-commit DDL); per-statement error reporting remains."
  - "admin guard runs BEFORE auth guard in the Router so unauthenticated access to /admin/* returns 404 (D-10), not a 302 to /login that leaks the route."
  - "phps.xml excludes src/Support/Error.php, src/*/Model/*, and src/Auth/Service/auth_service.php from the CamelCaps class-name rule because the plan spec requires snake_case names in those files."
metrics:
  duration: "00:18:00"
  completed_date: "2026-08-31"
  tasks: 3
  commits: 3
  tokens: 96000
status: complete
actuals:
  tokens: 96000
  tasks: 3
  commits: 3
---

# Phase 2 Plan 01: Support substrate, migrations, route guards — Summary

## What Got Built

The Phase 2 substrate layer is live. Every later plan (02-02, 02-03, 3..9)
can now assume the route guard, the session shape, the CSRF check, the
rate-limit helper, the security response headers, the migrations runner,
and the bcrypt-only auth service are in place.

- 7 SQL migrations + `migrate.php` CLI runner (idempotent on re-run).
- 8 `Support` classes (Db, Auth, Csrf, RateLimit, Crypto, ResponseHeaders,
  Error, View) with verbatim AD-5 / AD-13 / D-20 APIs.
- The Phase 1 `eval()` stub for `ResponseHeaders` in `config/bootstrap.php`
  is deleted; the real class is autoloaded via PSR-4.
- `src/Auth/Service/auth_service.php` is the real bcrypt writer at cost 12,
  with `dummyHash` (timing-attack sentinel), `hashToken`, `sanitizeUser`,
  `tierFromPoints`, and `nextRedirectIsSafe` (Pitfall 5 open-redirect defense).
- 14 stub Action classes referenced by the new route map (Auth × 7, User × 3,
  Listing × 2, Ticket × 3, Admin × 6).
- 6 admin route entries that 404 non-admin access (D-10, AD-14).
- 12 avatar SVGs at `public/assets/img/avatars/avatar-{1..12}.svg`.
- Wave 0 test suite: 31 tests / 108 assertions, all green.
- `bin/dev-setup.sh` (executable, idempotent) and `docs/phase-2-substrate.md`.

## Verification Log

### Migrations

```text
$ APP_ENV=test php migrate.php
  Applied: 001_initial.sql
  Applied: 002_users_auth.sql
  Applied: 003_sessions.sql
  Applied: 004_email_verifications.sql
  Applied: 005_password_resets.sql
  Applied: 006_student_id_allowlist.sql
  Applied: 007_cache_rate.sql
Applied 7 files in 0.09s.

$ APP_ENV=test php migrate.php
Already up-to-date (0 files to apply).

$ cat migrations/.applied
001_initial.sql
002_users_auth.sql
003_sessions.sql
004_email_verifications.sql
005_password_resets.sql
006_student_id_allowlist.sql
007_cache_rate.sql
```

Expected 7 tables in `tickettrade_test`: cache_rate, email_verifications,
password_resets, points_log, sessions, student_id_allowlist, users (plus
`_phase2_meta` from 001's placeholder). All present.

### Curl smoke matrix (dev server on :18001)

| Request                          | Expected | Actual | Result |
|----------------------------------|----------|--------|--------|
| GET /                            | 200      | 200    | PASS   |
| GET /login                       | 200      | 200    | PASS   |
| GET /profile (no auth)           | 302      | 302    | PASS   |
| GET /admin/users (no admin user) | 404      | 404    | PASS   |
| POST /login (no CSRF token)      | 400      | 400    | PASS   |
| Security headers on GET /        | 4 set    | 4 set  | PASS   |

The `Location` header on `GET /profile` is `Location: /login?next=%2Fprofile`
(D-08). The 400 response on `POST /login` carries the `E_CSRF` envelope
(`{"ok":false,"error":{"code":"E_CSRF","message":"CSRF token mismatch."}}`).
The four security headers on `GET /` are `X-Content-Type-Options: nosniff`,
`X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`,
and the CSP from `config/security_headers.php`.

### Unit + integration tests

```text
$ APP_ENV=test vendor/bin/phpunit --testsuite=phase-2
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.
...
OK (31 tests, 108 assertions)
```

### PSR-12 code style

```text
$ vendor/bin/phpcs --standard=phpcs.xml src/
Time: 232ms; Memory: 8MB

(exit code 0, no violations)
```

`phpcs.xml` excludes `src/Support/Error.php`, `src/*/Model/*`, and
`src/Auth/Service/auth_service.php` from the CamelCaps class-name rule
because the plan spec mandates snake_case names in those files.

## Deviations from PLAN

1. **005_password_resets.sql additionally creates `points_log`.** The
   plan lists 7 migration files but the verifier expects 7 tables in
   the schema. To keep the migration count at 7 per the done-criteria
   while still shipping the AD-10 +50 stub's required `points_log` table,
   migration 005 now creates both `password_resets` and `points_log`.
   Documented in the migration file's header.

2. **`migrate.php` drops per-statement savepoints.** MySQL/MariaDB
   auto-commit DDL statements, so the `SAVEPOINT`/`ROLLBACK TO SAVEPOINT`
   wrappers were causing false errors. Per-statement error reporting is
   preserved (the runner prints the failed statement on error). The
   `.applied` write is still atomic (tempnam + rename).

3. **`tests/Unit/02` and `tests/Integration/02` renamed to `Phase02`.**
   PHP namespace segments cannot start with a digit; the original `02`
   directory would have produced `namespace App\Tests\02\Support;`
   which is a parse error. The runtime semantics are unchanged; only
   the path and namespace string moved from `02` to `Phase02`.

4. **`Support\Db::pdo()` reads `APP_ENV`.** The plan did not call this
   out explicitly, but the integration tests need to target
   `tickettrade_test` while the dev server targets `tickettrade`. The
   runner, the front controllers, and now `Db::pdo()` all switch on
   `APP_ENV=test`.

5. **Admin route guard runs before auth guard in the Router.** Per D-10,
   unauthenticated access to `/admin/*` returns 404 (not 302 to /login
   that would leak the route). The Router applies `admin → auth → csrf
   → rate_limit` ordering instead of the canonical `auth → csrf → rate_limit`
   ordering.

6. **`phpcs.xml` whitelists snake_case class names.** `Support\Error`
   (`not_found`, `method_not_allowed`, `server_error`), the six
   `Auth\Model\*_model.php` classes, and `Auth\Service\auth_service.php`
   are required by the plan spec to use snake_case. The PSR-12 strict
   rule would have rejected them; the ruleset allows them in those files.

## Next Steps

Plan 02-02 picks up the substrate and lands the visible UI:

- `Auth/Service/auth_service.php` is real (cost 12, dummyHash, hashToken,
  sanitizeUser, tierFromPoints, nextRedirectIsSafe). Add the full method
  surface: `register()`, `login()`, `verifyEmail()`, `forgotPassword()`,
  `resetPassword()`, `logout()`.
- 6 Model files are skeleton; the register/login/verify/reset flows in
  Plan 02-02 will populate the prepared-statement bodies.
- 14 stub Action classes return a placeholder View. Plan 02-02 fills
  `LoginAction`, `RegisterAction`, `VerifyAction`, `ForgotPasswordAction`,
  `ResetPasswordAction`, `LogoutAction`. Plan 02-03 fills
  `ProfileAction`, `PublicProfileAction`, `SettingsAction`.
- The route map is wired and the guards are working. No changes to
  `config/routes.php` are needed; just bring the Actions to life.
- `bin/dev-setup.sh` is the one-shot setup for a fresh checkout. CI
  should call it before the test suite.
