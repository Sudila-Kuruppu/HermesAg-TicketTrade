# Phase 2: Student Authentication & Profiles - Context

**Gathered:** 2026-08-31
**Status:** Ready for planning

<domain>
## Phase Boundary

Verified NSBM students can register against a seeded allowlist, log in, manage their profile, and log out. The phase also lands the `Support` substrate (Auth, Csrf, RateLimit, Crypto, ResponseHeaders, the route guards, the session config) and the `migrate.php` migrations runner — the substrate every later phase assumes. Phase 2 is the first phase with stateful data; it ships migrations `001_initial` through `007_cache_rate`. Profile read view ships as a summary header (no tabs) with the public `/profile/{nickname}` lookup and the private `/profile` edit form. Per-session CSRF, per-IP rate limits, bcrypt cost ≥12, and the security response headers all land here so Phase 3+ can assume them.

</domain>

<decisions>
## Implementation Decisions

### Allowlist seeding & verification flow
- **D-01:** Migration 002 creates the `student_id_allowlist` table **empty**. Phase 9's seed script populates it with ~50 demo accounts. Phase 2 manual testing is gated on Phase 9 work. — **Reversibility:** reversible — populating the table is one SQL `INSERT` per dev, or a one-liner script.
- **D-02:** On register, the user is auto-logged-in and shown a flash toast containing the actual `GET /verify?token=…` URL as a clickable link. Clicking lands on a "Email verified! +50 points" success screen. The verify endpoint itself is real; only the "email" round-trip is simulated via the toast. — **Reversibility:** one-way — if real email is added later, the toast must be removed (5-line removal in the register action + 1 template change). The verify endpoint stays.
- **D-03:** Separate `email_verifications` table with `token_hash`, `expires_at`, `used_at NULL`. Token used at most once; the row's `used_at` is the audit trail. — **Reversibility:** reversible — drop the table, fold `token_hash` back into `users` (one migration + Service change).

### Login session shape
- **D-04:** Fixed 7-day session from last activity. No "Remember me" checkbox. Every authenticated request bumps `sessions.last_seen` and resets the 7-day window. A daily-active user is permanently logged in; a 7-day-idle user is logged out on their next visit. — **Reversibility:** reversible — adding "Remember me" later is a 2-field addition to the login form.
- **D-05:** DB-backed sessions in a `sessions` table keyed by `session_id` (PK) with `user_id`, `last_seen`, `ip`, `user_agent`. Logout = `DELETE FROM sessions WHERE session_id = ? AND user_id = ?`. Admin "force logout" (Phase 8) = `DELETE FROM sessions WHERE user_id = ?`. — **Reversibility:** reversible — switching back to PHP file-based sessions is a config change.
- **D-06:** `users.is_banned` boolean short-circuits the auth check before consulting the `sessions` table. Banning a user = immediate logout across all devices with no `DELETE` storm. — **Reversibility:** reversible — drop the column and the check (one migration + one line in the auth guard).
- **D-07:** Self-serve "forgot password" simulated email flow mirroring the register-verify pattern. `password_resets` table mirrors `email_verifications` shape (`token_hash`, `expires_at`, `used_at NULL`). Flash toast on submit contains the reset link. `/reset-password?token=…` form flips `users.password_hash` through `Auth/Service/auth_service.php`, marks the reset row used, and logs the user in. No admin involvement. — **Reversibility:** reversible — drop the `password_resets` table.

### Route guard & redirect
- **D-08:** Stateful pages (`/profile`, `/my-tickets`, `/sales`, `/my-listings`, `/settings`) use `?next=` bounce: unauthenticated user is redirected to `/login?next=/profile`, and on successful login they're redirected back. The per-route `auth` flag in `config/routes.php` is the toggle. — **Reversibility:** reversible.
- **D-09:** Browseable pages (`/board`) render as guest per FR-LND-007. Buy Now is replaced with a "Sign in to buy" CTA. No redirect, no modal — the user sees the board, the listings, and a clear path to register/login. — **Reversibility:** reversible.
- **D-10:** Non-admin access to `/admin/*` renders the same generic 404 any unknown route gets. AD-14's "don't reveal the resource exists" posture. — **Reversibility:** reversible — switching to a 403 page is one `Support\Error::not_found()` call change.
- **D-11:** Public route set (Phase 2): `GET /`, `GET /login`, `POST /login`, `GET /register`, `POST /register`, `GET /verify`, `GET /forgot-password`, `POST /forgot-password`, `GET /reset-password`, `POST /reset-password`, `GET /board`, `GET /profile/{nickname}`. Private route set: `GET /profile`, `POST /profile`, `POST /logout`, `GET /settings`, `POST /settings`, `GET /my-tickets`, `GET /my-listings`, `GET /sales`, `GET /purchases`. Admin route set: all `GET/POST /admin/*` (admin role required, 404 if not). `/board` is public-browse per D-09; the rest of the private routes use D-08.
- **D-12:** Failed login error copy is the locked "Email or password is incorrect." with no field-level highlight, per UX-DR-36. Rate-limit error is "Too many attempts. Try again in 5 minutes." per EXPERIENCE.md. Both are inline errors in the form, not flash toasts.

### Register form anti-enumeration
- **D-13:** Email format wrong → field-level: "Use your `@students.nsbm.ac.lk` email" (the format constraint is public, no enumeration concern). Email not in allowlist / student ID not in allowlist / email already registered → single combined message: "Email or student ID not recognized. Check both and try again." (anti-enumeration: don't tell the attacker which field is wrong). Nickname taken → "Nickname taken. Pick another." (nicknames are public, no enumeration concern). Password too short / missing field → field-level. — **Reversibility:** reversible.

### Profile edit & view scope
- **D-14:** Phase 2 ships only the profile summary header (avatar, full name, nickname, bio, points, rank badge, verified badge, join date, transaction counts [0 in Phase 2], average rating ["no reviews yet" in Phase 2]). No tab navigation. Tabs are introduced in Phase 3 (My Listings), Phase 4 (tickets/purchase/sales), Phase 5 (reviews). — **Reversibility:** reversible — tabs are purely additive.
- **D-15:** Nickname is locked at registration and never changes. Profile edit form does not show a nickname field. URLs are stable: `/profile/{nickname}`. — **Reversibility:** one-way — adding nickname edit later needs a `user_id`-keyed redirect route + a nickname-change form field + a one-time slug migration for existing accounts.
- **D-16:** WhatsApp is never shown on `/profile/{nickname}`. The public profile has no contact affordance. Contact path is the Phase 4 ticket WhatsApp share only. `/profile` shows an "Edit profile" button; `/profile/{nickname}` shows a "Report user" link (Phase 7 wires it, Phase 2 renders it disabled with "Coming soon" tooltip). — **Reversibility:** reversible.
- **D-17:** Twelve SVG files in `public/assets/img/avatars/avatar-{1..12}.svg`, served directly as public assets (no proxy, no per-user check). — **Reversibility:** reversible — move to a proxy or inline SVGs is a template change.
- **D-18:** `users.avatar_id TINYINT NOT NULL DEFAULT 1`. View renders `<img src="/assets/img/avatars/avatar-{$user->avatar_id}.svg">`. — **Reversibility:** reversible.
- **D-19:** On registration, `users.avatar_id` is randomly assigned from 1..12. User can change it in the profile edit form. — **Reversibility:** reversible — change to "default 1" is one line in the register Service.

### `Support\ResponseHeaders` timing
- **D-20:** Phase 2 ships the real `Support\ResponseHeaders::boot()` with the full AD-13 policy (`X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, CSP with `cdn.jsdelivr.net` allowlist). The Phase 1 stub is replaced. CSP string lives in `config/security_headers.php` for tweakability. Phase 9's success criterion becomes verification, not implementation. — **Reversibility:** reversible — the class is one method.
- **D-21:** CSP includes `'unsafe-inline'` for `script-src` only (to allow Phase 1's FOUC-guard inline script per D-05 of Phase 1's CONTEXT.md). Same policy in dev and prod. `cookie_secure=1` is gated on `APP_ENV === 'production'`. Future hardening to nonces or an external FOUC-guard file is deferred. — **Reversibility:** reversible — convert to nonce/hashes later is a config + layout template change.

### Migrations runner & first migrations
- **D-22:** Phase 2 ships `migrate.php` (CLI) and the first migration files. The `POST /admin/cron/migrate` Action endpoint lands in Phase 9 alongside the admin surface (per AD-6's "also runnable from `POST /admin/cron/migrate` behind admin re-auth"). — **Reversibility:** reversible.
- **D-23:** Migration numbering: `001_initial.sql` (placeholder, demonstrates the runner works on a fresh checkout), then Phase 2's migrations as `002_users_auth.sql` through `007_cache_rate.sql`. Future phases continue from `008_*`. The ARCHITECTURE-SPINE.md's 12-migration sketch is preserved. — **Reversibility:** reversible — renumbering before production is cheap.
- **D-24:** Each migration file runs in a single explicit transaction. DDL statements use `IF NOT EXISTS` / `IF EXISTS` for natural idempotency on retry. The `.applied` set update is in the same transaction as the schema change. (MySQL 8 DDL is not transactional, but the `IF NOT EXISTS` discipline makes the half-applied edge case safe.) — **Reversibility:** reversible.
- **D-25:** File-based `.applied` at `migrations/.applied` — plain text, one filename per line. Written atomically (temp file + rename). NOT in git (each dev's `.applied` is local). No checksum in Phase 2. Phase 9 can add drift detection if needed. — **Reversibility:** reversible — switching to a DB table is one new migration that back-fills and the runner consults both.
- **D-26:** `.sql` files with MySQL 8 syntax. No PHP wrapper. utf8mb4, InnoDB, snake_case identifiers, `BIGINT UNSIGNED AUTO_INCREMENT` PKs, `DATETIME` columns — per the ARCHITECTURE-SPINE.md conventions table. — **Reversibility:** reversible — PHP wrappers can be added later for migrations that need runtime logic.
- **D-27:** Semicolons separate statements. The runner splits on `;` (after stripping `--` and `/* */` comments) and executes one statement at a time inside the transaction. Per-statement error reporting. No `DELIMITER` blocks (no stored procedures in Phase 2). — **Reversibility:** reversible.
- **D-28:** Header comment block at the top of every migration file: purpose, AD binds, requirement traces, depends-on list, author, date. No `down.sql` (forward-only per AD-6). Inline `--` comments on tricky lines. — **Reversibility:** reversible.

### the agent's Discretion

The following items were not explicitly decided but follow from locked requirements or are routine implementation choices:
- **Login form layout** — center-aligned card, max-width 400px, email above password, "Register" left and "Forgot password?" right below the form, all per EXPERIENCE.md's Login state pattern.
- **Rate-limit response shape for non-login state-changing endpoints** (register, password reset request, profile edit) — same inline error pattern as login. The `Support\RateLimit` helper centralizes the check.
- **`/settings` page scope** — theme toggle (light/dark/system, per Phase 1 D-07) + logout button. Notification preferences are out of scope for Phase 2; EXPERIENCE.md's "notification preferences (toast only)" is interpreted as "toasts are the only notification channel" not "user has a preference for toasts." — **Reversibility:** reversible.
- **The `+50` points stub for email verification** — the verify Action calls a `Points/Service/points_service.php` stub that writes a row to `points_log` (with `event_uuid` UUID v7) and updates `users.points`. The stub's signature matches the Phase 6 real implementation. Phase 6's full points engine reads the existing `points_log` rows and treats them as legitimate. — **Reversibility:** reversible.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing Phase 2.**

### Architecture and ADs
- `ARCHITECTURE-SPINE.md` — AD-1 (layered modular monolith), AD-2 (bounded contexts), AD-3 (webroot at `public/`, `src/` outside), AD-4 (hand-rolled route list), AD-5 (PDO with prepared statements, no ORM), AD-6 (versioned SQL migrations, idempotent runner), AD-13 (auth/session/CSRF/rate-limit shape — the source of truth for Phase 2's `Support` substrate), AD-14 (file storage outside webroot), AD-16 (failure envelope), AD-18 (bcrypt-only at cost ≥12, sole owner `Auth/Service/auth_service.php`, phpcs `Custom\Sniffs\NoRawHash` sniff), AD-19 (admin re-auth — Phase 2 lands the `users.is_banned` column as a related primitive). The Conventions table at the bottom of the file is binding for SQL/identifier naming.

### Phase 1 contracts carried forward
- `.planning/phases/01-ux-foundation-design-system/01-CONTEXT.md` — D-04/D-05/D-06 (theme persistence in localStorage with synchronous FOUC guard), D-09 (mockups link the same `tickettrade.css` bundle), D-12/D-13 (toast container with `data-flash-toast` div pattern — Phase 2 Actions emit server-set flash messages through this hook), D-20/D-21 (the now-replaced `Support\ResponseHeaders` stub).

### Requirements traceability
- `.planning/REQUIREMENTS.md` — AUTH-01..06, PROF-01..04, SEC-01..08, OPS-02, OPS-05, OPS-07. Phase 2 implements AUTH-01..06 + PROF-01..04. Phase 2 also lands the `Support` substrate (SEC-01, SEC-02, SEC-05, SEC-07, SEC-08) but does NOT implement the per-feature rate limits yet (those are SEC-06, wired into the relevant Actions in their own phases — login rate limit lands in Phase 2 because login is Phase 2; purchase/redemption/listings/points limits land in their respective phases).

### Roadmap and project context
- `.planning/ROADMAP.md` — Phase 2 entry: 3 plans, MVP mode. Plan 02-01 covers the `Support` substrate + route guards + session config. Plan 02-02 covers register/login flows + profile edit. Plan 02-03 covers the profile read view.
- `.planning/PROJECT.md` — Tech stack (PHP 8+, MySQL 8+, Bootstrap 5, `ramsey/uuid` only), constraints (PSR-12, security baseline, performance budget), key decisions (6-tier rank system, single-tenant cohort model, simulated payments, velocity cap thresholds).
- `AGENTS.md` — Operating manual (team structure, how to read this codebase, command conventions).

### Visual identity and experience
- `DESIGN.md` — Brand & style, color palette, typography (Inter display, system-ui body, mono-code), elevation, shapes, components. The contrast ledger is the source of truth for every token value. Avatar illustrations land in `public/assets/img/avatars/` (12 SVGs) — file naming `avatar-{1..12}.svg` matches the D-17 contract.
- `EXPERIENCE.md` — Information architecture, voice and tone, component patterns, accessibility floor, state patterns per surface (empty, cold, error, focused, offline), interaction primitives, banned interactions. The Login state pattern (cold load, wrong credentials, rate-limited) and the Register state pattern (cold load, email not @students.nsbm, student ID not in allowlist, nickname taken) are the source of truth for D-12 and D-13.

### Mockup references
- `public/mockups/board-mobile.html` — Visual reference for the `/board` guest-browse surface (Phase 2 renders a stub; Phase 3 fills in).
- `public/mockups/my-tickets.html` — Visual reference for the `/my-tickets` surface (Phase 2 stub).
- `public/mockups/admin-dashboard.html` — Visual reference for the `/admin` surface (Phase 2 stub).

### Existing code
- `config/bootstrap.php` — Already loads composer autoload, sets `Asia/Colombo` timezone, declares `Support\ResponseHeaders` as an eval'd no-op stub. Phase 2 replaces the eval stub with the real class via PSR-4 autoload (no `eval`).
- `config/routes.php` — Currently `return []`. Phase 2 populates the student route map per D-11.
- `config/contexts.php` — Already lists the bounded contexts (`Auth`, `Listing`, `Ticket`, `Points`, `User`, `Category`, `Report`, `Admin`, `Cron`). Phase 2 uses `Auth` and `User` (the user is a context in D-2, even though Phase 2's primary focus is `Auth`).
- `src/Support/Router.php` — Currently a stub that renders the Phase 1 landing page when the route map is empty. Phase 2 extends it to do real dispatch (route map lookup → handler invoke → 404 via D-10).
- `src/Support/View/landing.php` — The Phase 1 stub landing page. Phase 2 replaces the real landing page in Phase 3.
- `public/index.php`, `public/admin/index.php`, `public/router.php` — Front controllers and dev-server router. Phase 2 does not modify these; the router and controllers become the integration point for the new `Support\Auth` guard.
- `public/assets/css/tickettrade.css` — The token-driven CSS bundle. Phase 2 uses it as-is.
- `public/assets/js/tickettrade.js` — The Phase 1 component bundle. Phase 2 uses the toast component (`window.TicketTrade.toast.show(...)`) for server-set flash messages; no new JS components.
- `composer.json` — PSR-4 autoload `App\` → `src/`. Phase 2 adds no new Composer dependencies; `ramsey/uuid` is already there for the Phase 2 +50 points stub.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **Design token system** (`public/assets/css/tickettrade.{tokens,bootstrap-overrides,components}.css`) — Phase 2 uses these as-is. No new CSS files. The `surface-container-high`, `color-primary`, `shape-md`, etc. are all defined.
- **Toast container** (`data-component="toast"`) — Server-set flash messages via the `data-flash-toast` div pattern (Phase 1 D-12/D-13). Phase 2's register/login/logout/profile-edit Actions emit flash messages through this hook. The `window.TicketTrade.toast.show(message, type)` API is the programmatic entry point.
- **Bottom nav** (`data-component="bottom-nav"`) — Phase 1 ships the 5-item nav. Phase 2's `/profile` and `/settings` are accessible from it; `/my-tickets`, `/my-listings`, `/sales` are stubs (rendered in the nav but link to a "coming soon" page).
- **Skeleton shimmer** (`data-skeleton`) — Phase 2 uses this on `/profile` cold load (the profile summary header).
- **Theme controller** (`data-component="theme-controller"`) — Already wired to localStorage. Phase 2's `/settings` page uses the same API (`window.TicketTrade.setTheme(mode)`).
- **Bootstrap 5 CDN** — Already loaded in the layout. Phase 2 uses stock Bootstrap form controls (input, form-label, invalid-feedback, btn, btn-primary, alert-danger for inline errors).
- **Bootstrap modal** — Phase 2's verify-email success screen uses a centered modal (`max-width: 600px`) for the "Email verified! +50 points" celebration. Bootstrap's modal JS handles the open/close.
- **`Support\Router` stub** — Phase 2 extends it to do real dispatch. The existing `renderStubLanding()` and `loadRoutes()` are kept; the empty-route-map behavior is replaced.

### Established Patterns
- **Layered Modular Monolith** (AD-1) — Bootstrap → FrontController → Action → Service → Model → PDO. Phase 2's `Auth/Action/*_action.php` files are thin: validate input, call Service, render View. All state mutation goes through `Auth/Service/auth_service.php` (the bcrypt sole-writer, per AD-18) and the new `User/Service/user_service.php` (for non-password fields).
- **Failure envelope** (AD-16) — Every Action returns `['ok' => bool, 'data' => mixed, 'error' => ['code' => string, 'message' => string, 'fields' => array|null]]`. The View renders `error.message` only via `htmlspecialchars()`. UI switches on stable error codes (`E_AUTH_INVALID`, `E_RATE_LIMIT`, `E_CSRF`, `E_VALIDATION`), not on message text.
- **Tokens-as-contracts** (from Phase 1) — The contrast ledger in DESIGN.md is load-bearing. Every color/spacing/typography/elevation token in `tickettrade.tokens.css` traces to a row in the ledger. Phase 2 inherits this; no new token additions.
- **Server-set flash messages** — The `data-flash-toast="…"` div pattern means every page load can carry a server-set message. Phase 2's register action sets a flash message ("Account created. Verification link: …") before redirecting to `/board`.

### Integration Points
- **`config/bootstrap.php`** — Phase 2 adds: (1) `session_set_cookie_params()` and `session_start()` with AD-13's strict config, (2) require `Support\Auth` and call its `boot()` to start the auth guard (which reads/validates the session, checks `users.is_banned`, and sets `$GLOBALS['current_user']`), (3) require `Support\Csrf` and call its `verify()` for state-changing requests, (4) require `Support\ResponseHeaders` and call its `boot()` (the real implementation, replacing the eval stub).
- **`public/index.php` and `public/admin/index.php`** — Phase 2 does not modify these. The `Support\Auth` guard runs at bootstrap, before the front controller's `Router::dispatch()` call.
- **`public/router.php` (dev server)** — No changes. The dev server's static asset handling is already correct.
- **Layout template (new in Phase 2)** — `src/Support/View/layout.php` is the layout template that wraps every page. It includes the `<head>` (FOUC-guard inline script, Bootstrap CDN, `tickettrade.css`, `tickettrade.js`), the bottom nav, the toast container, the `data-flash-toast` div (when a flash message is set), and the page content. The layout is required by every View, not by individual Actions.

</code_context>

<specifics>
## Specific Ideas

- The `+50` points stub for email verification: the verify Action calls `Points/Service/points_service.php::awardVerificationBonus($user_id)`. The stub writes a `points_log` row with `event_uuid` UUID v7 (via `ramsey/uuid` which is already a Composer dep), `reference_type='email_verification'`, `delta=50`, and updates `users.points += 50`, `users.tier` recomputed from `config/ranks.php` (a stub `config/ranks.php` ships in Phase 2 with the 6-tier ladder, even though the points engine doesn't fully ship until Phase 6). The stub's signature matches what Phase 6's real `Points/Service/points_service.php` will use. The stub is the ONLY place outside of Phase 6 that writes to `points_log` and updates `users.points` — the AD-10 "sole writer" rule is preserved by the stub.
- The `email_verifications` and `password_resets` tables are nearly identical (`token_hash`, `expires_at`, `used_at`, FK to `users`, created_at). Phase 2 ships both; the schema is small enough that DRY-ing them into a `tokens` table is premature.
- The `student_id_allowlist` table has just three columns: `student_id` (the NSBM student ID, e.g., `NSBM/2023/001`), `email` (the `@students.nsbm.ac.lk` address), and `created_at`. The PRIMARY KEY is `student_id`. Phase 9's seed script populates ~50 rows. Phase 2 ships the empty table.
- The `cache_rate` table (rate-limit state) has columns: `rate_key` (PK, e.g., `login:ip:192.168.1.1:2026-09-01-10:30`), `count`, `window_start`, `expires_at`. The `Support\RateLimit` helper does `INSERT … ON DUPLICATE KEY UPDATE count = count + 1, expires_at = …` for atomic check-and-increment, then SELECTs the row to compare against the limit. The `expires_at` is the TTL; a periodic cleanup deletes expired rows (Phase 9 cron, or a lazy `DELETE` on next access).
- The "Email verified! +50 points" success screen is a centered modal with a checkmark icon, the user's nickname, and a "Continue to board" CTA. The modal closes on click and navigates to `/board`.
- The forgot-password "enter your email" form has only the email field (no student ID — student ID is for registration, not password reset; the email is the unique identifier post-registration). On submit, the form always shows the same toast: "If that email is registered, a reset link is in your inbox." (This is the same anti-enumeration principle as the register form: don't leak whether the email exists.) The flash toast on the next page contains the actual reset link, mirroring D-02.
- The `/settings` page is a thin form: theme toggle (three radio buttons: Light / Dark / System), a divider, a "Log out" button. The logout button is destructive-styled (btn-outline-danger) and requires a confirm modal before submitting the POST.
- The `Support\Auth` boot() reads the session, looks up `sessions` by `session_id`, validates `users.is_banned = FALSE`, and sets `$GLOBALS['current_user']` to the user row (or NULL for guests). It also calls `sessions.last_seen = NOW()` on every authenticated request (the 7-day refresh-on-activity from D-04).
- The `Support\Csrf` generates a token on first session start (`bin2hex(random_bytes(32))`) and stores it in `$_SESSION['csrf_token']`. The verify call is `hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')`. The token is per-session (not per-request) so the same form rendered on a slow connection doesn't 419.
- The `Support\ResponseHeaders` reads the CSP from `config/security_headers.php`, sets the four headers, and is a no-op for any header that's already set (so a later middleware can override per-route if needed, though AD-13 says no Action may override).
- The `migrate.php` script is a single file, ~80 LOC, with no external dependencies. It reads `config/db.php` for the DSN, opens a PDO connection, scans `migrations/`, reads `.applied`, applies missing files in lexical order inside a transaction, and updates `.applied`. Errors print a stack trace and exit 1.
- The 12 avatar SVGs are simple geometric shapes (circles, squares, triangles) in the brand palette — no characters, no faces, no copyrighted material. They're drawn at 200x200 viewBox so they scale cleanly to any size. Phase 2 ships them as a pre-step to the `Support\Auth` work; no code depends on them, just the migration and the asset files.

</specifics>

<deferred>
## Deferred Ideas

- **Cohort isolation (AD-20)** — The MVP is single-cohort. At S2 retro, the team decides whether to add `cohort_id` in migration `013` with belt-and-braces across every Model. This is a known gate; Phase 2's schema is single-cohort and the gate is documented in `PROJECT.md` Blockers.
- **Real SSO/LMS integration** — The simulated `@students.nsbm.ac.lk` email-domain check is the v1. Real LMS integration is v2 (`SCALE-01`).
- **Real email backend** — The flash-toast-with-link pattern in D-02 and D-07 is the simulation. A real email backend (e.g., Postmark, SES) would replace the toast with an actual email. The verify/reset endpoints stay; only the delivery mechanism changes.
- **"Remember me" checkbox** — D-04 fixed 7-day refresh-on-activity; "Remember me" extending to 30 days is a 2-field addition when needed.
- **Nickname edit** — D-15 locks nickname at registration. Adding nickname edit later needs a redirect table or a `user_id` fallback URL.
- **Public WhatsApp disclosure** — D-16 keeps WhatsApp private. A `users.show_whatsapp_public` opt-in toggle is a one-column + one-template change when needed.
- **CSP nonce hardening** — D-21 includes `'unsafe-inline'` for `script-src` to allow the Phase 1 FOUC-guard inline script. Converting to nonces (or moving the FOUC-guard to an external file) is a Phase 9+ hardening pass.
- **Drift detection for migrations** — D-25 ships a plain-text `.applied` set. A `migrations_checksums` table (or a JSON-with-checksum `.applied`) is a Phase 9+ addition.
- **Admin re-auth primitive (AD-19)** — Phase 2 lands the `users.is_banned` column (D-06) as a related primitive. The full `admin_reauth` table + 300s sliding window + 5/min/IP rate limit + re-auth modal is a Phase 8 deliverable.

## Reviewed Todos (not folded)

No todos were folded from `cross_reference_todos` (the step returned `todo_count: 0`).

</deferred>

---

*Phase: 2-Student Authentication & Profiles*
*Context gathered: 2026-08-31*
