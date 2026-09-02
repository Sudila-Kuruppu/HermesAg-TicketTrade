# Phase 4: Purchases, Tickets & Lifecycle - Context

**Gathered:** 2026-09-02
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 4 ships the buy/ticket/redeem/expire/dispute core loop end-to-end so that an NSBM student can buy an active listing, receive a digital ticket with a `TK-` code, and complete the handover on campus. Concretely:

1. **Buyer side** — "Buy Now" on an `active` listing opens the purchase confirmation modal ("Confirm purchase? This reserves the item with a digital ticket (a reservation, not payment)."); on confirm, ticket is generated atomically (code from `random_bytes(16)`, listing `quantity_sold` incremented, ticket `expires_at` written once); redirect to My Tickets with toast `Ticket created. Code: TK-...`. The `My Tickets` page (`/my-tickets`) lists the buyer's tickets with status tabs (Active / Redeemed / Expired / Disputed), the ticket-code block (mask/reveal/copy/WhatsApp), and per-session progress `#N/M` for service tickets. The `Purchase History` page (`/purchases`) is the chronological cross-section (Phase 5 layers the "Leave review" affordance on top).

2. **Seller side** — The `Sales` page (`/sales`) shows tickets for the seller's listings grouped by listing with quantity context (`#N/Q`). For `total_sessions > 1` (services), the group header shows the progress chip and the in-progress ticket carries a "Confirm next session" action. The redemption input (paste the buyer's code) sits at the top of the page (always visible) and is the seller-side entry point. Atomic guarded UPDATE validates `status='active' AND dispute_status != 'pending' AND seller_id = CURRENT_USER` and marks the ticket `redeemed` on success.

3. **Ticket lifecycle** — `expires_at` is written ONCE at creation as `created_at + INTERVAL 7 DAY, Asia/Colombo`. The hand-triggered `POST /admin/cron/ticket-expiry` Action (extending Phase 3's existing endpoint) runs three sweeps in order: (a) 24h listing auto-approve (Phase 3, kept), (b) 3-day dispute auto-dismiss (sets `dispute_status='rejected'`, restores ticket to pre-dispute `status`, never touches `created_at`), (c) ticket expiry (atomic UPDATE; for products decrement by 1, for services decrement by `total_sessions - (session_number - 1)`; restores listing `status='active'` if `sold` and now has stock; skipped if `dispute_status='pending'`). The cron is idempotent (re-running produces the same end state) per NFR-REL-002.

4. **Dispute flow** — Either party can file a dispute on `active` or `redeemed` tickets (text-only in Phase 4 — evidence image deferred to v2 per D-04). The modal has reason dropdown (seller_unresponsive, item_not_as_described, buyer_unresponsive, other) + required text (200-char max). On submit: ticket `dispute_status='pending'` (and `status='disputed'` if filed on an `active` ticket; stays `redeemed` if filed on a `redeemed` ticket per D-03); a `reports` row is created with `target_type='ticket'`. Admin resolution Actions land in Phase 7; Phase 4 ships only the buyer/seller file-dispute Action. The 3-day auto-dismiss timer is started by the dispute-pending transition (per D-03).

5. **Per-session service handover** — For `total_sessions > 1`, seller confirms each session in strict order (`session_number` 1..N) via `POST /tickets/{id}/confirm-session`. The action requires `active` ticket (no pending dispute) AND the confirming user to be the ticket's `seller_id`. Each confirmation increments `session_number`; only the final confirmation awards points and marks the ticket `redeemed`. Audit log row per confirmation (via `Support\Audit` stub).

6. **Points stub** — `points_service::awardTransaction()` is extended from the Phase 2 `awardVerificationBonus()` to handle the buyer/seller pair with FR-PTS-007 new-account halving (50% multiplier for the first 5 redemptions). The stub does NOT enforce FR-PTS-005 (velocity cap) or FR-PTS-006 (same-pair 2/day cap) — those are Phase 6. Velocity and same-pair caps are marked TODO in the Service. `users.redeemed_count` column is added in the Phase 4 migration to make the new-account check a single column read.

7. **Rate limits** — `Support\RateLimit` named limits `purchase` (10/hr/user, per NFR-SEC-007) and `redemption` (5/hr/ticket) are added to `config/rate_limits.php` and wired at the route map.

The core loop (list → approve → buy → ticket → redeem → expire → dispute → resolve) is now complete from the buyer + seller perspective. Admin resolution (Force Expire / Force Redeem / Dismiss) is Phase 7; the cron sweep that auto-dismisses a stale dispute is Phase 4.

### Assignment context (WAD_Batch26.md Topic 4)

Phase 4 ships the "simulate product purchases" + "ticket as proof of trade" halves of the WAD brief Topic 4. The brief does not require real payments, real email notifications, or buyer identity verification at handover — the ticket code IS the trust signal. The gamification layer is wired (rank badges on ticket cards, per-session progress, points awarded on redemption) but the full points engine (caps, leaderboards, streaks) is Phase 6; Phase 4 stub honors FR-PTS-007 only.

</domain>

<decisions>
## Implementation Decisions

### Ticket code format and display
- **D-01:** Ticket codes are generated and stored as `TK-XXXX-XXXX-XXXX-XXXX-XXXX` (six 4-char base62 groups after the `TK-` prefix; total 22 base62 chars from `random_bytes(16)`, ≥125 bits entropy, NOT timestamp-derived). The dashed form IS the canonical stored form in `tickets.ticket_code VARCHAR(30) UNIQUE`. Generator: `$code = 'TK-' . chunk_split(base62(random_bytes(16)), 4, '-')` (then strip trailing dash, validate length === 25). The redemption input parses dashes (or strips them server-side and matches against the dashed `ticket_code` after re-inserting dashes; the simpler path is to store the dashed form so the input matches it directly). Retry loop on `UNIQUE` violation (max 10 attempts per FR-TKT-001 / D-23 PRD open-question OQ-004). — **Reversibility:** one-way — the dashed form becomes a published contract (the buyer copies `TK-XXXX-XXXX-XXXX-XXXX-XXXX` and the seller pastes it back; future schema change would need a migration to translate and a UX break).

- **D-02:** Purchase success UX — redirect to My Tickets with toast `Ticket created. Code: TK-...` after the Action returns success. No intermediate success modal. The toast is announced via `aria-live=polite` and the new ticket card is auto-focused on the redirected page (CSS `:target` selector on the freshly-rendered `data-ticket-id` anchor, OR a tiny inline `el.focus()` on the matching card after page paint). — **Reversibility:** reversible — adding a success modal later is a View change in `MyTicketsAction`, no Service or DB impact.

### Dispute window scope
- **D-03:** Disputes are filable on BOTH `active` AND `redeemed` tickets (not on `expired` or `disputed`). The state machine splits: filing on an `active` ticket flips `status='disputed'` and `dispute_status='pending'`; filing on a `redeemed` ticket keeps `status='redeemed'` and only sets `dispute_status='pending'` (the handover already happened, but a dispute about the handover quality is a separate matter). Both paths create a `reports` row (`target_type='ticket'`). The 3-day auto-dismiss timer starts at the `dispute_status='pending'` transition (NOT at ticket creation). Admin resolution Actions check `dispute_status='pending'` (not the ticket's `status`). — **Reversibility:** costly — the dual-state branch is in the Service and the Views; tightening to active-only later is a one-line WHERE change in the Service but a UX update (Dispute button visibility on redeemed tickets).

- **D-04:** Evidence image upload is DEFERRED to v2. Phase 4 ships text-only disputes (required text 200-char max, required reason dropdown, no `evidence_image_path` column). The PRD marks evidence as optional; the storage + per-image auth + new modal layout for a single optional image would bloat Phase 4 without changing the state machine outcome. The PRD's "no-evidence disputes still resolve normally" path is the v1 contract. — **Reversibility:** reversible — adding evidence later is a new migration (`tickets.evidence_image_path VARCHAR(255) NULL`), a new `Support\ImageUpload::processTicketEvidence()` method, and a new field in the dispute modal.

### Per-session service handover UX
- **D-05:** Per-listing-group placement on Sales (NOT a global callout). Each listing renders as a card group; for `total_sessions > 1`, the group header shows the progress chip `2/5 sessions confirmed`; each ticket row in the group shows its own `#N/M` session progress inline. The seller expands the group to confirm the next session — the "Confirm next session" button appears next to the in-progress ticket (the one with the highest `session_number` whose ticket is `active`). The action is `POST /tickets/{id}/confirm-session` with `session_number` implicit (= current max + 1). On the last session, points are awarded and the ticket auto-marks `redeemed`. — **Reversibility:** reversible — the global callout is a pure View addition if Phase 6 wants it.

### Points stub fidelity
- **D-06:** `points_service::awardTransaction($buyerId, $sellerId, $ticketId, $deltaBuyer, $deltaSeller, $referenceType)` honors FR-PTS-007 (50% halving for the first 5 redemptions) but NOT FR-PTS-005 (velocity cap) or FR-PTS-006 (same-pair 2/day cap). The stub reads `users.redeemed_count` (new column added in the Phase 4 tickets migration) to determine the halving threshold. The stub also honors FR-PTS-010 (skip if `users.points_frozen=TRUE`). Two `points_log` rows are written with distinct UUID v7 `event_uuid` values (one per party) in the same DB transaction; both `users.points` and `users.tier` are recomputed via `auth_service::tierFromPoints()` and updated. Velocity and same-pair caps are documented as TODO comments in the Service for Phase 6 to wire. — **Reversibility:** reversible — the stub's signature is the Phase 6 contract; Phase 6 swaps the implementation without changing callers (the Service is the sole writer per AD-10).

### 3-day dispute auto-dismiss — created_at preservation
- **D-07:** The hand-triggered `POST /admin/cron/ticket-expiry` Action runs the three sweeps in order: (1) 24h listing auto-approve (kept from Phase 3), (2) 3-day dispute auto-dismiss, (3) ticket expiry. The auto-dismiss branch (3 days after `dispute_status → 'pending'`) does NOT touch `tickets.created_at`. The ticket's `status` is restored to its pre-dispute value (`active` if filed on `active`, `redeemed` if filed on `redeemed`) and `dispute_status → 'rejected'`. The composition note in PRD §4.2 (dismissed-after-expires yields immediate re-expiry) is the explicit intended behavior; the dismiss sweep runs BEFORE the expiry sweep so a dismissed-then-expired ticket lands in `expired` in the same tick. The cron is idempotent (re-running produces the same end state) per NFR-REL-002. — **Reversibility:** reversible — the cron logic is the Action's `handle()` body; a future scheduler (Phase 9) calls the same Action.

### the agent's Discretion

These items follow from locked requirements or are routine implementation choices appropriate for a WAD-assignment scope:

- **Migrations** — `013_tickets.sql` creates the `tickets` table (per PRD schema: `ticket_id, ticket_code, listing_id, buyer_id, seller_id, status, dispute_status, price_cents, session_number, total_sessions, created_at, expires_at, redeemed_at, disputed_at, resolved_at, resolution_note, FK to listings/users` with `ON DELETE RESTRICT` per NFR-REL-006). `014_users_redemption_count.sql` adds `users.redeemed_count INT NOT NULL DEFAULT 0` (cheap read for the FR-PTS-007 halving check). Per D-23 of Phase 2, migrations continue from the highest existing number — Phase 3 ends at `012_cron_log.sql`, so Phase 4 migrations start at `013_*`. — **Reversibility:** reversible before production.
- **`Support\Audit` stub** — Per Plan 04-01, Phase 4 ships a thin `Support\Audit::log($actor, $action, $target_type, $target_id, $metadata)` that writes to a plain `audit_log` table (NOT yet hash-chained per AD-12). The hash chain lands in Phase 8 alongside the admin console. Phase 4's per-session-confirm Action calls `Audit::log()` for every confirmation. — **Reversibility:** reversible — the hash chain is added in Phase 8 by changing `Audit::log()`'s INSERT to include the prev-hash.
- **Ticket code base62 alphabet** — `0-9A-Za-z` (62 chars), matching the canonical PRD example `TK-7QXK2M9WBV4N8PRTYC3AD`. Rejection of visually ambiguous chars (0/O, 1/I/l) is NOT applied — the PRD example includes them; honoring the PRD verbatim is the priority.
- **7-day expiry write-once** — `expires_at` is set at INSERT as `DATE_ADD(created_at, INTERVAL 7 DAY)` and never updated by any Action (the auto-dismiss branch per D-07 deliberately does not touch it). A manual `Force Expire` admin Action (Phase 7) sets `status='expired'` and `resolved_at=NOW()` but does not change `expires_at` either (the audit trail wants the original `expires_at` preserved for "should this have expired earlier" reviews).
- **Purchase rate-limit naming** — `Support\RateLimit` named limit `purchase` (10/hr/user, per NFR-SEC-007). The route map entry for `POST /listings/{id}/buy` carries `rate_limit => 'purchase'`.
- **Redemption rate-limit scoping** — The PRD NFR-SEC-007 says `5/hr/ticket` for redemption. The `Support\RateLimit` keys this as `redemption:ticket:{ticket_id}:{user_id}:{window}` so a wrong-code attempt on ticket A doesn't count against ticket B. The window is a 1-hour sliding window.
- **Dispute rate-limit** — NOT added in Phase 4. The PRD does not list a per-user dispute rate limit. The 3-day auto-dismiss timer + the fact that a ticket can only have one `dispute_status='pending'` at a time (the atomic UPDATE in the file-dispute Action sets `WHERE dispute_status='none'`) makes a rate limit unnecessary. If a future requirement adds it, it slots in via the route map.
- **`session_number` on product tickets** — Always 1 (products don't have per-session handover). The `total_sessions` column is also 1 for products. The `#N/M` display shows `1/1` for products (a no-op, but the UI is uniform).
- **Ticket auto-focus on redirect** — Implemented as a small inline `<script>` at the bottom of `my_tickets.php`: `setTimeout(() => document.querySelector('[data-ticket-id=\"{$new_id}\"]')?.focus(), 50);`. The `tabindex="-1"` on the card makes it focusable without breaking the tab order. Reversible to a `:target` CSS selector if preferred.
- **Self-purchase prevention** — The "Buy Now" button is HIDDEN on the listing modal when `listing.seller_id === current_user.user_id` (per EXPERIENCE.md "Self-owned listing" state). The Service also guards with `WHERE seller_id != buyer_id` in the atomic ticket-creation UPDATE for defense in depth.
- **Empty Sales state** — "No sales yet. Your first sale happens when someone buys one of your listings." with a "View your listings" link to `/my-listings`. The named copy follows EXPERIENCE.md's empty-state pattern.
- **Empty My Tickets state** — "No tickets yet. Buy your first item." with a "Browse Board" link to `/board`. The named copy follows EXPERIENCE.md's empty-state pattern.
- **Toast on dispute filed** — "Dispute submitted. Admin will review within 48 hours." — verbatim from EXPERIENCE.md L237.
- **Toast on ticket created** — "Ticket created. Code: TK-..." (the actual code is appended by the Action via `data-flash-toast` with the rendered code interpolated) — verbatim from EXPERIENCE.md L201.
- **Toast on session confirmed** — "Session N of M confirmed." (intermediate) / "Ticket redeemed. Handover complete." (final session) — `data-flash-toast` carries the message.
- **Dispute modal scrim-guard** — Suppress scrim click for 2 seconds after open, matching the purchase confirmation modal's scrim guard (per EXPERIENCE.md L157 + DESIGN.md L568). The existing `data-scrim-guard="2"` attribute is the Phase 1 wiring; Phase 4 reuses it.
- **My Tickets tab structure** — 5 tabs (All / Active / Redeemed / Expired / Disputed) per the mockup. Tab counts in the header (`All 2`, `Active 2`) use `bg-secondary` badges — Phase 1 nav convention is NO badge counts (per D-01 of Phase 3), but the mockup is the explicit reference and the badges are content (not nav). The Phase 3 D-01 rule was about bottom-nav tab counts; ticket-tab counts are different.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing Phase 4.**

### PRD and Topic 4 brief
- `prd.md` §1.5 User Journeys (Flows 2-5) — Authoritative UJ-2 (Tharushi buys + gets ticket), UJ-3 (Kasun redeems), UJ-4 (Tharushi's ticket expires after 7 days), UJ-5 (Tharushi disputes). These are the WAD demo paths.
- `prd.md` §4.2 Ticket State Machine + §4.3 Dispute State Machine — Authoritative state machine diagrams and the composition note (dismissed-after-expires yields immediate re-expiry).
- `prd.md` FR-TKT-001..014 — Normative ticket format, retry loop, dispute reasons, session handover, expiry rules.
- `prd.md` FR-BUY-001..002 — Purchase confirmation modal copy ("a reservation, not payment") and Purchase History surface.
- `prd.md` FR-PTS-001, FR-PTS-007 — Points stub interface (per-action delta) and the new-account 50% halving.
- `prd.md` NFR-SEC-007 — Rate limits (purchase 10/hr/user, redemption 5/hr/ticket).
- `prd.md` NFR-REL-001..006 — Idempotency, atomic UPDATE, FK ON DELETE RESTRICT, points_log uniqueness.
- `prd.md` NFR-PER-004 — Cron < 30s for 10k tickets (single guarded UPDATE).
- `prd.md` NFR-OPS-003 — Cron ownership (single `jobs/ticket_expiry.php` owns 24h auto-approve + 7-day expiry + 3-day dispute auto-dismiss; hand-triggered in Phase 4, scheduled in Phase 9).
- `.planning/WAD-CONTEXT.md` — Topic 4 scope reminder (simulated purchases, no real payments).

### Architecture and ADs
- `ARCHITECTURE-SPINE.md` AD-1..AD-20 — The binding layer rules. Critical for Phase 4:
  - AD-1: Action → Service → Model dependency arrow (Plan must not import upward).
  - AD-2: `Ticket`, `Points`, `Listing`, `User`, `Report` are bounded contexts; cross-context work goes through Services only.
  - AD-7: `quantity_sold` increments ONLY inside the ticket-creation transaction. Redemption is a no-op for stock. Expiry and Force Expire decrement by 1 (product) or `total_sessions - (session_number - 1)` (service).
  - AD-8: Ticket code format `TK-` + 22 base62 chars from `random_bytes(16)`, ≥125 bits entropy, NOT timestamp-derived. Dashed form per D-01.
  - AD-9: Every state-changing ticket operation is a single `UPDATE tickets SET ... WHERE ticket_code = ? AND status = ? AND dispute_status != 'pending' AND seller_id = ?` (with the matching guard for buyer-side Actions). `rowCount() === 0` is the invalid branch.
  - AD-10: `points_service` is the SOLE writer of `points_log` and the sole updater of `users.points` and `users.tier` outside of Phase 6. Every other context that adjusts points MUST go through this Service.
  - AD-11: `jobs/ticket_expiry.php` (hourly in Phase 9, hand-triggered in Phase 4 via `POST /admin/cron/ticket-expiry`) is the single owner of (a) ticket expiry, (b) 24h listing auto-approve, (c) 3-day dispute auto-dismiss.
  - AD-12: `audit_log` hash chain (`prev_hash = SHA256(prev.prev_hash || json_encode(canonical_row))`). Phase 4 ships a STUB `Support\Audit` that writes plain rows; hash chain lands in Phase 8.
  - AD-13: Rate limits (purchase 10/hr/user, redemption 5/hr/ticket per NFR-SEC-007), session config.
  - AD-15: Review/dispute state gate — disputes filable only on `active` or `redeemed` tickets (D-03), reviews insertable only on `redeemed`/`expired` tickets with `dispute_status='none'` (Phase 5).
  - AD-16: Failure envelope on every Action exit.
  - AD-19: Admin re-auth 300s sliding window for `POST /admin/cron/*` endpoints.
  - AD-20: Cohort gate is at S2 retro, not Phase 4 (single-cohort MVP).

### Visual identity and experience
- `DESIGN.md` — `ticket-code-block` component (monospace amber on near-black, letter-spacing 0.04em), `session-progress` component (`#N/M` with bar fill), `status-badge` (8 variants: pending/active/rejected/redeemed/expired/sold/disputed/removed), `dispute-modal` (4 reasons + 200-char text + optional image deferred per D-04), `purchase-confirmation-modal` (scrim-guard 2s, "reservation, not payment" copy). The contrast ledger is the source of truth for every token value.
- `EXPERIENCE.md` — Section 4 (Surfaces) and Section 5 (State Patterns). Purchase confirmation modal at the listing modal layer; My Tickets tabs; Sales page; Dispute modal; per-session progress on service tickets. The named copy per surface is the source of truth.
- `public/mockups/my-tickets.html` — Visual reference for the My Tickets surface (5 tabs, ticket cards with mask/reveal/copy/WhatsApp, per-session progress, dispute action). Phase 4 production renders match this markup.
- `public/mockups/admin-dashboard.html` — Reference for the "Run Ticket Expiry Now" admin button (lives in the Phase 8 admin surface, but the cron Action is the same).

### Existing code
- `config/routes.php` — Phase 3 populated routes. Phase 4 ADDS: `POST /listings/{id}/buy` (rate_limit `purchase`), `POST /tickets/redeem` (rate_limit `redemption`), `POST /tickets/{id}/confirm-session`, `POST /tickets/{id}/dispute`, `GET /tickets/{id}` (detail page if needed for the dispute deep-link). The existing `GET /my-tickets`, `GET /sales`, `GET /purchases` route entries get their Phase 2 stub Actions replaced with real ones. `POST /admin/cron/ticket-expiry` is extended (Phase 3 listed it as the listing-auto-approve endpoint; Phase 4 ADDS ticket expiry + dispute auto-dismiss branches).
- `config/contexts.php` — Already lists `Ticket`, `Points`, `Report` bounded contexts.
- `config/rate_limits.php` — Phase 2 ships with `login` and `register`. Phase 4 ADDS `purchase` (10/hr/user), `redemption` (5/hr/ticket).
- `config/bootstrap.php` — Phase 4 requires the new `Ticket\Service\ticket_service`, `Ticket\Model\ticket_model`, `Points\Service\points_service` (already exists, gets new method), `Support\Audit` stub, `Support\RateLimit` (new named limits). The bootstrap does not change structurally (PSR-4 autoload picks them up).
- `src/Support/{Router,Auth,Csrf,RateLimit,Crypto,ResponseHeaders,Db,Error,View}.php` — Phase 2 ships. Phase 4 imports them from Action files; no new Support class is created except `Audit`.
- `src/Support/View/layout.php` — Phase 2 ships. Phase 4 uses it as-is for every page wrapper.
- `src/Ticket/Action/{MyTicketsAction,SalesAction,PurchasesAction}.php` — Phase 2 stub Actions. Phase 4 REPLACES them with the real implementations. ADDS `src/Ticket/Action/{BuyAction,RedeemAction,ConfirmSessionAction,DisputeAction,TicketDetailAction}.php` and the matching Views.
- `src/Ticket/View/{my_tickets,sales,purchases}.php` — Phase 2 placeholder Views. Phase 4 REPLACES with real Views. ADDS `src/Ticket/View/{ticket_detail,dispute_modal,confirm_session_card}.php` (the dispute modal is reused on the listing modal too if needed; otherwise a self-contained modal).
- `src/Points/Service/points_service.php` — Phase 2 ships with `awardVerificationBonus()`. Phase 4 ADDS `awardTransaction()`. Sole writer per AD-10.
- `src/Points/Model/points_log_model.php` — Phase 2 ships. Phase 4 imports from `points_service` only.
- `src/Listing/Service/listing_service.php` — Phase 3 ships. Phase 4 imports for the `getByIdWithSeller()` lookup in `BuyAction` (validates the listing is `active` and the seller is set).
- `src/Listing/Action/ListingAutoApproveAction.php` — Phase 3 ships (handles the 24h listing auto-approve branch of the cron). Phase 4 EXTENDS this Action (or splits it into a `CronAction` that dispatches per-sweep) to add the ticket expiry and dispute auto-dismiss branches.
- `src/Auth/Service/auth_service.php` — Phase 2 ships. Phase 4 imports `tierFromPoints()` for the points stub. `sanitizeUser()` is reused for the seller info row in the dispute modal.
- `src/User/Model/user_model.php` — Phase 2 ships. Phase 4 uses `findById()` for the `redeemed_count` read.
- `src/Support/View/partials/{ticket_code_block,session_progress,status_badge}.php` — Phase 4 ADDS these partials (the CSS already exists in `tickettrade.components.css`; the partials are the PHP wrappers). The ticket-code-block, session-progress, and status-badge CSS selectors already ship from Phase 1.
- `public/assets/css/tickettrade.components.css` — Phase 1 ships. Phase 4 verifies the `.ticket-code-block`, `.session-progress`, `.status-badge`, `.modal-dialog` rules match the View markup (the rules are written, the PHP partials just instantiate them).
- `public/assets/js/tickettrade.js` — Phase 1 component bundle. Phase 4 REUSES `toast.show()`, `data-flash-toast`, `prefersReducedMotion`. ADDS a small `ticketCodeBlock` component (~30 LOC) for the mask/reveal/copy/WhatsApp behavior, registered via `data-component="ticket-code-block"`. The Phase 1 mockup shipped the mask/reveal handler as inline `<script>`; Phase 4 promotes it to a proper component for reuse across My Tickets, Sales, and the future Purchase History page.
- `migrations/001_initial.sql` through `012_cron_log.sql` — Phase 3 ships. Phase 4 ADDS `013_tickets.sql` (tickets table), `014_users_redemption_count.sql` (`users.redeemed_count`), `015_reports.sql` (reports table — needed for the dispute filing's `reports` row), `016_audit_log_stub.sql` (`audit_log` plain table, hash chain in Phase 8).

### Cross-phase lock-ins
- `.planning/REQUIREMENTS.md` BUY-01..02, TKT-01..12 — All implemented by Phase 4.
- `.planning/REQUIREMENTS.md` REL-01, REL-02, REL-04, REL-05, REL-06 — Idempotency, atomic UPDATE, FK ON DELETE RESTRICT, points_log uniqueness (Phase 4 ships the `UNIQUE KEY uniq_event` check on redemption's points_log INSERT).
- `.planning/REQUIREMENTS.md` SEC-06 — Rate limits (Phase 4 ADDS `purchase` and `redemption` named limits).

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **Design token system** (`public/assets/css/tickettrade.{tokens,bootstrap-overrides,components}.css`) — every token Phase 4 needs is already defined: `code-bg` / `code-text` (monospace amber on near-black for ticket codes), `status-active-fill` / `status-redeemed-fill` / `status-expired-fill` / `status-disputed-fill` (the 4 ticket-status badge colors), `legend-glow` (not relevant here), the rank-badge token set (used on ticket cards' seller info row), the `surface-error-container` token (used on the dispute modal's destructive-action border).
- **Toast container** (`data-component="toast"` + `window.TicketTrade.toast.show(...)`) — Phase 4 emits toasts for: ticket created, session confirmed (intermediate), session confirmed (final → "Ticket redeemed. Handover complete."), dispute filed, dispute auto-dismissed (3-day), ticket expired.
- **Server-set flash messages** (`data-flash-toast="..."` div from Phase 2) — Phase 4 uses this for the "Ticket created. Code: TK-..." message that survives the redirect.
- **Bottom nav** (`data-component="bottom-nav"`) — Phase 2 ships; Phase 4's `/sales` and `/my-tickets` and `/purchases` routes already point at the bottom-nav items.
- **Skeleton shimmer** (`data-skeleton`) — Cold-load states on My Tickets (5 ticket cards), Sales (5 ticket cards grouped by listing), Purchase History (6 rows), Ticket Detail (1 card with code + actions).
- **`Support\Auth`, `Support\Csrf`, `Support\RateLimit`, `Support\Crypto`, `Support\Db`, `Support\Error`, `Support\View`, `Support\Router`, `Support\ResponseHeaders`** — All Phase 2 ships. Phase 4 imports them from Action files.
- **`auth_service::tierFromPoints(int $points): string`** — Phase 2 ships; Phase 4's points stub calls it to recompute the tier on each award.
- **`points_service::awardVerificationBonus(int $userId): array`** — Phase 2 ships; Phase 4 ADDS `awardTransaction()` to the same Service. The existing Service is the template.
- **`points_log_model::insert(PDO, int $userId, int $delta, string $referenceType, ?int $referenceId, int $balanceAfter, string $eventUuid, ?string $metadataJson): int`** — Phase 2 ships; Phase 4 calls it twice per transaction (once for buyer, once for seller) with distinct UUID v7 `event_uuid` values.
- **`User\Model\user_model::findById(int $userId): ?array`** — Phase 2 ships; Phase 4 uses it for the `redeemed_count` lookup in the points stub.
- **`Support\RateLimit` named limits** — Phase 2 ships with `login` and `register`. Phase 4 adds `purchase` (10/hr/user) and `redemption` (5/hr/ticket).
- **Listing-modal Buy Now slot** — Phase 3's `src/Listing/View/listing_modal.php` already renders the "Buy now" `<a>` tag at lines 4973+ (the link points to `/listings/{id}/buy` for guests, opens the confirmation modal for logged-in users). Phase 4 reuses this slot; the `href` and the data attribute on the link are the only changes.
- **Listing-modal "Out of stock" + "Self-owned listing" affordances** — Phase 3's EXPERIENCE.md L196-197 already specifies these states. Phase 4 honors them in the modal markup (the existing "Buy now" link is already conditional on `$listing['status'] === 'active'`).

### Established Patterns
- **Layered Modular Monolith** (AD-1) — Bootstrap → FrontController → Action → Service → Model → PDO. Phase 4's `Ticket/Action/*_action.php` files are thin: validate input (CSRF + rate limit + atomic UPDATE preconditions), call `Ticket/Service/ticket_service.php`, render View. All state mutation goes through `Ticket/Service/ticket_service.php` (the ticket-context sole writer) and `Points/Service/points_service.php` (the points-context sole writer per AD-10).
- **Failure envelope** (AD-16) — Every Action returns `['ok' => bool, 'data' => mixed, 'error' => ['code' => string, 'message' => string, 'fields' => array|null]]`. The View renders `error.message` only via `htmlspecialchars()`. UI switches on stable error codes (`E_VALIDATION`, `E_TICKET_NOT_FOUND`, `E_TICKET_FORBIDDEN`, `E_TICKET_INVALID_STATE`, `E_TICKET_CODE_COLLISION`, `E_RATE_LIMIT`, `E_CSRF`, `E_DISPUTE_INVALID_REASON`).
- **Tokens-as-contracts** (Phase 1) — Every color/spacing/typography/elevation token in `tickettrade.tokens.css` traces to a row in `DESIGN.md`'s contrast ledger. Phase 4 inherits this; no new token additions (the ticket-code-block, session-progress, and status-badge tokens already ship).
- **Self-registering JS components** (Phase 1) — `data-component="..."` attributes register behavior. Phase 4 ADDS `data-component="ticket-code-block"` for the mask/reveal/copy/WhatsApp behavior (promoted from the Phase 1 mockup's inline script to a reusable component). Reuses `toast`, `bottomNav`, `skeleton`, `prefersReducedMotion`.
- **Migrations runner** (Phase 2 D-22..D-28) — Each `.sql` migration runs in a single transaction, `IF NOT EXISTS`/`IF EXISTS` discipline, `.applied` file tracks progress. Phase 4 adds four migration files following the same pattern.
- **Atomic UPDATE for state mutation** (AD-9) — Every state-changing ticket operation is a single `UPDATE tickets SET ... WHERE ticket_code = ? AND status = ? AND dispute_status != 'pending' AND seller_id = ?`. `rowCount() === 0` is the invalid branch and maps to `E_TICKET_INVALID_STATE` or `E_TICKET_FORBIDDEN`. The pattern is enforced in `Ticket/Service/ticket_service.php`; no Action writes to `tickets` directly.
- **Sole-writer for points** (AD-10) — `Points/Service/points_service.php` is the only writer of `points_log` and the only updater of `users.points` and `users.tier`. Phase 4's `awardTransaction()` lives in the same Service; the existing `awardVerificationBonus()` is the template.
- **Hand-triggered cron** (Phase 3 D-28..D-30) — `POST /admin/cron/ticket-expiry` is the manual trigger; admin-only with the AD-19 re-auth gate. Response shape is JSON: `{ok: true, processed: N, errors: []}`. Phase 4 EXTENDS the existing Phase 3 Action to add the ticket-expiry and dispute-auto-dismiss branches. The Action dispatches the three sweeps in order: 24h listing auto-approve → 3-day dispute auto-dismiss → ticket expiry (per D-07).
- **Idempotent cron** (NFR-REL-002) — Re-running the cron within the same wall-clock day produces the same end state. The Phase 3 `cron_log` table is the audit trail (a row per run with `processed_count` + `errors_json` + `actor_user_id`).

### Integration Points
- **`config/routes.php`** — Phase 4 ADDS the routes listed in the canonical-refs section. The existing `GET /my-tickets`, `GET /sales`, `GET /purchases` route entries stay (their Action class names do not change; their bodies do). The existing `POST /admin/cron/ticket-expiry` route's Action stays (the Phase 3 `ListingAutoApproveAction` is renamed to `CronAction` and the body is extended; the route entry's class name updates to match).
- **`config/bootstrap.php`** — Phase 4 requires the new Service / Model / Audit classes. The bootstrap does not change structurally.
- **`config/rate_limits.php` (MODIFIED)** — Returns the named limits map. Phase 4 adds `purchase => ['limit' => 10, 'window_seconds' => 3600, 'per' => 'user']` and `redemption => ['limit' => 5, 'window_seconds' => 3600, 'per' => 'ticket']`. `Support\RateLimit::hit($name, $key)` reads this map.
- **`src/Support/View/partials/ticket_code_block.php` (NEW)** — Renders the ticket-code-block markup: `<div class="ticket-code-block" data-component="ticket-code-block" data-code-value="{$code}"><code class="ticket-code-block__code">{$code}</code>...</div>`. The `data-code-value` attribute is what the JS reads for the WhatsApp share URL.
- **`src/Support/View/partials/session_progress.php` (NEW)** — Renders the session-progress markup: `<div class="session-progress" aria-label="Sessions used"><span class="session-progress__count">2/5</span><span class="session-progress__bar" aria-hidden="true"><span class="session-progress__fill" style="width: 40%"></span></span><span class="caption">sessions</span></div>`. The `width` style is computed as `($session_number / $total_sessions) * 100` percent.
- **`src/Support/View/partials/status_badge.php` (NEW)** — Renders the status-badge markup: `<span class="status-badge status-{$status}" role="status">{$label}</span>`. The label is the human name (`Active`, `Redeemed`, `Expired`, `Disputed`).
- **Layout template** — Phase 2 ships `src/Support/View/layout.php`. Phase 4 uses it as-is for every page wrapper.
- **Admin cron Action** — Phase 3 ships `src/Listing/Action/ListingAutoApproveAction.php`. Phase 4 either RENAMES it to `src/Admin/Action/CronAction.php` and moves it to the `Admin` context (since cron is admin-surface), or RENEWS it in place. The cleaner path is the rename + move (the listing-auto-approve branch is just one of three sweeps in the cron Action; the `Listing` context shouldn't own admin cron endpoints).
- **Public ticket detail page** — Optional. If Phase 4 ships a `GET /tickets/{id}` detail page (for the dispute deep-link in a notification), it's a thin View rendering the ticket-code-block, status, listing title, seller info, and the dispute action if eligible. The PRD does not require it; the route is OPTIONAL in Phase 4 and may be deferred to a later phase if scope is tight.

</code_context>

<specifics>
## Specific Ideas

- The My Tickets page matches the `mockups/my-tickets.html` markup exactly: 5 tabs (All / Active / Redeemed / Expired / Disputed), each ticket card with the corkboard-style article container (`<article class="listing-card" style="--rot: {$crc32_seed}deg">` for visual continuity with the board), status badge, listing title, price, seller info with rank badge, and the ticket-code-block (mask/reveal/copy/WhatsApp). The `--rot` rotation is reused (Phase 1 token; Phase 3 corkboard convention) so the My Tickets page has the same paper-on-cork feel.
- The ticket-code-block's WhatsApp share URL is built server-side: `https://wa.me/?text=My%20ticket%20code%3A%20{urlencode($code)}` if the seller has not provided WhatsApp, or `https://wa.me/{seller_whatsapp}?text=My%20ticket%20code%3A%20{urlencode($code)}` if the seller has. EXPERIENCE.md L146 specifies the disabled state with tooltip "Seller has not shared WhatsApp" when the seller's `users.whatsapp` is NULL.
- The Sales page group header for a `total_sessions > 1` listing shows the per-listing progress chip: `<span class="caption">2/5 sessions confirmed</span>` (computed as `count(redeemed sessions) / total_sessions` across the listing's tickets, OR per-ticket for the in-progress one). The "Confirm next session" button is rendered next to the in-progress ticket's `Confirm next session` row (not as a global button on the group).
- The dispute modal's reason dropdown values (in `ENUM` order): `seller_unresponsive, item_not_as_described, buyer_unresponsive, other`. The default is `seller_unresponsive` (per UJ-5 climax). The text field has a 200-char max with a live counter (`<small class="form-text">{$remaining} characters remaining</small>`). The submit button is disabled until the reason is selected AND the text is non-empty (per UX-DR-37).
- The cron Action's response payload includes per-sweep counts: `{ok: true, sweeps: {listing_auto_approve: {processed: 5}, dispute_auto_dismiss: {processed: 2, affected_tickets: ['TK-...', 'TK-...']}, ticket_expiry: {processed: 10}}, errors: []}`. This makes the admin-visible cron run report (Phase 8) actionable.
- The `Support\Audit` stub's `log()` signature: `log(?int $actorUserId, string $action, string $targetType, int $targetId, ?array $metadata): int`. The action names are namespaced (`ticket.created`, `ticket.redeemed`, `ticket.session_confirmed`, `ticket.dispute_filed`, `ticket.expired`, `ticket.dispute_auto_dismissed`). Phase 8's hash chain wraps the same signature.
- The points stub's `redeemed_count` is incremented inside the `awardTransaction()` transaction: `UPDATE users SET redeemed_count = redeemed_count + 1 WHERE user_id IN (?, ?)` (only after both points_log rows are inserted and both `users.points` / `users.tier` are updated). The increment is `+1` for the ticket redemption, regardless of `total_sessions` (the halving is about the user, not the ticket). For per-session confirmations, the count is only incremented on the FINAL session (per FR-PTS-007 wording "until the first 5 confirmed redemptions").
- The Phase 4 plan's WAD-friendly demo path: register as buyer → verify → browse board → click a textbook listing → click "Buy now" → confirm → see toast "Ticket created. Code: TK-XXXX-XXXX-XXXX-XXXX-XXXX" → redirected to My Tickets → masked code revealed → copy + WhatsApp share → switch to seller account → paste code into Sales redemption input → ticket redeemed, points awarded. For a service listing: buy 5 sessions → seller confirms session 1 (no points) → session 2 (no points) → ... → session 5 (points awarded, ticket auto-redeemed). For a dispute: buyer clicks Dispute on an active ticket → reason "seller_unresponsive" + text → toast "Dispute submitted" → ticket `dispute_status='pending'`. Admin can run the cron manually to see the 3-day auto-dismiss and 7-day expiry branches.

</specifics>

<deferred>
## Deferred Ideas

- **Evidence image upload on dispute** — D-04. Optional per PRD; deferred to v2. Would require a new `Support\ImageUpload::processTicketEvidence()` method, a new storage bucket (`/var/www/uploads/disputes/`), new per-image auth (buyer/seller/admin of the ticket), and a new file-picker UI in the dispute modal.
- **Real-time ticket status updates** — Polling on My Tickets is sufficient for the WAD scope. WebSockets or server-sent events would be a v2 enhancement.
- **Ticket-level WhatsApp deep-link from buyer's profile** — The PRD marks WhatsApp share as a ticket action; the deeper "message the seller from the buyer's profile" affordance is out of scope.
- **Bulk ticket operations for admin** — Phase 7's reports queue surfaces disputed tickets; bulk resolution is admin work, not user-facing.
- **Ticket code regeneration on collision** — D-01's retry loop handles the rare `UNIQUE` violation; UI shows an error after 10 attempts (PRD OQ-004). A "regenerate on demand" affordance is not needed.
- **Multiple tickets per purchase** — Out of scope. Each "Buy Now" produces exactly one ticket (the `total_sessions` field on the ticket handles the multi-session case for services).
- **Refund / cancellation flow** — Out of scope. The 7-day expiry + dispute system covers the trust gap; no formal return flow per PRD scope table.
- **Per-listing dispute (item_not_as_described affecting one of many)** — The current model disputes the WHOLE ticket. A per-session dispute (for `total_sessions > 1`) is a v2 enhancement.
- **Cohort isolation (AD-20)** — Single-cohort MVP. At S2 retro, the team decides whether to add `cohort_id` in a later migration. The gate is documented in `PROJECT.md` Blockers and applies to Phase 4's tickets / points_log / reports writes.
- **Full hash-chained audit log** — Phase 4 ships the `Support\Audit` stub that writes plain rows. Phase 8 (admin console + audit) wires the hash chain. The stub is forward-compatible: the `log()` signature does not change.
- **Scheduled cron** — Phase 4 ships the Action endpoint and the Phase 3 cron-log table. Phase 9 wires the actual scheduler (cron tab, systemd timer, or a lightweight `while (true) sleep 3600` daemon) to call the Action hourly. The manual trigger is the v1 contract.
- **Admin Force Expire / Force Redeem / Dismiss dispute resolution Actions** — Phase 7. Phase 4 ships only the buyer/seller file-dispute Action; the resolution branches in the cron are auto-resolve only (the 3-day auto-dismiss). Phase 7's reports queue surfaces the disputes and wires the manual resolution Actions.
- **Review surface on redeemed tickets** — Phase 5 wires the "Leave review" button on `redeemed` tickets within 14 days, per FR-RAT-001/006. Phase 4 ships the `purchases` View with the date column; the review affordance is a Phase 5 addition.
- **Real-time chat between buyer and seller** — Out of scope per PRD scope table; WhatsApp share is the primary handover coordination channel.

### Reviewed Todos (not folded)

No todos were folded from `cross_reference_todos` (the step returned `todo_count: 0`).

</deferred>

---

*Phase: 4-Purchases, Tickets & Lifecycle*
*Context gathered: 2026-09-02*
