---
title: "TicketTrade — Student Business and Service Platform (NSBM Marketplace)"
status: draft
created: "2026-08-17"
updated: "2026-08-26"
---

# PRD: TicketTrade
*Product name finalized as TicketTrade (PRFAQ Stage 2 user decision). Internal artifact paths keep the former working title "NSBM Marketplace".*

## 0. Document Purpose
This PRD defines the product requirements for TicketTrade (working title "NSBM Marketplace"), a campus-only peer-to-peer marketplace for NSBM Green University students. It is written for the product manager, the 6-member student development team (Batch 26.1, Web and Mobile Application Development), faculty stakeholders, and downstream workflow owners (UX design, architecture, epic/story breakdown). The document follows a Glossary-anchored vocabulary with features grouped by actor workflow and functional requirements (FRs) nested under globally numbered stable IDs. Assumptions are tagged inline as `[ASSUMPTION]` and indexed at the end. This PRD builds on the product brief (`_bmad-output/planning-artifacts/briefs/brief-nsbm-marketplace-2026-08-23/brief.md`, finalized 2026-08-16, updated 2026-08-23), the rank system & buy/sell flow specification (`_bmad-output/planning-artifacts/prds/prd-nsbm-marketplace-2026-08-22/rank-buy-sell-spec.md`, v1.0 2026-08-24), domain research (`_bmad-output/research/nsbm-marketplace-domain-research-domain-2026-08-17`), competitive research (`_bmad-output/research/competitive-campus-peer-to-peer-marketplace-trust-me-2026-08-22`), technical research (`_bmad-output/research/php-8-mysql-campus-marketplace-patterns-technical-2026-08-22`), ranking research (`_bmad-output/research/domain-gamified-ranking-systems-peer-marketplaces-20260822`), and the forged idea decision record (`_bmad-output/forge/remove-qr-ticket-system/forged-idea.md`, outcome: HARDENED). It does not duplicate those artifacts — it distills their decisions into requirements.

## 1. Vision
TicketTrade becomes the trusted campus commerce hub where every NSBM student can safely monetize their skills, creations, and second-hand goods — building entrepreneurial confidence before graduation. Every trade ends with proof: each purchase produces a confirmable digital ticket that both parties verify, so nobody trades blind. TicketTrade complements the batch WhatsApp groups students already use; the group has reach but zero memory, so TicketTrade adds the trust and record layer while handover coordination stays on chat. A zero-friction, gamified marketplace with built-in trust signals (verified tickets, seller ratings, rank badges) lets students trade confidently without leaving the university ecosystem, and a points-and-rank ladder turns repeat trade into visible reputation.

**Thesis**: Verified student identity (email-domain check) + lightweight 6-tier reputation (Recruit → Rookie → Operative → Specialist → Elite → Legend) + simulated ticket confirmation + seller ratings = sufficient trust for campus-scale peer trade without complex escrow or algorithmic reputation.

## 2. Target User

### 2.1 Jobs To Be Done
- **Student Seller:** "When I have something to sell (textbook, tutoring session, handmade item), I want to list it in minutes, reach only NSBM students, and know the buyer is committed — so I don't waste time on no-shows."
- **Student Buyer:** "When I need something affordable (textbook, service, event ticket), I want to find it quickly, know the seller is legitimate, and get proof of purchase I can show — so I don't get scammed."
- **Admin (Faculty/Staff):** "When the platform has content or behavior issues, I want a clear queue to review, act on, and audit — so the marketplace stays safe without consuming my teaching time."

### 2.2 Non-Users (v1)
- General public (no public registration; `@students.nsbm.ac.lk` email required)
- Alumni (not in v1 scope)
- External vendors or businesses

### 2.3 Key User Journeys
*Numbered globally as UJ-1 through UJ-7. FRs reference journeys by ID inline.*

**UJ-1. Kasun lists his used textbook and sells it.**
- **Persona + context:** Kasun, 3rd-year CS student, finished a module, wants to sell the textbook.
- **Entry state:** Authenticated as student, on dashboard.
- **Path:** Clicks "Create Listing" → fills title, description, price (LKR 2,500), category "Textbooks", condition "Good", uploads 2 photos, sets quantity 1 → saves as draft → edits later → submits → listing enters `pending` status.
- **Climax:** Admin approves → listing goes `active` → Kasun sees it on the board with his rank badge.
- **Resolution:** Kasun waits for buyers; gets notification when ticket created.
- **Edge case:** Admin rejects → Kasun sees reason, edits, resubmits (rejected listings can be edited and resubmitted, returning to `pending`).

**UJ-2. Tharushi buys the textbook and gets a ticket.**
- **Persona + context:** Tharushi, 1st-year CS student, needs the same textbook, budget-conscious.
- **Entry state:** Authenticated as student, browsing "Textbooks" category.
- **Path:** Clicks listing → views full-screen modal with image carousel → clicks "Buy Now" → confirmation modal → confirms → ticket generated with code `TK-7QXK2M9WBV4N8PRTYC3AD` → redirected to "My Tickets".
- **Climax:** Ticket shows `active` status, code with mask/reveal toggle, copy button, WhatsApp share to seller.
- **Resolution:** Tharushi contacts seller via WhatsApp to arrange handover at security gate.
- **Edge case:** Listing quantity > 1 → ticket shows `#2/5` suffix; multiple buyers can purchase simultaneously.

**UJ-3. Kasun redeems Tharushi's ticket at handover.**
- **Persona + context:** Kasun (seller) meets Tharushi (buyer) at security gate.
- **Entry state:** Kasun authenticated, on "Sales" page.
- **Path:** Tharushi shows code → Kasun enters `TK-7QXK2M9WBV4N8PRTYC3AD` → system validates (active, no pending dispute) → marks `redeemed` → awards points (buyer +10, seller +30). Kasun is a new account, so his seller award is halved under FR-PTS-007: +FLOOR(30 × 0.5) = +15; this is confirmed transaction 1 of 5 toward exiting the multiplier. Inventory was consumed at ticket creation; redemption never touches `quantity_sold`.
- **Climax:** Both see updated rank badges immediately; ticket shows `redeemed` with timestamp.
- **Resolution:** Transaction complete; Kasun hands over book.
- **Edge case:** Seller enters wrong code 5 times in an hour → 1-hour lockout on that ticket.

**UJ-4. Tharushi's ticket expires after 7 days (no handover).**
- **Persona + context:** Tharushi bought but never met seller; life got busy.
- **Entry state:** Ticket `active`, older than 7 days.
- **Path:** Cron job runs → checks ticket, no pending dispute → marks `expired` → buyer loses nothing (no points are deducted at purchase; points are awarded only at redemption) → decrements listing `quantity_sold` → if listing status = `sold` AND `quantity_sold < quantity`, listing returns to `active`.
- **Climax:** Tharushi sees ticket status `expired`, points unchanged from pre-purchase.
- **Resolution:** Listing available again; Tharushi can repurchase or move on.
- **Edge case:** If dispute was `pending`, cron skips this ticket — admin must resolve first.

**UJ-5. Buyer disputes a ticket (ghosting seller).**
- **Persona + context:** Tharushi paid (simulated) but seller never responds; 3 days pass.
- **Entry state:** Ticket `active`, Tharushi on "My Tickets".
- **Path:** Clicks "Dispute" → selects reason "seller unresponsive" + optional evidence → ticket `dispute_status='pending'`, report created → admin sees in queue with "Dispute" badge.
- **Climax:** Admin reviews → chooses "Force Expire" (buyer loses nothing — no points were deducted at purchase) or "Force Redeem" (award points if evidence shows handover happened).
- **Resolution:** Ticket updated, points adjusted, `dispute_status` set to `'upheld'` or `'rejected'` with the underlying ticket state restored per outcome.
- **Edge case:** 3-day auto-dismiss if admin idle → `dispute_status='rejected'`, ticket returns to `active`.

**UJ-6. Admin moderates a reported listing.**
- **Persona + context:** Admin (faculty) logs in, sees reports queue.
- **Entry state:** Authenticated as admin, on admin dashboard.
- **Path:** Clicks report → preview shows listing + reporter info → chooses "Remove Listing" + "Warn User" → listing status → `removed`, user gets warning flag.
- **Climax:** Reporter sees report status "Action taken"; seller sees listing removed with reason.
- **Resolution:** Platform stays clean; audit trail in reports table.
- **Edge case:** False report → admin dismisses, reporter gets no points.

**UJ-7. New student registers and earns first rank.**
- **Persona + context:** New student, first time on platform.
- **Entry state:** Unauthenticated, lands on public landing page.
- **Path:** Clicks "Get Started" → registers with `@students.nsbm.ac.lk` email + student ID → sets nickname → email verified (simulated) → lands on dashboard with rank "Recruit (E)" (0 points, gray shield).
- **Climax:** Completes profile verification → +50 points (one-time) → running total **50** → reaches **Rookie (D)** tier (50-149).
- **Resolution:** Creates first listing → approved → **+5** points (listing approvals are never halved) → running total **55**; buys first item → ticket created (no points at creation) → seller confirms handover → buyer award **+⌊10 × 0.5⌋ = +5** → running total **60** → still Rookie (D). This confirmed redemption consumes confirmed transaction **1 of 5** toward exiting the new-account multiplier (FR-PTS-007). Continued trading carries them to **Operative (C)** (150 pts), **Specialist (B)** (400 pts), **Elite (A)** (800 pts), **Legend (S)** (1500+ pts).

---

## 3. Scope

### 3.1 In Scope (MVP) — By Actor Workflow

#### 3.1.1 Seller Workflow
*All requirements for creating, managing, and fulfilling listings.*

- **FR-LST-001:** Create/Edit/Delete listings (CRUD) — each listing contains: title, description, price (LKR, stored as integer cents `price_cents`), category (Textbooks, Electronics, Fashion, Services, Food, Events, Other), **quantity (default 1)**, multiple images (one primary thumbnail, others in gallery), seller contact/info (auto-filled from profile), condition (for products): New, Like New, Good, Fair, service details: duration, delivery method (in-person/online/both), availability — realizes UJ-1
- **FR-LST-002:** Listing `type` ENUM: `product` | `service` (distinct from category) — realizes UJ-1
- **FR-LST-003:** Board view: responsive grid, category tabs/filter, keyword search (MySQL FULLTEXT), shows available quantity (`quantity - quantity_sold`) — realizes UJ-1, UJ-2
- **FR-LST-004:** Hover transition: subtle lift + shadow + border glow (CSS `transform: translateY(-4px)`) — realizes UJ-2 [UI-POLISH]
- **FR-LST-005:** Flyer modal (full-screen): all details, image carousel (click thumbnails → main swap), seller info with rank badge, "Buy Now" → confirmation modal → generates ticket (shows quantity available) — realizes UJ-2
- **FR-LST-006:** Category navigation in modal: "Next in category" / "Previous" arrows; ESC / click backdrop → close; keyboard navigation (←/→ arrows), swipe support on mobile — realizes UJ-2
- **FR-LST-007:** Listing status ENUM: `draft` | `pending` (default on submit) | `rejected` | `active` | `sold` | `removed`. Single approval model: a submitted listing enters `pending`; an admin may approve or reject it immediately; otherwise the hourly cron auto-approves it 24 hours after submission. **The auto-approve sweep sets `approved_at = NOW()` and leaves `approved_by NULL`.** Brand-new listings ALWAYS enter `pending`; the only immediate-active path is the approved-content fast-track (FR-LST-015): an edited-and-relisted or resubmitted listing whose source was previously approved (`approved_at` set) goes live immediately, subject to FR-LST-015's admin re-check rule. `sold` when `quantity_sold == quantity` — realizes UJ-1, UJ-2
- **FR-LST-008:** Products priced in integer cents (`price_cents`) — realizes UJ-1
- **FR-LST-009:** Services priced per session in integer cents (`price_cents` = per session); `duration_minutes` documents typical session length but does not affect pricing — realizes UJ-1
- **FR-LST-010:** Seller can edit/delete only their own listings **and only when status ∈ {draft, pending, active, rejected}** — realizes UJ-1. **Fast-track:** an edit to an `active` listing keeps it live during review; the edit sets a `review_flag` column on the listing, the admin queue surfaces flagged listings alongside pending ones, and an admin review action clears the flag (rejection can remove the listing). **Rejected listings can be edited and resubmitted; on submit they return to `pending`.**
- **FR-LST-011:** Multiple images per listing (primary + gallery), **stored on local filesystem outside webroot** (`/var/www/uploads/listings/`) with SHA256 rename, served via PHP proxy: thumbnails serve WITHOUT auth (public, keeps FR-LND-007 guest browse working); full-size images require auth — realizes UJ-1
- **FR-LST-012:** Quantity field: `quantity` INT DEFAULT 1, `quantity_sold` INT DEFAULT 0; at ticket creation `quantity_sold` increments by 1 per product ticket and by `total_sessions` per service ticket; ticket expiry or Force Expire decrements by the same amount (**for a partially delivered service ticket, decrement by `total_sessions - (session_number - 1)`, i.e., only undelivered sessions are restored to stock**); listing auto-sold when `quantity_sold == quantity` — realizes UJ-2, UJ-3
- **FR-LST-013:** Services use quantity = number of sessions (e.g., "5 tutoring sessions"); delivery method applies per session; each purchase yields ONE ticket whose `total_sessions` equals the sessions bought and whose `session_number` tracks confirmed handovers (per-session confirmation flow defined in FR-TKT-014) — realizes UJ-1
- **FR-LST-014:** Draft/save flow: seller can save listing as `draft` (not submitted for approval), edit later, then submit — realizes UJ-1
- **FR-LST-014a:** Draft listings support image upload/management (add/remove/reorder) before submission; images stored with `listing_id` FK but `draft` flag prevents public visibility — realizes UJ-1
- **FR-LST-015:** Relist after sold: one-click "Relist" copies listing to new `draft` with same details; **seller can adjust quantity** before submit; on submit, a relist goes directly to `active`, skipping `pending`, when the source listing was previously approved (`approved_at` set); relists without prior approval follow the normal `pending` path (approved-content fast-track) — realizes UJ-1
- **FR-LST-016:** Seller dashboard: tabs for Active / Pending / Sold / Draft listings with bulk actions (delete, relist) — realizes UJ-1
- **FR-LST-017:** Image delete/reorder on edit: seller can remove images, drag-to-reorder (updates `sort_order`) — realizes UJ-1

#### 3.1.2 Buyer Workflow
*All requirements for discovering, purchasing, and managing purchases.*

- **FR-LST-003:** See FR-LST-003 (Seller workflow).
- **FR-LST-005:** See FR-LST-005 (Seller workflow).
- **FR-BUY-001:** **Purchase confirmation modal**: "Confirm purchase? This reserves the item with a digital ticket (a reservation, not payment)." with Cancel/Confirm — realizes UJ-2
- **FR-TKT-001:** On confirmed "Buy Now": generates unique ticket code — normative format `TK-XXXXXXXXXXXXXXXXXXXXXX` (`TK-` + 22 random base62 chars drawn from `random_bytes(16)`; codes carry ≥125 bits of randomness and are not derived from timestamps), e.g., `TK-7QXK2M9WBV4N8PRTYC3AD` — and increments listing `quantity_sold` by 1 for product tickets and by `total_sessions` for service tickets (this creation-side increment is the ONLY inventory mutation at purchase; see FR-LST-012) — realizes UJ-2
- **FR-TKT-002:** Buyer view: "My Tickets" page — status: `active` | `redeemed` | `expired` | `disputed` (7 days), shows code with **mask/reveal toggle**, **copy-to-clipboard**, **WhatsApp share to seller** (pre-filled if seller provided WhatsApp) — realizes UJ-2, UJ-3
- **FR-TKT-003:** Seller view: "Sales" page — sees tickets for their listings (grouped by listing with quantity context `#N/Q`), can **redeem** by entering buyer's code — realizes UJ-3
- **FR-TKT-004:** Redemption flow: seller inputs code → atomic UPDATE validates (`status='active'`, `dispute_status != 'pending'`, **`seller_id = CURRENT_USER`**) → marks ticket `redeemed`, `redeemed_at=NOW()` (`dispute_status` stays `'none'` on a clean redemption; only an admin resolution sets a terminal dispute status) → awards points to both parties. Redemption marks the ticket redeemed; inventory was consumed at creation, so redemption never touches `quantity_sold` — realizes UJ-3
- **FR-TKT-005:** Ticket expiry: `expires_at` is written once at ticket creation (`created_at` + 7 days, Asia/Colombo); the hourly cron reads the stored `expires_at` value instead of recomputing from `created_at`, so a ticket auto-expires after 7 days if not redeemed → buyer loses nothing (no points are deducted at purchase; points are awarded only at redemption) → decrements listing `quantity_sold` (for service tickets, decrement by `total_sessions - (session_number - 1)`, i.e., undelivered sessions only, per FR-LST-012); if listing `status='sold'` AND `quantity_sold < quantity`, restore listing `status='active'`. **Skipped if `dispute_status='pending'`** — realizes UJ-4
- **FR-TKT-006:** No payment gateway — simulation only (assignment requirement) — realizes UJ-2
- **FR-TKT-007:** Ticket actions: **Copy code**, **WhatsApp share** (requires seller WhatsApp), **Dispute** (buyer or seller) — realizes UJ-2, UJ-5
- **FR-TKT-008:** Dispute flow: buyer or seller clicks "Dispute" on an **`active` ticket only** → modal with **reason (dropdown: seller_unresponsive, item_not_as_described, buyer_unresponsive, other)** + text + optional evidence image → filing sets ticket `status='disputed'` AND `dispute_status='pending'`, report created (`target_type='ticket'`) → admin sees in reports queue → actions: Force Expire (`dispute_status='upheld'`, ticket → `expired`), Force Redeem (`dispute_status='upheld'`, ticket → `redeemed`), Dismiss (`dispute_status='rejected'`, ticket restored to `active`) → resolution updates ticket, listing, points — realizes UJ-5
- **FR-TKT-009:** Dispute auto-expiry: 3 days after creation → auto-dismiss (`dispute_status='rejected'`, ticket returns to `active`); **executed by the hourly cron (`jobs/ticket_expiry.php`) alongside ticket expiry** — realizes UJ-5
- **FR-TKT-010:** Redemption rate limit: 5 attempts/hour per ticket, then 1-hour lockout; correct-code resubmission is idempotent and does NOT consume an attempt — realizes UJ-3
- **FR-TKT-011:** Ticket code generation: retry loop on unique constraint violation (collision handling) — realizes UJ-2
- **FR-TKT-012:** Ticket display suffix for quantity context: `TK-... #2/5` (UI only, not stored) — realizes UJ-2
- **FR-TKT-013:** On dispute resolution to `active`, ticket keeps original `created_at` (no clock reset; scoped to the Dismiss resolution branch only) — realizes UJ-4, UJ-5
- **FR-TKT-014:** Per-session service handover: seller confirms each session strictly in order (`session_number` 1..`total_sessions`); each confirmation requires an `active` ticket (with no pending dispute) **and the confirming user must be the ticket's `seller_id`**; logs an audit event; points award ONLY on the final session confirmation (FR-PTS-007 halving rules apply normally to that final award); buyer sees per-session progress (`#N/M`) on the ticket — realizes UJ-2, UJ-3
- **FR-BUY-002:** Purchase History tab: shows ticket code, status, listing title, price, date, seller name — realizes UJ-2, UJ-3
- **FR-BUY-003:** Rating eligibility: "Leave review" button appears only after ticket `redeemed`, within 14-day window; star rating required, comment optional (50+ chars earns the detailed-review points) — realizes UJ-3

#### 3.1.3 Admin Workflow
*All requirements for moderation, user management, and platform oversight.*

- **FR-AUTH-002:** Role: `student` (default) | `admin` (manual promotion in DB) — realizes UJ-6
- **FR-ADM-001:** Users: list, search, filter by role/status, promote/demote, ban/unban, suspend, **CSV export**, **bulk actions** (checkboxes + dropdown: ban, suspend, promote) — realizes UJ-6
- **FR-ADM-002:** Listings: pending approval queue (FIFO, `ORDER BY created_at ASC`), **default auto-approve after 24 hours (configurable timer; enforced by the hourly cron alongside ticket expiry so the delay holds even without admin activity)**, admin can override to manual; all listings with edit/delete, rejection requires reason, **bulk actions** (approve, reject, remove); flagged fast-track edits (see FR-LST-010) surface in this queue — realizes UJ-1, UJ-6
- **FR-ADM-003:** Categories: CRUD with sort_order — realizes UJ-1
- **FR-ADM-004:** Reports: table with status, actions (including dispute resolutions), **evidence detail view** (ticket code, buyer, seller, listing, images, timestamps), **bulk dismiss** — realizes UJ-6
- **FR-ADM-005:** Analytics cards: total users, active listings, tickets redeemed this week, total points awarded — realizes UJ-6
- **FR-ADM-006:** Audit log: immutable append-only with hash chain (actor, action, target, old/new values, IP, user-agent, `prev_hash`); **search/filter by date range, actor, action, target**; `prev_hash = sha256(prev_hash || json_encode(current_row))`; inserts serialized via a named lock (`GET_LOCK`) to prevent chain forks under concurrency — realizes UJ-6
- **FR-ADM-007:** Daily/weekly reports: sales volume, top sellers, category breakdown, dispute rate; generated by `jobs/daily_cron.php` alongside the leaderboard refresh (02:00 Asia/Colombo, logged to `cron_log`) — realizes UJ-6
- **FR-ADM-008:** **Admin re-auth for sensitive actions**: require password confirmation for ban, promote, delete, bulk actions — realizes UJ-6
- **FR-ADM-009:** **Suspicious activity flag**: flag any user >300 pts/day (>150 pts/hour also surfaced) or >3 tickets/day with the same partner (one above the pair cap of 2 counted transactions, so legal-but-uncounted third transactions never flag), shown in user list with badge; the >300 pts/day threshold sits deliberately above the computed legitimate ceiling of ~275 pts/day (150 transactions + 50 max single-day streak bonus + 60 reports + 15 approvals; the 7-day +15 and 30-day +50 streak bonuses cannot land on the same date), so maximum legitimate earners never trigger it; the >150 pts/hour threshold sits above the maximum legitimate hourly burst (the entire daily transaction cap of 150 points landing in one hour), so legitimate handover rushes never trigger it; admin can freeze points pending review — realizes UJ-3, UJ-6

#### 3.1.4 Cross-Cutting Features
*Features spanning multiple actors.*

**Authentication & Access Control**
- **FR-AUTH-001:** Registration with `@students.nsbm.ac.lk` email + student ID; simulated email verification; duplicate email or student ID rejected. **Risk note:** because verification is simulated, registration MUST validate the student ID against the seeded student-ID allowlist to prevent impersonation of real students; the allowlist is seeded with the ~50 demo accounts by the DB designer (OQ-004c) as part of seed data, and admins can extend it from the users panel — realizes UJ-7
- **FR-AUTH-003:** Login with email + password; bcrypt-hashed credentials (never plaintext); session via PHP sessions; login attempts rate-limited per NFR-SEC — realizes UJ-1..UJ-7
- **FR-AUTH-004:** Logout destroys the session and redirects to landing page — realizes UJ-1..UJ-7
- **FR-AUTH-005:** Password rules: minimum 8 characters, hashed with `password_hash()` (bcrypt); no plaintext storage or logging — realizes UJ-7
- **FR-AUTH-006:** Route guards: unauthenticated access to protected pages redirects to login (`index.php`); non-admin access to `admin/*` redirects with error — realizes UJ-6

**Seller Ratings & Reviews (BookBridge Parity)**
- **FR-RAT-001:** After ticket `redeemed`, both parties can leave a 1-5 star rating with an optional text comment within 14 days; comments of 50+ chars qualify as detailed reviews and earn the +10 review points (rating-only reviews are legal and earn no points) — realizes UJ-3
- **FR-RAT-002:** Seller profile shows average rating, review count, breakdown (5/4/3/2/1 stars) — realizes UJ-2
- **FR-RAT-003:** Reviews visible on listing modal and seller profile; reviews display the reviewer nickname (never the full name); only verified transactions (redeemed tickets) can review — realizes UJ-2, UJ-3
- **FR-RAT-004:** Buyer ratings also tracked (seller rates buyer) — realizes UJ-3
- **FR-RAT-005:** Seller profile shows public dispute count ("2 disputes on record") alongside ratings — count only, no narrative detail or party names. Population: ONLY tickets attributed to this seller whose dispute was resolved as UPHELD (buyer-favored, `dispute_status='upheld'`); rejected and auto-dismissed disputes do not appear in the count. Count recomputes on resolution — realizes UJ-2

**Points & Ranking System (Model C: Hybrid Campus — 6-Tier Anime-Style)**
- **FR-PTS-001:** Points awarded per action:

| Action | Points | Daily Cap | Pair Cap (same buyer-seller/day) | Notes |
|--------|--------|-----------|----------------------------------|-------|
| Profile verification (email confirm) | +50 | One-time | — | SSO @students.nsbm.ac.lk |
| Listing approved by admin | +5 | 15/day | — | Encourages quality listings |
| Sale completed (seller confirms handover) | +30 | 150/day | 2 counted | Core transaction reward |
| Purchase completed (buyer confirms receipt) | +10 | 50/day | 2 counted | Buyer participation |
| Detailed review left (50+ chars; any time after redemption within 14 days) | +10 | 50/day | — | Quality signal |
| Valid report filed (admin confirms) | +20 | 60/day | — | Community health |
| 7-day login streak | +15 | — | — | Retention (resets on miss) |
| 30-day login streak | +50 | — | — | Milestone bonus |
| Referral: referred user completes first sale | +100 | 1/week | — | Growth lever (deferred to post-MVP) |

  — realizes UJ-3, UJ-4, UJ-5, UJ-7

- **FR-PTS-002:** Rank ladder (6 tiers, anime-style, visible badges):

| Tier | Code | Name | Threshold | Signal |
|------|------|------|-----------|--------|
| 1 | E | Recruit | 0-49 | Gray shield badge |
| 2 | D | Rookie | 50-149 | Blue shield badge |
| 3 | C | Operative | 150-399 | Green shield badge |
| 4 | B | Specialist | 400-799 | Gold shield badge |
| 5 | A | Elite | 800-1499 | Orange shield badge |
| 6 | S | Legend | 1500+ | Red animated crown badge |

Tier calculation (MySQL):
```sql
CASE
  WHEN points >= 1500 THEN 'S'
  WHEN points >= 800  THEN 'A'
  WHEN points >= 400  THEN 'B'
  WHEN points >= 150  THEN 'C'
  WHEN points >= 50   THEN 'D'
  ELSE 'E'
END AS tier
```
  — realizes UJ-7

- **FR-PTS-003:** Tier badges render as inline SVG on profile, ticket pages, listing cards, and seller info in modal. Badge classes: E=bg-secondary, D=bg-primary, C=bg-success, B=bg-warning text-dark, A=bg-danger, S=bg-dark with animated `legend-glow` effect — realizes UJ-7

- **FR-PTS-004:** Points logged in `points_log` table (audit trail with `delta`, `reason`, `reference_type`, `reference_id`, `balance_after`, `metadata` JSON, and `event_uuid` UUID v7 carrying uniqueness via `UNIQUE KEY uniq_event (event_uuid)`); **DB trigger or app-layer enforcement maintains `balance_after`** — realizes UJ-3, UJ-4, UJ-5, UJ-7

- **FR-PTS-005:** Points velocity cap: 150 points/day per user from transactions (sale + purchase + review) [ASSUMPTION-009] — realizes UJ-3

- **FR-PTS-006:** Same buyer-seller pair: max 2 transactions/day counted for points — realizes UJ-3

- **FR-PTS-007:** New accounts: 50% multiplier on TRANSACTION-derived points only (purchase/redemption awards), until the first **5 confirmed redemptions** (`delta = FLOOR(delta * 0.5)` at insert). Verification, streaks, reports, and listing approvals are NEVER halved — realizes UJ-7

- **FR-PTS-008:** Inactivity signal: Active = 1+ action (login, list, buy, review) in last 14 days; **On Break** = 14+ days inactive → grayed-out tier badge + "On Break" tooltip; Re-activation: next action restores full badge instantly (no point penalty) — realizes UJ-7

- **FR-PTS-009:** Leaderboards (summary tables refreshed daily by cron; MySQL has no materialized views):
  - Campus Legends Wall: All-time, Tier S only, Top 20
  - Weekly Risers: Points gained this week (Mon-Sun; week boundaries computed in Asia/Colombo), Top 10 any tier (includes only users with weekly_points >= 50)
  - Category Leaders: Top 3 per category by successful sales
  - Streak Kings: Top 10 by current login streak
  - Privacy: Nickname (user-set display name; fallback: first name only — no student_id digits) + program/year — realizes UJ-7

- **FR-PTS-010:** Anti-farming rules (pragmatic, novice-buildable):
  1. Daily point cap: 150 points from transactions (sale + purchase + review)
  2. Same pair limit: Max 2 transactions/day counted for points between same buyer-seller
  3. New account multiplier: First 5 confirmed redemptions earn 50% points on transaction-derived awards only
  4. Review farming guard: Min 50 chars; review may be left any time after redemption within 14 days; detailed-review points count as transaction-derived and ARE halved under FR-PTS-007 until the first-5 allowance is consumed
  5. Velocity flag: >150 pts/hour → admin review queue (simple SQL check)
  6. Admin flag queue: Points frozen pending review; admin can void or approve. Model: flagging sets `users.points_frozen = TRUE` (new point awards are blocked while frozen); approving clears the flag and earning resumes; voiding inserts a negative `points_log` delta for the flagged events — `delta` is signed (`SMALLINT`), the void is floored at zero (`balance_after` can never go below 0; if the user's balance is already lower, the void is clipped to the current balance), and `users.points` and `users.tier` are recalculated from the new balance — realizes UJ-3, UJ-7

**User Profile Section**
- **FR-PRO-001:** Editable: name, bio, avatar (grid of 12 predefined illustrations), **WhatsApp number** (validated: Sri Lankan mobile `^(\+94|0)7[0-9]{8}$`); nicknames are moderated against a reserved list (staff names) at registration and on edit; privacy fallback everywhere shows nickname-or-first-name only (no student_id last-4 digits) — realizes UJ-1, UJ-7
- **FR-PRO-002:** Shows: rank badge, star row, total points, join date, transaction counts ("12 sales • 8 purchases"), average rating ("★ 4.8 (23 reviews)") — realizes UJ-7
- **FR-PRO-003:** Tabs: **My Listings** | **My Tickets** | **Purchase History** | **Sales History** | **Reviews** — realizes UJ-2, UJ-3
- **FR-PRO-004:** "Verified Student" checkmark (from SSO) displayed on profile and listing cards — realizes UJ-2

**Reporting & Moderation**
- **FR-RPT-001:** Report button on every listing + user profile + ticket (dispute) — realizes UJ-5, UJ-6
- **FR-RPT-002:** Reasons: `scam`, `inappropriate`, `spam`, `wrong_category`, `other`, `dispute` — realizes UJ-5, UJ-6
- **FR-RPT-003:** Admin dashboard: queue with listing/ticket preview, reporter info, action buttons (Dismiss, Remove Listing, Warn User, Ban User, Force Expire Ticket, Force Redeem Ticket) — realizes UJ-6. Write-back rule: dispute outcomes set `reports.status` accordingly — `'resolved'` when an upheld action (Force Redeem / Force Expire) lands, `'dismissed'` when the dispute is rejected or dismissed.
- **FR-RPT-004:** Admin "suspicious activity" flag on users → points frozen pending review — realizes UJ-3

**Landing Page**
- **FR-LND-001:** Public landing page accessible without login — realizes UJ-7
- **FR-LND-002:** Hero: product name (**TicketTrade**), tagline ("Every Trade Ends With Proof"), "Get Started" → register, "Explore Marketplace" → board (redirects to login) — realizes UJ-7
- **FR-LND-003:** Vision & Mission cards — realizes UJ-7
- **FR-LND-004:** How It Works (5 steps): List it → Find it → Claim it → Confirm it → Climb — realizes UJ-7
- **FR-LND-005:** Team section: 6 cards with photo/avatar, name, student ID, role, one-line contribution — realizes UJ-7
- **FR-LND-006:** Footer: NSBM branding, contact, links to GitHub/Drive — realizes UJ-7
- **FR-LND-007:** **Guest browse preview**: "Browse as Guest" shows board but "Buy Now" redirects to login — realizes UJ-7
- **FR-LND-008:** **Corkboard board presentation**: the main browse board renders as a visual corkboard — wood/cork background texture, listings displayed as paper "flyer" cards, each pinned with a pushpin graphic; cards carry a slight deterministic rotation (±2 degrees, seeded by listing id); hovering a card lifts it toward the viewer (scale + shadow). Constraints and acceptance criteria (single authoritative list): (a) rotation/pin styling is purely decorative — ranking/order information MUST NOT depend on it; (b) pin graphic, cork texture, and rotation transforms MUST be aria-hidden / not exposed to assistive tech; (c) a "list view" toggle provides a plain grid fallback with identical listing order and content, exposes state via `aria-pressed`, and persists per session; (d) below the md breakpoint (<768px) the corkboard degrades to the plain grid automatically (no cork texture); (e) honors `prefers-reduced-motion` (hover-lift transitions disabled); (f) touch devices get no hover-lift dependency: tap opens the listing directly and sticky-hover is suppressed; (g) all card text meets WCAG AA contrast (≥4.5:1) against the CARD background, not the cork; (h) card images lazy-load below the fold, the cork texture asset is ≤ 100 KB, and all motion is transform/opacity-only (compositor-friendly) — realizes UJ-2

**Toast Notifications**
- **FR-UX-001:** Toast system for all async actions: success/error/info types, auto-dismiss 4s, queue max 3, accessible (ARIA live region) — realizes UJ-1..UJ-7

### 3.2 Out of Scope (MVP)
- Real payments / gateways
- Chat / messaging between users (WhatsApp share replaces)
- Email notifications (simulation only)
- Multi-language / i18n
- PWA / offline mode
- Formal return/cancellation flow (handled via 7-day expiry + dispute system)
- Real-time notifications (WebSockets/push) — replaced by localStorage sync simulation
- SSO / LMS integration (simulated @students.nsbm.ac.lk verification)
- Advanced search ranking (MySQL FULLTEXT only)
- Buyer identity verification at redemption (code = trust signal, simulated payments)
- Algorithmic reputation scores (simple tiers + ratings only)
- Multi-badge systems (single 6-tier ladder only)

---

## 4. State Machines

### 4.1 Listing State Machine
```
                    ┌─────────────┐
                    │    DRAFT    │ (seller editing, images managed)
                    └──────┬──────┘
                           │ submit
                           ▼
                    ┌─────────────┐
                    │   PENDING   │ (awaiting review; hourly cron auto-approves at 24h)
                    └──────┬──────┘
                           │ admin approve OR 24h auto-approve / reject
                ┌──────────┴──────────┐
                ▼                     ▼
         ┌───────────┐          ┌───────────┐
         │  ACTIVE   │          │ REJECTED  │
         │ (for sale)│          └─────┬─────┘
         └─────┬─────┘                │ edit & resubmit
               │ buy (ticket created)  ▼
               ▼                 ┌───────────┐
         ┌─────────────┐         │  PENDING  │ (back to review queue)
         │    SOLD     │         └───────────┘
         └─────────────┘
               │ relist
               ▼
         ┌─────────────┐
         │    DRAFT    │ (copied, quantity editable)
         └─────────────┘
```

Fast-track edges (PRFAQ reconciliation):
- `ACTIVE` --edit--> `ACTIVE`: listing stays live; `review_flag` set; admin re-check clears flag (FR-LST-010)
- `DRAFT` (relist of previously-approved source) --submit--> `ACTIVE`: skips `PENDING` (FR-LST-015)
- `REJECTED` --edit & resubmit--> `PENDING`: rejected listing edited by seller returns to review queue (FR-LST-010)

Note: `REMOVED` is a distinct terminal node reached only by admin moderation action on an `ACTIVE` listing (`ACTIVE` --admin removes--> `REMOVED`). Rejection and removal are different outcomes: rejection ends the approval path from `PENDING`; removal takes down live content.

### 4.2 Ticket State Machine
```
                    ┌─────────────┐
                    │   ACTIVE    │ (for sale)
                    └──────┬──────┘
           ┌──────────────┼──────────────┐
           │              │              │
      ┌────▼─────┐   ┌────▼─────┐   ┌────▼─────┐
      │ REDEEMED │   │ EXPIRED  │   │ DISPUTED │
      │(complete)│   │(7 days)  │   │(pending) │
      └──────────┘   └──────────┘   └────┬─────┘
                                         │ admin resolves
                              ┌──────────┼──────────┐
                              ▼          ▼          ▼
                         ┌────────┐ ┌────────┐ ┌──────────┐
                         │ Force  │ │ Force  │ │ Dismiss  │
                         │ Expire │ │ Redeem │ │ (auto 3d)│
                         └───┬────┘ └───┬────┘ └────┬─────┘
                             ▼          ▼           ▼
                        ┌─────────┐ ┌──────────┐ ┌─────────────────────┐
                        │ EXPIRED │ │ REDEEMED │ │ ACTIVE              │
                        └─────────┘ └──────────┘ │ (original created_  │
                                                 │  at preserved,      │
                                                 │  FR-TKT-013)        │
                                                 └─────────────────────┘
```
Dispute resolution lands in three distinct terminal states matching section 4.3 and FR-TKT-008: Force Expire → `EXPIRED`, Force Redeem → `REDEEMED`, Dismiss → `ACTIVE`. Only the Dismiss branch preserves the original `created_at` (FR-TKT-013).

**Composition note:** If a dispute is dismissed (or auto-dismissed) after the ticket's `expires_at` has passed, the ticket returns to `active` with an expired `expires_at`; the next cron tick immediately expires it. This is the intended behavior: a dismissed dispute on an already-expired ticket yields immediate expiry (the buyer's dispute window closes with the ticket expiring moments later).

### 4.3 Dispute State Machine
```
┌─────────────┐
│   NONE      │ (normal ticket)
└──────┬──────┘
       │ dispute filed
       ▼
┌─────────────┐
│  PENDING    │ (admin review, excluded from cron)
└──────┬──────┘
       │ admin action / auto 3 days
  ┌────┼────┐
  ▼    ▼    ▼
┌────┐ ┌────┐ ┌──────────┐
│ EXP│ │RED │ │ REJECTED │
│IRED│ │EEM │ │ (back to │
│    │ │ED  │ │ ACTIVE)  │
└────┘ └────┘ └──────────┘
```

---

## 5. Non-Functional Requirements

### 5.1 Security
- **NFR-SEC-001:** Passwords hashed with bcrypt (cost ≥ 12) — never plaintext
- **NFR-SEC-002:** All SQL via prepared statements (PDO) — no concatenation
- **NFR-SEC-003:** CSRF tokens on all state-changing forms (synchronizer token pattern, `hash_equals()` validation)
- **NFR-SEC-004:** File uploads: 4-layer validation (finfo MIME, getimagesize dimensions ≤4000px/5MB, magic bytes, GD re-encode to WebP); **max chunk 2MB, total 5MB**; store outside webroot; serve via PHP proxy (thumbnails public, full-size images auth-checked)
- **NFR-SEC-005:** Input validation: server-side + client-side; XSS prevention via `htmlspecialchars` on output
- **NFR-SEC-006:** Session cookies: `HttpOnly`, `Secure` (in prod), `SameSite=Strict`, `use_strict_mode=1`, `sid_length=48`
- **NFR-SEC-007:** Rate limits: login 5/5min per IP, purchase 10/hr per user, listing_create 20/hr/user, points 150/day/user, **redemption 5/hr/ticket**; **per-user limits (not just IP)**
- **NFR-SEC-008:** Security headers: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, CSP with CDN allowances
- **NFR-SEC-009:** **WhatsApp number validation**: server regex `^(\+94|0)7[0-9]{8}$` (Sri Lankan mobile)
- **NFR-SEC-010:** **Admin re-auth**: password confirmation required for ban, promote, delete, bulk actions

### 5.2 Performance
- **NFR-PER-001:** Page load < 2s on localhost (uncached)
- **NFR-PER-002:** Board view loads ≤ 50 listings per page (pagination)
- **NFR-PER-003:** Image thumbnails generated on upload (3 sizes: 200px, 600px, 1200px, all WebP 80% quality)
- **NFR-PER-004:** Cron job (ticket expiry) completes < 30s for 10k tickets (single guarded UPDATE)
- **NFR-PER-005:** Leaderboard queries use indexes over summary tables refreshed daily by cron (MySQL has no materialized views)
- **NFR-PER-006:** Indexes: `tickets (status, created_at)`, `points_log (user_id, created_at)`, `listings (seller_id, status)`

### 5.3 Usability
- **NFR-USE-001:** Responsive: mobile-first, breakpoints at 576px, 768px, 992px, 1200px (Bootstrap 5 grid)
- **NFR-USE-002:** Keyboard navigable: all modals trap focus, ESC closes, ARIA labels on interactive elements; **focus returns to trigger on close**
- **NFR-USE-003:** Color contrast: WCAG AA minimum (4.5:1 text, 3:1 UI elements) — verified in test plan via axe-core or manual audit
- **NFR-USE-004:** Toast notifications for all async actions (success, error, info) — spec'd in FR-UX-001
- **NFR-USE-005:** Progressive disclosure: tier privileges explained on hover, not separate page

### 5.4 Reliability
- **NFR-REL-001:** Idempotent ticket redemption (re-redeeming returns current state, not error); a correct-code resubmission is idempotent and does NOT consume a rate-limit attempt
- **NFR-REL-002:** Idempotent cron job (re-running same day produces no duplicate effects); **staging replay: `TRUNCATE cron_log; php jobs/ticket_expiry.php` = identical result** for ticket expiry; documented exception: the 24h auto-approval sweep is intentionally non-idempotent across days (see NFR-OPS-003)
- **NFR-REL-003:** Database foreign keys with `ON DELETE CASCADE` / `SET NULL` / `RESTRICT` where appropriate
- **NFR-REL-004:** Atomic UPDATE for ticket redemption (no explicit transaction needed)
- **NFR-REL-005:** Points ledger uniqueness via `UNIQUE KEY uniq_event (event_uuid)` — one row per points event, covering retries and closing the duplicate-NULL hole of composite nullable keys
- **NFR-REL-006:** **FK `tickets.listing_id` ON DELETE RESTRICT** — seller cannot delete listing with active tickets

### 5.5 Compliance & Regulatory (from domain research)
- **NFR-CMP-001:** Computer Crimes Act No. 24 of 2007 Section 26 — the platform DOES screen content pre-publication: by default every listing enters the 24-hour review window (admin decision or auto-approval) before it goes active; the sole exception is the narrow FR-LST-015 fast-track, where already-approved content that is edited-and-relisted or resubmitted returns to active immediately and is re-checked via the admin review_flag queue. Because brand-new listings always enter pending and flagged relisted content stays under admin re-check, the platform does NOT rely on the intermediary safe-harbor "no proactive curation" argument. **Action:** the compliance basis is notice-and-takedown plus the tamper-evident audit trail (FR-ADM-006); document the moderation policy in the project report. [ASSUMPTION-004] NSBM IT policy aligns
- **NFR-CMP-002:** PDPA No. 9 of 2022 — substantive provisions not yet in force as of Aug 2026. **Action:** Minimal personal data collected (name, student ID, email, optional WhatsApp); no sensitive data. [ASSUMPTION-002] No PDPA compliance work needed for MVP
- **NFR-CMP-003:** Consumer protection — simulated transactions (no real money) likely exempt. **Action:** Clear "simulation only" labeling on all purchase flows. [ASSUMPTION-003] CAA Act does not apply
- **NFR-CMP-004:** NSBM IT policy for student projects — not found in public sources. **Action:** Document hosting, data retention, and liability in project report. [ASSUMPTION-001] Faculty sponsor approves deployment
- **NFR-CMP-005:** Data retention — user accounts retained indefinitely; tickets retained 1 year post-expiry/redemption; points_log retained indefinitely for audit; reports retained 2 years. Deletion on user request (GDPR-style) not required for MVP but documented for post-MVP. [ASSUMPTION-001] Aligns with NSBM IT policy

### 5.6 Operational
- **NFR-OPS-001:** Dev server: `php -S localhost:8000` from project root
- **NFR-OPS-002:** Migrations: `php migrate.php` (idempotent, versioned)
- **NFR-OPS-003:** Cron — Ticket Expiry (hourly): `php jobs/ticket_expiry.php` (idempotent for expiry itself, skips disputed tickets, **file lock via `flock()`**, **timezone = Asia/Colombo**); **this job ALSO owns the 24h listing auto-approval sweep** (see FR-LST-007 / FR-ADM-002) **and the 3-day dispute auto-dismiss** (see FR-TKT-009). Documented exception to NFR-REL-002: staging replay of auto-approvals is intentionally non-idempotent ACROSS days — the 24h cutoff advances with wall-clock time, so replaying on a later day approves a different set of listings. Same-day replay remains idempotent. **cron_log table** (job, run_at, affected_rows, duration_ms, status); **manual trigger endpoint** `POST /admin/cron/ticket-expiry` (admin only)
- **NFR-OPS-004:** Cron — Daily Leaderboards (02:00): `php jobs/daily_cron.php` (refresh leaderboard summary tables, update inactivity flags, streak updates, cache leaderboards to JSON); **file lock via `flock()`**; logs to `cron_log`
- **NFR-OPS-005:** Code style: PSR-12 — `vendor/bin/phpcs --standard=PSR12 src/`
- **NFR-OPS-006:** Git: never push to main; PRs only, one approval required
- **NFR-OPS-007:** Composer packages: `ramsey/uuid` (UUID v7) — no other deps

---

## 6. Data Model (Logical)

### 6.1 Core Tables

Dispute model (dual-field): every ticket carries BOTH `tickets.status` ENUM('active','redeemed','expired','disputed') AND `tickets.dispute_status` ENUM('none','pending','upheld','rejected') NOT NULL DEFAULT 'none'. Filing a dispute on an ACTIVE ticket sets `status='disputed'` AND `dispute_status='pending'`. Resolution sets `dispute_status` to `'upheld'`/`'rejected'` and restores the underlying ticket state per outcome: Force Expire → `expired`, Force Redeem → `redeemed`, Dismiss → `active`. Ticket writer note: `expires_at` is set at ticket creation as `created_at + INTERVAL 7 DAY` in Asia/Colombo; the expiry cron reads the stored value.

```sql
-- Users (extends existing auth)
CREATE TABLE users (
  user_id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  email            VARCHAR(190) NOT NULL UNIQUE,
  password_hash    VARCHAR(255) NOT NULL,
  full_name        VARCHAR(100) NOT NULL,
  nickname         VARCHAR(50) UNIQUE,
  student_id       VARCHAR(20) NOT NULL UNIQUE,
  program          VARCHAR(100),
  year             TINYINT UNSIGNED,
  avatar_path      VARCHAR(255),
  is_verified      BOOLEAN DEFAULT FALSE,
  is_admin         BOOLEAN DEFAULT FALSE,
  is_banned        BOOLEAN DEFAULT FALSE,
  points           INT UNSIGNED DEFAULT 0,
  points_frozen    BOOLEAN DEFAULT FALSE,
  tier             CHAR(1) DEFAULT 'E',
  current_streak   SMALLINT UNSIGNED DEFAULT 0,
  longest_streak   SMALLINT UNSIGNED DEFAULT 0,
  last_active_at   DATETIME,
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at       DATETIME NULL,
  INDEX idx_email (email),
  INDEX idx_tier_points (tier, points DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categories
CREATE TABLE categories (
  category_id      SMALLINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name             VARCHAR(50) NOT NULL UNIQUE,
  slug             VARCHAR(60) NOT NULL UNIQUE,
  icon             VARCHAR(50),
  sort_order       SMALLINT UNSIGNED DEFAULT 0,
  is_active        BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Listings
CREATE TABLE listings (
  listing_id       BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  seller_id        BIGINT UNSIGNED NOT NULL,
  category_id      SMALLINT UNSIGNED NOT NULL,
  type             ENUM('product','service') NOT NULL,
  title            VARCHAR(150) NOT NULL,
  description      TEXT NOT NULL,
  price_cents      INT UNSIGNED NOT NULL,
  quantity         SMALLINT UNSIGNED DEFAULT 1,
  item_condition   ENUM('new','like_new','good','fair') NULL,
  duration_minutes SMALLINT UNSIGNED NULL,
  delivery_method  ENUM('in_person','online','both') NULL,
  availability     JSON NULL,
  status           ENUM('draft','pending','active','rejected','sold','removed') DEFAULT 'draft',
  reject_reason    TEXT NULL,
  views            INT UNSIGNED DEFAULT 0,
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  approved_at      DATETIME NULL,
  approved_by      BIGINT UNSIGNED NULL,
  review_flag      BOOLEAN DEFAULT FALSE,
  flagged_at       DATETIME NULL,
  FOREIGN KEY (seller_id) REFERENCES users(user_id),
  FOREIGN KEY (category_id) REFERENCES categories(category_id),
  FOREIGN KEY (approved_by) REFERENCES users(user_id),
  INDEX idx_seller_status (seller_id, status),
  INDEX idx_category_status (category_id, status),
  INDEX idx_status_created (status, created_at DESC),
  INDEX idx_review_flag (review_flag),
  FULLTEXT INDEX ft_title_desc (title, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Listing Images
CREATE TABLE listing_images (
  image_id         BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  listing_id       BIGINT UNSIGNED NOT NULL,
  file_path        VARCHAR(255) NOT NULL,
  original_name    VARCHAR(255),
  mime_type        VARCHAR(50),
  file_size        INT UNSIGNED,
  sort_order       TINYINT UNSIGNED DEFAULT 0,
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (listing_id) REFERENCES listings(listing_id) ON DELETE CASCADE,
  INDEX idx_listing (listing_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tickets (Simulated Purchases)
CREATE TABLE tickets (
  ticket_id        BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  ticket_code      VARCHAR(30) NOT NULL UNIQUE,
  listing_id       BIGINT UNSIGNED NOT NULL,
  buyer_id         BIGINT UNSIGNED NOT NULL,
  seller_id        BIGINT UNSIGNED NOT NULL,
  status           ENUM('active','redeemed','expired','disputed') DEFAULT 'active',
  dispute_status   ENUM('none','pending','upheld','rejected') NOT NULL DEFAULT 'none',
  price_cents      INT UNSIGNED NOT NULL,
  session_number   SMALLINT UNSIGNED DEFAULT 1,
  total_sessions   SMALLINT UNSIGNED DEFAULT 1,
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at       DATETIME NOT NULL,
  redeemed_at      DATETIME NULL,
  disputed_at      DATETIME NULL,
  resolved_at      DATETIME NULL,
  resolution_note  TEXT NULL,
  FOREIGN KEY (listing_id) REFERENCES listings(listing_id),
  FOREIGN KEY (buyer_id) REFERENCES users(user_id),
  FOREIGN KEY (seller_id) REFERENCES users(user_id),
  INDEX idx_buyer_status (buyer_id, status),
  INDEX idx_seller_status (seller_id, status),
  INDEX idx_status_expires (status, expires_at),
  INDEX idx_code (ticket_code),
  INDEX idx_listing_sessions (listing_id, buyer_id, session_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reviews
CREATE TABLE reviews (
  review_id        BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  ticket_id        BIGINT UNSIGNED NOT NULL,
  reviewer_id      BIGINT UNSIGNED NOT NULL,
  reviewee_id      BIGINT UNSIGNED NOT NULL,
  rating           TINYINT UNSIGNED NOT NULL,
  comment          TEXT,
  reviewer_role    ENUM('buyer','seller') NOT NULL,
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES tickets(ticket_id),
  FOREIGN KEY (reviewer_id) REFERENCES users(user_id),
  FOREIGN KEY (reviewee_id) REFERENCES users(user_id),
  UNIQUE KEY uq_review_per_role (ticket_id, reviewer_role),
  INDEX idx_reviewee (reviewee_id, created_at DESC),
  INDEX idx_reviewer (reviewer_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Points Ledger (Append-only; authoritative schema — supersedes all other variants)
CREATE TABLE points_log (
  id               BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id          BIGINT UNSIGNED NOT NULL,
  delta            SMALLINT NOT NULL,
  reason           VARCHAR(100),
  reference_type   ENUM('verification','listing','transaction','streak','report','admin') NOT NULL,
  reference_id     BIGINT UNSIGNED NULL,
  balance_after    INT NOT NULL,
  metadata         JSON,
  event_uuid       CHAR(36) NOT NULL, -- UUID v7
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  UNIQUE KEY uniq_event (event_uuid),
  INDEX idx_user_created (user_id, created_at DESC),
  INDEX idx_reference (reference_type, reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reports / Flags
CREATE TABLE reports (
  report_id        BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  reporter_id      BIGINT UNSIGNED NOT NULL,
  target_type      ENUM('listing','user','review','ticket') NOT NULL,
  target_id        BIGINT UNSIGNED NOT NULL,
  reason           VARCHAR(100) NOT NULL,
  description      TEXT,
  status           ENUM('pending','reviewing','resolved','dismissed') DEFAULT 'pending',
  admin_id         BIGINT UNSIGNED NULL,
  admin_note       TEXT NULL,
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  resolved_at      DATETIME NULL,
  FOREIGN KEY (reporter_id) REFERENCES users(user_id),
  FOREIGN KEY (admin_id) REFERENCES users(user_id),
  INDEX idx_target (target_type, target_id),
  INDEX idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Login Streaks (AUTHORITATIVE source of streak truth; users.current_streak/longest_streak are denormalized
-- display copies refreshed by the daily cron — writes go to login_streaks only)
CREATE TABLE login_streaks (
  user_id          BIGINT UNSIGNED PRIMARY KEY,
  current_streak   SMALLINT UNSIGNED DEFAULT 0,
  longest_streak   SMALLINT UNSIGNED DEFAULT 0,
  last_login_date  DATE NULL,
  updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Referrals (Deferred to post-MVP)
CREATE TABLE referrals (
  referral_id      BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  referrer_id      BIGINT UNSIGNED NOT NULL,
  referred_id      BIGINT UNSIGNED NOT NULL UNIQUE,
  code             VARCHAR(12) NOT NULL UNIQUE,
  status           ENUM('pending','completed') DEFAULT 'pending',
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  completed_at     DATETIME NULL,
  FOREIGN KEY (referrer_id) REFERENCES users(user_id),
  FOREIGN KEY (referred_id) REFERENCES users(user_id),
  INDEX idx_referrer (referrer_id),
  INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit Log
CREATE TABLE audit_log (
  id               BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  actor_id         BIGINT UNSIGNED NOT NULL,
  actor_role       ENUM('admin','system') NOT NULL,
  action           VARCHAR(100) NOT NULL,
  target_type      VARCHAR(50),
  target_id        BIGINT UNSIGNED,
  old_values       JSON,
  new_values       JSON,
  ip_address       VARBINARY(16),
  user_agent       TEXT,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  prev_hash        CHAR(64),
  INDEX idx_actor_created (actor_id, created_at),
  INDEX idx_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cron Log
CREATE TABLE cron_log (
  id               BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  job              VARCHAR(100),
  run_at           TIMESTAMP,
  affected_rows    INT,
  duration_ms      INT,
  status           ENUM('success','failed','skipped'),
  error            TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 6.2 Leaderboard Summary Tables (Refreshed Daily by Cron; MySQL Has No Materialized Views)

```sql
-- Summary tables written by jobs/daily_cron.php at 02:00 Asia/Colombo.
-- Week boundaries for weekly_risers are computed in Asia/Colombo (Mon 00:00 local).

CREATE TABLE weekly_risers (
  user_id       BIGINT UNSIGNED PRIMARY KEY,
  display_name  VARCHAR(60) NOT NULL,
  program       VARCHAR(100),
  year          TINYINT UNSIGNED,
  tier          CHAR(1),
  weekly_points INT DEFAULT 0,
  refreshed_at  DATETIME NOT NULL
);

-- Daily refresh: TRUNCATE + INSERT
INSERT INTO weekly_risers (user_id, display_name, program, year, tier, weekly_points, refreshed_at)
SELECT
  u.user_id,
  COALESCE(u.nickname, SUBSTRING_INDEX(u.full_name, ' ', 1)) AS display_name,
  u.program,
  u.year,
  u.tier,
  SUM(pl.delta) AS weekly_points,  -- NET points (signed deltas): voided/admin-adjusted events subtract; gross-vs-net decided 2026-08-26 — net, so a voided farming week drops off the board
  NOW()
FROM users u
JOIN points_log pl ON pl.user_id = u.user_id
WHERE u.is_banned = FALSE
  AND pl.created_at >= :week_start_colombo
  AND pl.created_at <  :week_end_colombo
GROUP BY u.user_id
HAVING weekly_points >= 50
ORDER BY weekly_points DESC
LIMIT 10;

CREATE TABLE campus_legends (
  user_id       BIGINT UNSIGNED PRIMARY KEY,
  display_name  VARCHAR(60) NOT NULL,
  program       VARCHAR(100),
  year          TINYINT UNSIGNED,
  points        INT DEFAULT 0,
  tier          CHAR(1),
  refreshed_at  DATETIME NOT NULL
);

INSERT INTO campus_legends (user_id, display_name, program, year, points, tier, refreshed_at)
SELECT
  user_id,
  COALESCE(nickname, SUBSTRING_INDEX(full_name, ' ', 1)) AS display_name,
  program,
  year,
  points,
  tier,
  NOW()
FROM users
WHERE tier = 'S' AND is_banned = FALSE
ORDER BY points DESC
LIMIT 20;

CREATE TABLE category_leaders (
  category_id   SMALLINT UNSIGNED NOT NULL,
  user_id       BIGINT UNSIGNED NOT NULL,
  display_name  VARCHAR(60) NOT NULL,
  tier          CHAR(1),
  sales_count   INT DEFAULT 0,
  refreshed_at  DATETIME NOT NULL,
  PRIMARY KEY (category_id, user_id)
);

INSERT INTO category_leaders (category_id, user_id, display_name, tier, sales_count, refreshed_at)
SELECT category_id, user_id, display_name, tier, sales_count, refreshed_at FROM (
  SELECT
    c.category_id,
    u.user_id,
    COALESCE(u.nickname, SUBSTRING_INDEX(u.full_name, ' ', 1)) AS display_name,
    u.tier,
    COUNT(t.ticket_id) AS sales_count,
    NOW() AS refreshed_at,
    ROW_NUMBER() OVER (PARTITION BY c.category_id ORDER BY COUNT(t.ticket_id) DESC) AS rn
  FROM tickets t
  JOIN listings l ON l.listing_id = t.listing_id
  JOIN categories c ON c.category_id = l.category_id
  JOIN users u ON u.user_id = t.seller_id
  WHERE t.status = 'redeemed' AND u.is_banned = FALSE
  GROUP BY c.category_id, u.user_id
) ranked
WHERE rn <= 3;

CREATE TABLE streak_kings (
  user_id             BIGINT UNSIGNED PRIMARY KEY,
  current_streak      SMALLINT UNSIGNED DEFAULT 0,
  last_increment_date DATE NULL,
  refreshed_at        DATETIME NOT NULL
);
-- Refreshed daily at 02:00 Asia/Colombo by jobs/daily_cron.php alongside the other boards.
```

Privacy fallback on all boards: nickname-or-first-name only (no student_id digits). Week boundaries and all cron times run in Asia/Colombo.

### 6.3 Key Relationships
- User 1:N Listing (seller)
- User 1:N Ticket (buyer)
- User 1:N Ticket (seller)
- Listing 1:N Ticket
- Listing 1:N ListingImage
- Category 1:N Listing
- User 1:N PointsLog
- User 1:N Report (reporter)
- User 1:N Report (admin resolver)
- Ticket 1:1 Review (buyer) + 1:1 Review (seller)
- User 1:N AuditLog (actor)
- **Listing 1:N Ticket: ON DELETE RESTRICT** (prevents deletion with active tickets)
- User 1:1 LoginStreaks
- User 1:N Referrals (referrer) + 1:1 Referrals (referred) — deferred to post-MVP

## 7. WAD Topic 4 Traceability Matrix
*Mapping from WAD_Batch26.md Topic 4 to PRD FRs — critical for assignment grading*

| Assignment Requirement | PRD FRs | Status |
|------------------------|---------|--------|
| Separate Admin and Student/User interfaces | FR-AUTH-002, FR-ADM-001..009, FR-PRO-001..004, all student FRs | ✅ |
| Secure login functionality | FR-AUTH-001..006, NFR-SEC-001..010 | ✅ |
| Complete CRUD operations | FR-LST-001, FR-LST-010, FR-LST-014..017, FR-ADM-002, FR-ADM-003, FR-PRO-001 | ✅ |
| Responsive and user-friendly interface | NFR-USE-001..005, FR-LST-003..006, FR-UX-001 | ✅ |
| Database integration using PHP and MySQL | Data Model (§6), all FRs | ✅ |
| Proper form validation | NFR-SEC-005, FR-AUTH-003, FR-LST-001, FR-BUY-001, FR-TKT-008 | ✅ |
| Navigation menus and consistent page layouts | NFR-USE-001, FR-LND-001..008 | ✅ |
| Browse and display listings (board presentation) | FR-LND-008 (corkboard board view) | ✅ |
| Search and filtering functionality | FR-LST-003 | ✅ |
| **Additional innovative features** (encouraged) | Ticket system, Points/Rank (Model C), Dispute flow, Quantity model, WhatsApp share, Seller ratings, Audit log, Draft/Relist flow, Corkboard board view (FR-LND-008) | ✅ |

### Admin Expected Features (per Topic 4)
| Feature | PRD FRs |
|---------|---------|
| Login | FR-AUTH-001, FR-AUTH-002 |
| Manage users | FR-ADM-001 |
| Approve product listings | FR-ADM-002 |
| Manage product categories | FR-ADM-003 |
| View sales reports | FR-ADM-005, FR-ADM-007 |

### Student/User Expected Features (per Topic 4)
| Feature | PRD FRs |
|---------|---------|
| Register and login | FR-AUTH-001 |
| Add products or services | FR-LST-001, FR-LST-002 |
| Edit and remove listings | FR-LST-010, FR-LST-016 |
| Browse products | FR-LST-003 |
| Search by category | FR-LST-003 |
| Simulate product purchases | FR-TKT-001, FR-TKT-006, FR-BUY-001 |
| Manage personal listings | FR-LST-016 |

---

## 8. Success Metrics

| Metric | Target (MVP) | Measurement |
|--------|--------------|-------------|
| Student registrations | ≥ 50 (demo: ~50 users) | `users` count |
| Active listings (approved) | ≥ 30 (demo: ~100 listings) | `listings` where `status='active'` |
| Ticket redemption rate | ≥ 60% | `tickets` where `status='redeemed'` / `status IN ('redeemed','expired')` |
| Dispute rate | < 10% | `tickets` where `dispute_status != 'none'` / total tickets (clean redemptions never count) |
| Dispute upheld rate | tracked, no MVP target | `tickets` where `dispute_status = 'upheld'` / `tickets` where `dispute_status != 'none'` |
| Avg time to first purchase | < 7 days | `MIN(tickets.created_at) - users.created_at` per buyer |
| Rank progression | ≥ 5 users reach Operative (C) (150 pts) via confirmed transactions | `users` where `tier IN ('C','B','A','S')` |
| Seller rating coverage | ≥ 80% redeemed tickets have review | `reviews` / `tickets` where `status='redeemed'` |
| Page load (board) | < 2s | Browser dev tools on localhost |
| Zero critical security findings | 0 | Manual review + automated checks |

**Counter-metrics** (guardrails):
- Points farming velocity: flag any user >300 pts/day (>150 pts/hour also surfaced); the >300 pts/day threshold sits deliberately above the computed legitimate ceiling of ~275 pts/day (150 transactions + 50 max single-day streak bonus + 60 reports + 15 approvals; the 7-day +15 and 30-day +50 streak bonuses cannot land on the same date), and >150 pts/hour sits at or above the maximum legitimate hourly burst
- Collusion detection: flag when the same buyer/seller pair holds >3 tickets/day (above the pair cap; the third ticket is legitimate but uncounted)
- Admin action latency: median time to resolve report < 48h
- Review farming: min 50 chars + 14-day post-redemption window enforced

## 9. Risks & Mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Founding cohort graduation kills platform | High | High | Honest stance: no long-term plan yet; usage decides continuation. Document everything in project report for handover readiness; if faculty push on viability, succession becomes the first fast-follow commitment (one meeting with a faculty sponsor) |
| Trust collapse (scams go unresolved) | Medium | High | Dispute system + admin queue + 3-day auto-dismiss + zero-loss expiry (no points deducted at purchase) + seller ratings |
| Novice team cannot deliver in 3 weeks | Medium | High | Scope locked to MVP; daily standups; pair programming; reuse Bootstrap components |
| Week-2 crunch forces scope cuts mid-sprint | Medium | High | Pre-agreed sacrifice order: leaderboards → bulk admin actions → login streaks → draft/relist flow [ASSUMPTION-011]. The core loop (list, approve, ticket, redeem, expire, dispute) is never cut. Cut-decision owner named via OQ-011 before crunch starts |
| Cron job fails / not deployed | Medium | Medium | Idempotent design; manual trigger endpoint for testing; log every run in `cron_log` |
| Race condition on ticket redemption | Low | High | Atomic UPDATE with status guard; idempotent redemption logic |
| WhatsApp sharing fails (no number) | Medium | Low | Graceful fallback: copy-to-clipboard only; show "Seller has not shared WhatsApp" |
| `@students.nsbm.ac.lk` verification not enforced | Low | Medium | Simulated verification in MVP; document as known limitation |
| Review inflation (fake 5-stars) | Medium | Medium | Min 50 chars, 14-day post-redemption window, only on redeemed tickets, buyer+seller both rate |
| Low transaction volume → empty leaderboards | High | Medium | Seed demo data; Weekly Risers shows activity even at low volume |

---

## 10. Glossary
| Term | Definition |
|------|------------|
| **TicketTrade** | Final product name (PRFAQ Stage 2 decision); internal artifacts keep working title "NSBM Marketplace" |
| **Flyer** | Legacy term for "listing" — used in early briefs; UI uses "Listing" |
| **Ticket** | Digital proof of purchase with unique code in normative format `TK-XXXXXXXXXXXXXXXXXXXXXX` (`TK-` + 22 random base62 chars from `random_bytes(16)`; codes carry ≥125 bits of randomness and are not derived from timestamps); trust layer between buyer and seller |
| **Redemption** | Seller validates buyer's ticket code → marks transaction complete → awards points to both parties |
| **Quantity** | Number of units (products) or sessions (services) available per listing |
| **Quantity Sold** | Counter incremented per ticket created; listing auto-sold when equal to quantity |
| **Dispute** | Buyer or seller flags a ticket; admin resolves via Force Expire / Force Redeem / Dismiss |
| **Rank** | Gamified reputation tier (E → D → C → B → A → S) based on cumulative points: Recruit (0-49), Rookie (50-149), Operative (150-399), Specialist (400-799), Elite (800-1499), Legend (1500+) |
| **Badge** | Visual rank indicator (shield/crown icons) rendered as inline SVG with tier-specific animations |
| **Points Log** | Immutable audit trail of every points change (delta, reason, reference, balance_after); uniqueness per event via `event_uuid` + `uniq_event` |
| **Simulated Purchase** | No real money; "Buy Now" creates ticket, points awarded on redemption |
| **Cron** | Hourly PHP script (`jobs/ticket_expiry.php`) that expires 7-day-old active tickets; daily cron (`jobs/daily_cron.php`) refreshes leaderboards |
| **Atomic UPDATE** | Single MySQL UPDATE with WHERE guard — naturally idempotent, no transaction needed |
| **UUID v7** | Timestamp-ordered UUID for better index locality; used for internal row IDs only (e.g., `points_log.event_uuid`) — never for ticket codes |
| **Hash Chain** | `prev_hash = sha256(prev_hash || current_row)` — tamper-evident audit log |
| **On Break** | Inactivity label shown after 14 days no action; grayed badge + tooltip |
| **Velocity Flag** | Admin alert when user earns >300 pts/day or >150 pts/hour — potential farming; thresholds sit above the maximum legitimate daily (~290) and hourly (150) ceilings |
| **Pair Cap** | Max 2 counted transactions/day between same buyer-seller pair |
| **New Account Multiplier** | First 5 confirmed redemptions earn 50% points on transaction-derived awards only (FR-PTS-007, anti-farming) |
| **Session** | For service listings: one unit of service delivery (e.g., one tutoring hour); the single purchase ticket carries `total_sessions` and `session_number` tracks confirmed handovers — never one ticket per session |

---

## 11. Assumptions Index
| ID | Assumption | Source | Validation Plan |
|----|------------|--------|-----------------|
| [ASSUMPTION-001] | NSBM IT policy allows student project hosting with faculty sponsor | Research gap (NFR-CMP-004) | Confirm with faculty sponsor before deployment |
| [ASSUMPTION-002] | PDPA No. 9 of 2022 not in force → no compliance work needed | Research (verified ref [8]) | Monitor PDPA commencement notices |
| [ASSUMPTION-003] | Simulated transactions exempt from consumer protection | Research (unverified ref [9]) | Legal review if project continues post-MVP |
| [ASSUMPTION-004] | Computer Crimes Act posture holds with pre-publication screening documented via notice-and-takedown + audit trail (platform does not claim safe harbor) | Research (verified ref [6]); NFR-CMP-001 rewrite 2026-08-26 | Document moderation policy in report |
| [ASSUMPTION-005] | 3-week timeline feasible for 6 novice PHP/MySQL developers | Course context | Sprint planning with buffered estimates |
| [ASSUMPTION-006] | Bootstrap 5 via CDN acceptable for coursework | AGENTS.md | Bundle for production if deployed beyond coursework |
| [ASSUMPTION-007] | `@students.nsbm.ac.lk` email domain is stable and verifiable | Assignment spec | Test with sample student emails |
| [ASSUMPTION-008] | WhatsApp number format: Sri Lankan mobile (e.g., +94771234567) | Local context | Input mask + server validation |
| [ASSUMPTION-009] | Points velocity cap (150/day) prevents farming without false positives | Ranking research Model C | Monitor in staging; adjust if needed |
| [ASSUMPTION-010] | QR code removal does not reduce trust for coursework demo | Forged idea outcome | Demo uses code display + WhatsApp share |
| [ASSUMPTION-011] | Simple 1-5 star ratings sufficient for trust (BookBridge parity); badges alone underperform (Hamari), so ranks are a supporting signal, not the headline trust mechanism | Market research | **Load-bearing gate:** week-2 user test; if students ignore ranks entirely, execute the pre-agreed cut order starting with leaderboards |
| [ASSUMPTION-012] | UUID v7 available via `ramsey/uuid` v4.7+ | Technical research | Verify composer install |
| [ASSUMPTION-013] | MySQL session handler works on target hosting | Technical research | Test on staging |
| [ASSUMPTION-014] | 6-tier anime-style ranks (E→S) are intuitive and motivating for NSBM students | Spec decision 2026-08-24 | Week-2 user test; fallback to 4-tier if confusing |
| [ASSUMPTION-015] | Seller confirmation = both parties get points (BookBridge pattern) | Spec decision 2026-08-24 | Verified in BookBridge research; no buyer confirmation needed |
| [ASSUMPTION-016] | One service ticket per purchase: `total_sessions` = sessions bought, `session_number` tracks confirmed handovers, full points award on final session confirmation (per-session flow defined in FR-TKT-014); service purchase increments `quantity_sold` by `total_sessions` at ticket creation | Spec decision 2026-08-24; reconciled to D4 2026-08-26 | Service purchase yields exactly one ticket with `total_sessions` set; seller confirms each session |
| [ASSUMPTION-017] | User-set nicknames for leaderboard privacy (no SSO fields needed) | Spec decision 2026-08-24; fallback narrowed to first name 2026-08-26 | Registration includes nickname field moderated against a reserved staff-name list; fallback shows nickname-or-first-name only |
| [ASSUMPTION-018] | Referral system deferred to post-MVP | Spec decision 2026-08-24 | Schema includes referrals table; feature gated off |

---

## 12. Open Questions
| ID | Question | Owner | Resolve By |
|----|----------|-------|------------|
| OQ-001 | Exact NSBM IT policy for student project deployment? | Faculty sponsor | Before demo |
| OQ-002 | Should admin approval default to auto-approve or manual? | **RESOLVED: Auto-approve after 24h (configurable timer)** | Team lead | Day 1 |
| OQ-003 | Image storage: local filesystem vs. cloud (e.g., Cloudinary free tier)? | **RESOLVED: Local filesystem** (`/var/www/uploads/`) | Tech lead | Day 1 |
| OQ-004 | Team member roles & task allocation for 3-week sprint? | **RESOLVED: Proposed split below** | Team lead | Day 1 |
| OQ-004a | Backend Lead (2): Auth, Models, Migrations, Tickets, Points, Cron, Admin Actions | — | Day 1 |
| OQ-004b | Frontend Lead (2): Templates, Bootstrap, JS (modals, carousels, Uppy, toast), UX | — | Day 1 |
| OQ-004c | Database Designer (1): Schema, indexes, migrations, seed data | — | Day 1 |
| OQ-004d | QA/Docs Lead (1): Test cases, manual testing, bug triage, project report, video script | — | Day 1 |
| OQ-005 | Demo video script & recording responsibilities? | **RESOLVED: Doc/QA Lead owns script + recording** | Doc Lead | Week 2 |
| OQ-006 | SSO integration: what fields does NSBM provide? | **RESOLVED: Not needed** — users create nicknames on registration; no SSO fields required | Tech lead | Sprint 1 |
| OQ-007 | Transaction volume estimate for demo — how many test users? | Team lead | Sprint 1 |
| OQ-008 | Project report template structure & ownership? | **RESOLVED: Doc/QA Lead owns** | Doc Lead | Sprint 1 |
| OQ-009 | Google Drive folder setup & access permissions? | **RESOLVED: Doc/QA Lead owns** | Doc Lead | Sprint 1 |
| OQ-010 | Dispute-queue duty roster during demo week (who watches the queue; escalation when the 3-day auto-dismiss reverses a legitimate dispute)? | Team lead | Before demo week |
| OQ-011 | Who owns the cut decision when week-2 crunch hits (the person who says "cut it now")? | Team lead | Start of week 2 |

---

## 13. Downstream Handoffs
- **UX Design** (`bmad-ux`): Convert UJ-1..UJ-7 into screen flows, wireframes, component specs (listing card, ticket modal, dispute modal, admin queue, seller dashboard, leaderboards, toast system)
- **Architecture** (`bmad-architecture`): Define PHP/MySQL structure, migration strategy, API contracts for Actions, repository layout (`src/Model/`, `src/Action/`, `config/`, `migrations/`, `jobs/`, `public/serve_image.php`)
- **Epics & Stories** (`bmad-create-epics-and-stories`): Break FRs into sprint-ready stories with acceptance criteria
- **Test Plan** (`bmad-testarch-test-design`): Define acceptance tests for ticket redemption, expiry, dispute flows, ranking calculations, image upload, rate limits, state machine transitions
