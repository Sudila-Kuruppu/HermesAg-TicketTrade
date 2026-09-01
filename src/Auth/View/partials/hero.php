<?php

/**
 * TicketTrade — Auth/View/partials/hero
 *
 * Phase 3 Plan 03-04. The public landing hero. The CTA flips from
 * `Get Started` (-> /register) to `My listings` (-> /my-listings) when
 * the visitor is already authenticated. The second CTA is always
 * `Explore Marketplace` -> /board.
 *
 * Variables (from the parent View):
 *   - $is_logged_in (bool)
 */

$is_logged_in = $is_logged_in ?? false;
?>
<section class="hero bg-primary text-on-primary py-5" aria-labelledby="hero-heading">
  <div class="container text-center">
    <h1 id="hero-heading" class="display-lg mb-3">Every Trade Ends With Proof</h1>
    <p class="lead mt-3">NSBM's campus-only marketplace where every purchase produces a confirmable digital ticket.</p>
    <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center mt-4">
      <a class="btn btn-primary btn-lg" href="<?= $is_logged_in ? '/my-listings' : '/register' ?>"><?= $is_logged_in ? 'My listings' : 'Get Started' ?></a>
      <a class="btn btn-outline-primary btn-lg" href="/board">Explore Marketplace</a>
    </div>
  </div>
</section>
