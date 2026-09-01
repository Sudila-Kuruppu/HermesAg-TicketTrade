---
phase: "02"
slug: "student-authentication-profiles"
status: draft
nyquist_compliant: false
wave_0_complete: false
created: "2026-08-31"
---

# Phase 2 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.
> Derived from `.planning/phases/02-student-authentication-profiles/02-RESEARCH.md` (## Validation Architecture).

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11.5.x |
| **Config file** | `phpunit.xml` (exists from Phase 1) |
| **Quick run command** | `vendor/bin/phpunit --testsuite=phase-2 --stop-on-failure` |
| **Full suite command** | `vendor/bin/phpunit` |
| **Test bootstrap** | `vendor/autoload.php` (already configured) |
| **Estimated runtime** | ~30s for the Phase 2 suite (MySQL fixture boot + ~40 tests) |

---

## Sampling Rate

- **After every task commit:** Run `vendor/bin/phpunit --testsuite=phase-2 --stop-on-failure`
- **After every plan wave:** Run `vendor/bin/phpunit --testsuite=phase-2` (full Phase 2 suite)
- **Before `/gsd-verify-work`:** Full suite (`vendor/bin/phpunit`) must be green
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 02-01-01 | 02-01 | 1 | AUTH-04, AUTH-05, SEC-01 | T-2-01, T-2-02 | bcrypt cost 12, prepared statements only | Unit | `vendor/bin/phpunit --filter=PasswordHashTest` | Wave 0 | pending |
| 02-01-02 | 02-01 | 1 | AUTH-05, SEC-01 | T-2-03, T-2-04 | `users.is_banned` short-circuits auth, sessions.last_seen update | Unit | `vendor/bin/phpunit --filter=AuthGuardTest` | Wave 0 | pending |
| 02-01-03 | 02-01 | 1 | AUTH-06, SEC-02, SEC-07 | T-2-05, T-2-06 | CSRF hash_equals, rate-limit 5/5min/IP, session use_strict_mode=1 | Unit | `vendor/bin/phpunit --filter=RateLimitTest,CsrfTest,SessionConfigTest` | Wave 0 | pending |
| 02-01-04 | 02-01 | 1 | OPS-02, SEC-08 | T-2-07 | migrate.php applies migrations idempotently, ResponseHeaders set | Integration | `vendor/bin/phpunit --filter=MigrateRunnerTest,ResponseHeadersTest` | Wave 0 | pending |
| 02-02-01 | 02-02 | 2 | AUTH-01 | T-2-08 | Register with allowlist match, no plaintext password | Integration | `vendor/bin/phpunit --filter=RegisterFlowTest` | Wave 0 | pending |
| 02-02-02 | 02-02 | 2 | AUTH-01, SEC-02 | T-2-09 | CSRF on register POST, error code E_VALIDATION on format | Integration | `vendor/bin/phpunit --filter=RegisterCsrfTest` | Wave 0 | pending |
| 02-02-03 | 02-02 | 2 | AUTH-02, AUTH-04 | T-2-10 | Login bcrypt verify, single combined error | Integration | `vendor/bin/phpunit --filter=LoginFlowTest` | Wave 0 | pending |
| 02-02-04 | 02-02 | 2 | AUTH-03, AUTH-05 | T-2-11 | Logout deletes session row, private route bounce to login | Integration | `vendor/bin/phpunit --filter=LogoutTest,RouteGuardTest` | Wave 0 | pending |
| 02-02-05 | 02-02 | 2 | PROF-01, SEC-05 | T-2-12 | Profile edit WhatsApp regex, avatar_id clamped 1..12 | Integration | `vendor/bin/phpunit --filter=ProfileEditTest` | Wave 0 | pending |
| 02-02-06 | 02-02 | 2 | AUTH-01, SEC-02 | T-2-13 | Verify endpoint consumes token once, +50 points stub | Integration | `vendor/bin/phpunit --filter=VerifyTokenTest` | Wave 0 | pending |
| 02-02-07 | 02-02 | 2 | AUTH-01, SEC-02 | T-2-14 | Forgot-password/reset endpoint, token_hash + used_at | Integration | `vendor/bin/phpunit --filter=PasswordResetTest` | Wave 0 | pending |
| 02-03-01 | 02-03 | 3 | PROF-02 | T-2-15 | Public profile shows rank badge, transaction counts = 0 | Integration | `vendor/bin/phpunit --filter=PublicProfileTest` | Wave 0 | pending |
| 02-03-02 | 02-03 | 3 | PROF-02, PROF-04 | T-2-16 | Profile verified badge, 12-avatar grid, no WhatsApp public | Integration | `vendor/bin/phpunit --filter=PublicProfileRenderTest` | Wave 0 | pending |

---

## Wave 0 Requirements

- [ ] `tests/Integration/02/RegisterFlowTest.php` — stubs for AUTH-01
- [ ] `tests/Integration/02/LoginFlowTest.php` — stubs for AUTH-02, AUTH-04
- [ ] `tests/Integration/02/LogoutTest.php` — stubs for AUTH-03
- [ ] `tests/Integration/02/RouteGuardTest.php` — stubs for AUTH-05
- [ ] `tests/Integration/02/RateLimitTest.php` — stubs for AUTH-06
- [ ] `tests/Integration/02/ProfileEditTest.php` — stubs for PROF-01
- [ ] `tests/Integration/02/PublicProfileTest.php` — stubs for PROF-02
- [ ] `tests/Integration/02/PasswordResetTest.php` — stubs for forgot-password flow
- [ ] `tests/Integration/02/VerifyTokenTest.php` — stubs for email verification
- [ ] `tests/Unit/02/PasswordHashTest.php` — bcrypt cost 12 (AUTH-04)
- [ ] `tests/Unit/02/CsrfTest.php` — `hash_equals()` validation (SEC-02)
- [ ] `tests/Unit/02/SessionConfigTest.php` — session.use_strict_mode=1, sid_length=48 (AD-13)
- [ ] `tests/Integration/02/MigrateRunnerTest.php` — migrations runner idempotency
- [ ] `tests/Integration/02/ResponseHeadersTest.php` — security headers at boot
- [ ] `phpunit.xml` — add `phase-2` testsuite stanza
- [ ] `tests/Integration/02/Fixtures/` — MySQL test fixture loader (or SQLite fallback for dev machines)

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Login form anti-enumeration timing | AUTH-06 | Timing analysis needs HTTP client | Use `curl -w '%{time_total}'` against `/login` with valid and invalid email; assert delta is small |
| Email verification flash-toast | D-02 | Visual UX | Register a user, observe toast contains the `GET /verify?token=...` URL as a clickable link |
| 12-illustration avatar picker | PROF-01 | Visual UX | Open `/profile`, confirm 4x3 desktop or 3x4 mobile grid, 2px primary ring on selected |
| CSP header in browser dev tools | SEC-08 | Visual | `curl -I http://localhost:8000/`, confirm `Content-Security-Policy: default-src 'self'; ...` |
| Login rate-limit UX copy | AUTH-06 | Visual UX | Submit 6 wrong passwords, confirm "Too many attempts. Try again in 5 minutes." |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending

---

*Seeded by /gsd-plan-phase 2 from `02-RESEARCH.md` ## Validation Architecture.*
