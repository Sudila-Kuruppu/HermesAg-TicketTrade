---
title: "TicketTrade - Student Business & Service Platform (NSBM Marketplace)"
status: final
created: 2026-08-16
updated: 2026-08-23
---

# Product Brief: TicketTrade

> Renamed from "NSBM Marketplace" (PRFAQ Stage 2 decision); internal artifact paths keep the working title. This brief distills the authoritative [PRD](../../prds/prd-nsbm-marketplace-2026-08-22/prd.md) — every decision here carries full rationale, FR/NFR IDs, and state machines there.

## 1. Product Overview

**TicketTrade** is a campus-only peer-to-peer marketplace for NSBM Green University students to buy and sell products and services. Every purchase produces a confirmable digital ticket, a gamified 4-tier rank system rewards repeat trading, and seller ratings make trust visible — so nobody trades blind.

- **Form factor:** Responsive web application (desktop + mobile browsers, Bootstrap 5 grid)
- **Stack:** PHP 8+, MySQL 8+, HTML/CSS/vanilla JS (assignment-mandated; sole Composer dep: `ramsey/uuid`)
- **Timeline:** MVP due **2026-09-02** (~3-week sprint, 6-person team, Batch 26.1 WAD coursework)
- **Access:** NSBM community only — `@students.nsbm.ac.lk` email + student ID required; no public registration
- **Purchases are simulations** — no payment gateway, no real money (assignment requirement)

## 2. Vision & Thesis

**Vision:** The trusted campus commerce hub where every NSBM student safely monetizes skills, creations, and second-hand goods — building entrepreneurial confidence before graduation.

**Thesis:** Verified student identity + lightweight 4-tier reputation + simulated ticket confirmation + seller ratings = sufficient trust for campus-scale peer trade, without escrow or algorithmic reputation scores.

**Positioning:** Complements the batch WhatsApp groups students already use — the group has reach but zero memory. TicketTrade adds the trust and record layer while handover coordination stays on chat. A generic classifieds board cannot claim that.

## 3. Target Users

| User | Job To Be Done |
|------|----------------|
| **Student Seller** | List in minutes, reach only NSBM students, know the buyer is committed — no wasted trips |
| **Student Buyer** | Find it fast, know the seller is legitimate, hold proof of purchase — don't get scammed |
| **Admin (Faculty/Staff)** | Clear queue to review, act on, and audit — platform stays safe without eating teaching time |

**Non-users (v1):** general public, alumni, external vendors/businesses.

## 4. MVP Scope (by actor)

### Seller
- Listing CRUD with **draft/save flow**; separate `type` ENUM `product` | `service` (services priced per hour/session, delivery method, availability); **quantity** field (units or sessions) with `quantity_sold` counter; auto-sold when sold out; multiple images stored outside webroot (SHA256 rename, auth-checked proxy serving)
- States: `draft → pending → active | removed`; `sold` when exhausted. Relist copies to draft; previously-approved relists skip the approval queue. Edits to active listings stay live behind a `review_flag` the admin clears

### Buyer
- Board: responsive grid, category tabs/filter, keyword search (MySQL FULLTEXT), available-quantity display; guest browse preview ("Buy Now" gates to login)
- Buy Now → confirmation modal ("a reservation, not payment") → unique ticket code `TK-…` (UUID v7 base62) with mask/reveal toggle, copy-to-clipboard, **WhatsApp share to seller**
- Purchase History; "Leave review" unlocked only after redemption (14 days from redemption)

### Trust Layer (the differentiator)
- **Tickets expire after 7 days** — idempotent hourly cron cancels the reservation and restocks quantity (points are earned only on redemption, so an expired ticket is net zero); skipped while a dispute is pending
- **Redemption:** seller enters buyer's code → atomic guarded UPDATE → both parties earn points; rate-limited 5 attempts/hr/ticket
- **Disputes:** buyer or seller flags a ticket → admin resolves via Force Expire / Force Redeem / Dismiss; 3-day auto-dismiss returns ticket to active (original `created_at` preserved)
- **Ratings:** 1–5 stars + ≥50-char review, redeemed tickets only, both directions; seller profile shows average rating and a public dispute count

### Points & Ranks
| Tier | Threshold | Signal |
|------|-----------|--------|
| Green Knight (GK) | 0–99 | Gray shield |
| Striker | 100–399 | Blue shield |
| Captain | 400–999 | Gold shield |
| Campus Legend | 1000+ | Animated gold crown |

Earning: profile verified +100 (one-time) · approved listing +5 · complete sale +30 · complete purchase +10 · detailed review +10 · valid report +20 · 7-day login streak +15. Guards: 150 pts/day velocity cap · max 2 counted transactions/day per buyer-seller pair · 50% multiplier for new accounts until 5 confirmed trades · "On Break" label after 14 idle days. Leaderboards: Legends Wall (top 20), Weekly Risers (min +50/wk), Category Leaders.

### Admin
Approval queue (FIFO; **auto-approve after 24 h**, configurable timer enforced by cron) · users: search/filter, ban/suspend/promote, CSV export, bulk actions · categories CRUD · reports + dispute resolutions with evidence detail view · **hash-chained immutable audit log** · daily/weekly sales reports · password re-auth for sensitive actions · suspicious-activity flag (points guards breached → freeze pending review).

### Cross-cutting
Public landing page (hero "Every Trade Ends With Proof", how-it-works, team cards, guest browse) · toast notifications for all async actions · route guards and consistent layouts.

## 5. Data Model (summary)

Ten tables: `users` · `listings` · `listing_images` · `tickets` · `points_log` · `categories` · `reports` · `reviews` · `audit_log` · `cron_log`. Full logical schema with columns, enums, and indexes: PRD §6.

Conventions that bind implementation: money as integer cents (`price_cents`) — never floats; snake_case throughout; `tickets.listing_id` ON DELETE RESTRICT (no deleting listings with tickets); unique event key prevents double point awards.

## 6. Non-Functional Highlights

- **Security:** bcrypt (cost ≥ 12), PDO prepared statements everywhere, CSRF tokens, hardened session cookies, uploads re-encoded to WebP behind validation, Sri Lankan mobile regex for WhatsApp numbers, layered rate limits, security headers
- **Performance:** < 2 s pages, ≤ 50 listings/page, thumbnails generated at upload (200/600/1200 px WebP)
- **Reliability:** atomic UPDATE redemption (naturally idempotent), cron idempotent + `flock()`-guarded + Asia/Colombo timezone + replay-safe (`TRUNCATE cron_log` → rerun = identical result), manual trigger endpoint
- **Compliance (assumption-backed):** PDPA 2022 not yet in force → minimal data; Computer Crimes Act intermediary exemption via reactive moderation; clear "simulation only" labeling

## 7. Out of Scope (MVP)

Real payments · chat/messaging (WhatsApp share covers it) · real email notifications · multi-language · PWA/offline · real-time push · real SSO/LMS integration (simulated domain verification) · algorithmic reputation · buyer ID checks at redemption · formal return/cancellation flow (7-day expiry + disputes cover it).

## 8. Team (6)

Resolved split (names/IDs pending): **Backend ×2** (auth, models, migrations, tickets, points, cron, admin actions) · **Frontend ×2** (templates, Bootstrap, JS modals/carousels/toasts) · **Database ×1** (schema, indexes, seed data) · **QA/Docs ×1** (test cases, triage, project report, video script).

## 9. Risks & Sacrifice Order

Top risks: founding cohort graduates (high/high) → honest stance, document for handover; week-2 crunch (medium/high) → **pre-agreed cut order: leaderboards → bulk admin actions → login streaks → draft/relist — the core loop (list, approve, ticket, redeem, expire, dispute) is never cut**; trust collapse → dispute system + ratings; cron failure → manual trigger endpoint + `cron_log`. Cut-decision owner named before crunch starts (OQ-011).

## 10. Success Metrics

≥50 registrations · ≥30 active listings · ≥60% ticket redemption rate · <10% dispute rate · ≥5 students reach Striker · ≥80% of redeemed tickets reviewed · board < 2 s · zero critical security findings. Guardrails: farming velocity flags, pair-limit enforcement, admin resolution median < 48 h, review minimum length. Demo needs seeded data (~50 users, ~100 listings).

## 11. Open Items

NSBM IT policy for hosting (OQ-001, faculty sponsor) · real SSO fields (OQ-006) · demo transaction volume (OQ-007) · dispute-queue duty roster for demo week (OQ-010) · cut-decision owner (OQ-011) · team member names/IDs.

## 12. Downstream & Sources

**Handoffs:** UX design (`bmad-ux`) → architecture (`bmad-architecture`) → epics & stories → test plan (`bmad-testarch-test-design`). Assignment traceability (WAD Topic 4 rubric): PRD §7.

**Sources:** PRD (authoritative): `_bmad-output/planning-artifacts/prds/prd-nsbm-marketplace-2026-08-22/prd.md` · domain/competitive/technical/ranking research under `_bmad-output/research/` · QR-removal decision: `_bmad-output/forge/remove-qr-ticket-system/forged-idea.md` (HARDENED) · prior brief v1 (2026-08-16): superseded — see git history.
