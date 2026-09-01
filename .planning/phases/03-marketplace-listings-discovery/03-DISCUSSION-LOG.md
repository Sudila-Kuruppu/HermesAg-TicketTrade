# Phase 3: Marketplace Listings & Discovery - Discussion Log

**Gathered:** 2026-09-01
**Mode:** Default (interactive, gray areas auto-decided)
**Phase status:** Ready for planning

---

## Session summary

User invoked `/gsd-discuss-phase 3` on TicketTrade's Phase 3 with the assignment-context note: "since we're doing `WAD_Batch26.md` topic 4. use suitable as students make this... make reasonable defaults and continue".

This discussion log records that the user delegated all gray-area decisions to the agent with the framing constraint that Phase 3 should fit a WAD coursework Topic 4 student build (screenshot-ready demo, simple CRUD, search by category, admin approval queue, browse, listings management).

The agent made reasonable defaults on every gray area and wrote `03-CONTEXT.md` without a per-area question loop. All decisions are recorded in `03-CONTEXT.md`; this log records the framing context only.

---

## Framing context

- Source brief: `WAD_Batch26.md` Topic 4 (NSBM Marketplace - Student Business and Service Platform).
- Topic 4 minimum scope per the brief: admin (manage users, approve listings, manage categories, view sales reports) and students (register, add/edit/remove listings, browse, search by category, simulate purchases, manage personal listings).
- Topic 4 encourages "additional innovative features beyond the minimum requirements" - the PRD's gamification/ticket/dispute layers are those innovations.
- Constraint carried into every choice: keep the implementation screenshot-ready, demoable in <90s, and easy to explain in the WAD video demo.

---

## Gray areas decided (see 03-CONTEXT.md for full text)

### 1. Listing state machine UX
- D-01/D-02/D-03: Four tabs (Active / Pending / Sold / Draft) with minimal per-state action affordances, named empty-state copy, no badge counts. Decision driven by WAD screenshot-readiness.

### 2. Approved-content fast-track + review_flag
- D-04/D-05/D-06/D-07: Relist copies to draft with `source_listing_id`; fast-track skips pending when source was approved. Edits to active listings set `review_flag = TRUE` and surface an "Under review" pill. Admin queue merges flagged into the FIFO pending queue. `listing_revisions` audit table for soft-revert on rejected edits. Decision driven by PRD `LST-09` + "demonstrate edit history" angle for the WAD video.

### 3. Image upload UX + pipeline
- D-08..D-14: Sequential upload on submit (no async chunked), 8 images max, drag-to-reorder, 4-layer validation pipeline, content-addressed storage at `/var/www/uploads/listings/<sha256>.webp`, proxy-mediated serving. Decision driven by AD-14 (architectural mandate) and WAD scope (no need for chunked UX in v1).

### 4. Board view loading & search
- D-15..D-19: FULLTEXT BOOLEAN MODE search, numbered pagination (NOT infinite scroll), category tab strip with horizontal scroll on mobile, newest-first sort only, guest browse shows corkboard read-only with CTA gated to login. Decision driven by WAD demo path + D-09 from Phase 2.

### 5. Listing modal
- D-20..D-24: Full-screen mobile / centered desktop, Bootstrap carousel `interval: false`, keyboard arrows + ESC + focus trap, mobile swipe with 50px threshold. Inline scripts (~50 LOC total) rather than new JS components. Decision driven by WAD scope + EXPERIENCE.md.

### 6. Public landing page
- D-25..D-27: Hero + Vision & Mission + How It Works (5 steps) + Team (6 cards from `config/team.php`) + Footer. "Explore Marketplace" routes guests to `/board` directly (NOT a redirect to login). Decision driven by PRD `LND-01..08`.

### 7. Hand-triggered cron endpoint
- D-28..D-30: `POST /admin/cron/ticket-expiry` (forward-compat name) implements only the listing auto-approve branch in Phase 3; JSON response shape; admin re-auth gated (AD-19). Decision driven by Phase 3 success criterion #2 + forward-compat for Phase 4's expiry/dispute sweeps.

### 8. Categories seed
- D-31..D-32: Seven categories with stable `sort_order`; soft-deletable via `is_active`. Decision driven by Phase 3 success criterion #11.

---

## Deferred items (recorded in 03-CONTEXT.md)

- Async/chunked image upload (Uppy.js) - deferred per D-08; the `chunkSize: 2 MiB` gotcha note is preserved as a comment in `Support\ImageUpload`.
- Buy Now wiring - Phase 3 ships a disabled CTA; Phase 4 wires it.
- Hash-chained audit log - Phase 3 logs to plain `cron_log`; Phase 4 migrates rows.
- Cohort isolation (AD-20) - S2 retro gate, unchanged from prior phases.
- All "out of scope for v1" items per the PRD's Out of Scope list.

---

## Files written this session

- `.planning/phases/03-marketplace-listings-discovery/03-CONTEXT.md` - canonical context record (replaces per-question checkpoints).

---

*Logged: 2026-09-01 after default-mode auto-decision of all 8 gray areas*
