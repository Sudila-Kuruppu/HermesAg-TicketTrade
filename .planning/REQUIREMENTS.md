# Requirements: TicketTrade

**Defined:** 2026-08-26
**Core Value:** Every trade ends with proof — verified NSBM students trading peer-to-peer with a confirmable digital ticket, gamified reputation, and seller ratings, so nobody trades blind.

## v1 Requirements

Requirements for initial release (MVP due 2026-09-02). Each maps to roadmap phases.

### Authentication & Profile

- [ ] **AUTH-01**: Student can register with `@students.nsbm.ac.lk` email + student ID; simulated email verification; student ID validated against seeded allowlist (~50 demo accounts) to prevent impersonation
- [ ] **AUTH-02**: Student can log in with email + password; session persists across browser refresh
- [ ] **AUTH-03**: Student can log out, destroying the session and redirecting to landing
- [ ] **AUTH-04**: Password rules enforced server-side (≥8 chars) and stored as bcrypt (cost ≥12); never plaintext, never logged
- [ ] **AUTH-05**: Route guards redirect unauthenticated users from protected pages to login; non-admin access to `/admin/*` redirects with error
- [ ] **AUTH-06**: Login attempts rate-limited (5/5min/IP per NFR-SEC-007); wrong credentials show a single inline error (anti-enumeration per UX-DR-36)
- [ ] **PROF-01**: Student can edit profile: full name, bio, avatar (grid of 12 predefined illustrations), WhatsApp number (validated: Sri Lankan mobile `^(\+94|0)7[0-9]{8}$`)
- [ ] **PROF-02**: Profile shows rank badge, star row, total points, join date, transaction counts (sales + purchases), average rating + review count
- [ ] **PROF-03**: Profile tabs: My Listings · My Tickets · Purchase History · Sales History · Reviews
- [ ] **PROF-04**: Verified Student checkmark displayed on profile and listing cards (one-time +50 pts bonus on verification)

### Listings

- [ ] **LST-01**: Seller can create/edit/delete listings (CRUD) — title, description, price (LKR stored as integer cents `price_cents`), category, type ENUM `product` | `service`, quantity (default 1), multiple images (primary thumbnail + gallery), condition (for products: New, Like New, Good, Fair), service details (duration_minutes, delivery_method, availability)
- [ ] **LST-02**: Listing state machine: `draft → pending → active | rejected`; `sold` when `quantity_sold == quantity`; `removed` by admin moderation on `active` listing
- [ ] **LST-03**: Board view: responsive grid (corkboard default + plain-grid list-view toggle persisted per session), category tabs/filter, keyword search (MySQL FULLTEXT), shows available quantity
- [ ] **LST-04**: Hover transition on listing card: subtle lift + shadow + border glow (CSS `transform: translateY(-4px)`), suppressed on touch and `prefers-reduced-motion`
- [ ] **LST-05**: Listing modal (full-screen): image carousel, seller info with rank badge, "Buy Now" → confirmation modal → ticket creation
- [ ] **LST-06**: Listing modal navigation: Next/Previous in category, ESC + click backdrop close, keyboard arrows (←/→), swipe on mobile, focus returns to trigger on close
- [ ] **LST-07**: Submission flow: brand-new listings enter `pending`; admin approve/reject; otherwise hourly cron auto-approves 24h after submission (`approved_at = NOW()`, `approved_by NULL`)
- [ ] **LST-08**: Products priced in integer cents; services priced per session in integer cents (duration_minutes documents typical session length but does not affect pricing)
- [ ] **LST-09**: Edit/delete allowed only on own listings AND only when status ∈ {draft, pending, active, rejected}; fast-track — edit to `active` listing keeps it live behind a `review_flag`; admin queue surfaces flagged listings alongside pending ones
- [ ] **LST-10**: Multiple images stored on local filesystem outside webroot (`/var/www/uploads/listings/`) with SHA256 rename, served via PHP proxy — thumbnails public (200/600), full-size (1200) auth-checked
- [ ] **LST-11**: Quantity field: `quantity` INT DEFAULT 1, `quantity_sold` INT DEFAULT 0; inventory invariant — `quantity_sold` increments ONLY inside ticket-creation transaction; redemption is a no-op for stock; expiry and Force Expire decrement by the same amount (for partially delivered service tickets, only undelivered sessions are restored per FR-LST-012)
- [ ] **LST-12**: Services use quantity = number of sessions (e.g., "5 tutoring sessions"); each purchase yields ONE ticket whose `total_sessions` equals the sessions bought and whose `session_number` tracks confirmed handovers; per-session confirmation flow defined in TKT-12
- [ ] **LST-13**: Draft/save flow: seller can save as `draft`, edit later, then submit; draft listings support image upload/management before submission (`draft` flag prevents public visibility)
- [ ] **LST-14**: Relist after sold: one-click "Relist" copies listing to new `draft` with same details; seller can adjust quantity before submit; on submit, relist goes directly to `active` (skipping `pending`) when source was previously approved (`approved_at` set) — approved-content fast-track
- [ ] **LST-15**: Seller dashboard: tabs Active / Pending / Sold / Draft with bulk actions (delete, relist)
- [ ] **LST-16**: Image delete/reorder on edit: drag-to-reorder updates `sort_order`; remove individual images

### Tickets & Purchases

- [ ] **BUY-01**: Purchase confirmation modal: "Confirm purchase? This reserves the item with a digital ticket (a reservation, not payment)." with Cancel/Confirm; scrim click suppressed 2s
- [ ] **BUY-02**: Purchase History tab: ticket code, status, listing title, price, date, seller name
- [ ] **TKT-01**: On confirmed "Buy Now": generates unique ticket code — format `TK-XXXXXXXXXXXXXXXXXXXXXX` (22 random base62 chars from `random_bytes(16)`, ≥125 bits entropy, not timestamp-derived), retry loop on unique violation (max 10 attempts)
- [ ] **TKT-02**: "My Tickets" page — status: active | redeemed | expired | disputed; shows code with mask/reveal toggle (keyboard accessible, announces state), copy-to-clipboard, WhatsApp share to seller (pre-filled if seller WhatsApp provided)
- [ ] **TKT-03**: "Sales" page — seller sees tickets for their listings (grouped by listing with quantity context `#N/Q`); can redeem by entering buyer's code
- [ ] **TKT-04**: Redemption flow: atomic guarded UPDATE validates (`status='active'`, `dispute_status != 'pending'`, `seller_id = CURRENT_USER`) → marks ticket `redeemed`, `redeemed_at=NOW()` → awards points to both parties; rate-limited 5 attempts/hr/ticket; correct-code resubmission is idempotent and does NOT consume an attempt
- [ ] **TKT-05**: Ticket expiry: `expires_at` written once at creation (`created_at + INTERVAL 7 DAY`, Asia/Colombo); hourly cron reads stored value; expires if not redeemed → decrements listing `quantity_sold` (services: `total_sessions - (session_number - 1)`); if listing `status='sold'` AND `quantity_sold < quantity`, restore `status='active'`; skipped if `dispute_status='pending'`
- [ ] **TKT-06**: No payment gateway — simulation only (assignment requirement); "a reservation, not payment" copy on every purchase flow
- [ ] **TKT-07**: Ticket actions: Copy code · WhatsApp share · Dispute (buyer or seller)
- [ ] **TKT-08**: Dispute flow: on `active` ticket only → modal with reason dropdown (seller_unresponsive, item_not_as_described, buyer_unresponsive, other) + text (200-char max) + optional evidence image → sets `status='disputed'` AND `dispute_status='pending'`, report created (`target_type='ticket'`) → admin queue with Force Expire (`dispute_status='upheld'`, ticket → `expired`), Force Redeem (`dispute_status='upheld'`, ticket → `redeemed`), Dismiss (`dispute_status='rejected'`, ticket → `active`)
- [ ] **TKT-09**: Dispute auto-expiry: 3 days after creation → auto-dismiss (`dispute_status='rejected'`, ticket returns to `active`); executed by hourly cron alongside ticket expiry; original `created_at` preserved on dismiss branch only (FR-TKT-013)
- [ ] **TKT-10**: Redemption rate-limit UX: 1–4 wrong attempts → inline error "Code not recognized." with "N of 5 attempts remaining"; 5th attempt → "Too many attempts. Try again in 1 hour." (field disabled 1h); already-redeemed code → "This ticket was already redeemed on {timestamp}." (idempotent, no new state change); not-your-ticket → "Not authorized to redeem this ticket." + security log entry
- [ ] **TKT-11**: Ticket display suffix for quantity context: `TK-... #2/5` (UI only, not stored)
- [ ] **TKT-12**: Per-session service handover: seller confirms each session strictly in order (`session_number` 1..`total_sessions`); each confirmation requires `active` ticket (no pending dispute) AND confirming user must be `seller_id`; logs audit event; points award ONLY on final session confirmation (FR-PTS-007 halving applies); buyer sees per-session progress `#N/M`

### Reviews & Ratings

- [ ] **RAT-01**: After ticket `redeemed`, both parties can leave 1-5 star rating with optional text comment within 14 days; comments of 50+ chars qualify as detailed reviews and earn the +10 review points (rating-only reviews earn no points)
- [ ] **RAT-02**: Seller profile shows average rating, review count, 1-5 distribution breakdown
- [ ] **RAT-03**: Reviews visible on listing modal and seller profile; reviews display reviewer nickname (never full name); only verified transactions (redeemed tickets) can review — gate enforced at Service layer per AD-15
- [ ] **RAT-04**: Buyer ratings also tracked (seller rates buyer)
- [ ] **RAT-05**: Seller profile shows public dispute count ("2 disputes on record") alongside ratings — count only, no narrative detail or party names; population: ONLY tickets attributed to this seller whose dispute was resolved as UPHELD (`dispute_status='upheld'`); rejected and auto-dismissed disputes do not appear; count recomputes on resolution
- [ ] **RAT-06**: "Leave review" button appears only after ticket `redeemed` within 14-day window (entry point for RAT-01)

### Points & Ranks

- [ ] **PTS-01**: Points awarded per action with daily caps: profile verification +50 (one-time) · approved listing +5 (cap 15/day) · sale completed +30 (cap 150/day, 2 counted per pair) · purchase completed +10 (cap 50/day, 2 counted per pair) · detailed review +10 (cap 50/day) · valid report +20 (cap 60/day) · 7-day login streak +15 · 30-day login streak +50
- [ ] **PTS-02**: 6-tier rank ladder (anime-style badges, inline SVG): Recruit E (0-49, gray) · Rookie D (50-149, blue) · Operative C (150-399, green) · Specialist B (400-799, gold) · Elite A (800-1499, orange) · Legend S (1500+, red animated crown with `legend-glow`)
- [ ] **PTS-03**: Tier badges render as inline SVG on profile, ticket pages, listing cards, and seller info in modal; badge classes: E=bg-secondary, D=bg-primary, C=bg-success, B=bg-warning text-dark, A=bg-danger, S=bg-dark with `legend-glow`
- [ ] **PTS-04**: Points logged in `points_log` table (delta, reason, reference_type, reference_id, balance_after, metadata JSON, event_uuid UUID v7 with `UNIQUE KEY uniq_event`); users.points and users.tier updated in same DB transaction
- [ ] **PTS-05**: Points velocity cap: 150 points/day per user from transactions (sale + purchase + review); enforced at insert time
- [ ] **PTS-06**: Same buyer-seller pair: max 2 transactions/day counted for points (enforced at insert time)
- [ ] **PTS-07**: New accounts: 50% multiplier on TRANSACTION-derived points only (purchase/redemption awards), until the first 5 confirmed redemptions (`delta = FLOOR(delta * 0.5)` at insert); verification, streaks, reports, and listing approvals are NEVER halved
- [ ] **PTS-08**: Inactivity signal: Active = 1+ action in last 14 days; "On Break" = 14+ days inactive → grayed-out tier badge + tooltip "Inactive 14+ days — next action restores full badge"; re-activation on next action restores full badge instantly (no point penalty)
- [ ] **PTS-09**: Four leaderboards (summary tables refreshed daily by cron): Campus Legends Wall (top 20 tier S), Weekly Risers (top 10 with weekly_points >= 50, week boundaries in Asia/Colombo Mon-Sun), Category Leaders (top 3 per category by successful sales), Streak Kings (top 10 by current login streak); privacy: nickname + program/year (no student_id digits)
- [ ] **PTS-10**: Anti-farming rules enforced at insert time: velocity flag >300 pts/day or >150 pts/hr → `users.points_frozen = TRUE` (new awards blocked while frozen); admin can void or approve; voiding inserts negative `points_log` delta, `delta` signed `SMALLINT`, void floored at zero, `users.points` and `users.tier` recalculated

### Reports & Moderation

- [ ] **RPT-01**: Report button on every listing + user profile + ticket (dispute) with reason dropdown (scam, inappropriate, spam, wrong_category, other, dispute) and required text (200-char max)
- [ ] **RPT-02**: Admin reports queue with listing/ticket preview, reporter info, evidence detail view (ticket code, buyer, seller, listing, images, timestamps); bulk dismiss
- [ ] **RPT-03**: Admin resolution actions: Dismiss · Remove Listing · Warn User · Ban User · Force Expire Ticket · Force Redeem Ticket; destructive actions trigger admin re-auth modal (300s sliding window, FR-ADM-008)
- [ ] **RPT-04**: Report write-back rule: upheld actions → `reports.status='resolved'`; rejected/dismissed → `reports.status='dismissed'`
- [ ] **RPT-05**: Suspicious activity flag on users → points frozen pending review (link to PTS-10); flagging sets `users.points_frozen=TRUE`; approving clears the flag and earning resumes; voiding inserts negative points_log row

### Admin Console

- [ ] **ADM-01**: Admin can list/search/filter users by role/status; promote/demote, ban/unban, suspend; CSV export; bulk actions (checkboxes + dropdown: ban, suspend, promote); sensitive actions trigger re-auth (PTS-10 / RPT-05)
- [ ] **ADM-02**: Admin listings queue (FIFO `ORDER BY created_at ASC`): approve/reject pending listings; override to manual review; bulk approve/reject/remove with reason; flagged fast-track edits surface in this queue (LST-09); 24h auto-approval enforced by hourly cron
- [ ] **ADM-03**: Admin categories CRUD with sort_order
- [ ] **ADM-04**: Analytics cards on dashboard: total users · active listings · tickets redeemed this week · total points awarded (display-lg primary text; subtitle on-surface-variant)
- [ ] **ADM-05**: Audit log: immutable append-only with hash chain (`prev_hash = SHA256(prev.prev_hash || json_encode(canonical_row))`); inserts serialized via MySQL named lock `GET_LOCK('audit_log_chain', 5)`; search/filter by date range, actor, action, target; last 1000 rows re-verify on every page render; full re-walk at `POST /admin/cron/audit_reverify`
- [ ] **ADM-06**: Daily/weekly reports (sales volume, top sellers, category breakdown, dispute rate); generated by `jobs/daily_cron.php` alongside leaderboard refresh (02:00 Asia/Colombo)
- [ ] **ADM-07**: Admin re-auth primitive: 300s sliding window cached in `admin_reauth` table keyed by `(user_id, session_id)`; re-auth itself rate-limited 5/min/IP; full re-login is NOT the only mechanism
- [ ] **ADM-08**: Admin re-auth wired into all sensitive destructive Actions (ban, promote, delete, bulk actions, report resolutions, listings remove/reject with reason) — modal with error 2px border, password field, success closes modal and action proceeds

### Landing Page

- [ ] **LND-01**: Public landing page accessible without login
- [ ] **LND-02**: Hero: product name "TicketTrade", tagline "Every Trade Ends With Proof", "Get Started" → register, "Explore Marketplace" → board (redirects to login)
- [ ] **LND-03**: Vision & Mission cards
- [ ] **LND-04**: How It Works (5 steps): List it → Find it → Claim it → Confirm it → Climb
- [ ] **LND-05**: Team section: 6 cards with photo/avatar, name, student ID, role, one-line contribution
- [ ] **LND-06**: Footer: NSBM branding, contact, links to GitHub/Drive
- [ ] **LND-07**: Guest browse preview: "Browse as Guest" shows board but "Buy Now" redirects to login
- [ ] **LND-08**: Corkboard board presentation: wood/cork background texture, listings displayed as paper "flyer" cards with pushpin graphic, ±2° deterministic rotation (seeded by listing id); constraints: rotation/pin decorative-only (ranking data flows through list order, NOT card rotation); `aria-hidden` on decoration; list-view toggle with `aria-pressed` persists per session; auto-degrades below md breakpoint (<768px); honors `prefers-reduced-motion`; tap-opens on touch; WCAG AA contrast on card backgrounds; lazy-load below-fold images; cork texture asset ≤100KB; transform/opacity-only motion

### Cross-Cutting

- [x] **UX-01**: Toast system for all async actions (success/error/info types); auto-dismiss 4s; queue max 3; ARIA live region (`role='status'` for success/info, `role='alert'` for error/warning); bottom-right desktop / top mobile
- [x] **UX-02**: Skeleton loading on cold load for board (12 placeholder cards), listing modal, My Tickets, Sales, Profile, My Listings, Purchase History, Leaderboards, Admin Dashboard, Admin Listings, Admin Reports, Admin Users; surface-specific shimmer (1s, surface-container-high)
- [x] **UX-03**: Empty/error states for board, My Tickets, Sales, My Listings, Purchase History, Leaderboards, admin queues (UX-DR-34); "Couldn't load listings. Tap to retry." on fetch failure
- [x] **UX-04**: Design token system in `public/assets/css/tickettrade.css`: brand primary #1B5E20 (NSBM green), trust amber #F57F17, info blue #0277BD, six tier colors, semantic success/error/warning/info, eight status role fills, light + dark neutral surfaces, corkboard tokens, code surface, WhatsApp #25D366; AA-pass on-secondary and darkened status-sold/status-disputed text tokens
- [x] **UX-05**: Typography tokens (system-ui body + Inter display/headline + mono-code monospace with letter-spacing 0.04em for ticket codes and `points_log.event_uuid`)
- [x] **UX-06**: Theme persistence: student surfaces default dark mode, admin surfaces default light mode; user choice persisted in localStorage; system preference is first-visit fallback; toggle at `/settings`
- [x] **UX-07**: WCAG 2.1 AA floor (NFR-USE-003): text ≥4.5:1, UI elements ≥3:1, large text ≥3:1; verified via contrast ledger + axe-core in test plan
- [x] **UX-08**: Keyboard navigation floor (NFR-USE-002): all modals trap focus, ESC closes, focus returns to trigger, focus-visible 2px outlines; skip link first focusable element on every page jumping to `#main`
- [x] **UX-09**: Bottom nav: 64px tall, fixed, 5 items (Board, My Listings, My Tickets, Sales, Profile); hidden on ≥768px; `aria-current='page'` on active; no badge counts (anti-pattern)
- [x] **UX-10**: Avatar picker: grid of 12 predefined illustrations (4×3 desktop, 3×4 mobile); circular thumbnails; 2px primary ring on selection; no upload, no custom images
- [ ] **SEC-01**: All SQL via prepared statements (PDO); no concatenation (NFR-SEC-002)
- [ ] **SEC-02**: CSRF tokens on all state-changing forms (synchronizer token pattern, `hash_equals()` validation) (NFR-SEC-003)
- [ ] **SEC-03**: File uploads: 4-layer validation (finfo MIME, getimagesize dimensions ≤4000px/5MB, magic bytes, GD re-encode to WebP); max chunk 2MB, total 5MB; default 8 images/listing; Uppy.js `chunkSize` MUST be 2 MiB (NFR-SEC-004)
- [ ] **SEC-04**: XSS prevention via `htmlspecialchars` on output (NFR-SEC-005)
- [ ] **SEC-05**: Session cookies: `HttpOnly`, `Secure` (in prod), `SameSite=Strict`, `use_strict_mode=1`, `sid_length=48` (NFR-SEC-006)
- [ ] **SEC-06**: Rate limits: login 5/5min per IP, purchase 10/hr per user, listing_create 20/hr/user, points 150/day/user, redemption 5/hr/ticket; per-user limits (not just IP) (NFR-SEC-007)
- [ ] **SEC-07**: Security headers: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, CSP with CDN allowances (NFR-SEC-008); set by `Support\ResponseHeaders` at front-controller boot
- [ ] **SEC-08**: WhatsApp number validation server-side regex `^(\+94|0)7[0-9]{8}$` (Sri Lankan mobile) (NFR-SEC-009)
- [ ] **PER-01**: Page load < 2s on localhost uncached (NFR-PER-001)
- [ ] **PER-02**: Board view loads ≤ 50 listings per page (pagination) (NFR-PER-002)
- [ ] **PER-03**: Image thumbnails generated on upload (3 sizes: 200px, 600px, 1200px, all WebP 80% quality) (NFR-PER-003)
- [ ] **PER-04**: Cron ticket-expiry completes < 30s for 10k tickets (single guarded UPDATE) (NFR-PER-004)
- [ ] **PER-05**: Leaderboard summary-table queries served from indexes over summary tables refreshed daily by cron (NFR-PER-005)
- [ ] **REL-01**: Idempotent ticket redemption (re-redeeming returns current state, not error); correct-code resubmission is idempotent and does NOT consume a rate-limit attempt (NFR-REL-001)
- [ ] **REL-02**: Idempotent cron job within same wall-clock day (re-running produces no duplicate effects); staging replay: `TRUNCATE cron_log; php jobs/ticket_expiry.php` = identical result for ticket expiry (NFR-REL-002)
- [ ] **REL-03**: Database foreign keys with `ON DELETE CASCADE` / `SET NULL` / `RESTRICT` where appropriate (NFR-REL-003)
- [ ] **REL-04**: Atomic UPDATE for ticket redemption — no explicit transaction needed (NFR-REL-004)
- [ ] **REL-05**: Points ledger uniqueness via `UNIQUE KEY uniq_event (event_uuid)` — one row per points event, covering retries and closing duplicate-NULL hole (NFR-REL-005)
- [ ] **REL-06**: FK `tickets.listing_id ON DELETE RESTRICT` — seller cannot delete listing with active tickets (NFR-REL-006)
- [ ] **OPS-01**: Dev server: `php -S localhost:8000 -t public` from project root with `public/router.php` (NFR-OPS-001)
- [ ] **OPS-02**: Migrations: `migrations/NNN_*.sql` files + `migrations/.applied` set; `php migrate.php` runs missing files in lexical order inside a single transaction per file; forward-only (NFR-OPS-002, AD-6)
- [ ] **OPS-03**: Cron ticket-expiry (`php jobs/ticket_expiry.php`, hourly) — file lock via `flock()`, timezone Asia/Colombo, owns (a) ticket expiry, (b) 24h listing auto-approve, (c) 3-day dispute auto-dismiss; cron_log table; manual trigger endpoint `POST /admin/cron/ticket-expiry` (admin only) (NFR-OPS-003, AD-11)
- [ ] **OPS-04**: Cron daily leaderboards (`php jobs/daily_cron.php`, 02:00 Asia/Colombo) — refresh summary tables, inactivity flags, streak updates, cache leaderboards to JSON; file lock via `flock()`; logs to `cron_log` (NFR-OPS-004, AD-11)
- [ ] **OPS-05**: Code style: PSR-12 — `vendor/bin/phpcs --standard=PSR12 src/`; `Custom\Sniffs\NoRawHash` rejects `md5(`, `sha1(`, `crypt(`, `password_hash(` outside `Auth/Service/auth_service.php` and `Support\Crypto` (NFR-OPS-005, AD-18)
- [ ] **OPS-06**: Git: never push to main; PRs only, one approval required (NFR-OPS-006)
- [ ] **OPS-07**: Composer packages: `ramsey/uuid ^4.7` (UUID v7) only; dev: `phpcs`, `phpunit` (NFR-OPS-007)

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Multi-Cohort & Scaling

- **COHORT-01**: `cohort_id` column on every Model with `DEFAULT 'cohort-1'` and `WHERE cohort_id = ?` belt-and-braces — retro decision at S2 per AD-20
- **SCALE-01**: Real SSO/LMS integration with NSBM (replace simulated domain verification)
- **SCALE-02**: Multi-language / i18n support

### Engagement & Growth

- **GROW-01**: Referral system — referrer earns +100 pts when referred user completes first sale (already specified in FR-PTS-001 table; deferred to post-MVP implementation)
- **GROW-02**: Real email notifications (replace simulation)
- **GROW-03**: Real-time push notifications / WebSockets (replace localStorage sync simulation)

### Moderation Depth

- **MOD-01**: Buyer ID verification at redemption (in-person check)
- **MOD-02**: Algorithmic reputation scores (replace simple tiers + ratings)
- **MOD-03**: Real-time chat between buyer and seller (WhatsApp share remains primary path)
- **MOD-04**: Formal return/cancellation flow beyond 7-day expiry + dispute system

### Platform Capabilities

- **PLAT-01**: Real payment gateway integration (assignment currently forbids real payments)
- **PLAT-02**: PWA / offline mode
- **PLAT-03**: Buyer ratings depth — narrative detail on profile (currently count only per RAT-05)
- **PLAT-04**: Multi-badge systems (currently single 6-tier ladder only)

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Real payments / gateways | Assignment requirement (WAD coursework); all transactions simulated |
| Chat / messaging between users | WhatsApp share replaces it (handover coordination stays on chat) |
| Real email notifications | Simulation only — no email server required for MVP |
| Multi-language / i18n | Single locale (English) sufficient for NSBM cohort |
| PWA / offline mode | Responsive web app only; no service worker required |
| Real-time push notifications | Replaced by localStorage sync simulation; WebSockets out of scope |
| Real SSO/LMS integration | Simulated `@students.nsbm.ac.lk` email-domain check; allowlist seeded with demo accounts |
| Advanced search ranking | MySQL FULLTEXT only — no Elasticsearch, no ML ranking |
| Buyer identity verification at redemption | Code = trust signal; simulated payments have no real loss |
| Algorithmic reputation scores | Simple tiers + ratings only; deliberate scope choice |
| Multi-badge systems | Single 6-tier ladder only; no dual-tier or specialty badges |
| Formal return/cancellation flow | 7-day expiry + dispute system covers the trust gap |
| Mobile native apps | Responsive web (Bootstrap 5) covers desktop + mobile browsers |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| UX-01..UX-10 | Phase 1 | Pending |
| AUTH-01..AUTH-06 | Phase 2 | Pending |
| PROF-01..PROF-04 | Phase 2 | Pending |
| LST-01..LST-16 | Phase 3 | Pending |
| LND-01..LND-08 | Phase 3 | Pending |
| ADM-03 | Phase 3 | Pending (Service only; admin Action in Phase 8) |
| PER-02, PER-03 | Phase 3 | Pending |
| SEC-03, SEC-04 | Phase 3 | Pending |
| BUY-01, BUY-02 | Phase 4 | Pending |
| TKT-01..TKT-12 | Phase 4 | Pending |
| REL-01, REL-02, REL-04..REL-06 | Phase 4 | Pending |
| SEC-06 | Phase 4 | Pending |
| RAT-01..RAT-06 | Phase 5 | Pending |
| PTS-01..PTS-10 | Phase 6 | Pending |
| PER-05 | Phase 6 | Pending |
| RPT-01..RPT-05 | Phase 7 | Pending |
| ADM-01, ADM-02, ADM-04..ADM-07 | Phase 8 | Pending |
| OPS-01..OPS-07 | Phase 9 | Pending |
| SEC-01, SEC-02, SEC-05, SEC-07, SEC-08 | Phase 9 | Pending |
| PER-01, PER-04 | Phase 9 | Pending |
| REL-03 | Phase 9 | Pending |

**Coverage:**

- v1 requirements: 111 total
- Mapped to phases: 111
- Unmapped: 0

---
*Requirements defined: 2026-08-26*
*Last updated: 2026-08-26 after initial definition*
