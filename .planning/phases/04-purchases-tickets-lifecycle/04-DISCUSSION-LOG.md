# Phase 4: Purchases, Tickets & Lifecycle - Discussion Log

**Gathered:** 2026-09-02
**Mode:** default (interactive, user opted for all-areas without per-question follow-up)

## Discussion Summary

User chose **"A — discuss all seven gray areas"** and asked the agent to use the most suitable option for the WDS assignment and continue without further questions. The discussion ran in single-pass mode; each area was locked with a decision, a rationale, and a carry-forward reference.

## Areas Discussed

### 1. Ticket code display format
- **Question:** PRD says `TK-XXXXXXXXXXXXXXXXXXXXXX` (22 contiguous base62 chars); `mockups/my-tickets.html` renders `TT-7H42-9XQM-LK81` (dashed4-char groups). Should the UI render dashed for readability while keeping canonical 22-char form, or stick with PRD's contiguous form?
- **Options presented:** (A) Dashed form `TK-XXXX-XXXX-XXXX-XXXX-XXXX` for readability; canonical 22-char form is what's stored and validated. (B) Contiguous form per PRD verbatim; same data, no dashes anywhere.
- **Locked:** (A) — Dashed form `TK-XXXX-XXXX-XXXX-XXXX-XXXX` is the canonical stored form. The dashed form IS the contract: buyer copies dashed, seller pastes dashed, redemption matches against the dashed `ticket_code`. **Reversibility:** one-way — dashed form becomes a published contract.
- **Rationale:** PRD FR-TKT-001 normative format is the source of truth for storage; UX improvements (readability) are appropriate at the View layer. The dashed form serves both the canonical contract and the human-facing display.

### 2. Purchase success UX
- **Question:** After Buy Now confirm, show the code in an intermediate success modal OR redirect to My Tickets with toast?
- **Options presented:** (A) Redirect to My Tickets with toast `Ticket created. Code: TK-...` per PRD/EXPERIENCE prescription. (B) Brief success modal showing the code then auto-redirect.
- **Locked:** (A) — Redirect to My Tickets with toast. The new ticket card is auto-focused on the redirected page. **Reversibility:** reversible.
- **Rationale:** PRD §1.5 Flow 2 climax and EXPERIENCE.md L200-201 prescribe this verbatim. One-click path, no double-modal.

### 3. Dispute window scope
- **Question:** Disputes filable on `active` only, OR on `active` AND `redeemed`?
- **Options presented:** (A) `active` only (PRD FR-TKT-008 strict reading). (B) `active` AND `redeemed` (EXPERIENCE.md L235 + UJ-5 ghosting case).
- **Locked:** (B) — Disputes on `active` AND `redeemed` tickets. State machine splits: filing on `active` flips `status='disputed'` + `dispute_status='pending'`; filing on `redeemed` keeps `status='redeemed'` and only sets `dispute_status='pending'`. The 3-day auto-dismiss timer starts at the `dispute_status='pending'` transition. **Reversibility:** costly.
- **Rationale:** EXPERIENCE.md L235 explicit; UJ-5 climax shows the post-handover case. The state machine splits naturally — a dispute about handover quality is a separate matter from "is this ticket still redeemable".

### 4. Dispute evidence image
- **Question:** Ship evidence image upload in Phase 4, OR defer to text-only disputes?
- **Options presented:** (A) Ship evidence upload (new storage bucket, new per-image auth, new modal UI). (B) Defer to v2; Phase 4 is text-only.
- **Locked:** (B) — Defer evidence image to v2. Phase 4 ships text-only disputes. **Reversibility:** reversible.
- **Rationale:** Phase 3 `Support\ImageUpload` is listing-specific. Reusing for tickets needs parallel pipeline + new storage bucket + new per-image auth. The PRD marks evidence as optional. Deferring keeps Phase 4 scoped to the core state machine + UX + 3 sweeps.

### 5. Per-session service handover placement on Sales
- **Question:** Per-listing-group placement OR a global "Next pending session" callout at top of Sales?
- **Options presented:** (A) Per-listing-group (PRD FR-TKT-014 explicit shape). (B) Global callout at top of Sales.
- **Locked:** (A) — Per-listing-group. Each listing renders as a card group; the in-progress ticket carries the "Confirm next session" button. **Reversibility:** reversible.
- **Rationale:** PRD FR-TKT-014 explicit; EXPERIENCE.md shows grouping; per-ticket `#N/M` progress is PRD-mandated. Per-listing-group matches the buyer My Tickets view and is symmetric across the two halves of the flow.

### 6. Points stub fidelity
- **Question:** Should Phase 4's points stub honor FR-PTS-007 (new-account halving) or award flat values?
- **Options presented:** (A) Stub honors FR-PTS-007 only (halve for first 5 redemptions); FR-PTS-005/006 deferred to Phase 6. (B) Stub awards flat values; all multipliers in Phase 6.
- **Locked:** (A) — Stub honors FR-PTS-007 via `users.redeemed_count` column. FR-PTS-005/006 deferred to Phase 6 (marked TODO). **Reversibility:** reversible.
- **Rationale:** FR-PTS-007 is a per-row computation the stub can do in the same transaction. FR-PTS-005/006 are aggregate-window checks that need a daily summary table (Phase 6 domain). The Service interface is the Phase 6 contract.

### 7. 3-day dispute auto-dismiss — created_at preservation
- **Question:** Confirm the cron implements FR-TKT-013 precisely (no `created_at = NOW()` on dismiss branch)?
- **Options presented:** (A) Cron implements FR-TKT-013 precisely: dismiss does NOT touch `created_at`; restored to pre-dispute `status`; idempotent; sweep order dismiss → expire. (B) Cron resets `created_at` for a fresh 7-day window on dismiss.
- **Locked:** (A) — Cron implements FR-TKT-013 precisely. **Reversibility:** reversible.
- **Rationale:** PRD FR-TKT-013 explicit; PRD §4.2 composition note explicit; AD-11 cron ownership. Locking the sweep order (dismiss before expire) avoids the edge case where a dismissed ticket is `active` for one wall-clock minute before expiring.

## the agent's Discretion Items (not discussed, locked from prior decisions)

The following are routine implementation choices that follow from locked requirements or are appropriate for a WAD-assignment scope. They are recorded in CONTEXT.md under the agent's Discretion section:

- Migrations: `013_tickets.sql` (tickets table), `014_users_redemption_count.sql` (`users.redeemed_count`), `015_reports.sql` (reports table), `016_audit_log_stub.sql` (plain `audit_log`, hash chain in Phase 8).
- `Support\Audit` stub: `log($actor, $action, $targetType, $targetId, $metadata): int` with namespaced action names. Hash chain wired in Phase 8.
- Base62 alphabet: `0-9A-Za-z` (62 chars), matching the canonical PRD example `TK-7QXK2M9WBV4N8PRTYC3AD`. No ambiguous-char rejection.
- 7-day expiry write-once: `expires_at` set at INSERT, never updated.
- Purchase rate-limit naming: `purchase` (10/hr/user) on `POST /listings/{id}/buy`.
- Redemption rate-limit keying: `redemption:ticket:{ticket_id}:{user_id}:{window}` for per-ticket scoping.
- No dispute rate limit in Phase 4 (3-day auto-dismiss + atomic WHERE clause make it unnecessary).
- `session_number` = 1 for product tickets; `1/1` display is a no-op.
- Ticket auto-focus on redirect via inline `<script>`.
- Self-purchase prevention: Buy Now hidden when `seller_id === current_user`; Service also guards atomically.
- Empty states with named copy (My Tickets / Sales).
- Toast copy verbatim from EXPERIENCE.md.
- Dispute modal scrim-guard 2s (matches purchase confirmation modal).
- My Tickets 5-tab structure with `bg-secondary` badge counts.

## Deferred Ideas (captured for future phases)

Recorded in CONTEXT.md under `<deferred>`:

- Evidence image upload on dispute (D-04)
- Real-time ticket status updates
- Ticket-level WhatsApp deep-link from buyer's profile
- Bulk ticket operations for admin
- Ticket code regeneration on collision (PRD OQ-004 — retry loop sufficient)
- Multiple tickets per purchase
- Refund / cancellation flow
- Per-listing dispute (item_not_as_described affecting one of many)
- Cohort isolation (AD-20 — S2 retro)
- Full hash-chained audit log (Phase 8)
- Scheduled cron (Phase 9)
- Admin Force Expire / Force Redeem / Dismiss dispute resolution Actions (Phase 7)
- Review surface on redeemed tickets (Phase 5)
- Real-time chat between buyer and seller (out of scope per PRD)

## Reviewed Todos (not folded)

No todos were folded from `cross_reference_todos` (the step returned `todo_count: 0`).

---

*Phase: 4-Purchases, Tickets & Lifecycle*
*Discussion log: 2026-09-02*
