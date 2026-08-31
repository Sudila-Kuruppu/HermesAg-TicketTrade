# Phase 2 Plan 02-03 - Public Profile (Developer Notes)

This document covers the public read-side of `/profile/{nickname}` - the summary header that ships in Phase 2. The per-tab content (My Listings in Phase 3, My Tickets / Purchase History / Sales History in Phase 4, Reviews in Phase 5) lands in later phases. The summary header shape is locked here; later phases ADD tabs below this header without modifying it.

## The public profile contract

The public profile summary header renders:

- **Avatar** - `<img src="/assets/img/avatars/avatar-{N}.svg">` for N in 1..12, with `(int) max(1, min(12, $avatar_id))` clamp in the View (Pitfall 11 + D-18).
- **Full name** - `users.full_name`, escaped via `View::h()`.
- **@nickname** - `users.nickname` rendered with the `@` prefix, escaped.
- **Bio** - `users.bio`, escaped + `nl2br()`. If empty: muted copy "No bio yet.".
- **Points** - `users.points` (re-injected by the Service after `auth_service::sanitizeUser` strips it).
- **Rank badge SVG** - `users.tier` mapped through `config/ranks.php` to one of six shield/crown shapes.
- **Verified checkmark** - `<i class="bi bi-patch-check-fill">` rendered only when `users.is_verified = TRUE` (PROF-04).
- **Join date** - `users.created_at` formatted in `Asia/Colombo` (D-14 + ARCHITECTURE-SPINE Conventions).
- **Stats row** - hardcoded placeholders: `0 Sales / 0 Purchases / No reviews yet / 0 Disputes`. No DB query (D-14).
- **Disabled "Report user" link** - `<a class="btn btn-outline-secondary disabled" aria-disabled="true" data-bs-toggle="tooltip" title="Coming soon">` (D-16).

The public profile View does NOT render:

- `email` / `student_id` (T-02-20)
- `is_admin` / `is_banned` / `points_frozen` / `password_hash` (T-02-10, T-02-27)
- `whatsapp` (D-16 - WhatsApp is private; the contact path is the Phase 4 ticket WhatsApp share)
- any tab navigation (D-14 - tabs are per-user-content; the public profile is summary-only)
- the edit form or any owner-only affordances (the owner sees an "Edit profile" link to `/profile` if `$is_owner` is true; guests see only the summary)

## Why no tabs in Phase 2

D-14 pins the rule: the public profile ships only the summary header in Phase 2. The 5 tabs (My Listings, My Tickets, Purchase History, Sales History, Reviews) are deferred:

- **Phase 3** - adds the **My Listings** tab.
- **Phase 4** - adds **My Tickets**, **Purchase History**, **Sales History** tabs.
- **Phase 5** - adds the **Reviews** tab.

When Phase 3+ add tabs, they MUST append the tab nav below the `<hr>` in the summary header card, never modify the header. The summary header card's avatar / name / nickname / bio / points / rank badge / verified checkmark / join date / disabled Report-user link is the locked portion of the public profile. If a future task needs to change the header markup, it should be reviewed against this contract.

The `test_profile_no_tabs` test in `tests/Integration/Phase02/User/PublicProfileTest.php` enforces the absence of `role="tablist"`, `nav-tabs`, "My Listings", and "My Tickets" in Phase 2.

## The D-16 Report user pattern

The "Report user" link is rendered as a plain `<a>` with the bootstrap "disabled" class plus a Bootstrap JS tooltip:

```html
<a href="#"
   class="btn btn-outline-secondary disabled"
   aria-disabled="true"
   data-bs-toggle="tooltip"
   data-bs-placement="top"
   title="Coming soon">Report user</a>
```

Why this exact markup:

1. **`href="#"`** - the link has no real target in Phase 2; clicking it does nothing.
2. **`class="btn btn-outline-secondary disabled"`** - Bootstrap's `disabled` class makes the link look greyed out and prevents `:hover` styling.
3. **`aria-disabled="true"`** - assistive tech announces the link as disabled.
4. **`data-bs-toggle="tooltip" data-bs-placement="top"`** - the Bootstrap JS component auto-attaches a tooltip on hover/focus via the Phase 1 `tickettrade.js` (no new JS in Phase 2).
5. **`title="Coming soon"`** - the tooltip text.

When Phase 7 wires the real `/reports/users/{id}/new` endpoint, the markup swaps to:

```html
<a href="/reports/users/{id}/new"
   class="btn btn-outline-secondary">Report user</a>
```

Remove `disabled`, `aria-disabled`, and the `data-bs-toggle` attributes. Phase 7's commit should also add a test for the wired-up state to prevent regressions.

## How to add a new field to the public profile

Step-by-step recipe (use this when Phase 3+ needs to surface a new piece of user data, e.g. `users.location`):

1. **Schema change** - add the column to a new migration (e.g. `migrations/008_user_location.sql`). For sensitive data, plan the migration's data-redaction behavior (do we backfill old rows? do we allow NULL?).
2. **Auth\Service\auth_service** - extend `sanitizeUser()` to strip the field if it's sensitive. Sensitive = anything that should never appear on a public endpoint (admin flags, internal IDs, internal timestamps). Don't strip public fields like `bio` or `avatar_id`.
3. **User\Service\user_service** - extend `getByNicknameForPublicProfile()` to:
   - Add the column to the `SELECT` projection.
   - If the field is needed on the public view but `sanitizeUser` strips it, re-inject it after sanitization (the same way `points` and `is_verified` are re-injected today).
4. **User\View\public_profile** - add the markup with `View::h()` on every dynamic value. Never use `htmlspecialchars()` directly - `View::h()` is the canonical wrapper per AD-16.
5. **Tests** - extend `tests/Integration/Phase02/User/PublicProfileTest.php` to assert the new field renders. If sensitive, also extend `test_profile_no_sensitive_fields` to assert the field name does NOT appear in the rendered HTML.
6. **Lint** - run `vendor/bin/phpcs` and `vendor/bin/phpunit --testsuite=phase-2`. Fix any PSR-12 violations.
7. **Document** - update this file's "public profile contract" section to list the new field.

## Common pitfalls

Five mistakes a Phase 3+ implementer is most likely to make when extending this area:

1. **Adding a tab nav to the public profile.** D-14 forbids tabs in Phase 2; Phase 3+ ADD tabs below the locked summary header, never above or beside it. The `test_profile_no_tabs` test enforces the absence of tab markup; if your change adds tabs, that test fails. The right place for the tab nav is below the `<hr>` and the stats row, inside the same `<div class="card surface-raised p-4">`.

2. **Showing the WhatsApp on the public view.** D-16 - WhatsApp is never sent to the public View. The sanitized row does not include `whatsapp`, and the View template does not echo it. The contact path is the Phase 4 ticket WhatsApp share, not the public profile. `test_profile_no_whatsapp` enforces the absence of the WhatsApp string.

3. **Using `WHERE nickname LIKE '%alice%'` or `LOWER(nickname) = LOWER(?)` in the public lookup.** D-15 - nickname is locked at registration and preserved in storage; the URL is the literal stored value. Case-mismatched URLs are 404s. The current `getByNicknameForPublicProfile` uses `BINARY nickname = ?` to enforce case-sensitivity on the utf8mb4_unicode_ci table. Don't switch to `LOWER()` unless you also change D-15.

4. **Querying `users.points` directly in the View.** `auth_service::sanitizeUser()` strips `points` (and `points_frozen`) - if a tab View needs `points`, the Service re-injects it AFTER sanitization with the canonical field name. Don't bypass the sanitization step by querying `users` directly in a View; that's an information-disclosure escape hatch.

5. **Using `htmlspecialchars($bio, ENT_QUOTES, 'UTF-8')` directly in a View instead of `View::h()`.** `View::h()` is the canonical escape wrapper per AD-16. The wrapper guarantees the same charset and flag combination across every View. The bio field also needs `nl2br()` for multi-line preservation - the wrapper order is `nl2br(View::h($bio))`.

## Common pitfalls - Wave 0 specifics (this plan)

Two more pitfalls specific to this plan's Wave 0 code:

6. **`View::partial('rank_badge.php', ...)` adds `.php` to the name.** `View::partial()` already appends `.php` internally. Pass `'rank_badge'`, not `'rank_badge.php'`. The current public_profile View does it correctly; future code should follow the same pattern.

7. **Forgetting to extract `$vars` from `$GLOBALS['_tt_view_vars']` in a partial.** `View::render()` extracts `$vars` into the local scope (so a View can use `$profile` directly). `View::partial()` does NOT - partials must read from `$GLOBALS['_tt_view_vars']` explicitly, or be `require`d from a View that already extracted them. The current `rank_badge.php` partial uses the `$GLOBALS['_tt_view_vars']` form. New partials should do the same unless they're being rendered from inside the layout's `require` chain.

## Verification

The current state:

- `vendor/bin/phpunit --testsuite=phase-2` - 52 tests, 215 assertions, all green.
- `vendor/bin/phpunit --testsuite=phase-2 --filter='PublicProfile'` - 21 tests, 107 assertions, all green.
- `vendor/bin/phpcs` - zero violations in files modified by this plan.
- `curl -sS -i http://127.0.0.1:18004/profile/alice` - 200 with summary header (live smoke test).
- `curl -sS -i http://127.0.0.1:18004/profile/nonexistent` - 404 (generic page).
- `curl -sS -i http://127.0.0.1:18004/profile/ALICE` - 404 (case mismatch).
- `curl -sS -i http://127.0.0.1:18004/profile/alice-123` - 404 (invalid char).

## What ships next

Plan 02-03 closes ROADMAP Phase 2 success criterion 7 (profile shows rank badge, points, join date, transaction counts, average rating). Phase 3 can ADD the My Listings tab to the public profile View without modifying the summary header markup (additive, not breaking).

The 02-03-SUMMARY.md captures the verification log and deviations for this plan.
