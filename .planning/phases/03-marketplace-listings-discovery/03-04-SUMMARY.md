---
phase: 03-marketplace-listings-discovery
plan: 04
subsystem: public-landing + auto-approve-sweep-tests
tags:
  - landing-page
  - public-surface
  - team-section
  - how-it-works
  - vision-mission
  - footer
  - display-lg
  - auto-approve-sweep
  - integration-test
  - pcntl-fork
  - tz-aware-strtotime
dependency_graph:
  requires:
    - phase: 02-student-authentication-profiles
      provides: Support\Auth (currentUser + requireReAuth), View::render,
                layout.php + head.php + bottom_nav + flash_toast partials,
                route map shape
    - phase: 03-01 (Plan 03-01)
      provides: migrations (008_listings, 009_categories, 010_listing_revisions),
                ListingModel, listing_service, ImageProxy, listing_card partials
    - phase: 03-02 (Plan 03-02)
      provides: ListingAutoApproveAction + listing_service::runAutoApproveSweep,
                cron_log migration (012), admin_cron rate limit, re-auth gate
                in Support\Auth::requireReAuth
    - phase: 03-03 (Plan 03-03)
      provides: BrowseAction + listing_modal + 6 View partials
                (corkboard grid + Bootstrap carousel)
  provides:
    - src/Auth/Action/HomeAction.php (replaced Phase 2 stub with real landing)
    - src/Auth/View/home.php (replaced Phase 2 stub with 5-section landing)
    - src/Auth/View/partials/hero.php (new — CTAs flip on auth state)
    - src/Auth/View/partials/vision_mission.php (new — 2-card row)
    - src/Auth/View/partials/how_it_works.php (new — 5-step row per D-25)
    - src/Auth/View/partials/team_section.php (new — 6 cards from config/team.php)
    - src/Auth/View/partials/landing_footer.php (new — NSBM + GitHub + Drive)
    - src/Support/View/layout.php (extended: 'public' surface → light theme)
    - src/Support/View/partials/head.php (extended: FOUC-guard honors 'public')
    - public/assets/css/tickettrade.components.css (display-lg / display-md /
      display-sm utility classes + landing page section helpers)
    - src/Support/Auth.php (requireReAuth parses last_seen as Asia/Colombo wall
      clock per AD-17; was using strtotime() in script-default TZ, off by 5.5h)
    - 17 new integration tests:
        tests/Integration/Phase03/Landing/HomeLandingTest.php (7)
        tests/Integration/Phase03/Landing/TeamSectionTest.php (5)
        tests/Integration/Phase03/Listing/ListingAutoApproveSweepTest.php (5)
  affects:
    - "Phase 4 (purchases + tickets) — the landing's `Explore Marketplace`
      CTA already routes to /board; purchasing lands later"
    - "Phase 5 (reviews) — no overlap; the landing surfaces a static copy
      block that does not change when reviews ship"
    - "Phase 8 (admin console) — admin re-auth gate is now the canonical
      testable interface for sensitive admin Actions"
    - "The public/data-surface='public' is honored by both layout.php
      (default theme) and head.php (FOUC-guard); other Actions that want
      the same treatment can set $GLOBALS['_tt_surface']='public'"
tech-stack:
  added: []
  patterns:
    - "HomeAction: thin Action; reads current_user from $GLOBALS, requires
      config/team.php via require, calls View::render with [current_user,
      team, is_logged_in] — NO DB call, NO listings count on the landing"
    - "data-surface='public' (new surface value): the layout's
      $themeDefault treats 'public' like 'admin' (light) so the landing
      ships light-mode by default per UX-06. The CSS body class
      `surface-public` is currently a no-op (no rules) but the class is
      added for forward-compat if a future surface-public token set lands"
    - "View partials: home.php requires the 5 partials via absolute path
      ($partialsDir = __DIR__ . '/partials'); View::partial() is NOT used
      because it hard-codes src/Support/View/partials/. The 5 landing
      partials live in src/Auth/View/partials/ (Auth context) so the
      direct require keeps the concerns split"
    - "Auto-approve test isolation: pcntl_fork() spawns a child to
      invoke the Action; the parent's PDO handle is reset via Db::reset()
      after pcntl_waitpid() so the inherited handle doesn't dangle. The
      child writes the captured response (http_response_code + buffered
      body) to a side file via register_shutdown_function BEFORE exit()
      terminates it; the parent reads the file after the child exits"
    - "Auth::requireReAuth TZ fix: the prior strtotime($last_seen) call
      parsed the DB string in the script-default TZ (UTC for CLI). The
      DB stores last_seen as Asia/Colombo wall clock per AD-17, so the
      cutoff math was off by 5.5h. The fix wraps the parse in
      new DateTime($row['last_seen'], new DateTimeZone('Asia/Colombo'))
      and reads getTimestamp() — the 25h-old + 5/min cron-log test
      case now correctly returns 403 when re-auth is stale"
key-files:
  created:
    - src/Auth/View/partials/hero.php
    - src/Auth/View/partials/vision_mission.php
    - src/Auth/View/partials/how_it_works.php
    - src/Auth/View/partials/team_section.php
    - src/Auth/View/partials/landing_footer.php
    - tests/Integration/Phase03/Landing/HomeLandingTest.php
    - tests/Integration/Phase03/Landing/TeamSectionTest.php
    - tests/Integration/Phase03/Listing/ListingAutoApproveSweepTest.php
  modified:
    - src/Auth/Action/HomeAction.php (replaced Phase 2 stub with real landing)
    - src/Auth/View/home.php (replaced Phase 2 stub with 5-section landing)
    - src/Support/View/layout.php ($themeDefault honors 'public' surface)
    - src/Support/View/partials/head.php (FOUC-guard honors 'public' surface)
    - public/assets/css/tickettrade.components.css (.display-lg, .display-md,
      .display-sm, .hero/.how-it-works/.team section helpers)
    - src/Support/Auth.php (requireReAuth: TZ-aware DateTime parse of last_seen)
decisions:
  - "data-surface='public' is treated as 'light theme' in both layout.php
    and head.php. Admin and public both default to light; student defaults
    to dark. No new CSS rules for surface-public were needed; the public
    landing uses Bootstrap utility classes (bg-primary, text-on-primary,
    bg-surface-container) that already honor the theme tokens."
  - "The Hero CTA flips from `Get Started` (-> /register) to
    `My listings` (-> /my-listings) for logged-in users. Phase 2's
    HomeAction redirected logged-in users to /board; Phase 3 keeps
    them on the landing but with the right CTA. The 5-section landing
    has educational value for first-time visitors even when they're
    already registered."
  - "View::partial() resolves to src/Support/View/partials/. The
    landing's 5 partials live in src/Auth/View/partials/ instead, and
    home.php requires them directly via __DIR__ . '/partials/*.php'.
    This keeps the Auth-specific landing partials next to home.php
    and avoids polluting the global partials/ directory."
  - "The 5 How-It-Works steps are hard-coded in how_it_works.php per
    D-25 (Register & verify, List or browse, Buy with a digital ticket,
    Redeem in person, Rate & review). Phase 5+ may add a 6th step for
    reviews; for now the count is fixed at 5."
  - "The Team section reads from config/team.php (already shipped by
    Phase 1+2). The file already has 6 entries with placeholder names;
    the team lead updates the names + leader at submission time. The
    Team section is the WAD rubric's Project Report 'group member
    names + roles' evidence source per AGENTS.md sec 4."
  - "Auto-approve test isolation via pcntl_fork: the alternative
    (subprocess via shell_exec to php) is 100-1000x slower per test
    invocation, and 5 tests × ~1s each = 5s of subprocess overhead.
    pcntl_fork is faster (sub-millisecond fork) but requires the parent
    to reset its PDO handle after the child exits. The
    register_shutdown_function captures http_response_code + the
    buffered body before exit() terminates the child, which is the
    only way to observe a controller's exit() response from a test."
  - "Auth::requireReAuth TZ fix was discovered while debugging the
    testSweepWithoutReAuthReturns403 failure. The DB stores last_seen
    as Asia/Colombo wall clock per AD-17, but strtotime() in CLI
    context (where the test runs) parses the string in UTC. The 5.5h
    delta flipped the staleness check — a 10-minute-old session
    looked fresh to strtotime() in some test env states. The fix
    is a DateTime + getTimestamp() pair with the Asia/Colombo TZ
    explicitly bound. This is also a 1/3-fidelity proxy for the
    full admin_reauth table that lands in Phase 8 (AD-19)."
deviations:
  - "[Rule 2 - TZ correctness] Support\Auth::requireReAuth parsed
    last_seen with strtotime() in the script-default TZ. Under PHP
    CLI (default UTC) this was off by 5.5h from the DB's
    Asia/Colombo wall clock per AD-17. Without the fix,
    testSweepWithoutReAuthReturns403 would return 200 instead of 403
    in some test env states. Auto-fixed per Rule 2 (correctness for
    the 1/3-fidelity re-auth proxy)."
  - "[Rule 2 - missing CSS class] The plan calls for `<h1 class='display-lg'>`
    in hero.php, but .display-lg was not defined as a CSS class. The
    existing public_profile.php (Phase 2) used the same class name
    but never defined the rule. Auto-fixed per Rule 2: added
    .display-lg / .display-md / .display-sm utility classes to
    tickettrade.components.css that map to the design tokens. The
    Phase 2 public_profile now also renders correctly (was silently
    unstyled before)."
verification:
  - "phpunit (full suite) on a freshly reset test DB: 304 tests, 1462
    assertions, all green. The 17 new tests (7 HomeLanding + 5
    TeamSection + 5 ListingAutoApproveSweep) bring the count from
    287 / 1353 (Phase 3 Plan 03-03 close) to 304 / 1462."
  - "phpcs (project ruleset) on src/: 0 errors, 0 blocking warnings.
    LineLength is severity 0 in phpcs.xml so long lines in templates
    are non-blocking. phpcbf auto-fixed 22 PSR-12 indentation +
    blank-line + closing-tag errors in the 5 landing partials and
    home.php."
test-counts:
  pre-03-04:  287 tests, 1353 assertions
  post-03-04: 304 tests, 1462 assertions  (+17 tests, +109 assertions)
  landing:    12 tests,  84 assertions  (7 HomeLanding + 5 TeamSection)
  sweep:      5 tests,   25 assertions  (ListingAutoApproveSweep)
  net-add:    17 tests, 109 assertions
risk-register:
  - id: R-03-04-01
    severity: low
    title: "View::partial() is not used for the landing partials"
    mitigation: "Documented in the SUMMARY decisions block; home.php
      requires partials via __DIR__ . '/partials/*.php'. Any future
      refactor that moves the partials into src/Support/View/partials/
      can switch to View::partial() without behavior change."
  - id: R-03-04-02
    severity: low
    title: "pcntl_fork test isolation requires PDO reset in parent"
    mitigation: "Documented in the test helper's docblock; the parent
      calls App\Support\Db::reset() and re-fetches $this->pdo
      after pcntl_waitpid(). Verified on the fresh-DB CI run that
      PDO is consistent across the 5 sweep tests."
  - id: R-03-04-03
    severity: low
    title: "Auth::requireReAuth is a 1/3-fidelity proxy for AD-19"
    mitigation: "Phase 8 replaces requireReAuth with a proper
      admin_reauth table + re-auth modal. The current implementation
      uses sessions.last_seen (any authenticated action within the
      window counts as a re-auth), which is sufficient for Phase 3's
      cron hand-trigger use case."
next:
  - "/gsd:verify-work 03 (the verifier subagent should walk the landing
    page in a browser and confirm the 5 sections render correctly;
    the auto-approve sweep tests are the regression net)"
  - "Phase 4 Plan 04-01 lands the purchase flow; the landing's
    `Explore Marketplace` CTA already routes to /board where buyers
    can click a listing and see the modal — Phase 4 wires the modal's
    Buy button to /listings/{id}/buy"
