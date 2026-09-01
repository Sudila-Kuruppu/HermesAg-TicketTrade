# Phase 3: Marketplace Listings & Discovery - Context

**Gathered:** 2026-09-01
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 3 ships the full listings slice end-to-end so that NSBM students can sell products/services to other students and discover what is on offer. Concretely:

1. **Seller side** - full CRUD on listings through the state machine `draft -> pending -> active | rejected | removed` (plus `sold` when exhausted), with the approved-content fast-track for relists and a `review_flag` for edits to active listings. Seller dashboard with four tabs (Active / Pending / Sold / Draft) and a one-click Relist action.
2. **Buyer + guest side** - corkboard board view at `/board` with category tabs/filter, FULLTEXT search, pagination (<=50/page), per-card hover lift, full-screen listing modal with image carousel + keyboard + swipe navigation, and the guest-browse preview that gates "Buy Now" to login (D-09 from Phase 2 - guest can browse, cannot buy).
3. **Public landing page** at `/` with hero, Vision & Mission, How It Works (5 steps), Team cards (6 students), and Footer. Replaces the Phase 2 stub.
4. **Image pipeline** - `Support\ImageUpload` (4-layer validation: finfo -> getimagesize -> magic bytes -> GD re-encode to WebP), three thumbnails (200/600/1200 px WebP 80%), storage at `/var/www/uploads/listings/<sha256>.webp`, served via `Support\ImageProxy` with the AD-14 auth rules (thumbnails public-rate-limited, full-size auth-checked).
5. **Categories seed** - seven hand-curated categories ship with Phase 3 so the tab strip and search work from day one. Admin categories CRUD remains Phase 8 work.
6. **Hand-triggered 24h listing auto-approve sweep** at `POST /admin/cron/ticket-expiry` (the route is forward-compat: Phase 4 extends it with ticket expiry and dispute auto-dismiss).

The core loop "list -> approve -> buy -> ticket -> redeem -> expire -> dispute" (PRD sec 1) is NOT in Phase 3 - buy/ticket/redeem/expire/dispute land in Phase 4. Phase 3 closes once a seller can list a product/service, an admin (or 24h cron) can approve it, and a guest or buyer can find it on the board and open it in the modal.

### Assignment context (WAD_Batch26.md Topic 4)

This phase implements Topic 4 of the WAD coursework brief (Student Business and Service Platform). The WAD brief specifies the minimum scope (admin approve product listings, manage categories, view sales reports; students add/edit/remove listings, browse, search by category, simulate purchases, manage personal listings). TicketTrade's PRD add gamification, digital tickets, disputes, reviews, and points on top of that minimum - those are the "additional innovative features" the brief encourages. Phase 3 ships the WAD-minimum CRUD + discovery surfaces and exposes the gamification hooks (rank badge on seller info, verified checkmark, profile link) without yet awarding them; Phase 6 lights up the points engine, Phase 4 wires purchases to tickets.

</domain>

<decisions>
## Implementation Decisions

### State machine UX (seller dashboard)
- **D-01:** Four tabs in fixed order - Active / Pending / Sold / Draft - with the active tab underlined and `aria-current="page"`. Counts in each tab header are NOT badges (Phase 1 nav convention); the count is a small `text-on-surface-variant` inline next to the tab label. **Reversibility:** reversible - changing to badge counts later is a template tweak.
- **D-02:** Per-listing action affordances by state, kept minimal so the dashboard demo is screenshot-ready in under a minute:
  - **Active** -> Edit . Delete (soft-delete sets status to `removed`)
  - **Pending** -> Edit . Delete (no resubmit button - submit only happens once at draft->pending; further edits keep status pending)
  - **Sold** -> Relist (primary CTA, fills the draft with same fields)
  - **Draft** -> Edit . Submit (moves draft -> pending)
  - **Rejected** -> Edit . Delete (Edit on rejected: status flips to `draft` so the seller can fix and resubmit; rejection reason shown at top of edit form in a `surface-error-container` banner with `aria-live="polite"`)
- **D-03:** Empty tab state uses the named copy from `EXPERIENCE.md`'s empty-state pattern ("No active listings yet - your first listing is one click away" with a primary "Create listing" button). All four tabs follow the same skeleton-on-cold-load pattern as the board. **Reversibility:** reversible.

### Approved-content fast-track + review_flag behavior
- **D-04:** Relist copies all fields (title, description, price, category, type, quantity, condition / service details, image attachments) into a new `draft` with `source_listing_id` pointing at the original. On Submit: if `source_listing_id` is non-null AND the source listing has `approved_at IS NOT NULL`, the relist goes directly to `active` (skipping `pending`) with `approved_at = NOW(), approved_by = NULL`; otherwise it goes to `pending` like any new listing. - **Reversibility:** reversible - `source_listing_id` is a nullable column.
- **D-05:** Edit to an `active` listing keeps it live and sets `review_flag = TRUE, review_flag_at = NOW()`. The seller sees a small inline toast on save: "Listing saved. Edits to active listings are reviewed by an admin - your listing stays live while we check." A subtle "Under review" pill (Phase 1 `surface-warning-container` token) renders on the card in My Listings when `review_flag = TRUE`. - **Reversibility:** reversible - the pill is a template conditional.
- **D-06:** Admin queue merges flagged edits into the same FIFO pending queue (no separate "Flagged" tab) - `WHERE status='pending' OR review_flag=TRUE ORDER BY COALESCE(review_flag_at, created_at) ASC`. This keeps admin cognitive load low (one queue) and is the PRD's intent per `LST-09` ("admin queue surfaces flagged listings alongside pending ones"). - **Reversibility:** costly - splitting flagged into its own tab later is a query + UI change.
- **D-07:** When the admin approves a flagged edit, `review_flag` flips to `FALSE` (the column stays for audit trail until the listing reaches terminal state). When the admin rejects a flagged edit, the listing returns to its pre-edit state via a soft-revert (we re-load the previous approved version from a `listing_revisions` audit table). If no audit row exists yet (first edit), rejection moves the listing to `rejected` with the standard rejection reason. - **Reversibility:** costly - the audit table is migration work.

### Image upload UX + pipeline
- **D-08:** Sequential upload on form submit (option-a in the gray area). When the seller picks files in the file input, the form renders an in-page preview grid via `URL.createObjectURL()` (no actual upload yet). On submit, files POST as `multipart/form-data` and the server processes them through `Support\ImageUpload::process()` in source order. The client never sees per-image progress (a single submit spinner is sufficient for an 8-image cap). **Reversibility:** reversible - converting to async XHR later is a JS rewrite of the form, the server endpoint stays.
- **D-09:** Max 8 images per listing (AD-14). The file input has `multiple` and the client-side preview blocks the 9th pick with a `Support\Toast` warning "Maximum 8 images per listing." Server-side enforces the same cap with `E_VALIDATION` and the field-level message "Too many images - remove one and try again." **Reversibility:** reversible - change the cap in one config.
- **D-10:** Image sort order is the upload order; the FIRST uploaded image is the primary thumbnail (`is_primary = TRUE`). On edit, the seller can drag-to-reorder via the existing `data-component="listViewToggle"` pattern extended with HTML5 drag-and-drop (`dragstart`/`dragover`/`drop`) - no extra library. Remove via a small x button on each thumbnail; removal updates `sort_order` and re-numbers. **Reversibility:** reversible.
- **D-11:** WebP is the only accepted format on output. Source files can be JPEG, PNG, or WebP; the 4-layer pipeline (`finfo` MIME check -> `getimagesize` <=4000px and <=5MB -> magic-byte check -> GD re-encode to WebP) hard-fails any deviation. Failed images produce a per-image error in the form preview (red border + inline message) - the seller can remove the failed image and re-pick. On full failure of all images, the listing is still saved with zero images (admin can reject for "no images" but the form does not block submission - WAD rubric does not require blocking). - **Reversibility:** reversible.
- **D-12:** Storage path is `/var/www/uploads/listings/<sha256-of-original-bytes>.webp`. The SHA256 is content-addressed so the same file uploaded twice dedupes naturally. Three sizes generated at upload time (200/600/1200 px WebP 80% quality). Filename on disk is the SHA256 hex - never the user's original filename - to neutralize path-traversal attempts. - **Reversibility:** one-way - changing storage layout is a migration of existing files.
- **D-13:** Dev environment uses `public/uploads/listings/` (under webroot, but the proxy still mediates every read so the public path is never directly accessible - see D-14). Production deploy places `/var/www/uploads/listings/` outside webroot. The `Support\ImageUpload` helper takes the storage root from `config/uploads.php` so dev/prod swap by config alone. - **Reversibility:** reversible.
- **D-14:** `Support\ImageProxy` is the ONLY path to read a stored image. URLs are `/img/{listing_id}/{size}` where `size in {thumb, medium, full}`. Thumbnails (`thumb`=200, `medium`=600) are public but rate-limited at 60/min/IP (AD-13). Full-size (`full`=1200) requires session AND one of: seller, ticket holder, admin - missing auth returns 404 (not 403, per AD-14). Direct file-system URLs are never exposed in any rendered HTML. - **Reversibility:** reversible.

### Board view loading & search
- **D-15:** FULLTEXT search uses MySQL `MATCH(title, description) AGAINST(? IN BOOLEAN MODE)` so partial keywords work (no relevance ranking required for v1). The search box is in the page header, always visible, `placeholder="Search by title or description"`. Submitting the form preserves the query string in the URL (`?q=...&cat=...&page=...`) so results are bookmarkable and the back button works. - **Reversibility:** reversible.
- **D-16:** Pagination is numbered pages (1, 2, 3, Next) - NOT infinite scroll. Reason for the WAD audience: numbered pages are screenshot-ready, easy to demo, and "back button restore" is automatic without any JS work. Page size is 50 (PRD NFR-PER-002). The pagination control renders at the top AND bottom of the result set for desktop; bottom only on mobile (Bootstrap `pagination` component). - **Reversibility:** reversible - switching to infinite scroll is a JS rewrite of the result container.
- **D-17:** Category tab strip across the top of the board: All + 7 specific tabs (Textbooks, Electronics, Fashion, Services, Food, Events, Other). On mobile (<768px) the strip scrolls horizontally (`overflow-x: auto; -webkit-overflow-scrolling: touch`) with the active tab snapping into view. The active tab is `aria-current="page"` and styled with the underline pattern. **Reversibility:** reversible.
- **D-18:** Sort order: **newest first** (`ORDER BY created_at DESC`) is the only sort for Phase 3. PRD does not lock a sort order; the simplest demo-ready default is most-recent first. Phase 6's "Most active sellers" leaderboard surface can add a secondary sort if needed. - **Reversibility:** reversible - adding a sort dropdown later is one Model change.
- **D-19:** Guest browse (`/board` while logged-out) shows the corkboard view with full content; only the card's CTA flips from "Buy Now" to "Sign in to buy" (which links to `/login?next=/board`). No modal, no redirect. The list-view toggle, category tabs, and search all work for guests. - **Reversibility:** reversible.

### Listing modal
- **D-20:** Modal opens full-screen on mobile (Bootstrap `modal-fullscreen-sm-down`) and centered with `max-width: 800px` on desktop. The opening animation is Bootstrap's default fade. The backdrop click closes the modal (Phase 1 `modalScrimGuard` is NOT applied - that 2s suppression is reserved for the Phase 4 purchase confirmation modal per EXPERIENCE.md). - **Reversibility:** reversible.
- **D-21:** Image carousel uses Bootstrap 5's stock carousel component with `interval: false` (no auto-advance - the WAD audience expects manual control). Indicators are dots at the bottom; prev/next arrows render only when more than one image exists. **Reversibility:** reversible.
- **D-22:** Next/Previous-in-category navigation lives as two icon buttons in the modal header (`<` / `>`). They cycle through listings in the same category the modal was opened from, ordered by `created_at DESC`. End-of-list wraps around to the start. ESC, click backdrop, and the close button all dismiss the modal; focus returns to the originating card on the board. **Reversibility:** reversible - disabling wrap-around is a one-line change.
- **D-23:** Keyboard support: left/right arrows navigate prev/next; Esc closes; Tab cycles within the modal (focus trap). The keyboard handler is a small inline script (~30 LOC) at the bottom of the modal View, not a new component - the `data-component="modal"` pattern from Phase 1 does not yet exist, and the WAD brief does not need it as a separate reusable component. - **Reversibility:** reversible - extracting to a component is mechanical.
- **D-24:** Mobile swipe: `touchstart`/`touchend` with a 50px threshold for prev/next navigation. No external library - the implementation is ~20 LOC and matches the existing vanilla-JS convention. If `prefers-reduced-motion: reduce` is set, swipe still works but the visual slide is replaced with a cross-fade. **Reversibility:** reversible.

### Public landing page
- **D-25:** `/` landing page replaces the Phase 2 stub. Sections in order: Hero (display-lg "Every Trade Ends With Proof", two CTAs: "Get Started" -> `/register`, "Explore Marketplace" -> `/board`) -> Vision & Mission (two cards side-by-side on desktop, stacked on mobile) -> How It Works (5 step cards: 1. Register & verify, 2. List or browse, 3. Buy with a digital ticket, 4. Redeem in person, 5. Rate & review) -> Team (6 cards - see D-26) -> Footer (NSBM branding, "Simulation only" disclaimer, link to `/board`). **Reversibility:** reversible - sections are independent template partials.
- **D-26:** Team section reads from `config/team.php` (a static PHP file returning `[['name'=>'Tharushi P.', 'role'=>'Backend Lead', 'initials'=>'TP'], ...]`). Six entries matching the PRD's 6-person team (Backend x2, Frontend x2, Database x1, QA/Docs x1). Each card shows initials avatar (the first 2 letters of the name, on the `surface-container-high` token), full name, role, and a one-line bio. Editing the team is a one-line config change. **Reversibility:** reversible.
- **D-27:** "Explore Marketplace" CTA from the landing page routes guests to `/board` directly (NOT a redirect to login). The board itself shows the corkboard view in read-only mode for guests per D-19. The WAD rubric's "browse products" expectation is met for guests without an account. **Reversibility:** reversible.

### Hand-triggered cron endpoint
- **D-28:** `POST /admin/cron/ticket-expiry` (the route name is forward-compat - Phase 4 extends it with ticket expiry and 3-day dispute auto-dismiss). Phase 3 only implements the **listing auto-approve** branch: `UPDATE listings SET status='active', approved_at=NOW(), approved_by=NULL WHERE status='pending' AND created_at <= NOW() - INTERVAL 24 HOUR`. Admin-only with the AD-19 re-auth gate (the endpoint requires the admin's password to be re-verified within the last 300s). **Reversibility:** reversible - the route handler can be split in Phase 4 into separate sub-actions.
- **D-29:** Response shape is JSON: `{ok: true, processed: N, errors: []}`. Status 200 on success, 403 on re-auth failure, 404 on missing endpoint. The endpoint is idempotent (running twice produces the same end state because the WHERE clause selects only already-eligible rows). - **Reversibility:** reversible - changing to silent 204 is a one-line change.
- **D-30:** Manual trigger UI lives in `/admin` (Phase 8 surfaces it as a button on the dashboard); Phase 3 only ships the Action endpoint. The plan documents the curl example: `curl -X POST -b 'PHPSESSID=...' /admin/cron/ticket-expiry`. - **Reversibility:** reversible.

### Categories seed
- **D-31:** Seven categories ship with stable `sort_order`: 1=Textbooks, 2=Electronics, 3=Fashion, 4=Services, 5=Food, 6=Events, 7=Other. Migration `011_categories.sql` creates the `categories` table and inserts the seven rows in a single transaction. These are the v1 set; Phase 8's admin categories CRUD allows renaming/adding. - **Reversibility:** reversible - adding a category is one SQL `INSERT`.
- **D-32:** Categories are soft-deletable via `categories.is_active` (default TRUE). Listing queries filter `JOIN categories ON ... WHERE categories.is_active = TRUE`. Phase 8's admin CRUD sets `is_active = FALSE` to "delete" without losing historical listing references. - **Reversibility:** reversible.

### the agent's Discretion

These items were not explicitly decided but follow from locked requirements or are routine implementation choices appropriate for a WAD-assignment scope:

- **Migrations** - `008_listings.sql` creates `listings`, `listing_images`, `listing_revisions` tables. `009_listing_images.sql` is split out for index clarity. `010_listing_revisions.sql` for the audit table. `011_categories.sql` for the categories seed. Per D-23 of Phase 2, migrations continue from the highest existing number - Phase 2 ends at `007_cache_rate.sql`, so Phase 3 migrations start at `008_*`. - **Reversibility:** reversible - renumbering before production is cheap.
- **Validation rules** - title `MAX 80 chars`; description `MAX 2000 chars`; price `INTEGER cents > 0` and `<= 100_000_00` (LKR 100,000 ceiling to match the WAD "second-hand goods" scope); quantity `INTEGER >= 1, <= 999`. All enforced at the Service layer with `E_VALIDATION` error codes; UI surfaces field-level messages.
- **Service details (type='service')** - `duration_minutes INT NULL` (1..600), `delivery_method ENUM('in_person','online','hybrid') NULL`, `availability TEXT NULL` (free-text, MAX 500 chars). Stored only when type='service'; products have all three NULL.
- **Condition (type='product')** - `condition ENUM('new','like_new','good','fair') NULL`. Stored only when type='product'.
- **Rate limit for listing creation** - 20/hr/user (AD-13 / NFR-SEC-007). Wired via the `rate_limit: 'listing_create'` flag on the route map; uses the `Support\RateLimit` helper.
- **Listing detail URL** - `/board` opens the modal via fragment (`#listing-{id}`); there is NO standalone `/listings/{id}` page in Phase 3. Phase 4 may add it for deep-link / share / SEO. For now, the modal URL pattern matches the WAD "browse + click" demo flow.
- **Listing status display** - small status pill on each card (`bg-pending-fill` for Pending, `bg-success-fill` for Active, `bg-error-fill` for Rejected/Removed, `surface-container-high` for Sold, dashed border for Draft). Pill text is the human label (`Pending`, `Active`, `Sold`, `Draft`, `Rejected`, `Removed`).
- **The "Buy Now" stub** - Phase 3 renders the button on the modal as `disabled` with a tooltip "Available after Phase 4 launch" OR as `active` for logged-in buyers with a JS console message `console.info('Buy Now is wired in Phase 4')`. Plan 03-03 picks one - recommendation is the `disabled` path so the WAD demo is not broken by a stub that pretends to work. - **Reversibility:** reversible - wiring real Buy Now is Phase 4's first task.
- **Reject / remove reasons** - `rejection_reason TEXT NULL` on the `listings` table; admin enters free-text reason on rejection (Phase 8 admin queue UI). Phase 3 ships the column but not the UI.
- **Listing revisions audit table** - `listing_revisions(id, listing_id, snapshot_json, created_at, created_by)` captures the pre-edit state on every edit to an `active` listing, so admin "reject edit" can soft-revert. Cheap and student-friendly for the WAD video demo ("edit history is preserved").
- **MySQL FULLTEXT minimum word length** - default 4 chars (InnoDB). The search box tells users via `aria-describedby` "Type at least 4 characters" so the demo audience does not wonder why "car" does not match "carpool".

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing Phase 3.**

### Topic 4 brief and prior decisions
- `WAD_Batch26.md` section 2 Topic 4 - Authoritative WAD assignment scope: admin approves product listings, manages categories, views sales reports; students add/edit/remove listings, browse, search by category, simulate purchases, manage personal listings. Topic 4 explicitly encourages "additional innovative features" - the PRD's gamification/ticket/dispute layers are those innovations.
- `.planning/phases/02-student-authentication-profiles/02-CONTEXT.md` - All 28 Phase 2 decisions (D-01..D-28). Critical for Phase 3: D-09 (guest browse), D-11 (route map shape), D-20/D-21 (response headers / CSP), D-22..D-28 (migrations runner), the `Support\Auth`/`Csrf`/`RateLimit`/`Crypto`/`ResponseHeaders` substrate every later phase assumes.
- `.planning/phases/01-ux-foundation-design-system/01-CONTEXT.md` - D-11/D-12/D-13 (the `tickettrade.js` bundle and its data-component pattern), D-05/D-06 (theme + FOUC guard), D-09 (mockups link the same `tickettrade.css` bundle).
- `.planning/PROJECT.md` - Tech stack, constraints, key decisions (corkboard is decorative-only, 6-tier rank system, single-tenant cohort, simulated payments). Blockers section confirms AD-20 cohort gate is at S2 retro, not Phase 3.
- `.planning/REQUIREMENTS.md` - LST-01..16 (listings), LND-01..08 (landing + board), PER-02/PER-03 (image pipeline), SEC-03/SEC-04 (rate limits + CSP). Phase 3 implements all of LST, LND, PER-02, PER-03, SEC-03.

### Architecture and ADs
- `ARCHITECTURE-SPINE.md` AD-1..AD-20 - The binding layer rules and decisions. Critical for Phase 3:
  - AD-1: Action -> Service -> Model dependency arrow (Plan must not import upward).
  - AD-2: `Listing`, `Category` are bounded contexts; cross-context work goes through Services only.
  - AD-3: `public/` is the webroot, `src/` is outside.
  - AD-4: hand-rolled route list in `config/routes.php` (Phase 2 already populates student routes - Phase 3 adds listing CRUD routes).
  - AD-5: PDO prepared statements, no ORM.
  - AD-13: rate limits (listing_create 20/hr/user per NFR-SEC-007), session config.
  - AD-14: image storage outside webroot; ImageUpload 4-layer pipeline (finfo -> getimagesize -> magic bytes -> GD re-encode); ImageProxy auth rules; the Uppy.js `@uppy/tus` client-side `chunkSize: 2 MiB` override note (Phase 3 ships without Uppy per D-08, but the note is preserved for any later async-upload refactor).
  - AD-15: tickets are not in Phase 3 (this AD binds Phase 4+), but the gate exists for review.
  - AD-16: failure envelope on every Action exit.
  - AD-17: operational envelope (`php -S localhost:8000 -t public`, `php migrate.php`, `vendor/bin/phpcs --standard=PSR12 src/`).
  - AD-19: admin re-auth 300s sliding window for `POST /admin/cron/*` endpoints.
  - AD-20: cohort gate is at S2 retro, not Phase 3.

### Visual identity and experience
- `DESIGN.md` - Component patterns: `{components.listing-card}` (standard and corkboard variants), `{components.listing-card-cork}` (paper-card surface `#FFF8E7`, +/-2 deg rotation, pushpin graphic), the cork-base/cork-grain decorative tokens. Typography (Inter display, system-ui body). Spacing/elevation/shapes. The contrast ledger is the source of truth for every token value.
- `EXPERIENCE.md` Information Architecture (Board at `/board` with corkboard + list-view toggle, Listing Modal at `/board#listing-{id}`, Landing at `/`), Component Patterns (Listing card single tab stop, Listing card - corkboard aria-hidden rotation/pin, Listing modal full-screen on mobile centered on desktop, Image carousel no auto-advance), Interaction Primitives (keyboard nav, screen reader ARIA mappings), State Patterns (empty/error/cold-load for every list surface). The WAD rubric values screenshot-ready states; the named copy per surface is the source of truth.
- `mockups/board-mobile.html` - Visual reference for the corkboard board view (4 listings, 2-column grid, dark mode, list-view toggle visible). The Phase 3 board matches this markup.
- `mockups/my-tickets.html`, `mockups/admin-dashboard.html` - Other promoted mockups; Phase 3 does not modify these but the team-section cards on the landing page borrow their card pattern.

### Existing code
- `config/routes.php` - Phase 2 populated student routes. Phase 3 ADDS: `GET/POST /listings/create`, `GET/POST /listings/{id}/edit`, `POST /listings/{id}/delete`, `POST /listings/{id}/relist`, `POST /listings/{id}/submit` (draft->pending), `POST /admin/cron/ticket-expiry`. The existing `GET /board` and `GET /my-listings` routes get their Phase 2 stub Actions replaced with real ones.
- `config/contexts.php` - Already lists `Listing` and `Category` bounded contexts.
- `config/bootstrap.php` - Loads autoload, sets timezone, runs auth/CSRF/response-headers boot. Phase 3 extends the route-load step (no change here).
- `config/db.php`, `config/security_headers.php` - Phase 2 ships. Phase 3 ADDS `config/uploads.php` (storage root: dev=`public/uploads/listings/`, prod=`/var/www/uploads/listings/`).
- `src/Support/{Router,Auth,Csrf,RateLimit,Crypto,ResponseHeaders,Db,Error,View}.php` - Phase 2 ships. Phase 3 ADDS `src/Support/ImageUpload.php` (validation pipeline + thumbnail generation) and `src/Support/ImageProxy.php` (auth-checked serving).
- `src/Auth/Action/*`, `src/User/Action/*`, `src/Auth/Service/auth_service.php`, `src/User/Service/user_service.php`, `src/Auth/Model/user_model.php` - Phase 2 ships. Phase 3 imports `auth_service::sanitizeUser` for the seller-info row in the listing modal.
- `src/Listing/Action/{BrowseAction,MyListingsAction}.php`, `src/Listing/View/{browse,my_listings,placeholder}.php` - Phase 2 stub Actions. Phase 3 REPLACES `BrowseAction` and `MyListingsAction` with the real implementations. ADDS `src/Listing/Action/{CreateListingAction,EditListingAction,DeleteListingAction,RelistListingAction,SubmitDraftAction,ListingAutoApproveAction}.php` and the matching Views under `src/Listing/View/`.
- `src/Support/View/layout.php` - Phase 2 ships. Phase 3 uses it as-is for the board, listing modal, seller dashboard, and landing page wrappers.
- `public/assets/css/tickettrade.css` + `{tokens,bootstrap-overrides,components}.css` - Phase 1 token system. Phase 3 uses these as-is. The corkboard paper-card surface (`#FFF8E7`) and rotation transform are inherited.
- `public/assets/js/tickettrade.js` - Phase 1 component bundle. Phase 3 reuses `toast.show()`, `listViewToggle`, `skeleton`, `prefersReducedMotion`. No new JS components in Phase 3 (the modal/swipe/carousel are inline in their respective Views per D-23).
- `public/mockups/*.html` - Visual references; Phase 3 production surfaces render the same markup as `board-mobile.html`.
- `migrations/001_initial.sql` through `007_cache_rate.sql` - Phase 2 ships. Phase 3 ADDS `008_listings.sql`, `009_listing_images.sql`, `010_listing_revisions.sql`, `011_categories.sql`.

### Cross-phase lock-ins
- `.planning/REQUIREMENTS.md` PER-02/PER-03 - `< 2s` page loads, <=50 listings/page, thumbnails generated at upload (200/600/1200 px WebP 80% quality). Phase 3 implements these.
- `.planning/REQUIREMENTS.md` SEC-04 - uploaded files re-encoded to WebP behind validation (D-11 of this CONTEXT).
- `.planning/REQUIREMENTS.md` SEC-03 - rate limits wired at the route map layer via `Support\RateLimit` (listing_create 20/hr/user per NFR-SEC-007).
- `.planning/REQUIREMENTS.md` LND-01..08 - Landing (hero "Every Trade Ends With Proof", how-it-works, team cards, footer, guest browse). Phase 3 implements all eight.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **Design token system** (`public/assets/css/tickettrade.{tokens,bootstrap-overrides,components}.css`) - every token Phase 3 needs is already defined: cork-base, cork-grain, pin-red, pin-blue, paper-card surface (`#FFF8E7`), listing-card-cork component class, the rank-badge token set (used on the modal's seller info row), status-pending-fill / status-active-fill / status-rejected-fill pills, the on-surface-variant color for "Under review" subtitles.
- **Toast container** (`data-component="toast"` + `window.TicketTrade.toast.show(...)`) - Phase 3 emits toasts for: image-upload errors, listing save success, "Listing created - pending admin approval", "Relisted as draft - edit and submit when ready", "Draft saved", admin auto-approve success.
- **Bottom nav** (`data-component="bottom-nav"`) - Phase 2 ships; the `/my-listings` link now points at the real seller dashboard instead of a stub.
- **Skeleton shimmer** (`data-skeleton`) - Cold-load states on the board, seller dashboard, listing modal (image carousel + content).
- **Theme controller** (`data-component="theme-controller"`) - Phase 3 uses as-is.
- **List view toggle** (`data-component="listViewToggle"`) - Phase 1 ships; Phase 3 wires it into the board page header so `aria-pressed` persists per session. `prefers-reduced-motion` disables the corkboard hover lift automatically.
- **Bootstrap 5 CDN** - Phase 3 uses Bootstrap form controls (input, form-label, invalid-feedback, btn, btn-primary, btn-outline-danger, btn-outline-secondary), modal (`modal-fullscreen-sm-down`, `modal-dialog-centered`), carousel (`interval: false`, indicators, prev/next), tabs (`nav nav-tabs`), pagination (`pagination`), badge (`badge bg-*`), form-select for category and condition/type pickers, input-group for the price field with "LKR" prefix, custom-file-input for the image picker.
- **`Support\Auth`, `Support\Csrf`, `Support\RateLimit`, `Support\Crypto`, `Support\Db`, `Support\Error`, `Support\View`, `Support\Router`, `Support\ResponseHeaders`** - All Phase 2 ships. Phase 3 imports them from Action files; no new Support class is created except `ImageUpload` and `ImageProxy`.
- **`auth_service::sanitizeUser($user)`** - Phase 2 ships; Phase 3 uses it to project the seller info row on the listing modal.
- **`User/Model/user_model.php::findById($user_id)`** - Phase 2 ships; Phase 3 uses it for the seller info lookup.
- **`Support\RateLimit` named limits** - Phase 2 ships with `login` and `register`. Phase 3 adds `listing_create` (20/hr/user) and `admin_cron` (5/min/IP).

### Established Patterns
- **Layered Modular Monolith** (AD-1) - Bootstrap -> FrontController -> Action -> Service -> Model -> PDO. Phase 3's `Listing/Action/*_action.php` files are thin: validate input (CSRF + rate limit + format), call `Listing/Service/listing_service.php`, render View. All state mutation goes through `Listing/Service/listing_service.php` (the listing-context sole writer) and `Category/Service/category_service.php`. The image-pipeline state lives in `Support\ImageUpload` (called from the Service) and writes to disk; the listing-row writes go to the Service.
- **Failure envelope** (AD-16) - Every Action returns `['ok' => bool, 'data' => mixed, 'error' => ['code' => string, 'message' => string, 'fields' => array|null]]`. The View renders `error.message` only via `htmlspecialchars()`. UI switches on stable error codes (`E_VALIDATION`, `E_LISTING_NOT_FOUND`, `E_LISTING_FORBIDDEN`, `E_RATE_LIMIT`, `E_CSRF`).
- **Tokens-as-contracts** (Phase 1) - Every color/spacing/typography/elevation token in `tickettrade.tokens.css` traces to a row in `DESIGN.md`'s contrast ledger. Phase 3 inherits this; no new token additions (the corkboard tokens already ship).
- **Self-registering JS components** (Phase 1) - `data-component="..."` attributes register behavior. Phase 3 reuses `toast`, `bottomNav`, `skeleton`, `listViewToggle`, `prefersReducedMotion`. The new modal keyboard handler, carousel swipe, and drag-to-reorder are inline `<script>` blocks in the corresponding Views (~50 LOC total) - not new components. The WAD scope does not justify extracting them.
- **Server-set flash messages** (Phase 2) - `data-flash-toast="..."` div carries server-set messages on the next page load. Phase 3 uses this for listing-save confirmations and admin auto-approve results.
- **Migrations runner** (Phase 2 D-22..D-28) - Each `.sql` migration runs in a single transaction, `IF NOT EXISTS`/`IF EXISTS` discipline, `.applied` file tracks progress. Phase 3 adds four migration files following the same pattern.

### Integration Points
- **`config/routes.php`** - Phase 3 ADDS the routes listed in the canonical-refs section above. The existing `GET /board` and `GET /my-listings` route entries stay (their Action class names do not change).
- **`config/bootstrap.php`** - Phase 3 requires the new `Support\ImageUpload` and `Support\ImageProxy` classes. The bootstrap does not change structurally (PSR-4 autoload picks them up).
- **`config/uploads.php` (NEW)** - Returns `['storage_root' => __DIR__ . '/../public/uploads/listings']` in dev and `['storage_root' => '/var/www/uploads/listings']` in prod. `Support\ImageUpload` reads this once at boot.
- **`public/index.php`, `public/admin/index.php`, `public/router.php`** - Phase 3 does not modify these front controllers; the existing dev-server router already handles `/uploads/*` as 404 (the path is never served because Phase 3 routes every read through `/img/{listing_id}/{size}` and `Support\ImageProxy` blocks direct filesystem reads via `.htaccess` deny + PHP-side guard).
- **`public/uploads/.htaccess` (NEW)** - `Deny from all` to neutralize accidental direct reads even though the proxy is the canonical path.
- **Layout template** - Phase 2 ships `src/Support/View/layout.php`. Phase 3 uses it as-is for every page wrapper; the listing modal and the listing edit form are full-page Views rendered inside the layout (the modal is rendered via a View partial that the board page's PHP includes inline).

</code_context>

<specifics>
## Specific Ideas

- The corkboard "paper card" with +/-2 deg deterministic rotation is the visual signature of Phase 3's board view. The rotation seed is `crc32($listing_id) % 5 - 2` (yielding -2..+2 degrees). The pin graphic alternates red/blue by `($listing_id % 2)`. Both are `aria-hidden` per EXPERIENCE.md Component Patterns.
- The seven categories ship with these descriptions (used as the `<option>` text in the form and as the tooltip on the tab):
  1. Textbooks - "Course books, reference material, notes"
  2. Electronics - "Phones, laptops, accessories, gadgets"
  3. Fashion - "Clothing, shoes, accessories"
  4. Services - "Tutoring, design, freelance help"
  5. Food - "Homemade, snacks, baked goods"
  6. Events - "Tickets, group buys, event services"
  7. Other - "Anything else campus-trade"
- The price field shows "LKR" prefix in the input group; the server stores `price_cents` as `INTEGER` (LKR cents). The form sends the user-typed rupee amount and the Service multiplies by 100 before INSERT (or accepts a hidden `price_cents` from a client-side calculation if the JS team prefers; recommended path is server-side multiplication for the WAD audience because it is harder to get wrong).
- The image preview grid in the create/edit form is a 4-column responsive grid (`<576: 2 cols, >=576: 3 cols, >=768: 4 cols`) with each preview tile ~120px square. The primary thumbnail (first image) gets a small "Primary" pill in the corner. Reordering via HTML5 drag-and-drop (Phase 1 has no drag-and-drop component; Phase 3 inlines ~30 LOC per D-10).
- The seller dashboard's per-listing row layout on desktop is a 3-column row: `[thumbnail 64px] [title + price + status pill + meta] [actions]`. On mobile it stacks vertically. The actions column is right-aligned on desktop, full-width below on mobile.
- The "Under review" pill on a flagged edit (`review_flag = TRUE`) renders as a small `bg-warning text-dark` Bootstrap badge with `aria-label="Edits pending admin review"`; clicking it does nothing (it is a status indicator, not an action).
- The admin `POST /admin/cron/ticket-expiry` Action's listing-auto-approve branch logs an `audit_log` row with `action='listing.auto_approve'` and `metadata={count: N}`. The full `Support\Audit` hash-chained writer is wired in Phase 4 when it ships - Phase 3 logs to a plain `cron_log` table (or a `.cron_log` plain-text file under `var/`) and Phase 4 migrates the rows into the hash-chained log. - **Reversibility:** costly - log format choice has audit implications.
- The Rejected listing edit flow: clicking Edit on a rejected listing moves status from `rejected` to `draft` and opens the edit form pre-populated with the original fields plus the rejection reason shown in a `surface-error-container` banner. After fixing, seller clicks Submit to move draft -> pending again.
- The My Listings page has TWO empty states: one for sellers with zero listings ("Create your first listing"), one for sellers who have only drafts ("Submit a draft to make it live"). Both use the named copy pattern from EXPERIENCE.md.
- The Phase 3 plan's WAD-friendly demo path: register -> verify (auto via D-02 of Phase 2) -> list a textbook -> admin approves via the cron Action -> log in as a buyer -> browse the corkboard -> click the listing -> see the modal -> note the "Available after Phase 4 launch" disabled Buy Now. This entire flow is screenshot-able in under 90 seconds for the WAD video demo.

</specifics>

<deferred>
## Deferred Ideas

- **Real-time listing updates** (FR-LND-008 enrichment) - Out of Phase 3 scope. Polling or WebSockets for "new listings while you browse" is a v2 enhancement.
- **Saved searches / favorites** - New capability; would be its own phase.
- **Listing comments / Q&A** - New capability; PRD scope creep.
- **Listing share to social** (other than WhatsApp) - Out of scope; Phase 4 wires WhatsApp on the ticket code block.
- **Map view** (showing listings near the seller) - Out of scope; would need geocoding infra.
- **Bundle deals / multi-listing purchases** - Out of scope; PRD caps purchases to one listing per ticket.
- **AI auto-tagging** / category suggestion - Out of scope; admin re-tags in Phase 8.
- **CSV export of listings** (admin) - Phase 8 admin surface.
- **Listing analytics for sellers** (views, saves, conversion) - Out of scope; would be its own phase.
- **Async / chunked image upload** (Uppy.js per ARCHITECTURE-SPINE conventions table) - Deferred per D-08; the conventions table notes the client-side `chunkSize: 2 MiB` override for `@uppy/tus` is preserved as a comment in `Support\ImageUpload` so any later async-upload refactor does not reintroduce the gotcha.
- **Stripe / real payments** - PRD scope: simulated only.
- **i18n** - PRD scope: English only for v1.
- **The "Buy Now" wiring** - Phase 3 ships a disabled CTA. Phase 4 wires it for real (with the purchase confirmation modal, ticket generation, points stub call). The disabled state is the explicit handoff signal.
- **Cohort isolation (AD-20)** - The MVP is single-cohort. At S2 retro, the team decides whether to add `cohort_id` in migration `013` with belt-and-braces across every Model. This gate is documented in `PROJECT.md` Blockers and applies to Phase 3's listings writes just as it would to any other table.
- **Full hash-chained audit log** - Phase 3 logs cron runs to a plain log (or `.cron_log` file). Phase 4 migrates the rows into the hash-chained `audit_log` (AD-12) when it ships. This avoids Phase 3 depending on Phase 4's audit infrastructure.

### Reviewed Todos (not folded)

No todos were folded from `cross_reference_todos` (the step returned `todo_count: 0`).

</deferred>

---

*Phase: 3-Marketplace Listings & Discovery*
*Context gathered: 2026-09-01*
