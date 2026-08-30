# Walking Skeleton — TicketTrade (NSBM Marketplace)

**Phase:** 1
**Generated:** 2026-08-30
**Mode:** mvp / walking_skeleton

## Capability Proven End-to-End

A visitor opens `/mockups/board-mobile.html` in a browser, the FOUC-guard inline script reads `localStorage.tickettrade.theme` and applies the dark student default, the design-token CSS bundle renders the corkboard board with paper-card listings (deterministic +/-2 degrees rotation, pushpin graphic, AA-pass contrast), the toast container is mounted and announces `theme loaded` after a programmatic `TicketTrade.toast.show('Theme loaded.', 'info')` call, and a tab into the first listing card shows a single 2px primary focus ring. The `php -S localhost:8000 -t public` dev server serves the same bundle from the production webroot path.

That single capability exercises: webroot layout (AD-3), hand-rolled router bootstrap (AD-4, AD-17), design-token layer (DESIGN.md sections Colors/Typography/Spacing/Shape/Elevation), Bootstrap re-skin via `--bs-*` overrides, theme persistence (D-04..D-07), the `tickettrade.js` self-registering component system (D-12), the toast ARIA live region, the keyboard-nav floor, the contrast ledger, and the `prefers-reduced-motion` class toggle.

## Architectural Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Webroot | `public/` only; `src/` outside webroot | AD-3 from `ARCHITECTURE-SPINE.md`. nginx/Apache docroot must not expose PHP source or config. |
| Routing | Hand-rolled route list in `config/routes.php` and `admin/config/routes.php` | AD-4. No regex, no auto-discovery, no framework. The Front Controller (`public/index.php`) loads the route map and dispatches. |
| Front Controller | `public/index.php` (student), `public/admin/index.php` (admin) | AD-3. Only entry point for HTTP. Each row in the route map carries `{method, path, context, action, auth, csrf, rate_limit}`. |
| Dev server router | `public/router.php` (path-info) | AD-17. Required for `php -S localhost:8000 -t public` so `/admin/*` and `/mockups/*` and the root all resolve to the right front controller. |
| CSS architecture | Two source files (`tickettrade.tokens.css`, `tickettrade.bootstrap-overrides.css`) bundled via `tickettrade.css` `@import` | D-01 (CONTEXT.md). Tokens are the brand layer, Bootstrap overrides are the integration layer, the bundle is the user-facing entry point. |
| JS architecture | Single `tickettrade.js` bundle, self-registering on `data-*` attributes, no build step | D-11/D-12. Assignment-mandated no-framework, no-bundler, no-transpiler. Single file ~300 LOC. |
| Theme persistence | `localStorage` key `tickettrade.theme` (`light`/`dark`/`system`); FOUC-guard is a synchronous inline `<script>` in `<head>` | D-04/D-05. Three-state toggle per DESIGN.md; per-surface system default (student=dark, admin=light) read from `data-surface` on `<html>`. |
| Component API | `window.TicketTrade` namespace for programmatic use (`toast.show(message, type)`, `toast.dismiss(id)`, `setTheme(mode)`, `getTheme()`); data-attribute driven for the rest | D-12. Server-rendered flash-toast (`<div data-flash-toast="...">`) requires a programmatic API; everything else stays declarative. |
| Font loading | Inter via Google Fonts CDN; `system-ui` body fallback; `ui-monospace` for codes | DESIGN.md Typography. `display: swap` to prevent blocking; mono-code 0.04em letter-spacing preserved. |
| Mockup strategy | Three static HTML files in `public/mockups/` linking the production CSS bundle; no PHP, no JS data binding | D-08/D-09/D-10. The mockups are the verification harness for contrast (SC #5) and responsive (SC #7) — opening in a browser is the acceptance test. |
| Package management | `composer.json` declaring `ramsey/uuid ^4.7` only at runtime; `phpcs`, `phpunit` as dev | OPS-07. PSR-4 autoload namespace `App\\` -> `src/`. Composer runs once at scaffold; runtime has no other deps. |
| Bootstrap version | 5.3.x from CDN | DESIGN.md Layout. Bundle locally for prod (deferred to Phase 9). Bootstrap JS for modal/dropdown/accordion/pagination/table/form; brand layer is the token CSS, not custom CSS per component. |

## Stack Touched in Phase 1

- [x] Project scaffold — `composer.json` (ramsey/uuid + dev tools), `public/` webroot, `src/` source root, `config/` config root, `migrations/`, `data/`, `tests/` directories created.
- [x] Routing — `public/index.php` front controller, `public/router.php` path-info router, `public/admin/index.php` admin front controller; `config/routes.php` and `admin/config/routes.php` declared (empty maps — Phase 2 fills them).
- [ ] Database — deferred. **No DB is created in Phase 1.** Phase 2 (AUTH) ships `migrations/001_users_auth.sql` and `Support\Db`. The skeleton's "one real DB read/write" is replaced by `localStorage` write/read for the theme, which is the only state that exists in Phase 1. This is recorded as a known skeleton-shape deviation in the plan 01-01 task `done` block.
- [x] UI — Three interactive elements wired to the production code path: (a) the theme toggle on `/settings` writes to `localStorage` and the script applies `data-theme` to `<html>`; (b) the toast container is mounted and renders a programmatic toast; (c) the bottom nav highlights the active item via `aria-current="page"`.
- [x] Deployment — Documented local dev command: `php -S localhost:8000 -t public` from project root, with the `public/router.php` mapping every path. Mockups also open directly via `file://` for design verification.

## Out of Scope (Deferred to Later Slices)

- **Database, PDO singleton, migrations, sessions, auth** — Phase 2 introduces `Support\Db`, `migrations/001_*`, the session config in `config/bootstrap.php`, and the `Auth` context. Phase 1 ships an empty `config/bootstrap.php` skeleton with the autoloader wired so Phase 2 only adds session config.
- **Form validation, CSRF tokens, rate limits, login/register/logout** — Phase 2.
- **Listing CRUD, board view, listing modal, landing page** — Phase 3.
- **Ticket creation, redemption, dispute, expiry** — Phase 4.
- **Reviews, points engine, leaderboards** — Phase 5 and Phase 6.
- **Reports queue, admin reports, admin re-auth** — Phase 7 and Phase 8.
- **Cron jobs, security headers wiring at front-controller boot, compliance docs, phpcs sniff, seed data** — Phase 9. (`Support\ResponseHeaders` is *referenced* in the public/index.php front-controller boot per AD-13, but the actual header emit is a no-op stub until Phase 9 wires the policy.)
- **Image upload pipeline, image proxy, listing images, avatars** — Phase 3 introduces `Support\ImageUpload`/`Support\ImageProxy`. The avatar picker in this phase is a static 12-cell SVG grid in the mockup.
- **Point deltas, ticket codes, redemption rate-limit UX, dispute modal, per-session handover** — Phase 4.
- **Real bootstrap, real admin re-auth, audit log, hash chain, daily/weekly reports** — Phase 7/8/9.

## Subsequent Slice Plan

Each later phase adds one vertical slice on top of this skeleton without altering its architectural decisions:

- **Phase 2 (Auth):** adds `Support\Db` (PDO), `Support\Auth`, `Support\Csrf`, `Support\RateLimit`, `Support\Crypto`, `Support\ResponseHeaders` (real headers per AD-13), `migrations/001_users_auth.sql`, the `Auth` context (register/login/logout/profile), and the `Support\View` template include.
- **Phase 3 (Listings):** adds the `Listing` context, the `Listing/View/board.php` corkboard board, the `Listing/View/detail.php` modal, the listing state machine, the image upload pipeline, and the landing page.
- **Phase 4 (Tickets):** adds the `Ticket` context, the buy/redemption/expire flow, the `Support\Audit` stub, and the hand-triggered `POST /admin/cron/ticket-expiry` endpoint.
- **Phase 5 (Reviews):** adds the review Service, the star-rating input wiring, and the public profile aggregation View.
- **Phase 6 (Points):** adds the `Points/Service/points_service.php` (the only writer per AD-10), the 6-tier ladder config, and the four leaderboards.
- **Phase 7 (Reports):** adds the `Report` context, the destructive-action re-auth modal wired to AD-19, and the unified reports queue.
- **Phase 8 (Admin):** adds the `Admin` context, the `Support\Audit` hash-chain implementation, the 4-KPI dashboard, and the audit log View.
- **Phase 9 (Operational):** adds the cron job runners (`jobs/ticket_expiry.php`, `jobs/daily_cron.php`), the `Custom\Sniffs\NoRawHash` phpcs sniff, the seed data script, and the security header / CSP final wiring.

The skeleton decision *not to introduce a DB in Phase 1* is the most likely surface a later phase will need to re-derive against. The decision is recorded here so Phase 2 can extend the bootstrap (sessions, PDO, headers) without revisiting the Phase 1 architecture.

---

*SKELETON.md generated: 2026-08-30*
*Phase 1 / Plan 01-01 is the corresponding PLAN.md; Plan 01-02 is the expansion task that wires the three mockups and the three reusable component shells (toast, bottom nav, skeleton) against the same architecture.*
