---
phase: 02-student-authentication-profiles
reviewed: 2026-09-05T00:00:00Z
depth: deep
files_reviewed: 44
files_reviewed_list:
  - admin/config/routes.php
  - bin/dev-setup.sh
  - config/auth.php
  - config/bootstrap.php
  - config/db.php
  - config/db.test.php
  - config/.env.example
  - config/error_codes.php
  - config/ranks.php
  - config/rate_limits.php
  - config/reserved_nicknames.php
  - config/routes.php
  - config/security_headers.php
  - docs/phase-2-public-profile.md
  - docs/phase-2-substrate.md
  - .gitignore
  - migrate.php
  - migrations/001_initial.sql
  - migrations/002_users_auth.sql
  - migrations/003_sessions.sql
  - migrations/004_email_verifications.sql
  - migrations/005_password_resets.sql
  - migrations/006_student_id_allowlist.sql
  - migrations/007_cache_rate.sql
  - migrations/.gitignore
  - phpcs.xml
  - phpunit.xml
  - public/admin/index.php
  - public/index.php
  - src/Auth/Service/auth_service.php
  - src/Auth/View/placeholder.php
  - src/Support/Auth.php
  - src/Support/Crypto.php
  - src/Support/Csrf.php
  - src/Support/Db.php
  - src/Support/Error.php
  - src/Support/RateLimit.php
  - src/Support/ResponseHeaders.php
  - src/Support/Router.php
  - src/Support/View/layout.php
  - src/Support/View/partials/rank_badge.php
  - src/Support/View.php
  - src/User/Action/PublicProfileAction.php
  - src/User/Model/user_model.php
  - src/User/Service/user_service.php
  - src/User/View/public_profile.php
  - tests/bootstrap.php
  - tests/Integration/Phase02/Fixtures/Fixtures.php
  - tests/Integration/Phase02/User/PublicProfileRenderTest.php
  - tests/Integration/Phase02/User/PublicProfileTest.php
findings:
  critical: 4
  warning: 8
  info: 5
  total: 17
status: issues_found
---

# Phase 2: Code Review Report

**Reviewed:** 2026-09-05T00:00:00Z
**Depth:** deep
**Files Reviewed:** 44 (listed above; 7 referenced supporting files inspected for cross-file analysis)
**Status:** issues_found

## Summary

Phase 2 (student-authentication-profiles) ships the auth substrate, public profile,
admin route stub, and seven SQL migrations. The substrate is solid: AD-18 bcrypt
sole-writer enforced (only `auth_service.php` calls `password_hash`), the failure
envelope is consistent (AD-16), CSRF per-session + constant-time compare (AD-13),
rate-limit fixed-window via atomic `INSERT … ON DUPLICATE KEY UPDATE` (D-12/D-13),
session cookie hardening (Strict + httponly + 7-day lifetime), CSP/Referrer-Policy/
X-Frame-Options, BINARY case-sensitive nickname lookup for the public profile (D-15),
sanitizeUser() strip-list enforced on every public read.

Cross-file review surfaced 4 critical defects, 8 warnings, and 5 informational
items. The criticals cluster around (a) a behavioral gap between the public
profile's stated "On-Break pill" support and the actual SQL projection that
omits `last_active_at`, (b) the `.gitignore` not actually excluding the
gitignored-by-AD-17 config files (`config/db.php`, `config/db.test.php`)
creating a credential-leak risk on subtree-split pushes to GitHub, (c) the
`register()` race-condition catch collapsing `E_NICKNAME_TAKEN` to the misleading
`E_AUTH_ALLOWLIST` envelope, and (d) the public profile View parsing
`created_at` as UTC when the database stores it as Asia/Colombo wall-clock.

No structural-findings payload was supplied to this review (fallow section is
absent by design).

## Critical Issues

### CR-01: Public profile View's `on_break_pill` always renders nothing (docstring vs. implementation gap)

**File:** `src/User/View/public_profile.php:92-94` (calls `View::partial('on_break_pill', ['lastActiveAt' => $laPublic])`) and `src/User/Service/user_service.php:61-67` (the SELECT projection)

**Issue:** The View comment at lines 88-94 documents "On-Break pill on public profiles too — the rank badge wrapping is the grayed-out signal." The View passes `$profile['last_active_at'] ?? null` to the `on_break_pill` partial, but the `getByNicknameForPublicProfile()` SELECT projection does NOT include `last_active_at`:

```php
// user_service.php line 61-67
SELECT user_id, nickname, full_name, bio, avatar_id, tier,
       points, is_verified, created_at
FROM users
WHERE BINARY nickname = ? AND is_banned = FALSE
LIMIT 1
```

The column `last_active_at` is added by migration `019_users_last_active.sql` (Phase 6), so it exists in the schema for later phases, but Phase 2's public profile SQL projection omits it. Result: `$laPublic` is always `null`, the partial's `if ($lastActiveAt === null) { return; }` short-circuit at line 24-26 fires every time, and the pill never renders — a feature gap between the docstring contract and the runtime behavior.

**Fix:** Add `last_active_at` to the SELECT projection in `getByNicknameForPublicProfile()`:

```php
$stmt = $pdo->prepare(
    'SELECT user_id, nickname, full_name, bio, avatar_id, tier, '
    . 'points, is_verified, last_active_at, created_at '
    . 'FROM users '
    . 'WHERE BINARY nickname = ? AND is_banned = FALSE '
    . 'LIMIT 1'
);
```

Or, if the On-Break pill is intentionally Phase 6+, remove the partial call from the public profile (and the misleading comment) until then.

---

### CR-02: `.gitignore` does not exclude `config/db.php` and `config/db.test.php` — credential leak risk on subtree-split pushes

**File:** `.gitignore` (whole file) and `config/db.php`, `config/db.test.php`

**Issue:** AGENTS.md / ARCHITECTURE-SPINE.md AD-17 states that `config/db.php` and `config/db.test.php` are `.gitignore`'d. The actual `.gitignore` at the repo root only excludes `.env`, `.env.local`, vendor, IDE files, lockfiles, and the data directory. It does NOT exclude `config/db.php` or `config/db.test.php`. The files contain the DSN, user, and password:

```php
// config/db.php
return [
    'dsn'  => getenv('DB_DSN')  ?: 'mysql:unix_socket=/tmp/mysql.sock;dbname=tickettrade;charset=utf8mb4',
    'user' => getenv('DB_USER') ?: 'user',
    'pass' => getenv('DB_PASS') ?: '',
];
```

After `bin/dev-setup.sh` runs (which writes them), they sit on disk with the actual socket/host/user. If anyone commits with `git add -A` from the monorepo root (a footgun the AGENTS.md explicitly warns against) or if the subtree-split workflow doesn't filter them out, the credentials land in GitHub history. The `migrations/.gitignore` correctly excludes the `.applied` file, but the root `.gitignore` is incomplete.

**Fix:** Append the following to `.gitignore`:

```gitignore
# Per AD-17: runtime DB config (DSN + credentials) is generated locally
# by bin/dev-setup.sh and never committed.
config/db.php
config/db.test.php
```

And verify with `git check-ignore -v config/db.php` returns a match before pushing.

---

### CR-03: `register()` race-condition catch returns misleading `E_AUTH_ALLOWLIST` instead of `E_NICKNAME_TAKEN`

**File:** `src/Auth/Service/auth_service.php:299-315` (the `\PDOException` catch in the `beginTransaction` block)

**Issue:** The pre-check at lines 267-276 detects an already-taken nickname and returns `E_NICKNAME_TAKEN` with the proper field-specific copy. But the race-condition catch at lines 299-315 collapses EVERY unique-constraint violation (uniq_email, uniq_student_id, **uniq_nickname**) to `E_AUTH_ALLOWLIST` with copy "Email or student ID not recognized. Check both and try again." That copy is technically a SECURITY win (no enumeration), but for a race on the nickname index specifically, the user sees a message that has nothing to do with their actual problem (their nickname was just registered by someone else a few ms before them). The legitimate UX expectation is "Nickname taken. Pick another." — the same code the pre-check returns.

This also creates an asymmetry: pre-check returns `E_NICKNAME_TAKEN`, race returns `E_AUTH_ALLOWLIST`. Two users registering the same nickname at the same instant get two different error codes. That's a correctness gap, not a security one — the underlying anti-enumeration rationale ("collapse to the same copy") doesn't apply to nickname uniqueness, which is intentionally public per D-13.

**Fix:** Inspect the PDOException message to differentiate unique-index violations:

```php
} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // 1062 = MySQL duplicate-key; differentiate nickname vs email/student_id.
    if ((string) $e->getCode() === '23000'
        && str_contains($e->getMessage(), 'uniq_nickname')) {
        return [
            'ok' => false,
            'error' => [
                'code' => 'E_NICKNAME_TAKEN',
                'message' => 'Nickname taken. Pick another.',
                'fields' => ['nickname' => 'Nickname taken. Pick another.'],
            ],
        ];
    }
    // Email / student_id race — keep the combined anti-enumeration copy.
    return [
        'ok' => false,
        'error' => [
            'code' => 'E_AUTH_ALLOWLIST',
            'message' => 'Email or student ID not recognized. Check both and try again.',
            'fields' => null,
        ],
    ];
}
```

(Or rely on the constraint name via `$e->errorInfo[1] === 1062` + index lookup, but the message-string approach works for MariaDB/MySQL.)

---

### CR-04: Public profile View parses `created_at` as UTC but DB stores Asia/Colombo wall-clock — wrong join date displayed

**File:** `src/User/View/public_profile.php:50` (the `new DateTime($profile['created_at'], new DateTimeZone('UTC'))` call)

**Issue:** The DB stores `users.created_at` as the result of `(new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s')` — see `src/Auth/Model/user_model.php:58` and `src/Auth/Service/auth_service.php:285`. The wall-clock string is Colombo time. But the View parses it as UTC:

```php
// public_profile.php line 50
$createdAtFormatted = (new DateTime((string) $profile['created_at'], new DateTimeZone('UTC')))
    ->setTimezone(new DateTimeZone('Asia/Colombo'))
    ->format('d M Y');
```

If a user registered at Colombo wall-clock `2026-08-31 18:00:00`, the View parses it as `18:00 UTC`, converts to Colombo `23:30 same day`. The displayed date is correct in this particular case (same day) but the time-of-day is off by 5h30m. For users who registered at Colombo wall-clock `2026-08-31 02:00:00`, the View parses as UTC, converts to `07:30 same day` (still same day — OK by coincidence). But for a Colombo wall-clock `2026-08-31 22:00:00`, the View parses as UTC, converts to next-day `03:30 Sep 1` — **wrong day**. The test `test_join_date_uses_asia_colombo` (PublicProfileRenderTest.php:130-137) seeds `created_at = '2026-08-31 18:00:00'` and asserts only "31 Aug 2026" — which passes for the wrong reason (the date is the same day in both interpretations at that specific time). The test does not catch the bug.

**Fix:** Parse as Asia/Colombo (the storage TZ per AD-17), then format in the same TZ (no conversion needed):

```php
$createdAtFormatted = (new DateTime((string) $profile['created_at'], new DateTimeZone('Asia/Colombo')))
    ->format('d M Y');
```

Or, simpler, since the value is already in the target TZ, just format it directly:

```php
try {
    $createdAtFormatted = (new DateTime((string) $profile['created_at'], new DateTimeZone('Asia/Colombo')))
        ->format('d M Y');
} catch (\Throwable $e) {
    $createdAtFormatted = (string) $profile['created_at'];
}
```

And tighten the test to seed a value that exercises the timezone bug (e.g. `created_at = '2026-08-31 23:00:00'` should still display as `31 Aug 2026` if parsed as Colombo; the current UTC parse would shift it to `01 Sep 2026`).

---

## Warnings

### WR-01: `register()` does not check `is_banned` — banned user could re-register if their row is deleted

**File:** `src/Auth/Service/auth_service.php:240-265` (the allowlist + duplicate-email branch)

**Issue:** The combined anti-enumeration branch at lines 242-265 checks: allowlist email, allowlist student_id, pair mismatch, duplicate email, duplicate nickname. It does NOT check `users.is_banned`. A banned user whose row was deleted from `users` (e.g., as part of a future unban-then-purge flow, or a manual admin purge) could pass the duplicate-email check (their email is gone), pass the allowlist check, and re-register successfully. The `is_banned` flag is not consulted because there is no row to consult — the deleted user has no row at all. Phase 2 doesn't have an unban/purge flow, so this is latent. When Phase 8 lands admin user management (per the AGENTS.md phase order), this gap becomes exploitable.

**Fix:** Add an explicit `is_banned` check against the allowlist's `email` lookup — but `student_id_allowlist` doesn't carry `is_banned`. Either (a) add an `is_banned` column to `student_id_allowlist` and gate by it, or (b) keep a soft-delete tombstone (`users.deleted_at`) so the duplicate-email pre-check still catches banned-then-deleted users:

```sql
ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL AFTER is_banned;
```

And in the model:

```php
// user_model::findByEmail — modify the WHERE
'SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1'
```

The auth_service::register flow can stay as-is; the `deleted_at IS NULL` filter makes re-registration impossible.

---

### WR-02: `migrate.php` truncates `migrations/.applied` in `bin/dev-setup.sh` line 102 — risky for any future non-idempotent migration

**File:** `bin/dev-setup.sh:101-103`

```bash
if [ "$ACTUAL_TABLES" -lt "$EXPECTED_TABLES" ]; then
  echo "[dev-setup] Running php migrate.php (DB has $ACTUAL_TABLES tables, $EXPECTED_TABLES expected)..."
  : > migrations/.applied
  php migrate.php
```

**Issue:** The script truncates `migrations/.applied` (the file that tracks which migrations have been applied) before running the migration runner. The condition `ACTUAL_TABLES < EXPECTED_TABLES` triggers this on partial-DB state. The migration files use `IF NOT EXISTS` and `IF NOT EXISTS INDEX`, so DDL is idempotent. But if a future migration introduces a non-idempotent INSERT (e.g., a seed row), re-running after truncation would double-insert. The 007_cache_rate.sql has no seed, but a hypothetical 050_seed_categories.sql would.

**Fix:** Don't truncate. Let the migrate.php runner itself read `.applied` and only apply the missing ones (which is its default behavior). The reason for the truncate is the dev-setup.sh comment says "Re-running migrations against a populated DB fails on UNIQUE constraints" — that's a per-migration problem, not a bootstrap problem. If a particular migration needs to be re-run, the dev can delete that one filename from `.applied` manually.

```bash
# REMOVE the `: > migrations/.applied` line. The runner already skips
# applied migrations per its .applied read.
php migrate.php
```

---

### WR-03: `bin/dev-setup.sh` shell-quoting risk: `$DB_USER` is interpolated into a heredoc that writes PHP

**File:** `bin/dev-setup.sh:58-66` (and the matching block at 76-84 for `db.test.php`)

**Issue:** `DB_USER` is set from `mysql -u... -e 'SELECT 1'` probe results (line 28-35), which means it comes from a controlled set: `root`, `user`, `$USER` (shell env), or the literal default `user`. None of those are user-controlled in any meaningful way. But the heredoc interpolation at line 62 puts `$DB_USER` directly into the generated `config/db.php`:

```bash
cat > config/db.php <<EOF
<?php
return [
    'dsn'  => getenv('DB_DSN') ?: '$DSN',
    'user' => getenv('DB_USER') ?: '$DB_USER',
    'pass' => getenv('DB_PASS') ?: '',
];
EOF
```

If `$USER` env var contains a single quote or backslash, the generated PHP file is malformed. Not exploitable today (dev-only script), but worth tightening.

**Fix:** Use `printf` with `%s` to avoid heredoc interpolation:

```bash
cat > config/db.php <<EOF
<?php
return [
    'dsn'  => getenv('DB_DSN') ?: %s,
    'user' => getenv('DB_USER') ?: %s,
    'pass' => getenv('DB_PASS') ?: '',
];
EOF
DSN_LITERAL=$(printf "%s" "$DSN" | php -r 'echo var_export(stream_get_contents(STDIN), true);')
USER_LITERAL=$(printf "%s" "$DB_USER" | php -r 'echo var_export(stream_get_contents(STDIN), true);')
# then interpolate $DSN_LITERAL / $USER_LITERAL into the heredoc
```

---

### WR-04: `migrate.php` SQL comment-stripping is fragile — `--` inside string literals would corrupt the statement

**File:** `migrate.php:96-101`

```php
// Strip -- line comments (must not be inside strings; per D-27 we
// forbid ; inside string literals)
$lines = explode("\n", $sql);
$cleaned = [];
foreach ($lines as $line) {
    $stripped = preg_replace('/--.*$/', '', $line);
    $cleaned[] = $stripped;
}
```

**Issue:** The comment-stripper strips `--` to end-of-line unconditionally, which would corrupt any string literal containing `--`. The D-27 invariant forbids `;` in strings but doesn't forbid `--`. If a future migration has an INSERT with `'-- my note'` in a string column, the comment stripper would corrupt it. The seven Phase 2 migrations don't trip this, but the runner's contract is brittle.

**Fix:** Use a SQL parser aware of string-literal boundaries, OR document D-27 to also forbid `--` inside string literals. Or simpler: skip the `--` strip and rely on `/* ... */` stripping only. Most real-world migrations don't have `--` inside string literals, and the runtime impact is zero if they do — the SQL just executes with a trailing `-- rest of line` comment, which MySQL ignores. Actually wait — the current strip happens BEFORE `explode(';', $sql)`. If a `--` comment is on the same line as a `;`-terminated statement, the strip removes the comment AND the `;`-terminator, breaking the split. The simplest fix is to strip `--` only when it's preceded by whitespace or at line start:

```php
$stripped = preg_replace('/(^|\s)--.*$/', '$1', $line);
```

This still strips `  -- my comment` but not `'--foo'`.

---

### WR-05: `migrate.php` runs concurrent invocations against the same `.applied` file without locking — race risk on non-idempotent migrations

**File:** `migrate.php:51-58` (read) and 117-138 (write)

**Issue:** Two `php migrate.php` processes started simultaneously read the same `.applied`, both compute the same `$pending` list, both apply the same migrations, both write back. The current DDL is `IF NOT EXISTS` so it's safe, but the `INSERT ... .applied` rename at line 135 is non-atomic across processes — the second writer's tempnam + rename could clobber the first writer's. Plus any future non-idempotent migration (a seed INSERT) would double-execute.

**Fix:** Add `flock()` around the read-compute-write cycle, or move the applied-tracking to a DB table (e.g., `schema_migrations`):

```php
// At the top of the loop, acquire a file lock:
$lockFh = fopen($appliedFile . '.lock', 'c');
if (!flock($lockFh, LOCK_EX)) {
    fwrite(STDERR, "[migrate] Could not acquire lock.\n");
    exit(1);
}
// ... existing read/compute/write logic ...
fflush($lockFh);
flock($lockFh, LOCK_UN);
fclose($lockFh);
```

---

### WR-06: `Support\Router::dispatch()` emits verbose `error_log` calls on every request — production log noise + potential PII leak

**File:** `src/Support/Router.php:36, 43, 81, 97`

```php
error_log("[ROUTER] dispatch surface=$surface method=$method path=$path routes_count=" . count($routes) . " key={$method} {$path}");
// ...
error_log("[ROUTER] trying placeholders, count=" . count($routes));
// ...
error_log("[ROUTER] final route=" . var_export($route, true));
// ...
error_log("[ROUTER] class=$class method=$methodName admin_guard=" . var_export(!empty($opts['admin']), true));
```

**Issue:** Four `error_log` calls per request. Three of them include the full request path (which can contain URL-encoded sensitive data like email-verification tokens, password-reset tokens, or any other path-param the user pastes into the URL). `var_export($route, true)` logs the route config including class names — minor info leak in shared logs. This is debug-grade logging that was probably added during initial development but never gated on `APP_ENV === 'development'`.

**Fix:** Gate on dev-mode:

```php
$isDev = getenv('APP_ENV') === 'development';
if ($isDev) {
    error_log("[ROUTER] dispatch surface=$surface method=$method path=$path routes_count=" . count($routes));
}
```

Or use `error_log` only when an actual error condition is hit (unknown route, dispatch failure, etc.) — never on the happy path.

---

### WR-07: `migrate.php` and `bin/dev-setup.sh` race condition on `migrations/.applied` (shared file between dev + test DB)

**File:** `bin/dev-setup.sh:102` (`: > migrations/.applied`) and the migrate.php reads at line 53-58

**Issue:** Per AGENTS.md (env-quirks) and docs/phase-2-substrate.md (Pitfall 5): "Running `php migrate.php` against the dev DB while a PHPUnit suite is running against the test DB races on `migrations/.applied` (the file is shared by both surfaces)." This is documented but still a real risk — a developer running both at once (e.g., a long phpunit run on a CI worker that someone else is also using for dev) could see migrations re-applied or, worse, partial-write corruption if the tempnam/rename happens mid-flight.

**Fix:** The AGENTS.md and phase-2-substrate.md already document this. Two hardening options:

1. Have `migrate.php` take an explicit `--db=dev|test` flag and write `migrations/.applied-dev` or `migrations/.applied-test`. Both files are gitignored.
2. Wrap the `migrations/.applied` read/write in a `flock(LOCK_EX)` (see WR-05 fix).

The simplest is option 1 — explicit per-surface state files — which the migrate.php can derive from the DSN or the `APP_ENV` already in scope.

---

### WR-08: `config/ranks.php` defines a global function via `function_exists` guard — works but creates a fragile load-order dependency

**File:** `config/ranks.php:21-42` (the global `tierFromPoints` function) and `src/Auth/Service/auth_service.php:114-127` (the static method `tierFromPoints`)

**Issue:** `config/ranks.php` defines a global function `tierFromPoints()` at the bottom of the file, guarded by `function_exists`. `auth_service.php` ALSO defines a static method `tierFromPoints()` that has its own copy of the ladder logic — fallback for when the global function isn't loaded yet. The dual-implementation is fragile:

- If `auth_service.php` is loaded BEFORE `config/ranks.php` (e.g., composer autoload picks it up before any code requires `ranks.php`), the global function doesn't exist yet. `auth_service::tierFromPoints()` falls through to its local `foreach` ladder — which uses the SAME ladder (re-required via `require APP_ROOT . '/config/ranks.php'` at line 119). So the function gets defined and used. OK.
- If both paths are taken in a single process (e.g., `points_service::simpleAward` calls `auth_service::tierFromPoints` at line 929 AND `require APP_ROOT . '/config/ranks.php'` at line 948), the global is defined via the require. Subsequent calls to the static method check `function_exists` (true), call the global. Both branches return the same value — but the auth_service local ladder is now dead code in any well-ordered initialization.

The auth_service local copy is technically dead code (it's only used if ranks.php wasn't loaded yet, which never happens in the request lifecycle). The redundancy is also a maintenance trap — if the ladder gains a new tier, both copies must be updated, but the static method's local require pulls the live ladder, so it's safe by accident.

**Fix:** Delete the `auth_service::tierFromPoints()` local ladder loop. Make the static method just call the global function (which is loaded by `require APP_ROOT . '/config/ranks.php'` at the top of the static method):

```php
public static function tierFromPoints(int $points): string
{
    // The global function is defined in config/ranks.php on first require.
    if (!function_exists('tierFromPoints')) {
        require_once APP_ROOT . '/config/ranks.php';
    }
    return tierFromPoints($points);
}
```

This eliminates the dual-implementation while preserving the contract.

---

## Info

### IN-01: `Support\Auth::adminGuard()` is dead code in the Router dispatch path

**File:** `src/Support/Auth.php:95-101` and `src/Support/Router.php:105-112`

**Issue:** `Support\Auth::adminGuard()` exists with the documented contract "404 for non-admin access (D-10, AD-14)." The Router's `dispatch()` method implements the same check inline at lines 105-112 (a direct `is_admin` check), so `adminGuard()` is not called from the Router. `adminGuard()` is only referenced in its own docstring and the AGENTS.md / CONTEXT / RESEARCH docs. The actual call sites that DO use the Auth class are `Listing/Action/CreateListingAction.php:35,54` and `EditListingAction.php:38,77` — they call `requireAuth()`, not `adminGuard()`. The Phase 8 admin console will likely call `adminGuard()` (or its replacement `requireAdmin()` per Phase 3 docs at line 213 of 03-02-PLAN.md), so the method has a future caller — but in Phase 2, it's never invoked.

**Fix:** Either (a) accept the dead-code-for-future-use (low cost — 7 lines), or (b) delete `adminGuard()` and inline-replace it with `requireAdmin()` when Phase 8 lands. Recommend (a) — the Phase 8 admin console will need it, and the inline Router check at lines 105-112 mirrors the same semantics, so it's fine to leave both.

---

### IN-02: `config/db.php` and `config/db.test.php` default password is empty string `''`

**File:** `config/db.php:15` and `config/db.test.php:14`

**Issue:** Both files default `pass` to `getenv('DB_PASS') ?: ''`. In production this is fine ONLY if `DB_PASS` env var is set to a real password (the fallback is empty). For dev, empty password on a unix socket is the typical MariaDB setup. The risk: if `config/db.php` is shipped to production WITHOUT setting `DB_PASS`, the prod DB accepts unauthenticated connections. Per AGENTS.md the env var swap is documented in `.env.example`, which is fine. Combined with CR-02 (gitignore gap), this is a defense-in-depth concern.

**Fix:** Add a prod-time assertion that `DB_PASS` is set:

```php
$pass = getenv('DB_PASS');
if ($pass === false || $pass === '') {
    if (getenv('APP_ENV') === 'production') {
        throw new RuntimeException('DB_PASS must be set in production.');
    }
    $pass = '';
}
```

---

### IN-03: `Support\Auth::boot()` reads `last_seen` and compares with `time()` — assumes app TZ matches DB TZ

**File:** `src/Support/Auth.php:64-72`

```php
$now = (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
$lastSeen = strtotime($row['last_seen']);
if ($lastSeen !== false && $lastSeen < time() - 300) {
```

**Issue:** `$now` is a wall-clock Asia/Colombo string formatted into MySQL DATETIME. `strtotime($row['last_seen'])` parses that string — but `strtotime` uses the script's default TZ (which bootstrap.php line 30 sets to Asia/Colombo). Then compares against `time()` (Unix timestamp). This works because both the stored value and `time()` are compared as Unix timestamps after parsing — the TZ cancels out. **Today it works** because bootstrap.php pins the TZ. But it's brittle: if a future code path changes the TZ (e.g., during a test), the comparison silently breaks.

**Fix:** Be explicit about the TZ on both sides:

```php
$tz = new DateTimeZone('Asia/Colombo');
$now = new DateTime('now', $tz);
$lastSeenDt = new DateTime((string) $row['last_seen'], $tz);
if ($now->getTimestamp() - $lastSeenDt->getTimestamp() >= 300) {
    // touch
}
```

---

### IN-04: `auth_service::startSession()` mutates `$GLOBALS['current_user']` redundantly after Auth::boot() already set it

**File:** `src/Auth/Service/auth_service.php:445-467`

**Issue:** The method calls `session_regenerate_id(true)` (which deletes the old session file), then inserts a new session row, then sets `$GLOBALS['current_user'] = $user;` (line 463). The docstring (lines 462-466) says "Force Auth::boot() to re-read on the next request" — but for the CURRENT request, the mutation is unused: `Auth::boot()` already ran at bootstrap and set `current_user` to the OLD session's user row. The mutation is harmless (the user row is the same), but the comment is misleading. The current request doesn't need `current_user` re-read — `boot()` already populated it. The next request will re-read on its own `boot()`.

**Fix:** Either (a) drop the mutation entirely (the comment was a future-proofing idea that didn't pan out), or (b) update the comment to explain it's a no-op-for-current-request defensive assignment:

```php
// Defensive: re-populate current_user from the freshly-inserted row.
// Auth::boot() already populated it from the old session's JOIN, but
// the user row might have changed since (rare in practice). The next
// request will re-read normally.
$GLOBALS['current_user'] = $user;
```

---

### IN-05: `Router::renderGenericError()` is a fallback that doesn't render through the layout

**File:** `src/Support/Router.php:162-175`

**Issue:** When the layout file exists, `renderGenericError()` does render through it (lines 165-170). When the layout doesn't exist (e.g., during test runs where `View/layout.php` was deleted or the file is unreachable), it falls back to inline `echo` of a minimal HTML page. This is OK as a safety net, but the inline HTML is not escaped through the same path as the layout's chrome (head, skip link, bottom nav). For a 404 page in dev, that's acceptable. In production, the layout always exists.

**Fix:** No action needed. The fallback is intentional defense-in-depth.

---

## Cross-File Analysis Notes

**AD-18 (bcrypt sole-writer) compliance verified.** `password_hash()` appears only in `src/Auth/Service/auth_service.php` lines 57 and 77. The `password_verify()` call is also restricted to that file (line 65). `tests/Integration/Phase02/Fixtures/Fixtures.php:82` calls `password_hash` directly — but that's in `tests/`, which is excluded from the phpcs sniff scope (per the docs/phase-2-substrate.md pitfall 2 and the unit test `test_no_password_hash_outside_auth_service` referenced in docs). Compliant.

**AD-16 failure envelope compliance.** Every Action return is `['ok' => bool, 'data' => mixed, 'error' => ['code', 'message', 'fields']]`. Verified across `auth_service::register/login/verifyEmail/consumePasswordReset/requestPasswordReset`, `user_service::updateProfile`. No drift.

**Cross-context import graph.** `Action -> Service -> Model -> PDO` arrow holds. Cross-context Service imports:
- `src/User/Service/user_service.php:34` imports `App\Auth\Service\auth_service` (for `sanitizeUser` + `tierFromPoints`) — per AD-1, cross-context work goes through Services, OK.
- `src/Auth/Service/auth_service.php:38` imports `App\Support\Auth as AuthGuard` — Support layer can be imported by anyone (it's the cross-cutting layer), OK.
- `src/User/Action/PublicProfileAction.php:30` imports `App\Review\Service\review_service` — User context's Action importing Review's Service for the public profile aggregation. The dependency is Action -> Review.Service (cross-context, but Review is a separate context from User). Per AD-1, this is acceptable because Action -> Service is the documented direction; the dependency is one-way (PublicProfileAction uses review_service but review_service doesn't import anything from User). One-way is fine.

**No circular dependencies detected.** The import graph is a strict DAG: Bootstrap -> FrontController -> Router -> Action -> Service -> Model -> Support -> PDO. Cross-context Services are imported only at the Service layer (per AD-1). No model imports another context's model.

**Error propagation verified.** `Auth::boot()` catches DB exceptions and degrades to `$GLOBALS['current_user'] = null` (guest). `RateLimit::hit()` catches cache_rate-table-missing and returns `['allowed' => true, ...]` (fail-open). `Csrf::verify()` emits 400 + JSON + exit on mismatch. `ResponseHeaders::boot()` silently returns when `headers_sent()`. The Router's admin/auth/rate-limit gates each handle failure modes independently. No uncaught exceptions in the request lifecycle.

**State mutation consistency.** `$GLOBALS['current_user']` is set in exactly three places: `Auth::boot()` (line 62), `auth_service::startSession()` (line 463), and tests (Fixtures). `_tt_view_vars` is set by `View::render` and `View::partial`. `_tt_path_params` is set by the Router's placeholder match. `_tt_content_view` is set by `View::render`. No conflicting writers.

---

_Reviewed: 2026-09-05T00:00:00Z_
_Reviewer: the agent (gsd-code-reviewer)_
_Depth: deep_
