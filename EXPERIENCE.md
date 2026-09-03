---
name: TicketTrade
status: final
updated: 2026-08-27
sources:
  - "{planning_artifacts}/prds/prd-nsbm-marketplace-2026-08-22/prd.md"
  - "{planning_artifacts}/prds/prd-nsbm-marketplace-2026-08-22/addendum.md"
  - "{planning_artifacts}/prds/prd-nsbm-marketplace-2026-08-22/rank-buy-sell-spec.md"
  - "{repo_root}/WAD_Batch26.md"
---

# TicketTrade — Experience Spine

> Campus-only peer-to-peer marketplace for NSBM Green University students. Verified student identity, 6-tier rank ladder, simulated ticket-based trust. Paired with `DESIGN.md` (visual identity reference). Mobile-first responsive web, Bootstrap 5. Dark mode default for student surfaces; light mode for admin.

## Foundation

**Form factor:** Responsive web, mobile-first. Breakpoints: 576px, 768px, 992px, 1200px (Bootstrap 5 grid). Desktop is a first-class surface for the admin panel; student surfaces optimize for mobile.

**UI System:** Bootstrap 5 via CDN (dev) / bundled (prod). `DESIGN.md` specifies brand-layer deltas only. Stock Bootstrap components (accordion, dropdown, pagination, table, form controls, modal) inherit Bootstrap defaults. Custom components (listing card, rank badge, ticket-code block, status badge, leaderboard row, point delta, toast, bottom nav, verified badge, velocity flag, on-break pill, star rating, dispute modal, purchase confirmation, admin re-auth dialog) are fully specified in `DESIGN.md.Components` with behavioral rules in this file's Component Patterns section.

**Mode Defaults:** Student surfaces → dark mode default. Admin surfaces → light mode default. User preference persists in localStorage; `prefers-color-scheme` respected on first visit only.

**Cross-references:** `DESIGN.md` tokens referenced via `{path.to.token}` syntax. Examples: `{colors.primary}`, `{colors.secondary}`, `{colors.status-pending-fill}`, `{typography.body-md.fontSize}`, `{rounded.md}`, `{components.listing-card}`, `{components.ticket-code-block}`. The path follows the YAML structure; for nested objects (typography, components) the leaf property must be named explicitly (e.g., `fontFamily`, `fontSize`, `background`).

**Inheritance rule:** `DESIGN.md` and this file win on conflict with any mock, wireframe, or import. Visual references land in `imports/`, `mockups/`, and `wireframes/`; the spines own the load-bearing decisions.

---

## Information Architecture

### Student Surfaces

| Surface | Route | Reached From | Purpose | FRs |
|---------|-------|--------------|---------|-----|
| Landing | `/` | Direct, unauthenticated | Public hero, value prop, "Get Started" → register, "Browse as Guest" → read-only board preview | FR-LND-001..008 |
| Login | `/login` | Landing, expired session, route-guard redirect | Email + password, "Register" link, "Forgot password" (simulated) | FR-AUTH-003 |
| Register | `/register` | Login, Landing | `@students.nsbm.ac.lk` email + student ID + nickname, simulated verification against allowlist | FR-AUTH-001, FR-PRO-001 |
| Board (corkboard or list) | `/board` | Bottom nav "Board", Landing | Browse listings: corkboard board view (FR-LND-008) with list-view toggle and <768px auto-fallback to plain grid. Category tabs, search, filter, quantity available. Guest mode: read-only, Buy Now → login redirect. → see `mockups/board-mobile.html` (illustration: corkboard + plain-grid default, 4 listings, 2-column grid) | FR-LST-003, FR-LND-007, FR-LND-008 |
| Listing Modal | `/board#listing-{id}` | Board card click | Full-screen on mobile, centered on desktop. Image carousel, seller info + rank + verified + rating, description, price, condition/duration, "Buy Now" → confirmation modal, Report action. → see `imports/listing-modal-prior.html` (illustration: full-screen modal with carousel, seller row, CTA stack) | FR-LST-005, FR-LST-006, FR-RPT-001 |
| Purchase Confirmation Modal | (on Listing Modal) | "Buy Now" click | "Confirm purchase? This reserves the item with a digital ticket (a reservation, not payment)." Scrim click suppressed 2s. Cancel/Confirm | FR-BUY-001, FR-TKT-001 |
| My Listings | `/my-listings` | Bottom nav "My Listings" | Tabs: Active / Pending / Sold / Draft. Bulk actions: delete, relist. Create Listing FAB. Velocity flag visible to seller on their own listings if earnings today cross the threshold | FR-LST-016, FR-LST-014..017, FR-ADM-009 |
| Create/Edit Listing | `/listing/create`, `/listing/edit/{id}` | My Listings FAB, edit action | Multi-step: Basics → Details → Images → Review. Draft save. Product vs Service type. Edit on active sets `review_flag` for admin re-check (FR-LST-010) | FR-LST-001, FR-LST-010, FR-LST-014, FR-LST-014a, FR-LST-017 |
| My Tickets | `/my-tickets` | Bottom nav "My Tickets" | Tabs: Active / Redeemed / Expired / Disputed. Ticket cards: code (mask/reveal/copy/Share), status badge, listing title, date, #N/M session progress for services. Dispute button on active and redeemed tickets only (FR-TKT-007/008). → see `mockups/my-tickets.html` (illustration: two ticket cards, code-block with mask/reveal + copy, WhatsApp CTA, per-session progress for the service ticket) | FR-TKT-002, FR-TKT-007 |
| Purchase History | `/purchases` | My Tickets → "History" | All tickets chronologically: code, status, listing, price, seller, date. "Leave review" on redeemed tickets within 14 days | FR-BUY-002, FR-BUY-003 |
| Sales | `/sales` | Bottom nav "Sales" | Tickets for seller's listings grouped by listing with quantity context `#N/Q`. Redeem flow: enter buyer code → validate → award points. Per-session confirm for services | FR-TKT-003, FR-TKT-004, FR-TKT-014 |
| Profile | `/profile` | Bottom nav "Profile" | Editable: name, bio, avatar (12 presets), WhatsApp. Tabs: My Listings / My Tickets / Purchase History / Sales History / Reviews. Shows: rank badge (with On-Break pill if inactive 14+ days), stars + rating breakdown + review count, points, join date, transaction counts, dispute count ("N disputes on record"). Verified Student badge. Velocity flag if applicable | FR-PRO-001..004, FR-PTS-008, FR-RAT-001..005, FR-ADM-009 |
| Leaderboards | `/leaderboards` | Board header link, Profile rank badge click | Four boards: Campus Legends Wall (top 20, tier S only), Weekly Risers (top 10, min +50/wk), Category Leaders (top 3/category), Streak Kings (top 10). Daily cron refresh. Rows show nickname-or-first-name + program/year | FR-PTS-009 |
| Dispute Flow | (modal from My Tickets / Sales) | "Dispute" button on active or redeemed ticket | Reason dropdown + text + evidence image → submits report, ticket `dispute_status=pending`. 3-day auto-dismiss by hourly cron | FR-TKT-008, FR-TKT-009 |
| Review Compose | (modal from Purchase History / Profile Reviews tab) | "Leave review" on redeemed ticket within 14 days | Star rating input (1-5) + optional text (50+ chars for detailed-review points). Min 50 chars required for points | FR-RAT-001, FR-BUY-003 |
| Report Modal | (modal from Listing Modal, Profile, Ticket card) | "Report" action | Reason dropdown (scam, inappropriate, spam, wrong_category, other). Required text field (200-char max). On submit: report created, toast confirms | FR-RPT-001, FR-RPT-002 |
| Settings | `/settings` | Profile menu | Theme toggle, notification preferences (toast only), logout | FR-AUTH-004 |

### Admin Surfaces

| Surface | Route | Reached From | Purpose | FRs |
|---------|-------|--------------|---------|-----|
| Admin Dashboard | `/admin` | `/admin` (role guard) | Analytics cards: users, active listings, tickets redeemed this week, total points awarded. Quick links to queues. → see `mockups/admin-dashboard.html` (illustration: KPI grid, queue quick-links, velocity-flag table, audit log panel, bulk action bar) | FR-ADM-005 |
| Users | `/admin/users` | Sidebar "Users" | List, search, filter (role/status), promote/demote, ban/unban, suspend, CSV export, bulk actions. Suspicious activity badge (>300 pts/day or >150 pts/hour or >3 tickets/day with same partner). Re-auth dialog before ban/promote/bulk | FR-ADM-001, FR-ADM-008, FR-ADM-009, NFR-SEC-010 |
| Listings Queue | `/admin/listings` | Sidebar "Listings" | Pending approval (FIFO, auto-approve 24h enforced by hourly cron), flagged edits (`review_flag`). Bulk approve/reject/remove. Rejection requires reason. Relist fast-tracks surface in a separate tab | FR-ADM-002, FR-LST-007, FR-LST-010, FR-LST-015 |
| Categories | `/admin/categories` | Sidebar "Categories" | CRUD with `sort_order` drag | FR-ADM-003 |
| Reports | `/admin/reports` | Sidebar "Reports" | Queue with preview, reporter info, evidence detail view. Actions: Dismiss, Remove Listing, Warn User, Ban User, Force Expire Ticket, Force Redeem Ticket. Bulk dismiss. Re-auth before destructive actions | FR-ADM-004, FR-RPT-001..003, FR-ADM-008 |
| Analytics | `/admin/analytics` | Sidebar "Analytics" | Sales volume, top sellers, category breakdown, dispute rate. Daily/weekly. Generated by `jobs/daily_cron.php` at 02:00 Asia/Colombo | FR-ADM-007 |
| Audit Log | `/admin/audit` | Sidebar "Audit Log" | Immutable hash chain. Search/filter by date, actor, action, target. Re-auth to view full chain | FR-ADM-006 |
| Manual Cron Trigger | `POST /admin/cron/ticket-expiry` | Sidebar (admin only) | Manual trigger for the hourly cron job. Re-auth required. Logs to `cron_log` | NFR-OPS-003 |

### Navigation Model

**Mobile (<768px).** Fixed bottom nav (5 items: Board, My Listings, My Tickets, Sales, Profile). Modal stack max 1 level. Sheet drawers for filters/side menus. Listing modal is full-screen on tap.

**Desktop (≥768px).** Top bar with logo, search, and user menu. Sidebar (admin) is collapsible. Bottom nav hidden. Modals centered with `max-width: 600px`.

**Modal stack.** One level maximum. Listing modal opens from board. Purchase confirmation opens on top of listing modal (listing modal stays mounted but inert). Dispute modal opens from My Tickets / Sales. No nested modals beyond this. The admin re-auth dialog is the deepest stack on admin surfaces.

**Focus management.** All modals trap focus. ESC closes. Focus returns to the trigger element on close. Focus-visible outlines on all interactive elements (`{colors.primary}` 2px, offset 2px). Skip link as the first focusable element to `#main`.

→ Composition references:

- `imports/board-mobile-prior.html` — Board surface, mobile-first dark, prior-run final (kept as reference; design tokens in this run differ — see DESIGN.md for the current spec)
- `imports/listing-modal-prior.html` — Listing Modal surface, full-screen mobile, prior-run final
- `imports/admin-dashboard-prior.html` — Admin Dashboard, light mode, prior-run final
- `mockups/board-mobile.html` — Board surface, corkboard + list-view toggle, dark mode, 4 listings, 2-column grid (new for this run, 2026-08-27, promoted from `.working/`)
- `mockups/my-tickets.html` — My Tickets surface, 2 cards (active service with progress, active product with masked code), WhatsApp CTA, dispute action (new for this run, 2026-08-27, promoted from `.working/`)
- `mockups/admin-dashboard.html` — Admin Dashboard, light mode, KPI grid, queue quick-links, velocity-flag table, audit log panel, bulk action bar (new for this run, 2026-08-27, promoted from `.working/`)

**Spines win on conflict.** The new key-screens in `mockups/` are the canonical visual references for this run. The prior-run imports illustrate the same product and are kept as reference only; their decisions are inherited only where the new spine is silent.

---

## Voice and Tone

Microcopy principles. Brand voice and aesthetic posture live in `DESIGN.md.Brand & Style`.

| Do | Don't |
|----|-------|
| "List your item" | "Start your entrepreneurial journey" |
| "Confirm purchase? This reserves the item with a digital ticket." | "Buy now and unlock your potential!" |
| "Image must be under 5MB" | "Upload failed" |
| "Ticket created. Code: TK-7QXK2M9WBV4N8PRTYC3AD" | "🎉 Your purchase is complete!" |
| "Seller hasn't shared WhatsApp. Copy the code instead." | "WhatsApp sharing unavailable" |
| "Dispute submitted. Admin will review within 48 hours." | "Your dispute has been received and is being processed." |
| "No listings yet. Create your first one." | "Looks like it's empty here! 😢" |
| "Verified Student" | "Verified ✅" |
| "Recruit (E) — 0 points" | "🌱 Just getting started!" |
| "5 disputes on record" | "Be careful with this seller!" |
| "On Break" | "Inactive user" |

**Rules:**
- Short, complete sentences. Period at end.
- No exclamation marks in functional copy. Landing hero may use one.
- No streak language, no encouragement filler ("Great job!", "You're doing great!").
- No emoji in functional UI. Rank icons are SVG. Status badges are text + color.
- Errors are actionable: state the constraint, not the failure.
- Trust signals use plain language: "Verified Student" not "✅ Verified".
- Ticket codes are always monospace, always copyable, always preceded by `TK-`.
- Rank tier names are always paired with the tier code on first reference: "Recruit (E)".
- Dispute counts on profile are factual: "N disputes on record" — never "N complaints" or "N issues".

---

## Component Patterns

Behavioral specs. Visual specs live in `DESIGN.md.Components`.

### Core structural

| Component | Use | Behavioral rules |
|---|---|---|
| Button — primary | One per view, the dominant action | Loading state replaces label with spinner (button width preserved). Disabled when action is invalid; tooltip explains why on hover/focus |
| Button — danger | Destructive actions only | Always followed by a confirmation step (modal or re-auth) |
| Button — secondary | Cancel, Back, optional | Disabled state shows `surface-container-high` fill, `on-surface-variant` text |
| Button — ghost | Edit, delete, "Clear" in dense lists | Transparent fill, hover → `surface-container` fill. Disabled state shows `surface-container-high` fill, `on-surface-variant` text |
| Input field | All text inputs | `autocomplete` mapping per accessibility floor. Error state shows `error` border + 12px error text below. Numeric inputs use `inputmode="numeric"` or `inputmode="decimal"`. Password uses `autocomplete="current-password"` |
| Card surface | Grouped content | Single tab stop on clickable cards; inner text marked `aria-hidden` to avoid double-reading. Hover affordance on clickable variants only |
| Modal dialog | Confirmations, detail views, forms | Traps focus; ESC closes; scrim click closes (except purchase confirmation, suppressed 2s); focus returns to trigger on close; `aria-modal="true"`; `role="dialog"`; title has `aria-labelledby` |
| Bottom nav | Mobile student surfaces | Active state on current route; badge counts with `aria-label="N new"` for unread items; hidden on ≥768px |
| Toast | Async result feedback | ARIA live region (`role="status"` for success/info, `role="alert"` for error/warning). Auto-dismiss 4s; error and warning toasts include a manual dismiss button. Auto-dismiss pauses on hover/focus. Max 3 queued |

### Brand-specific

| Component | Use | Behavioral rules |
|---|---|---|
| Rank badge | Profile, listing cards, listing modal seller info, leaderboards, ticket cards | Tooltip on hover/focus with full tier name + threshold (e.g., "Operative (C) — 150 to 399 points"). On-Break state swaps to grayed surface when user is inactive 14+ days. Tier S has the only animation: subtle `legend-glow` 2.4s ease-in-out. Click on a leaderboard-row rank badge → no-op (rank is display, not action). The badge never carries a numeric points total — that lives in the meta text next to it |
| Ticket-code block | My Tickets, Sales, Profile ticket history | Always monospace with letter-spacing. Default state: `••••••••••••••••••••••••` masked. Reveal toggle on the right: "Show" / "Hide" (keyboard accessible, announces state). Copy button: "Copy" → "Copied" 1.5s confirmation. WhatsApp button: only enabled if seller has set a WhatsApp number; if not, "Seller has not shared WhatsApp" tooltip + the copy button is the fallback. Code is always preceded by `TK-` and rendered in one unbroken line (no wrap; horizontal scroll inside the block if the container is too narrow) |
| Status badge | Listing rows, ticket cards, admin tables | One of 8 fixed states (pending, active, rejected, redeemed, expired, sold, disputed, removed). Read-only — never user-editable. Always paired with a timestamp tooltip on hover/focus ("Active since 3 days ago") |
| WhatsApp button | Ticket-code block, dispute confirm | Disabled state with explanatory tooltip when the seller has not set a WhatsApp number. Disabled label: "Seller has not shared WhatsApp" with copy-to-clipboard as the only fallback |
| Listing card | Board, My Listings, Profile listings, search results | Single tab stop. Tapping anywhere opens the listing modal. Hover on desktop: translateY(-4px) + 0 4px 12px shadow at 12% (or paper-card lift on corkboard). Drag-to-reorder disabled on board; only enabled inside My Listings edit mode. Image lazy-loads below the fold. The card has no inner focusable elements — the whole card is the link |
| Listing card — corkboard | Board view only (FR-LND-008) | Rotation, pin graphic, and cork texture are aria-hidden. List-view toggle in the page header exposes this state via `aria-pressed` and persists per session. Below the md breakpoint, auto-falls back to the plain grid. `prefers-reduced-motion` disables the hover lift. Touch devices suppress hover-lift dependency — tap opens the listing directly. Card text contrast is measured against the paper-card surface (`#FFF8E7`), not the cork |
| Leaderboard row | Campus Legends, Weekly Risers, Category Leaders, Streak Kings | Rank number in `{colors.secondary}`. Display name in `body-md`; program/year in `body-sm` (`on-surface-variant`). Tier badge right-aligned. Empty state for each board: copy + an icon. Pagination on Weekly Risers (top 10) is fixed — no pagination control; the rows are the only content |
| Point delta | Profile summary, leaderboard row, points-log detail modal | Always prefixed with `+` or `−`, in `{colors.secondary}`. Hover on profile summary opens a popover with the recent 5 points-log entries (delta, reason, balance_after, timestamp) |
| Verified Student badge | Profile, listing card, listing modal | Renders on any surface where a student is identified. Never clickable. The icon is an inline SVG checkmark; the label is "Verified Student" (full label, not the badge alone — for accessibility) |
| Velocity flag badge | Admin Users list (and on the user's own profile if `points_frozen`) | Tooltip: "Earning above legitimate ceiling — review queued". Click on admin row opens the user detail with the velocity log (timestamps and per-event deltas). On the user's own profile, the badge links to a static page explaining the freeze (no admin action) |
| On-Break pill | Profile rank badge, leaderboard row rank badge | Tooltip: "Inactive 14+ days — next action restores full badge". Re-activation: the next action (login, list, buy, review) instantly restores the full color |
| Admin re-auth dialog | Ban, promote, delete, bulk actions | Modal with destructive-action border. Single password field, primary Confirm + secondary Cancel. Failure shows inline error; success closes the dialog and proceeds with the action. Backed by FR-ADM-008 / NFR-SEC-010 |
| Star rating input | Review compose modal | Fieldset of 5 named radio inputs (1–5). Each radio is hidden; the visible label is a 24px star icon. Hover and focus preview the rating; keyboard arrow keys cycle. Screen reader announces "Rating: 3 of 5". A "Clear" link resets to 0 (no rating). Stars are filled in `{colors.secondary}`; empty stars use `{colors.outline-variant}` |
| Dispute modal | My Tickets / Sales "Dispute" button | Reason dropdown (seller_unresponsive, item_not_as_described, buyer_unresponsive, other). Required text field (200-char max with counter). Optional evidence image upload (one image, 5MB max, 4-layer validation per NFR-SEC-004). On submit: ticket `status='disputed'` AND `dispute_status='pending'`, report created. Disabled if ticket is not `active` or `redeemed` |
| Purchase confirmation modal | Listing Modal "Buy Now" | Body: "Confirm purchase? This reserves the item with a digital ticket (a reservation, not payment)." Scrim click suppressed 2 seconds. Cancel + Confirm. On confirm: ticket created with `TK-` + 22-char base62 code, listing `quantity_sold` incremented, redirect to My Tickets with toast "Ticket created. Code: TK-..." |
| Report modal | Listing Modal, Profile, Ticket card | Reason dropdown (scam, inappropriate, spam, wrong_category, other). Required text field (200-char max). On submit: report created with `status='pending'`, toast "Report submitted. Admin will review within 48 hours." |
| Avatar picker | Register, Edit Profile | Grid of 12 predefined illustrations (4 columns × 3 rows on desktop, 3 × 4 on mobile). Tap to select. Selected avatar has a 2px primary-color ring. No upload, no custom images |
| Bulk action bar | Admin Users, Admin Listings Queue, Admin Reports, My Listings | Sticky bar appears when 1+ rows are checked. Shows the count + dropdown of bulk actions (ban, suspend, promote, approve, reject, remove, dismiss, delete, relist). Re-auth required for destructive bulk actions. Cancel resets selection |
| Filter tab | Board, My Listings, Admin queues | Pill-style tabs. Active tab uses `primary-container` fill; inactive is transparent. Tab order follows PRD state machine (Active / Pending / Sold / Draft; Pending / Active / Rejected for admin listings). Keyboard arrow keys cycle. `aria-current="page"` on the active tab |
| Search input | Board, Admin Users, Admin Reports, Admin Audit Log | Leading magnifier icon. Debounced 250ms. Empty state copy when no results. Server-side fulltext on Board, LIKE search on admin. Pressing `/` focuses the board search from any surface |
| List view toggle | Board header | Two-state toggle (cork / list). `aria-pressed` exposes state. Persists per session. Below the md breakpoint the toggle hides and the cork auto-degrades to the plain grid |
| Analytics card | Admin Dashboard | One card per KPI (4 cards). KPI in `display-lg` `{colors.primary}`. Subtitle in `body-sm` `{colors.on-surface-variant}`. Click opens the analytics detail with the chart |
| Report row | Admin Reports | Thumbnail of the target (listing or ticket), target title, reporter nickname + tier, reason, age, status. Row click expands the evidence detail view inline. Bulk-select checkbox on the left |
| Audit log row | Admin Audit Log | Timestamp, actor, action, target, old/new values (collapsed). Hash cell uses `mono-code` font and `code-bg` / `code-text` colors. Filter by date range, actor, action, target. Search box with debounce. Hash chain integrity check on every page load (mismatch shows a banner) |
| Tier progress bar | Profile | Horizontal bar showing points earned within the current tier (e.g., 60 of 149 toward Rookie → Operative). Track is 8px tall with `rounded/full`. Fill uses the current tier color. Tooltip on hover/focus: "X of Y toward {next tier name}" |
| KPI counter | Profile stats line, Admin Dashboard | Single number in `display-lg`. Subtitle in `caption`. No animation; the value updates in place when data refreshes |
| Tier privilege tooltip | Profile (hover/focus on rank badge) | Popover explaining what the current tier unlocks: tier C+ can list up to 5 active listings; tier B+ gets search rank boost; tier A+ gets featured listings; tier S gets Hall of Fame + early access. Progressive disclosure — these are revealed on hover/focus, not on a separate page |

---

## State Patterns

Per surface, the states the product must support. Empty / cold / focus / error / offline / permission-denied apply where relevant.

| Surface | State | Treatment |
|---|---|---|
| **Landing** | First visit (unauthenticated) | Hero, "Get Started" + "Browse as Guest", value prop, team, footer |
|  | Authenticated first visit | Hero + "Go to Board" CTA replacing "Get Started" |
| **Login** | Cold load | Empty form, "Register" link below, "Forgot password" (simulated, links to a static "we'll add this later" page) |
|  | Wrong credentials | Inline error: "Email or password is incorrect." No field-level highlight (anti-enumeration) |
|  | Rate-limited (5/5min) | Inline error: "Too many attempts. Try again in 5 minutes." |
| **Register** | Cold load | Empty form. Email field has `@students.nsbm.ac.lk` placeholder. Student ID + nickname fields visible. Reserved-nickname check on blur |
|  | Email not @students.nsbm.ac.lk | Inline error: "Use your @students.nsbm.ac.lk email" |
|  | Student ID not in allowlist | Inline error: "Student ID not recognized. Contact your admin." |
|  | Nickname taken | Inline error: "Nickname taken. Pick another." |
| **Board** | Cold load (auth) | Skeleton grid: 12 placeholder cards, image area + title + price lines, 16:9 each, 3-col grid on ≥768, 2-col on 576-767, 1-col <576. Skeleton uses `surface-container-high` fill, 1s shimmer |
|  | Cold load (guest) | Same skeleton, but Buy Now on cards is hidden and replaced with "Sign in to buy" link |
|  | Empty first visit (auth) | "No listings yet. Create your first one." CTA to Create Listing (FAB on mobile) |
|  | Empty filtered category | "No listings in {category}." with "Clear filter" link |
|  | Search empty | "No matches for {query}." with "Clear search" link |
|  | Fetch failed | "Couldn't load listings. Tap to retry." with a refresh icon |
| **Listing Modal** | Cold load | Skeleton: image carousel placeholder + title + price + seller row. Loaded within 200ms (content pre-fetched) |
|  | Fetch failed | "Couldn't load this listing." with "Close" button. The modal does not auto-retry |
|  | Out of stock (`quantity_sold == quantity`) | Buy Now button replaced with "Sold out" text. Status badge shows `sold` |
|  | Self-owned listing | Buy Now button hidden. "Edit" + "Delete" actions visible. "You own this listing." note above the seller row |
| **Purchase Confirmation** | Open | Body text, Cancel + Confirm. ESC = Cancel. Scrim click suppressed 2s |
|  | Submitting | Confirm button shows spinner; Cancel disabled |
|  | Success | Modal closes. Toast: "Ticket created. Code: TK-...". Redirect to My Tickets (auto-focus on the new ticket card) |
| **My Tickets** | Cold load | Skeleton: 5 ticket cards, each with code block, status badge, listing title |
|  | Empty first visit | "No tickets yet. Buy your first item." with link to Board |
|  | Active tab empty | "No active tickets." with "Browse Board" link |
|  | Ticket with pending dispute | Card shows disputed badge; "View dispute" link replaces "Dispute" button |
| **Sales** | Cold load | Skeleton: 5 ticket cards grouped by listing |
|  | Empty first visit | "No sales yet. Create your first listing." with link to Create Listing |
|  | Wrong redemption code (1-4 attempts) | Inline error in the redemption input: "Code not recognized." Counter: "4 of 5 attempts remaining" |
|  | Wrong redemption code (5th attempt) | Inline error: "Too many attempts. Try again in 1 hour." Field disabled for 1 hour |
|  | Already-redeemed code | Inline error: "This ticket was already redeemed on {timestamp}." Idempotent: no new state change |
|  | Not-your-ticket (entered buyer code on a ticket where you're not the seller) | Inline error: "Not authorized to redeem this ticket." Security log entry |
| **Profile** | Cold load | Skeleton: avatar, rank badge, name, points, 3 stat lines, 4 tabs |
|  | Inactive 14+ days | Rank badge swapped to On-Break pill. Tooltip explains. "Welcome back" toast on next action |
| **Leaderboards** | Cold load | Skeleton: 10 rows per board |
|  | Campus Legends empty (no tier S users) | "No Campus Legends yet. The first to reach 1500 points claims the wall." |
|  | Weekly Risers empty (no one earned +50 this week) | "No Weekly Risers this week. Check back Monday." |
|  | Category Leaders empty (no sales in this category) | "No leaders in {category} yet." |
| **Admin Dashboard** | Cold load | Skeleton: 4 stat cards + 3 quick-link rows |
|  | No flagged users | "No velocity flags today." in the flag-list panel |
| **Admin Listings Queue** | Cold load | Skeleton: 10 rows, each with thumbnail + title + seller + status + age |
|  | Empty | "Nothing in the queue. Listings auto-approve after 24 hours." |
| **Admin Reports** | Cold load | Skeleton: 10 rows, each with target type + reporter + reason + age |
|  | Empty | "No reports today." |
| **Admin Users** | Cold load | Skeleton: 20 rows, each with avatar + name + email + role + tier + last-active |
|  | Filtered empty | "No users match the filter." |
| **Modal — generic** | Focus | First focusable element gets focus on open. Tab cycles within the modal. Shift-Tab cycles backward. Focus returns to the trigger on close |
| **My Listings** | Cold load | Skeleton: 6 rows, each with thumbnail + title + price + status badge. Skeleton uses `surface-container-high` fill, 1s shimmer |
|  | Empty tab | "No {status} listings." with a "Create Listing" CTA on the Active tab empty state |
|  | Review-flagged active listing | Yellow badge "Pending admin re-check" appended to the card |
|  | Bulk-select active | Sticky bulk action bar slides in with count + actions (delete, relist) |
|  | Rejected listing | Card carries the reject reason as muted meta text; "Edit & Resubmit" CTA visible |
| **Purchase History** | Cold load | Skeleton: 6 rows, each with code + status + listing + price + date |
|  | Empty | "No purchases yet. Browse the board." with a Board link |
|  | Leave review eligible | "Leave review" button on rows within 14 days of `redeemed` status |
|  | Leave review window closed | "Leave review" hidden after 14 days |
| **Dispute Flow** | Pre-flight (eligible) | "Dispute" button enabled only on `active` or `redeemed` tickets (FR-TKT-007/008) |
|  | Submitting | Submit button shows spinner; Cancel disabled |
|  | Filed | Ticket → `disputed`; dispute_status → `pending`. Toast: "Dispute submitted. Admin will review within 48 hours." |
|  | Resolved by admin | Ticket state restored per outcome. Toast: "Dispute resolved: {outcome}." |
| **Review Compose** | Cold load | Empty form, star rating at 0, text area empty with placeholder "Tell other students about your experience..." |
|  | Star preview (hover/focus) | Stars fill to the hovered index; "Clear" link visible if a rating is selected |
|  | Comment under 50 chars | Char counter "N of 50+" below. Points warning: "Detailed reviews (50+ chars) earn +10 points" |
|  | Submitting | Submit disabled, spinner shown |
| **Report Modal** | Cold load | Empty form, reason dropdown defaulting to "other" |
|  | Submitting | Submit disabled, spinner shown |
|  | Success | Modal closes. Toast: "Report submitted. Admin will review within 48 hours." |
| **Settings** | Cold load | Theme toggle (Light / Dark / System), notification toggles, logout link. Defaults: System theme, all notifications on (toast only) |
|  | Theme switched | Page transitions to the new mode; choice persists in localStorage. Toast: "Theme set to {mode}." |
| **Admin Categories** | Cold load | Drag-handle list of categories with sort order, edit, delete actions |
|  | Empty | "No categories yet. Create your first one." |
|  | Drag reorder | Live aria-live updates; persist on drop |
| **Admin Analytics** | Cold load | Skeleton: 4 KPI cards + 1 chart placeholder |
|  | Date range picker | Filters all analytics to the selected range; defaults to last 7 days |
| **Admin Manual Cron Trigger** | Cold load | Button "Run Ticket Expiry Now" with last-run timestamp |
|  | Running | Button shows spinner; result streams into a log panel below |
|  | Success | "Cron run complete. {N} tickets expired." with the affected ticket codes listed |
|  | Error | "Cron run failed. Check cron_log." with the error message |
|  | Submit error | Inline error in the modal. Modal stays open. Focus on the error field |
|  | Network error during submit | Toast: "Couldn't reach the server. Try again." Modal stays open. The submit button is re-enabled |
| **Toast** | Queued | Max 3 visible; queue overflow drops the oldest. ARIA live region announces each new toast |
|  | Hover/focus | Auto-dismiss paused. Resume 4s after blur |
|  | Error/warning | Manual dismiss button required. Auto-dismiss 8s instead of 4s |
| **Offline (any surface)** | Network failure | All network calls fail with a toast. Reads from cache where possible (board, profile) |
| **Route guard** | Unauthenticated access to protected | Redirect to `/login?next={path}`. After login, return to `next` |
|  | Non-admin access to `/admin/*` | Redirect to `/` with error toast: "Admin access required." |
| **Admin Audit Log** | Cold load | Skeleton: 20 rows, each with timestamp + actor + action + target + hash |
|  | Filtered empty | "No entries match the filter." |
|  | Hash chain mismatch | Red banner across the top of the page: "Audit log integrity check failed. Contact system admin." Re-auth to view full chain |
| **Bulk action bar** | 0 rows selected | Hidden |
|  | 1+ rows selected | Sticky bar slides in with count + dropdown. Re-auth required for destructive actions |
|  | Destructive action confirmed (re-auth passed) | Optimistic update: rows fade to disabled state, toast "Action applied to N rows". On failure: rows re-enable, toast "Action failed. Try again." |
| **Edit Listing** | Cold load | Form pre-filled with current values. Image thumbnails shown with drag-to-reorder. Save Draft + Submit buttons |
|  | Image upload error | Inline error under the image: "Image must be under 5MB" / "Format not supported" |
|  | Edit on active listing | Toast: "Edits saved. Listing stays live during admin re-check." + `review_flag` set |

---

## Interaction Primitives

- **Tap to act.** Long-press reserved for system text selection.
- **Swipe-down to dismiss** modals on mobile (native pattern, confirm sheet for destructive only).
- **Pull-to-refresh** on Board only. Skeleton during refresh.
- **Keyboard navigation.** All modals trap focus. ESC closes. `/` focuses the board search. `Enter` submits forms. Arrow keys cycle star ratings. The list-view toggle and most switches are native Bootstrap controls.
- **Drag-to-reorder** inside My Listings edit mode (images and listing order).
- **Auto-save on draft listings** every 30 seconds while editing. Toast on save: "Draft saved."

**Banned interactions (anti-patterns locked into the spine):**
- Carousels on landing.
- Hero animations on page open.
- Badge counts on the bottom nav (unread counts are buried into the surfaces themselves, not surfaced as a re-engagement lever).
- Streak counters or daily-login-bonus displays.
- Push notifications or re-engagement nags.
- Infinite scroll — pagination is 50 listings per page.
- Nested modals (one level maximum; purchase confirmation on listing modal is the deepest stack).
- Algorithmic reputation scores in user-facing chrome (transparent 6-tier ladder + star ratings only).
- Real payment simulation language — every purchase confirmation says "a reservation, not payment."

---

## Accessibility Floor

Behavioral. Visual contrast lives in `DESIGN.md` (Contrast Ledger).

- **WCAG 2.1 AA throughout.** Color contrast targets met per the Contrast Ledger in `DESIGN.md`. Text ≥4.5:1, UI elements ≥3:1, large text ≥3:1.
- **Keyboard navigable.** All modals trap focus; ESC closes; focus returns to the trigger on close; focus-visible outlines on all interactive elements (`{colors.primary}` 2px, offset 2px); the skip link is the first focusable element on every page and jumps to `#main`.
- **Screen reader support.** `aria-live` regions on toast, form errors, and async status. `aria-modal="true"` + `role="dialog"` on modals. `aria-labelledby` and `aria-describedby` on every modal. Decorative elements (cork texture, pin graphic, rotation transform) are `aria-hidden`. Status badges carry `aria-label` with the human-readable state.
- **Form `autocomplete` mapping:** email, current-password, new-password, one-time-code, name, given-name, family-name, tel, url, off (for sensitive state like redemption code).
- **Reflow at 320px.** All content reflows to 320px width without horizontal scroll, except data tables (which scroll inside their container with a sticky first column).
- **Reduce motion.** `prefers-reduced-motion: reduce` disables the corkboard hover lift, the rank-S glow, the modal slide-up, the toast slide-in, the save spinner, and the auto-save pulse. State transitions remain (e.g., disabled → enabled) but with no transform.
- **`aria-current="page"` on the active bottom-nav item.** `aria-current="step"` on the active step in the Create Listing wizard.
- **Toast dismiss button required on error and warning toasts** (auto-dismiss alone is not enough). Auto-dismiss pauses on hover/focus. ARIA `role="alert"` for error/warning, `role="status"` for success/info.
- **Input purpose (`autocomplete`)** covers all form fields where it is meaningful. Numeric inputs use `inputmode`. The redemption code input is `autocomplete="off"` (security).
- **Tap targets ≥ 44pt (iOS) / 48dp (Android).** Bottom nav items are 64px tall. Star rating input has 48dp touch targets. Status badges are not interactive.
- **On-Break pill tooltip and focus parity.** Hover and focus both show the tooltip. The rank badge carries a `title` attribute and `aria-describedby` so screen readers announce the same information.
- **Ticket code is always monospace and always copyable.** The reveal/mask toggle and copy button are keyboard-accessible. The WhatsApp share button is keyboard-accessible.
- **Corkboard a11y.** Rotation, pin graphics, and cork texture are aria-hidden. The list-view toggle exposes its state via `aria-pressed` and persists per session. Below the md breakpoint, the corkboard auto-degrades to the plain grid.

---

## Inspiration & Anti-patterns

**Lifted from BookBridge (competitive research).** Simulated ticket + 7-day expiry as the trust layer; seller-confirms-handover = both parties get points; symmetric disputes (buyer or seller can file); the dual-field ticket status model (`status` + `dispute_status`) so admin actions restore the underlying state cleanly.

**Lifted from Hamari (gamified rankings research).** 6-tier anime-style ladder (E→S) with tier names that read as a story (Recruit → Rookie → Operative → Specialist → Elite → Legend). The rank is one trust signal among several — verified, rating, dispute count — not the headline.

**Rejected — Streaks (Duolingo, most habit apps).** Streaks weaponize the user's calendar. The PRD-stored 7-day and 30-day streak bonuses exist as anti-farming mechanics in the data model, not as user-facing surfaces. The product shows "On Break" instead — neutral, reversible, never punitive. **This overrides PRD FR-PTS-001's "7-day login streak +15" row for display purposes only; the data model still records the streak — the override is the visibility policy, not the points rule. Cut order is pre-agreed in PRD §9: leaderboards → bulk admin actions → login streaks → draft/relist flow; story-dev should not re-add streak points or visible streak counters.**

**Rejected — Push notifications / re-engagement nags.** Student surfaces are pull-only. Toast only for user-initiated actions. The bottom nav does not carry unread counts.

**Rejected — Carousels on landing.** Static hero only. Content density over motion.

**Rejected — Emoji in functional UI.** Rank icons are SVG. Status badges are text + color. No ✅, no 🎉, no 🔥 in any functional surface.

**Rejected — Red error fills on forms.** Border + inline text only. Error containers for inline messages only.

**Rejected — Nested modals.** One level max. Purchase confirmation on listing modal is the deepest stack.

**Rejected — Infinite scroll.** Pagination (50 listings/page). Predictable, bookmarkable, performant.

**Rejected — Algorithmic reputation scores.** Simple 6-tier rank + star ratings + dispute count. Transparent, explainable, no hidden weighting.

**Rejected — Real payment flow simulation.** Explicit "reservation, not payment" language on every purchase confirmation, in every placeholder for an order, in the FAQ, in the help copy. The product simulates trust, not money.

---

## Key Flows

Numbered UJ-1 through UJ-7 per the PRD. Each has a named protagonist, numbered steps, a climax beat, and a failure path where applicable.

### Flow 1 — UJ-1: Kasun lists his used textbook and sells it (Kasun, 3rd-year CS, finished a module, wants to sell)

1. Kasun signs in. Lands on the Board.
2. Taps the FAB on My Listings → Create Listing.
3. Step 1: Basics — title "Calculus Textbook 2nd Ed", category "Textbooks", type "Product".
4. Step 2: Details — description, price `LKR 2,500` (price field `inputmode="decimal"`), quantity 1, condition "Good".
5. Step 3: Images — uploads 2 photos. Client-side resize to 1200px max; chunked upload with Uppy.js; 4-layer validation runs (MIME, dimensions, magic bytes, GD re-encode). Three thumbnail sizes generated.
6. Step 4: Review — sees the rendered card. Saves as Draft first. Toast: "Draft saved."
7. Edits the draft to fix a typo. Submits. Toast: "Listing submitted. Auto-approves in 24 hours unless an admin reviews first."
8. **Climax:** The listing enters `pending`. The hourly cron auto-approves it 24 hours later (or an admin approves sooner), setting `approved_at = NOW()` and `approved_by NULL`. Kasun sees it on the board with his rank badge.

**Failure path:** Admin rejects → Kasun sees the reason on My Listings → Pending tab. Edits, resubmits → returns to `pending`.

**Edge:** If Kasun's source listing was previously approved and he relists (one-click "Relist" from a Sold listing), the relist skips `pending` and goes to `active` immediately. The `review_flag` is set if the relist quantity differs from the original.

### Flow 2 — UJ-2: Tharushi buys the textbook and gets a ticket (Tharushi, 1st-year CS, budget-conscious)

1. Tharushi signs in. Lands on the Board (corkboard view by default).
2. Browses the Textbooks category. Sees Kasun's listing as a paper card on the cork.
3. Taps the card → listing modal opens full-screen on mobile.
4. Reviews images, description, price, seller info (rank, verified, rating). Taps "Buy Now".
5. **Climax:** Purchase confirmation modal opens. Body: "Confirm purchase? This reserves the item with a digital ticket (a reservation, not payment)." Scrim click is suppressed for 2 seconds. Tharushi taps Confirm.
6. Server creates the ticket. `ticket_code` = `TK-` + 22 random base62 chars from `random_bytes(16)` (e.g., `TK-7QXK2M9WBV4N8PRTYC3AD`). Listing `quantity_sold` increments to 1; listing auto-sold if quantity was 1. Idempotency: the unique-key retry loop on `ticket_code` collision handles concurrent purchases.
7. Redirected to My Tickets. Toast: "Ticket created. Code: TK-7QXK2M9WBV4N8PRTYC3AD". The new ticket card is auto-focused.

**Failure path:** Network error during purchase → toast: "Couldn't reach the server. Try again." Modal stays open. Confirm button re-enabled.

**Edge:** If the listing has `quantity > 1` and multiple buyers purchase simultaneously, each gets a ticket with a `#N/Q` suffix (UI-only, not stored). The listing auto-sold when `quantity_sold == quantity`.

### Flow 3 — UJ-3: Kasun redeems Tharushi's ticket at handover (Kasun meets Tharushi at the security gate)

1. Tharushi shows the code on her phone. Kasun navigates to Sales.
2. Kasun taps the ticket card. The redemption input opens (or the input is always visible at the top of Sales).
3. Kasun types the code. The system validates atomically: `status='active' AND dispute_status != 'pending' AND seller_id = CURRENT_USER`.
4. On match: ticket marked `redeemed`, `redeemed_at=NOW()`, points awarded to both parties. Inventory was consumed at ticket creation; redemption never touches `quantity_sold`.
5. **Climax:** Both see updated rank badges. The ticket card shows `redeemed` with the timestamp. Kasun hands over the book.

**Failure path:** Wrong code → inline error "Code not recognized" + attempt counter. 5 wrong attempts in an hour → 1-hour lockout on that ticket.

**Edge:** If Kasun is a new account (first 5 confirmed redemptions not yet used), his seller award is halved under FR-PTS-007: `+FLOOR(30 × 0.5) = +15`. This is the transaction-derived points halving; verification, listings, streaks, and reports are never halved.

### Flow 4 — UJ-4: Tharushi's ticket expires after 7 days (no handover)

1. Ticket `active`, older than 7 days. The hourly cron (`jobs/ticket_expiry.php`) runs.
2. The cron reads `expires_at` (written at creation as `created_at + 7 days, Asia/Colombo`). Atomic UPDATE: `WHERE status='active' AND dispute_status != 'pending' AND expires_at <= NOW()`.
3. Ticket marked `expired`. Listing `quantity_sold` decremented by 1 (for service tickets: decremented by `total_sessions - (session_number - 1)`, undelivered sessions only). If listing was `sold` and now has stock, restored to `active`.
4. **Climax:** Tharushi sees the ticket status `expired`. No points deducted (points are awarded only at redemption, not at purchase). The listing is available again for another buyer.

**Failure path:** A pending dispute blocks expiry. The cron skips disputed tickets; admin must resolve first. If a dispute is dismissed after the `expires_at` has passed, the ticket returns to `active` with an expired `expires_at`; the next cron tick immediately expires it (intended behavior).

### Flow 5 — UJ-5: Buyer disputes a ticket (Tharushi paid, Kasun never responds, 3 days pass)

1. Tharushi opens My Tickets. Sees the ticket as `active` with a "Dispute" button (Dispute is also available on `redeemed` tickets per FR-TKT-007/008).
2. Taps Dispute. Dispute modal opens. Reason dropdown (seller_unresponsive selected by default). Required text field. Optional evidence image.
3. Submits. Server: ticket `status='disputed'` AND `dispute_status='pending'`. Report created with `target_type='ticket'`. Toast: "Dispute submitted. Admin will review within 48 hours."
4. Admin sees the report in the queue with a "Dispute" badge. Opens the evidence detail view.
5. **Climax:** Admin chooses "Force Expire" (`dispute_status='upheld'`, ticket → `expired`), "Force Redeem" (`dispute_status='upheld'`, ticket → `redeemed`), or "Dismiss" (`dispute_status='rejected'`, ticket → `active`). The underlying ticket state is restored per the outcome.
6. 3-day auto-dismiss if admin idle: the hourly cron auto-dismisses (`dispute_status='rejected'`, ticket returns to `active`).

**Failure path:** Dispute filed on a non-active / non-redeemed ticket → button is disabled, tooltip explains.

### Flow 6 — UJ-6: Admin moderates a reported listing (Admin, faculty)

1. Admin signs in. Lands on Admin Dashboard. Sees a notification badge on Reports.
2. Opens Reports queue. Sees a row: target listing (thumbnail + title), reporter (nickname + tier), reason, age.
3. Opens the row → evidence detail view: listing preview, reporter info, action buttons.
4. Chooses "Remove Listing" + "Warn User". Re-auth dialog opens (password confirm, per NFR-SEC-010). Admin enters password.
5. **Climax:** Listing status → `removed`. User gets a warning flag. Report `status='resolved'`. Audit log entry with hash chain.

**Failure path:** Wrong re-auth password → inline error. Three wrong attempts → 15-minute cooldown on admin actions. Audit log entry for the failed re-auth.

**Edge:** False report → admin dismisses, reporter gets no points. Velocity flag → user frozen pending review; admin can void or approve (FR-PTS-010).

### Flow 7 — UJ-7: New student registers and earns first rank (Sachini, 1st-year, first time)

1. Sachini lands on the public landing page. Sees hero, value prop, "Get Started" CTA.
2. Taps Get Started → Register form. Email, student ID, password, nickname.
3. Submits. Server validates the student ID against the seeded allowlist (≈50 demo accounts). Email format checked. Simulated verification runs.
4. **Climax:** Lands on dashboard with rank "Recruit (E)" (0 points, gray shield). Profile verification +50 points (one-time). Running total **50**. Tier calculation: `points >= 50` → tier D (Rookie). Badge updates instantly.
5. Creates a first listing → approved → **+5** points (listing approvals are never halved) → running total **55**. Buys a textbook → ticket created (no points at creation) → seller confirms handover → buyer award **+⌊10 × 0.5⌋ = +5** → running total **60** → still Rookie (D). This confirmed redemption is the first of 5 toward exiting the new-account multiplier.

**Failure path:** Email not `@students.nsbm.ac.lk` → inline error. Student ID not in allowlist → inline error with "Contact your admin" guidance. Nickname taken → inline error.

**Edge:** If Sachini's nickname matches a reserved staff name, the registration is rejected on submit. Reserved list is moderated by admins.

---

## Responsive & Platform

### Breakpoint Behavior

| Surface | <576px | 576-767px | 768-991px | 992-1199px | ≥1200px |
|---------|--------|-----------|-----------|------------|---------|
| Board | 1 col, plain grid (cork auto-disabled) | 2 col, plain grid (cork auto-disabled) | 3 col, cork enabled, sidebar collapsed | 4 col, cork enabled, sidebar full | 4 col, cork enabled, sidebar full |
| Listing Modal | Full screen | Full screen | Centered 600px | Centered 600px | Centered 600px |
| Create Listing | Full screen steps | Full screen steps | Centered 700px | Centered 700px | Centered 700px |
| My Tickets | Stacked cards | Stacked cards | 2-col grid | 2-col grid | 2-col grid |
| Sales | Stacked, listing-grouped | Stacked, listing-grouped | 2-col grid, listing-grouped | 2-col grid, listing-grouped | 2-col grid, listing-grouped |
| Profile | Stacked tabs | Stacked tabs | Sidebar + tabs | Sidebar + tabs | Sidebar + tabs |
| Leaderboards | Stacked (each board its own card) | Stacked | 2-col grid | 2-col grid | 2-col grid |
| Admin Tables | Horizontal scroll (sticky first col) | Horizontal scroll (sticky first col) | Full table | Full table | Full table |
| Admin Dashboard | Stacked stat cards | 2-col stat grid | 4-col stat grid | 4-col stat grid | 4-col stat grid |
| Ticket Code | Stacked actions | Inline actions | Inline actions | Inline actions | Inline actions |

### Platform Conventions

- **iOS.** Respects safe area insets (bottom nav above home indicator). Native scroll bounce. `inputmode="decimal"` on price. `autocomplete="email"` / `"current-password"` / `"one-time-code"` per the accessibility floor.
- **Android.** Back gesture closes modal/sheet. `inputmode="numeric"` on price. Share target API for WhatsApp (future). Material You dynamic color is not honored (we use our own tokens).
- **Desktop.** Hover states on all interactive elements. Keyboard shortcuts: `/` focuses the board search, `Escape` closes the modal, `Enter` submits the form, `Tab` cycles focus.
- **PWA.** Not in MVP. Manifest + service worker are documented for post-MVP.

### Performance Budgets

- Board initial load: < 2s on localhost (uncached, NFR-PER-001). 50 listings per page (NFR-PER-002).
- Image thumbnails generated on upload: 200px, 600px, 1200px, all WebP at 80% quality (NFR-PER-003).
- Listing modal open: < 200ms (content pre-loaded or fetched via a fast endpoint).
- Toast appear: < 100ms.
- Cron — ticket expiry: < 30s for 10k tickets (single guarded UPDATE, NFR-PER-004).
- Leaderboard queries: indexed summary tables refreshed daily by cron (NFR-PER-005). MySQL has no materialized views.

---

## Open Items

| ID | Item | Type | Blocking |
|----|------|------|----------|
| OQ-001 | NSBM IT policy for student project deployment | open_question | No (faculty sponsor; not UX) |
| OQ-002 | Admin panel default theme (light vs shared tokens) | resolved | — (light default; student dark; user toggle persists) |
| OQ-003 | WhatsApp share deep link format validation | assumption | No (server regex `^(\+94\|0)7[0-9]{8}$`; format documented) |
| OQ-004 | Ticket code collision handling UI | assumption | No (backend retry loop on unique constraint; UI shows error only if 3+ collisions) |
| OQ-005 | Leaderboard pagination | resolved | — (top 10/20 are fixed; no pagination control) |
| OQ-006 | SSO integration with NSBM | resolved | — (not in MVP; users create nicknames) |
| OQ-007 | Demo transaction volume — test users | assumption | No (≈50 demo accounts seeded) |
| OQ-008 | Project report structure & ownership | resolved | — (Doc/QA Lead owns) |
| OQ-009 | Google Drive folder & permissions | resolved | — (Doc/QA Lead owns) |
| OQ-010 | Dispute-queue duty roster during demo week | open_question | No (team-lead ownership; not UX) |
| OQ-011 | Cut-decision owner for week-2 crunch | resolved | — (named before week 2; cut order pre-agreed in PRD §9) |
| OQ-UX-1 | Reserved-nickname list (staff names) | open_question | No (admin-editable; falls back to first-name + last-initial) |
| OQ-UX-2 | Velocity flag badge copy on user-self profile | assumption | No ("Earning above legitimate ceiling — review queued"; links to static explanation page) |
| OQ-UX-3 | Service handover session-confirm UI on Sales | assumption | No (per-session button with `#N/M` progress; same flow as single-shot redemption) |
