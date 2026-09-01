# Roadmap: TicketTrade

## Overview

Build TicketTrade (NSBM campus-only peer-to-peer marketplace) in 9 sequential phases over a 3-week sprint, from design tokens and theme system through listings, ticket lifecycle, points/reputation, moderation, admin console, and operational substrate. Phases 1-7 are vertical MVP slices — each one delivers a user-visible capability that an NSBM student can demo end-to-end. Phases 8-9 are horizontal layers that wire admin tooling and operational substrate across everything built earlier. The core loop (list, approve, ticket, redeem, expire, dispute) is never cut, even under week-2 crunch — the pre-agreed cut order is leaderboards → bulk admin actions → login streaks → draft/relist.

## Phases

**Phase Numbering:**

- Integer phases (1, 2, 3): Planned work
- Decimal phases (2.1, 3.1): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 1: UX Foundation & Design System** — Tokens, theme, a11y floor, toast container, bottom nav, mockup-driven surfaces (completed 2026-08-30)
- [x] **Phase 2: Student Authentication & Profiles** — Register, log in, profile mgmt, session/CSRF/rate-limit Support primitives (completed 2026-09-01)
- [ ] **Phase 3: Marketplace Listings & Discovery** — Listings CRUD + state machine, corkboard board, landing page, image storage outside webroot
- [ ] **Phase 4: Purchases, Tickets & Lifecycle** — Buy Now, ticket generation, redemption, 7-day expiry, disputes, per-session service handover
- [ ] **Phase 5: Reviews & Ratings** — Post-redemption reviews, public profile aggregation, dispute count
- [ ] **Phase 6: Points, Ranks & Leaderboards** — Points engine, 6-tier ladder, daily leaderboards, anti-farming rules
- [ ] **Phase 7: Reports, Disputes & Moderation Workflow** — User reports, admin queue, destructive actions with re-auth
- [ ] **Phase 8: Admin Console, Audit & Analytics** — User mgmt, listings approval queue, audit log, analytics, admin re-auth primitive
- [ ] **Phase 9: Operational Substrate** — Migrations, cron jobs, security headers, compliance docs, phpcs sniff

## Phase Details

### Phase 1: UX Foundation & Design System

**Goal**: Ship a complete design token system, theme persistence (dark student / light admin defaults), accessibility floor, toast container, bottom nav, and the three promoted mockup-driven surfaces so every later screen inherits identical look, feel, and behavior.
**Depends on**: Nothing (first phase)
**Mode**: mvp
**Requirements**: UX-01, UX-02, UX-03, UX-04, UX-05, UX-06, UX-07, UX-08, UX-09, UX-10
**Success Criteria** (what must be TRUE):

  1. `public/assets/css/tickettrade.css` defines every color/spacing/typography/elevation token from UX-DR-1..3; no hex values appear outside the token set
  2. Theme toggle on `/settings` persists choice in localStorage; system preference is first-visit fallback; student surfaces default dark, admin surfaces default light
  3. Toast container renders with ARIA live region (`role='status'` for success/info, `role='alert'` for error/warning); auto-dismiss 4s; queue max 3; bottom-right desktop / top mobile
  4. Bottom nav renders 64px tall, 5 items, hidden ≥768px, `aria-current='page'` on active; no badge counts
  5. All three promoted mockups (`mockups/board-mobile.html`, `mockups/my-tickets.html`, `mockups/admin-dashboard.html`) render against the token system with full WCAG AA contrast (≥4.5:1 text, ≥3:1 UI elements)
  6. Skeleton shimmer (1s, surface-container-high fill) renders on board, listing modal, My Tickets, Sales, Profile, My Listings, Purchase History, Leaderboards, admin surfaces
  7. Empty/error states with named copy (UX-DR-34) render for every list surface

**Plans**: 2/2 plans executed

Plans:

- [x] 01-01-PLAN.md
- [x] 01-02-PLAN.md

**Wave 1**

- [x] 01-01: Design token system + theme persistence + a11y floor (CSS custom properties, Inter/system-ui/mono-code, breakpoints, theme controller)

**Wave 2** *(blocked on Wave 1 completion)*

- [x] 01-02: Mockup-driven shells — toast container, bottom nav, skeleton shimmer, empty/error states, three promoted mockups wired to tokens

---

### Phase 2: Student Authentication & Profiles

**Goal**: Verified NSBM students can register against a seeded allowlist, log in, manage their profile, and log out. Session, CSRF, rate-limit, and bcrypt-only primitives land here as `Support` services for later phases to consume. Route guards ensure later phases can assume `current_user` is authenticated.
**Depends on**: Phase 1
**Mode**: mvp
**Requirements**: AUTH-01, AUTH-02, AUTH-03, AUTH-04, AUTH-05, AUTH-06, PROF-01, PROF-02, PROF-03, PROF-04
**Success Criteria** (what must be TRUE):

  1. A student can register with `@students.nsbm.ac.lk` email + student ID; student ID validated against seeded allowlist (~50 demo accounts); duplicate email or student ID rejected; simulated email verification grants `is_verified=TRUE` and +50 pts (points engine stubbed)
  2. A student can log in with email + password; bcrypt (cost ≥12) verified; session persists across browser refresh; logout destroys session
  3. Wrong credentials show a single inline error "Email or password is incorrect." with no field-level highlight (anti-enumeration per UX-DR-36)
  4. Login attempts rate-limited (5/5min/IP, per NFR-SEC-007); sixth attempt within window returns generic error
  5. Route guards redirect unauthenticated users from any protected page to login; non-admin access to `/admin/*` redirects with error
  6. Profile edit accepts full name, bio, avatar (12-illustration grid), WhatsApp number (`^(\+94|0)7[0-9]{8}$` server-validated)
  7. Profile page shows rank badge, star row, total points, join date, transaction counts (sales + purchases), average rating + review count; five tabs (My Listings, My Tickets, Purchase History, Sales History, Reviews) render correctly
  8. `Support\Auth`, `Support\Csrf`, `Support\RateLimit`, `Support\Crypto` exist and are used by all state-changing endpoints; `Support\ResponseHeaders` writes security headers at front-controller boot

**Plans**: 3 plans

Plans:
**Wave 1**

- [x] 02-01-PLAN.md — substrate + migrations + route guards (Support\Auth, Csrf, RateLimit, Crypto, ResponseHeaders, Db, Error, View; 7 migrations + migrate.php; 12 avatar SVGs; route map populated; front controllers wired)

**Wave 2** *(blocked on Wave 1 completion)*

- [x] 02-02-PLAN.md — register/login/logout/forgot-password/reset/verify/profile-edit/settings flows (Auth/Service/auth_service.php, User/Service/user_service.php, Points stub, all views, full route map)
- [x] 02-03-PLAN.md — public /profile/{nickname} read view (User/Action/public_profile_action.php, User/View/public_profile.php, summary header with rank badge + verified checkmark + 0 transaction counts + "no reviews yet")

---

### Phase 3: Marketplace Listings & Discovery

**Goal**: Sellers can create, edit, and manage listings through the full state machine (draft → pending → active / rejected / sold / removed, with the approved-content fast-track and the review_flag). Buyers and guests can browse the corkboard board, search with FULLTEXT, filter by category, and open the full-screen listing modal. Categories Service ships here; the admin categories Action moves to Phase 8. The 24-hour listing auto-approval sweep is implemented as a hand-triggered Action; the cron schedule lands in Phase 9.
**Depends on**: Phase 2
**Mode**: mvp
**Requirements**: LST-01, LST-02, LST-03, LST-04, LST-05, LST-06, LST-07, LST-08, LST-09, LST-10, LST-11, LST-12, LST-13, LST-14, LST-15, LST-16, LND-01, LND-02, LND-03, LND-04, LND-05, LND-06, LND-07, LND-08, PER-02, PER-03, SEC-03, SEC-04
**Success Criteria** (what must be TRUE):

  1. A seller can create a listing with title, description, price (LKR stored as integer cents `price_cents`), category, type ENUM `product` | `service`, quantity (default 1), multiple images (primary thumbnail + gallery), condition (products), service details (services)
  2. New listings enter `pending` on submit; admin (or hand-triggered `cron/ticket-expiry` Action) approves → `active`; rejection requires reason → `rejected`; rejected listing can be edited and resubmitted → returns to `pending`; admin removal of `active` listing → `removed`
  3. Corkboard board view renders listings as paper flyer cards with ±2° deterministic rotation (seeded by listing id), pushpin graphic, `aria-hidden` decorations; list-view toggle in header with `aria-pressed` state persists per session; auto-degrades below md breakpoint (<768px) to plain grid; honors `prefers-reduced-motion`; tap-opens on touch
  4. Board loads ≤50 listings/page (pagination); MySQL FULLTEXT search by title+description; category tab/filter; available-quantity display; guest browse shows board but "Buy Now" gates to login
  5. Listing modal opens full-screen with image carousel, seller info + rank badge, Next/Previous in category, ESC + backdrop close, focus trap, keyboard arrows (←/→), swipe on mobile; focus returns to trigger on close
  6. Draft/save flow works (seller saves as `draft`, edits, submits); draft supports image upload/management before submission (`draft` flag prevents public visibility)
  7. Relist after sold: one-click "Relist" copies to new `draft`; on submit, relist of previously-approved source skips `pending` and goes directly to `active` (approved-content fast-track); relists without prior approval follow the normal `pending` path
  8. Edit to `active` listing keeps it live behind a `review_flag`; admin queue surfaces flagged listings alongside pending ones
  9. Multiple images stored outside webroot (`/var/www/uploads/listings/`) with SHA256 rename; served via PHP proxy — thumbnails (200/600) public, full-size (1200) auth-checked; 4-layer validation pipeline (finfo → getimagesize → magic bytes → GD re-encode to WebP); 3 thumbnail sizes generated on upload (200/600/1200 px WebP 80% quality)
  10. Public landing page at `/` renders hero ("Every Trade Ends With Proof"), Vision & Mission cards, How It Works (5 steps), Team section (6 cards), Footer with NSBM branding; "Get Started" → register; "Explore Marketplace" → board (redirects to login)
  11. Categories table seeded with hand-curated set (Textbooks, Electronics, Fashion, Services, Food, Events, Other); admin categories Action deferred to Phase 8

**Plans**: 4 plans

Plans:

- [x] 03-01: Listings Support primitives + image pipeline — `Support\ImageUpload` (4-layer validation, WebP re-encode, thumbnail generation), `Support\ImageProxy` (auth-checked serving), `Listing/Service`, `Listing/Model`, migrations 002 (listings, listing_images), 003 (categories)
- [x] 03-02: Listing CRUD Actions + state machine — `/listings/create`, `/listings/{id}/edit`, `/listings/{id}/delete`, draft/save flow, approved-content fast-track, `review_flag` for active edits, seller's dashboard tabs (Active/Pending/Sold/Draft)
- [x] 03-03: Board view + listing modal — `/board` (corkboard + plain-grid list-view toggle, category tabs, FULLTEXT search, pagination, guest browse), listing modal (carousel, Next/Previous, keyboard nav, focus trap)
- [x] 03-04: Landing page + hand-triggered 24h auto-approve Action — `/` landing (hero, Vision/Mission, How It Works, Team, Footer), `POST /admin/cron/ticket-expiry` Action (admin-only re-auth) implementing the 24h sweep alongside the future expiry job

---

### Phase 4: Purchases, Tickets & Lifecycle

**Goal**: A buyer can confirm a purchase and receive a digital ticket with a `TK-` code; the seller can redeem the code; the ticket auto-expires after 7 days if not redeemed; either party can file a dispute while active; per-session service handover confirms one session at a time and awards points on the final session. The full ticket state machine (PRD §4.2) is in place. The expiry, 24h listing auto-approve, and 3-day dispute auto-dismiss sweeps are hand-triggered; the cron schedule lands in Phase 9.
**Depends on**: Phase 3
**Mode**: mvp
**Requirements**: BUY-01, BUY-02, TKT-01, TKT-02, TKT-03, TKT-04, TKT-05, TKT-06, TKT-07, TKT-08, TKT-09, TKT-10, TKT-11, TKT-12, REL-01, REL-02, REL-04, REL-05, REL-06, SEC-06
**Success Criteria** (what must be TRUE):

  1. "Buy Now" on an `active` listing (or logged-in modal that gates guest) opens confirmation modal "Confirm purchase? This reserves the item with a digital ticket (a reservation, not payment)." with Cancel/Confirm; scrim click suppressed 2s
  2. On confirm, ticket code `TK-XXXXXXXXXXXXXXXXXXXXXX` (22 random base62 chars from `random_bytes(16)`, ≥125 bits entropy, not timestamp-derived) is generated via retry loop on UNIQUE violation (max 10 attempts); `quantity_sold` increments by 1 (product) or `total_sessions` (service) inside the same DB transaction; buyer redirected to My Tickets with toast "Ticket created. Code: TK-..."
  3. My Tickets page shows tickets with status (active/redeemed/expired/disputed), mask/reveal toggle (keyboard accessible, announces state), copy-to-clipboard (Copy → Copied 1.5s confirmation), WhatsApp share to seller (pre-filled if seller WhatsApp provided)
  4. Seller's Sales page shows tickets for their listings (grouped by listing with quantity context `#N/Q`); seller enters buyer's code → atomic guarded UPDATE validates (`status='active'`, `dispute_status != 'pending'`, `seller_id = CURRENT_USER`) → marks ticket `redeemed`, `redeemed_at=NOW()` → awards points (stub call to points engine); `rowCount() === 0` is the invalid branch
  5. Redemption rate limit: 5 wrong attempts/hr/ticket → 1-hour lockout; correct-code resubmission is idempotent and does NOT consume an attempt; UX inline errors per UX-DR-37
  6. Ticket expiry: `expires_at` written once at creation (`created_at + INTERVAL 7 DAY`, Asia/Colombo); hand-triggered expiry sweep reads stored value, marks `expired`, decrements `quantity_sold` (services: `total_sessions - (session_number - 1)`), restores `status='active'` if listing was `sold` and now has stock; skipped if `dispute_status='pending'`
  7. Dispute flow on `active` ticket only: modal with reason dropdown + text + optional evidence → sets `status='disputed'` AND `dispute_status='pending'`, creates `reports` row (`target_type='ticket'`); admin can Force Expire / Force Redeem / Dismiss (resolution Actions land in Phase 7); 3-day auto-dismiss returns ticket to `active` with original `created_at` preserved (Dismiss branch only, per FR-TKT-013)
  8. Per-session service handover: seller confirms each session strictly in order (`session_number` 1..`total_sessions`); each confirmation requires `active` ticket + confirming user must be `seller_id`; logs audit event; points award ONLY on final session confirmation; buyer sees per-session progress `#N/M`
  9. Tickets FK to listings with `ON DELETE RESTRICT` — seller cannot delete listing with active tickets (NFR-REL-006)
  10. `points_log.event_uuid` uniqueness via `UNIQUE KEY uniq_event (event_uuid)` covers retries and closes the duplicate-NULL hole (stub writes from redemption; full points engine in Phase 6)
  11. Rate limits enforced: purchase 10/hr/user; redemption 5/hr/ticket; listing_create 20/hr/user

**Plans**: 4 plans

Plans:

- [ ] 04-01: Ticket Support primitives + migrations — `Ticket/Service` (atomic UPDATE pattern, code generation with retry loop), `Ticket/Model`, migration 004 (tickets, points_log stub), `Support\Audit` helper stub
- [ ] 04-02: Buy Now + My Tickets — `/listings/{id}/buy` Action with confirmation modal + ticket creation, `/tickets` (My Tickets) View with mask/reveal/copy/WhatsApp, `/tickets/{id}` detail
- [ ] 04-03: Sales page + redemption — `/sales` View with grouped tickets + quantity context, `POST /tickets/redeem` Action with atomic UPDATE + rate-limit + idempotency + UX-DR-37 error states; per-session confirmation sub-flow for services
- [ ] 04-04: Ticket expiry + dispute filing + hand-triggered sweeps — `POST /admin/cron/ticket-expiry` Action extending Phase 3's cron Action to add ticket expiry and 3-day dispute auto-dismiss; dispute modal at `/tickets/{id}/dispute`; buyer/seller dispute Actions

---

### Phase 5: Reviews & Ratings

**Goal**: After a ticket is redeemed (within 14 days), both buyer and seller can leave a 1-5 star review with optional text. The seller's public profile shows an aggregated average, a 1..5 distribution, the review count, and the public dispute count (count only, populates only on `dispute_status='upheld'`).
**Depends on**: Phase 4
**Mode**: mvp
**Requirements**: RAT-01, RAT-02, RAT-03, RAT-04, RAT-05, RAT-06
**Success Criteria** (what must be TRUE):

  1. After ticket `redeemed`, both buyer and seller can leave a 1-5 star rating with optional text comment within 14 days; "Leave review" button appears only within that window
  2. Comments of 50+ chars qualify as detailed reviews and earn the +10 review points (rating-only reviews earn no points)
  3. Reviews insertable only when `tickets.status IN ('redeemed','expired') AND tickets.dispute_status='none'` (AD-15 gate enforced at Service layer); `reviews UNIQUE (ticket_id, reviewer_role)` prevents double-entry
  4. Seller profile shows average rating, review count, 1-5 distribution breakdown; reviews display reviewer nickname (never full name)
  5. Public dispute count on seller profile ("N disputes on record") counts ONLY tickets whose dispute was resolved as UPHELD (`dispute_status='upheld'`); rejected and auto-dismissed disputes do not appear; count only, no narrative or party names
  6. Star rating input is a fieldset of 5 named radio inputs (1-5); radios hidden; visible label is 24px star icon; hover and focus preview; keyboard arrow keys cycle; screen reader announces "Rating: N of 5"; Clear link resets to 0

**Plans**: 2 plans

Plans:

- [ ] 05-01: Reviews Service + Actions — `Ticket/Service/review_service.php` with AD-15 gate, `Ticket/Model/review_model.php`, `POST /tickets/{id}/review` Action (1-5 star + text), `GET /tickets/{id}/review` form
- [ ] 05-02: Public profile aggregation — extend `/profile/{nickname}` View (from Phase 2) with rating average, distribution, count, dispute count; profile tabs Reviews; seller profile on listing modal shows aggregation

---

### Phase 6: Points, Ranks & Leaderboards

**Goal**: Every point-earning action writes to an append-only `points_log` (with `UNIQUE uniq_event (event_uuid)`) and updates `users.points` + `users.tier` in the same DB transaction. The new-account 50% multiplier applies only to the first 5 counted transactions and only to counted transactions (not verification, streak, report, or listing). The per-pair daily cap (2 counted transactions per buyer-seller pair) and the velocity check (>300 pts/day or >150 pts/hr → `points_frozen=TRUE` + admin flag) are enforced at insert time. Four daily-refreshed leaderboards surface the top users with privacy-preserving nicknames.
**Depends on**: Phase 5
**Mode**: mvp
**Requirements**: PTS-01, PTS-02, PTS-03, PTS-04, PTS-05, PTS-06, PTS-07, PTS-08, PTS-09, PTS-10, PER-05
**Success Criteria** (what must be TRUE):

  1. Every point-earning action (profile verification +50, approved listing +5, sale +30, purchase +10, detailed review +10, valid report +20, 7-day streak +15, 30-day streak +50) writes a `points_log` row with event_uuid UUID v7 (UNIQUE) and updates `users.points` + `users.tier` in the same DB transaction; tier recomputed from `config/ranks.php`
  2. 6-tier ladder renders inline SVG with correct badge classes (E=bg-secondary, D=bg-primary, C=bg-success, B=bg-warning text-dark, A=bg-danger, S=bg-dark with `legend-glow`); tier S has 2.4-second ease-in-out `legend-glow` animation, disabled under `prefers-reduced-motion`
  3. Tier progress bar on Profile: track `surface-container` fill, fill uses current tier color, tooltip "X of Y toward {next tier name}"
  4. Velocity cap (150 pts/day from transactions) enforced at insert time; same buyer-seller pair cap (2 counted transactions/day) enforced at insert time
  5. New account 50% multiplier (`delta = FLOOR(delta * 0.5)`) applies ONLY to transaction-derived points AND ONLY to the first 5 confirmed redemptions; verification, streaks, reports, and listing approvals are NEVER halved
  6. Velocity flag >300 pts/day OR >150 pts/hr → `users.points_frozen = TRUE`, blocks new awards; admin can void (inserts negative `delta` row, floored at zero) or approve (clears flag); `users.points` and `users.tier` recalculated after void
  7. Inactivity signal: Active = 1+ action in last 14 days; "On Break" = 14+ days inactive → grayed-out tier badge + tooltip "Inactive 14+ days — next action restores full badge"; re-activation restores instantly (no point penalty)
  8. Four leaderboards render from summary tables refreshed by hand-triggered daily Action: Campus Legends Wall (top 20 tier S), Weekly Risers (top 10 with weekly_points >= 50, week boundaries in Asia/Colombo Mon-Sun), Category Leaders (top 3 per category by successful sales), Streak Kings (top 10 by current login streak)
  9. Leaderboard privacy: nickname + program/year shown; never student_id digits

**Plans**: 3 plans

Plans:

- [ ] 06-01: Points engine + ranks — `Points/Service/points_service.php` (the only writer per AD-10), `Points/Model/points_log_model.php`, `config/ranks.php`, migration 005 (leaderboard summary tables), migration 006 (login_streaks)
- [ ] 06-02: Velocity, multiplier, anti-farming — velocity check (>300/day, >150/hr) at insert time, FR-PTS-007 multiplier on first-5 counted transactions, FR-PTS-006 pair cap, void/approve Actions for admin (used in Phase 8); admin flag badge (UX-DR-16) wired to user list
- [ ] 06-03: Leaderboards + tier progress UI — `/leaderboards` View with four boards, tier progress bar on Profile, "On Break" pill (UX-DR-17) on rank badge, hand-triggered `POST /admin/cron/daily` Action extending Phase 3's cron

---

### Phase 7: Reports, Disputes & Moderation Workflow

**Goal**: A user can file a report on a listing, a profile, or a ticket (dispute). The admin sees a unified queue with bulk dismiss and per-row resolution actions: Dismiss / Remove Listing / Warn User / Ban User / Force Expire / Force Redeem. The admin re-auth modal is in front of every destructive action. The report write-back rule (`reports.status='resolved'` on upheld, `'dismissed'` on reject/auto-dismiss) is enforced.
**Depends on**: Phase 6
**Mode**: mvp
**Requirements**: RPT-01, RPT-02, RPT-03, RPT-04, RPT-05
**Success Criteria** (what must be TRUE):

  1. A user can file a report on a listing, profile, or ticket (dispute) with reason dropdown (scam, inappropriate, spam, wrong_category, other, dispute) + required text (200-char max) + optional evidence image (one image, 5MB max, 4-layer validation per NFR-SEC-004); toast "Report submitted. Admin will review within 48 hours."
  2. Admin sees a unified `/admin/reports` queue with status, target preview, reporter info, evidence detail view (ticket code, buyer, seller, listing, images, timestamps); bulk dismiss checkbox works
  3. Admin resolution actions: Dismiss · Remove Listing · Warn User · Ban User · Force Expire Ticket · Force Redeem Ticket; destructive actions trigger admin re-auth modal (300s sliding window per FR-ADM-008 / NFR-SEC-010)
  4. Report write-back rule: upheld actions (Force Redeem / Force Expire) → `reports.status='resolved'`; rejected/dismissed → `reports.status='dismissed'`; auto-dismiss by 3-day cron → `reports.status='dismissed'`
  5. Force Expire on a ticket with pending dispute: ticket → `expired`, `dispute_status='upheld'`; Force Redeem: ticket → `redeemed`, points awarded; Dismiss: ticket returns to `active` with original `created_at` preserved (FR-TKT-013); user can't delete listing with tickets (FK RESTRICT)
  6. Velocity flag from PTS-10 surfaces on user list with badge "Earning above legitimate ceiling — review queued"; admin can freeze points (link to PTS-10)

**Plans**: 2 plans

Plans:

- [ ] 07-01: User-facing reports — `Report/Service`, `Report/Model`, `POST /listings/{id}/report`, `POST /users/{id}/report`, `POST /tickets/{id}/dispute` (extends Phase 4 dispute); `GET /reports/{target}/{id}/new` modal View
- [ ] 07-02: Admin reports queue + destructive Actions — `/admin/reports` View (status, target preview, reporter, evidence detail, bulk select), resolution Actions (Dismiss, Remove Listing, Warn User, Ban User, Force Expire, Force Redeem) all behind admin re-auth modal; audit-log writes on every action

---

### Phase 8: Admin Console, Audit & Analytics

**Goal**: An admin can manage users (promote/demote/ban/suspend, CSV export, bulk actions, velocity-flag view), manage listings (FIFO pending queue, flagged fast-track, bulk approve/reject/remove with reason), see the 4-KPI analytics dashboard, and walk the immutable audit log with hash-chain verification. Admin re-auth (300s sliding window, `admin_reauth` table, 5/min/IP) gates every sensitive action.
**Depends on**: Phase 7
**Mode**: standard
**Requirements**: ADM-01, ADM-02, ADM-04, ADM-05, ADM-06, ADM-07, ADM-03, ADM-08
**Success Criteria** (what must be TRUE):

  1. Admin `/admin/users` lists/searches/filters users by role/status; promote/demote, ban/unban, suspend, CSV export, bulk actions (ban, suspend, promote) all behind admin re-auth modal
  2. Admin `/admin/listings` queue (FIFO `ORDER BY created_at ASC`) shows pending listings; admin can approve/reject with reason; bulk approve/reject/remove; flagged fast-track edits from FR-LST-09 surface here alongside pending ones
  3. Admin `/admin/categories` CRUD with `sort_order` (Service from Phase 3, Action lands here)
  4. Admin `/admin/dashboard` shows 4 KPI cards: total users, active listings, tickets redeemed this week, total points awarded (display-lg primary text + on-surface-variant subtitle); click opens analytics detail
  5. Admin `/admin/audit` shows immutable append-only audit log with hash chain (`prev_hash = SHA256(prev.prev_hash || json_encode(canonical_row))`); inserts serialized via MySQL named lock `GET_LOCK('audit_log_chain', 5)`; search/filter by date range, actor, action, target; last 1000 rows re-verify on every page render; mismatch shows red banner; full re-walk at `POST /admin/cron/audit_reverify`
  6. Daily/weekly reports generated by `POST /admin/cron/daily` Action (sales volume, top sellers, category breakdown, dispute rate); logged to `cron_log`
  7. Admin re-auth primitive: 300s sliding window cached in `admin_reauth` table keyed by `(user_id, session_id)`; re-auth itself rate-limited 5/min/IP; full re-login is NOT the only mechanism
  8. Velocity flag badge (UX-DR-16) appears on user list for users with >300 pts/day or >150 pts/hr; clickable → user detail with flag log
  9. Admin re-auth modal (300s sliding window) gates every sensitive destructive Action: ban, promote, delete, bulk actions, report resolutions, listings remove/reject with reason — modal shows error 2px border, password field, success closes modal and action proceeds

**Plans**: 3 plans

Plans:

- [ ] 08-01: User mgmt + listings approval — `Admin/Service`, `/admin/users` View + Actions (promote, demote, ban, suspend, CSV export, bulk); `/admin/listings` View + Actions (approve, reject with reason, bulk, flagged fast-track)
- [ ] 08-02: Categories CRUD + analytics dashboard — `/admin/categories` View + Actions (CRUD with sort_order); `/admin/dashboard` View (4 KPI cards + quick-links to queues + velocity-flag table)
- [ ] 08-03: Audit log + admin re-auth primitive — `Support\Audit` (full implementation with hash chain, MySQL named lock, re-verify on render), `admin_reauth` table + Service (300s sliding, 5/min/IP), `/admin/audit` View + `POST /admin/cron/audit_reverify` Action

---

### Phase 9: Operational Substrate

**Goal**: Migrations run idempotently; both cron jobs run on schedule with `flock()` + Asia/Colombo timezone + replay-safe semantics; security headers are enforced at front-controller boot; compliance documentation lives in the project report; the `Custom\Sniffs\NoRawHash` phpcs sniff rejects `md5(`, `sha1(`, `crypt(`, and `password_hash(` outside `Auth/Service/auth_service.php` and `Support\Crypto`; seed data populates the demo accounts and ~50-user allowlist.
**Depends on**: Phase 8
**Mode**: standard
**Requirements**: OPS-01, OPS-02, OPS-03, OPS-04, OPS-05, OPS-06, OPS-07, SEC-01, SEC-02, SEC-05, SEC-07, SEC-08, PER-01, PER-04, REL-03
**Success Criteria** (what must be TRUE):

  1. `php migrate.php` runs `migrations/NNN_*.sql` files in lexical order inside a single transaction per file; forward-only; tracks applied set in `migrations/.applied`; idempotent re-run is a no-op
  2. `php jobs/ticket_expiry.php` runs hourly, owns (a) ticket expiry, (b) 24h listing auto-approve, (c) 3-day dispute auto-dismiss; file-locked via `flock()`; Asia/Colombo timezone; logs to `cron_log`; replay-safe (`TRUNCATE cron_log; php jobs/ticket_expiry.php` = identical result for ticket expiry)
  3. `php jobs/daily_cron.php` runs at 02:00 Asia/Colombo; refreshes leaderboard summary tables, inactivity flags, streak updates, caches leaderboards to JSON; file-locked via `flock()`; logs to `cron_log`
  4. Cron jobs exposed as admin endpoints: `POST /admin/cron/ticket-expiry` (from Phase 3, extended in Phase 4), `POST /admin/cron/daily` (from Phase 6), `POST /admin/cron/audit_reverify` (from Phase 8); all behind admin re-auth
  5. Security headers set by `Support\ResponseHeaders` at front-controller boot: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, CSP with CDN allowances, `Referrer-Policy`
  6. All SQL via prepared statements (PDO); CSRF tokens on all state-changing forms (`hash_equals()` validation); session cookies hardened (`HttpOnly`, `Secure` in prod, `SameSite=Strict`, `use_strict_mode=1`, `sid_length=48`)
  7. WhatsApp number validation server-side regex `^(\+94|0)7[0-9]{8}$` (Sri Lankan mobile)
  8. `vendor/bin/phpcs --standard=PSR12 src/` passes; `Custom\Sniffs\NoRawHash` sniffs rejects `md5(`, `sha1(`, `crypt(`, `password_hash(` outside `Auth/Service/auth_service.php` and `Support\Crypto`
  9. Seed data script populates ~50 demo accounts, ~100 listings, the student-ID allowlist for AUTH-01, and one admin account
  10. Compliance documentation in project report: Computer Crimes Act §26 notice-and-takedown basis (every listing enters 24h review window by default; FR-LST-015 fast-track stays under admin re-check), PDPA 2022 minimal-data posture, NSBM IT policy assumptions
  11. Page load < 2s on localhost uncached; cron completes < 30s for 10k tickets (single guarded UPDATE)

**Plans**: 3 plans

Plans:

- [ ] 09-01: Migrations runner + seed data — `migrate.php` (lexical order, single transaction per file, `.applied` set tracking); `seed.php` (~50 demo accounts, ~100 listings, allowlist, one admin)
- [ ] 09-02: Cron jobs + security headers + phpcs sniff — `jobs/ticket_expiry.php` (3 sweeps, flock, Asia/Colombo, cron_log), `jobs/daily_cron.php` (leaderboards, streaks, JSON cache); `Support\ResponseHeaders` at boot; `Custom\Sniffs\NoRawHash` sniff + `phpcs.xml` config
- [ ] 09-03: Compliance docs + performance budget checks — project report section covering Computer Crimes Act §26, PDPA 2022, NSBM IT assumptions; `<link rel="preconnect">` and image lazy-load for page-load budget; cron timing benchmarks

---

## Progress

**Phases complete:** 0 / 9
**Current phase:** None (initialization complete)

---
*Roadmap created: 2026-08-26*
*Last updated: 2026-08-26 after initialization*
