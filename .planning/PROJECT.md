# TicketTrade (NSBM Marketplace)

## What This Is

TicketTrade is a campus-only peer-to-peer marketplace for NSBM Green University students to buy and sell products and services. Every purchase produces a confirmable digital ticket, a 6-tier gamified rank system rewards repeat trading, and seller ratings make trust visible — so nobody trades blind. Form factor is a responsive web app (desktop + mobile browsers, Bootstrap 5 grid). Stack is PHP 8+ with MySQL 8+, HTML/CSS/vanilla JS, sole Composer dependency `ramsey/uuid`. Purchases are simulated — no payment gateway, no real money (assignment requirement).

## Core Value

Every trade ends with proof: each purchase produces a confirmable digital ticket that both parties verify, so nobody trades blind on campus. Verified identity plus lightweight reputation plus simulated ticket confirmation plus seller ratings equals sufficient trust for campus-scale peer trade without escrow or algorithmic reputation.

## Business Context

- **Customer**: NSBM Green University students (and faculty admins) — campus-only, `@students.nsbm.ac.lk` email required, no public registration
- **Revenue model**: None for MVP — academic coursework (WAD Topic 4, Batch 26.1), all purchases are simulated, no real money flows
- **Success metric**: ≥50 registrations, ≥30 active listings, ≥60% ticket redemption rate, <10% dispute rate, ≥5 students reach Striker tier, zero critical security findings, board < 2s
- **Strategy notes**: PRD at `_bmad-output/planning-artifacts/prds/prd-nsbm-marketplace-2026-08-22/prd.md` is authoritative; brief at `_bmad-output/planning-artifacts/briefs/brief-nsbm-marketplace-2026-08-23/brief.md`

## Requirements

### Validated

(None yet — ship to validate)

### Active

- [ ] Listings CRUD with draft/save flow, ENUM `product` | `service`, quantity field with `quantity_sold` counter, multiple images stored outside webroot (SHA256 rename, auth-checked proxy serving)
- [ ] Listing state machine: `draft → pending → active | removed`; `sold` when exhausted; relist copies to draft; previously-approved relists skip the approval queue; edits to active listings stay live behind a `review_flag` the admin clears
- [ ] Board: responsive grid, category tabs/filter, keyword search (MySQL FULLTEXT), available-quantity display; guest browse preview ("Buy Now" gates to login)
- [ ] Buy Now → confirmation modal ("a reservation, not payment") → unique ticket code `TK-…` (UUID v7 base62) with mask/reveal toggle, copy-to-clipboard, WhatsApp share to seller
- [ ] Purchase History; "Leave review" unlocked only after redemption (14 days from redemption)
- [ ] Tickets expire after 7 days via idempotent hourly cron (cancels reservation, restocks quantity; points earned only on redemption so net-zero); skipped while dispute pending
- [ ] Redemption: seller enters buyer's code → atomic guarded UPDATE → both parties earn points; rate-limited 5 attempts/hr/ticket; idempotent
- [ ] Disputes: buyer or seller flags active ticket → admin resolves via Force Expire / Force Redeem / Dismiss; 3-day auto-dismiss returns ticket to active (original `created_at` preserved)
- [ ] Ratings: 1–5 stars + ≥50-char review, redeemed tickets only, both directions; seller profile shows average rating, distribution, and public dispute count
- [ ] Points & ranks (6 tiers per PRD FR-PTS-002: Recruit E → Rookie D → Operative C → Specialist B → Elite A → Legend S, badges E=gray → S=red animated crown)
- [ ] Earning: profile verified +50 (one-time) · approved listing +5 · complete sale +30 · complete purchase +10 · detailed review +10 · valid report +20 · 7-day login streak +15 · 30-day login streak +50
- [ ] Guards: 150 pts/day transaction cap, max 2 counted transactions/day per buyer-seller pair, 50% multiplier for new accounts until 5 confirmed trades, "On Break" label after 14 idle days, velocity flag >300 pts/day or >150 pts/hr
- [ ] Leaderboards: Campus Legends Wall (top 20 tier S), Weekly Risers (min +50/wk), Category Leaders (top 3 per category), Streak Kings (top 10)
- [ ] Admin approval queue (FIFO; auto-approve after 24 h via hourly cron); users: search/filter, ban/suspend/promote, CSV export, bulk actions; categories CRUD; reports + dispute resolutions with evidence detail view; hash-chained immutable audit log; daily/weekly sales reports; password re-auth for sensitive actions; suspicious-activity flag
- [ ] Public landing page (hero "Every Trade Ends With Proof", how-it-works, team cards, guest browse); toast notifications for all async actions; route guards; consistent layouts

### Out of Scope

- Real payments / gateways — simulation only (assignment requirement)
- Chat/messaging between users — WhatsApp share covers it
- Email notifications — simulation only
- Multi-language / i18n
- PWA / offline mode
- Real-time push notifications / WebSockets
- Real SSO/LMS integration — simulated domain verification only
- Algorithmic reputation scores — simple tiers + ratings only
- Buyer identity verification at redemption — code = trust signal
- Formal return/cancellation flow — 7-day expiry + dispute system covers it
- Real-time notifications — replaced by localStorage sync simulation

## Context

- **Technical environment**: PHP 8+, MySQL 8+, HTML/CSS/vanilla JS, Bootstrap 5, responsive web (mobile-first, breakpoints at 576/768/992/1200px). Layered Modular Monolith architecture: Bootstrap → FrontController → Action → Service → Model → PDO. Webroot at `public/`, `src/` outside webroot. Sole Composer dep: `ramsey/uuid` for UUID v7 generation.
- **Prior work**: PRD finalized 2026-08-26 (initial 2026-08-17). Brief finalized 2026-08-16, updated 2026-08-23. Architecture spine 2026-08-27. UX design 2026-08-27. Epic & story breakdown complete. Domain, competitive, technical, and ranking research under `_bmad-output/research/`. QR-removal decision hardened at `_bmad-output/forge/remove-qr-ticket-system/forged-idea.md`.
- **User research themes**: NSBM batch WhatsApp groups have reach but zero memory — TicketTrade adds the trust and record layer; handover coordination stays on WhatsApp. Students want to monetize skills/creations/second-hand goods before graduation; they want legitimacy signals (verified student, ratings, dispute system) over algorithmic reputation.
- **Known issues to address**:
  - Cohort isolation gate (AD-20): MVP assumes single cohort; team decides at S2 retro whether to add `cohort_id` in migration `013` with belt-and-braces across every Model — must be decided before per-screen implementation work for FR-LST-005 (flyer modal) begins
  - NSBM IT policy alignment (ASSUMPTION-001) — faculty sponsor approval pending
  - Login streaks schema: `login_streaks` table is authoritative; `users.current_streak` / `users.longest_streak` are denormalized display copies refreshed by daily cron — writes go to `login_streaks` only
  - Corkboard view (FR-LND-008) is decorative only — rotation/pin styling is `aria-hidden`, list-view toggle persists per session, auto-degrades below md breakpoint; all ranking data flows through list order, not card rotation
  - Velocity flag threshold (300 pts/day) sits deliberately above legitimate ceiling (~275 pts/day) so real high-volume traders never trigger

## Constraints

- **Tech stack (assignment-mandated)**: PHP 8+ / MySQL 8+ / HTML/CSS/vanilla JS — no frameworks, no ORM, no regex routing. Sole Composer dependency: `ramsey/uuid`. Dev: `phpcs`, `phpunit`.
- **Timeline**: MVP due 2026-09-02 (~3-week sprint, 6-person team, Batch 26.1 WAD coursework)
- **Code style**: PSR-12 — `vendor/bin/phpcs --standard=PSR12 src/`
- **Security baseline**: bcrypt cost ≥ 12, PDO prepared statements everywhere, CSRF tokens, uploaded files re-encoded to WebP behind validation, Sri Lankan mobile regex for WhatsApp, layered rate limits, hardened session cookies, security headers
- **Performance**: < 2 s pages, ≤ 50 listings/page, thumbnails generated at upload (200/600/1200 px WebP 80% quality), cron jobs complete < 30 s for 10k tickets
- **Reliability**: atomic UPDATE redemption (naturally idempotent), cron idempotent + `flock()`-guarded + Asia/Colombo timezone + replay-safe (`TRUNCATE cron_log` → rerun = identical result), manual trigger endpoint, points log `UNIQUE KEY uniq_event (event_uuid)` closes the duplicate-NULL hole
- **Compliance (assumption-backed)**: PDPA 2022 not yet in force → minimal data; Computer Crimes Act §26 intermediary exemption via reactive moderation (every listing enters 24h review window by default); clear "simulation only" labeling
- **Operational**: dev server `php -S localhost:8000 -t public`; migrations `php migrate.php` (idempotent, versioned); Git never push to main; PRs only, one approval required; admin/sensitive actions write audit_log row
- **Cut order (pre-agreed for week-2 crunch)**: leaderboards → bulk admin actions → login streaks → draft/relist — the core loop (list, approve, ticket, redeem, expire, dispute) is never cut

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Product name "TicketTrade" (was "NSBM Marketplace") | PRFAQ Stage 2 user decision; internal artifact paths keep working title | ✓ Good |
| 4-tier vs 6-tier rank system | Brief specifies 4-tier (Green Knight / Striker / Captain / Campus Legend); PRD specifies 6-tier anime-style (Recruit E → Legend S). Reconcile to 6-tier (PRD is authoritative per latest reconciliation) | — Pending |
| Single-tenant cohort model | MVP is one cohort (NSBM 26.1); defer `cohort_id` to migration `013` retro decision | ✓ Good |
| Simulated payments only | Assignment requirement (WAD coursework); "a reservation, not payment" copy everywhere | ✓ Good |
| Ticket code format `TK-` + 22 base62 chars | `random_bytes(16)` → ≥125 bits entropy, not timestamp-derived; retry loop on unique violation | ✓ Good |
| Corkboard board presentation | Decorative-only (`aria-hidden`); list-view toggle persists per session; auto-degrades below md breakpoint | ✓ Good |
| Login streaks authoritative table | `login_streaks` is source of truth; `users.current_streak/longest_streak` are denormalized display copies | ✓ Good |
| Velocity flag thresholds | 300 pts/day (above ~275 legitimate ceiling) and 150 pts/hr (above max legitimate hourly burst) — set so real top performers never trigger | ✓ Good |
| Layered Modular Monolith (no framework) | AD-1..AD-4: hand-rolled PHP, no ORM, no regex routing; FrontController dispatches via route list | ✓ Good |
| Auth/Service sole owner of bcrypt | AD-18: phpcs sniff `Custom\Sniffs\NoRawHash` rejects `md5(`, `sha1(`, `crypt(`, `password_hash(` outside Auth/Service and Support/Crypto | — Pending (sniff lands in Epic 9) |
| Admin re-auth sliding 300s window | AD-19: cached in `admin_reauth` table keyed by `(user_id, session_id)`; rate-limited 5/min/IP | ✓ Good |

---

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd-transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd-complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Business Context check — customer, revenue model, success metric still accurate?
4. Audit Out of Scope — reasons still valid?
5. Update Context with current state (users, feedback, metrics)

---
*Last updated: 2026-08-26 after initialization*
