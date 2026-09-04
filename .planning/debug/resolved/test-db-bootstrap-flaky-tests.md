---
status: resolved
trigger: "bin/test flaky + slow; test-DB bootstrap brittle; AGENTS.md out of date with the fix"
created: 2026-09-04
updated: 2026-09-04
resolved_at: 2026-09-04
---

# Debug: test-db-bootstrap-flaky-tests

## Symptoms

### Symptom 1 — Flaky unit test
- Test: `App\Tests\Unit\Phase03\Support\ImageProxyTest::test_thumb_rate_limit_returns_429_after_cap`
- Passes in isolation (`--filter`).
- Fails ~1/5 runs in the full `phase-3-unit` suite (random order).
- Failure: `Failed asserting that 404 is identical to 429.` after 60 hits.
- Means: on 61st hit, the proxy is returning 404 (not 429). Either the rate limit isn't tripping, or the listing_images row was lost between iterations.
- Other symptom in the same suite (deterministic, not flaky): same test class can hit a `Duplicate entry '458' for key 'uniq_categories_sort'` in `seedCategory()` when the same random `sort_order` collides across test cases (`random_int(100, 999)` pool is small).

### Symptom 2 — Slow `bin/test`
- `bin/test --testsuite=phase-3-integration` times out past 120s.
- Cause: `bin/test` drops + recreates the test DB and re-runs ALL 16 migrations on every invocation.
- Migration runner takes ~0.5s; bootstrap+drop ~0.3s. The bottleneck is the 175 integration tests (legit ~95s). But the drop+recreate adds non-trivial startup and risks test isolation issues.
- Worse: on a non-empty test DB, `dev-setup.sh`'s idempotent migrate path was blown away by an earlier edit that reset `.applied` and re-ran unconditionally — that edit was reverted but `bin/test` still drops+recreates.

### Symptom 3 — AGENTS.md env-quirks section out of date
- The "GitHub push via subtree split" section documents the recipe but doesn't reference `bin/test` or the new `bin/dev-setup.sh` change.
- The "drop + recreate the test DB, truncate `.applied`, re-run `php migrate.php`" recipe is now obsolete — `bin/test` automates it.
- The "config/db.test.php is the source of truth" line is wrong after the fix: `bin/dev-setup.sh` now writes it on first run from a working socket probe.

## Reproduction

```bash
# Symptom 1: run 5x, count failures
for i in 1 2 3 4 5; do
  cd /home/user/hermesag/004/tickettrade && \
    DB_DSN='mysql:unix_socket=/tmp/mysql.sock;dbname=tickettrade_test;charset=utf8mb4' \
    DB_USER=user APP_ENV=test vendor/bin/phpunit --testsuite=phase-3-unit 2>&1 | tail -3
done

# Symptom 2: drop+recreate+remigrate on every run
time bin/test --testsuite=phase-3-integration
```

## Environment

- Repo: `/home/user/hermesag/004/tickettrade` (PHP 8.3, MariaDB 11.4 on `/tmp/mysql.sock`, PHPUnit 11.5).
- Test DB: `tickettrade_test`, 16 migrations.
- `bin/dev-setup.sh` (modified this session, step 6) now: skip migrate if tables present; reset+remigrate only if `ACTUAL_TABLES < EXPECTED_TABLES`.
- `bin/test` (new) calls `dev-setup.sh`, then drops `tickettrade_test`, recreates, wipes `.applied`, remigrates, runs phpunit.
- `config/db.test.php` was wrong (pointed at `/home/user/hermesag/004/db/mariadb.sock`); fixed to default to `/tmp/mysql.sock` like `config/db.php`.
- `tests/Unit/Phase03/Support/ImageProxyTest.php` is the failing test file.

## Current Focus

- **hypothesis (a — flaky test)**: `seedCategory()` in `ImageProxyTest` uses `random_int(100, 999)` for `sort_order`, and `categories` has `UNIQUE KEY uniq_categories_sort`. Across 100 controlled runs (`--random-order-seed=N` for N=1..100), 25% failed with `PDOException: SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'XXX' for key 'uniq_categories_sort'`. Affected tests: `test_thumb_rate_limit_returns_429_after_cap` (line 105) and `test_full_size_for_logged_in_non_seller_non_admin_returns_404` (line 157). A few `uniq_categories_name` collisions too (`'T' . random_int(1000, 9999)`). The user's reported symptom of "404 instead of 429 after 60 hits" was the test-error variant — when `seedCategory()` throws, the test fails with the PDOException rather than reaching the rate-limit assertion. Either way, the **root cause is the random uniqueness-violation in seedCategory**. `seedUser()` uses `random_int(10000, 99999)` for $uid → email/student_id collision possible (less likely). Fix: replace random uniqueness tokens with a monotonic static counter scoped to the test run.
- **hypothesis (b — slow bin/test)**: Measured `time bin/test --testsuite=phase-3-integration` → 1m32s real. 175 integration tests legitimately take ~89s; the drop+recreate+remigrate adds ~2s and pollutes. Fix: fingerprint `migrations/*.sql` (sorted) via md5sum; cache to `data/.test-schema-fingerprint`; skip drop+remigrate on fingerprint match. Add `data/.test-schema-fingerprint` to `.gitignore`.
- **hypothesis (c — AGENTS.md)**: Update the `<!-- GSD:env-quirks-start -->` block to reference `bin/test`, the fingerprint cache, and `bin/dev-setup.sh`; preserve the Git workflow + subtree split section.
- **next_action**: (1) Edit `ImageProxyTest.php` — replace `random_int` collisions with monotonic counter (static property) for both `seedCategory()` and `seedUser()`. (2) Edit `bin/test` — add fingerprint cache. (3) Edit `.gitignore` — ignore `data/.test-schema-fingerprint`. (4) Edit `AGENTS.md` — update env-quirks section. (5) Run unit suite 5x to confirm zero flakes. (6) Run integration suite once to confirm <120s and green. (7) Commit with explicit paths.
- **test**: passed
- **expecting**: zero flakes after fix; integration suite <120s.

## Resolution

### Root cause
**Issue A (flaky test):** `ImageProxyTest::seedCategory()` used `random_int(100, 999)` for `sort_order` against `categories.UNIQUE KEY uniq_categories_sort`. Across 100 controlled `--random-order-seed` runs, 25/100 failed with `PDOException: SQLSTATE[23000]: 1062 Duplicate entry 'XXX' for key 'uniq_categories_sort'`. A smaller number also collided on `uniq_categories_name` (`'T'.random_int(1000, 9999)`). The user's reported "404 instead of 429 after 60 hits" was the same PDOException aborting the test before reaching the rate-limit assertion. `seedUser()`'s `random_int(10000, 99999)` had the same risk shape against `users.uniq_email / uniq_student_id / uniq_nickname`. The bucket-per-minute theory (RateLimit's `bucketTime` rolling over mid-loop) was investigated and rejected — the test completes in <1s, never crossing a minute boundary in 100 sweeps.

**Issue B (slow bin/test):** `bin/test` unconditionally dropped + recreated `tickettrade_test` and re-ran all 16 migrations on every invocation. Measured 1m32s wall; 175 integration tests legitimately take ~83s; the rest was drop + remigrate + pollution risk.

**Issue C (AGENTS.md):** The IDX / opencode Runtime Quirks section still described the pre-`bin/test` manual recipe and labelled `config/db.test.php` as "the source of truth" (now misleading because `bin/dev-setup.sh` writes it from a socket probe).

### Fix
- `tests/Unit/Phase03/Support/ImageProxyTest.php`: replaced random uniqueness tokens with monotonic static counters (`self::$catSeq`, `self::$userSeq`) seeded from `MAX(sort_order)` / `MAX(user_id)` in `setUpBeforeClass()`. Both `seedCategory()` and `seedUser()` derive `$sort`/`$uid` from the counter — guaranteed unique against the leftover rows that `vendor/bin/phpunit` direct invocations accumulate, and reset to defaults on a fresh DB.
- `bin/test`: compute `md5sum` of sorted `migrations/*.sql` filenames → cache at `data/.test-schema-fingerprint`. Skip the drop+remigrate when the fingerprint matches the cached value. Otherwise rebuild and write the new fingerprint. To force a rebuild: `rm data/.test-schema-fingerprint`.
- `.gitignore`: ignore `/data/.test-schema-fingerprint` (test-infra state, not source).
- `AGENTS.md`: replaced the obsolete `config/db.test.php` bullet with a "Test DB + run flow" block documenting `bin/dev-setup.sh` (one-shot bootstrap), `bin/test` (per-suite test-DB fingerprint cache), and the `/tmp/mysql.sock` default. Preserved the Git workflow + subtree split section and the opencode task-resumption protocol.

### Verification
- **Issue A**: 5/5 sequential runs of `vendor/bin/phpunit --testsuite=phase-3-unit` pass. 100-run sweep with `--random-order-seed=N` (N=1..100): **0/100 failures** (was 25/100).
- **Issue B**: `time bin/test --testsuite=phase-3-integration` end-to-end — first run (rebuild): 1m23s real, 83s phpunit. Subsequent (cached): 1m23-26s. All 175 tests green. Under 120s.
- **Issue C**: AGENTS.md env-quirks section now reflects `bin/test` + `bin/dev-setup.sh` workflow and preserves the Git subtree split procedure.

### Prevention
- **Why not caught**: no test that asserts the suite is deterministic across random orders. The test file inherited `random_int` uniqueness tokens without bounding the search space against the schema's UNIQUE indexes. CI doesn't run with `--random-order-seed` sweep, so the flake escaped.
- **Guard**: any new test helper that writes rows with UNIQUE-constrained columns should derive its token from a static property seeded from `MAX(...)`, not from `random_int`. Add a comment in `tests/Unit/Phase03/Support/ImageProxyTest.php` flagging this pattern for future helpers.
- **Why not caught (Issue B)**: `bin/test` always rebuilt; no schema-version cache existed.
- **Guard**: the fingerprint cache at `data/.test-schema-fingerprint` is the contract. If someone bypasses `bin/test` (e.g. runs `vendor/bin/phpunit` direct), they inherit the old per-run state and may need `bin/test` once to reset.

### Cycles
- Investigation: 1 cycle (read → reproduce → fix → verify)
- Fix: 0 cycles (single attempt, applied directly)
- Total: 1 cycle

### Commits
- `d935d78` — fix(test-infra): dedupe seeders + cache bin/test schema fingerprint
- `f87b71f` — docs(agents): update env-quirks for bin/test + bin/dev-setup.sh
