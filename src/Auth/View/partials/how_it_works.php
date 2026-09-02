<?php

/**
 * TicketTrade — Auth/View/partials/how_it_works
 *
 * Phase 3 Plan 03-04. Five step cards on desktop (col-md grid via
 * col-lg auto-fit), stacked on mobile. Hard-coded per D-25.
 */

?>
<section class="how-it-works bg-surface-container py-5" aria-labelledby="how-it-works-heading">
  <div class="container">
    <h2 id="how-it-works-heading" class="h3 text-center mb-4">How It Works</h2>
    <div class="row g-4">
      <?php
        $steps = [
        ['n' => 1, 'title' => 'Register & verify', 'desc' => 'Sign up with your @students.nsbm.ac.lk email and student ID. Verified students get a +50 trust bonus.'],
        ['n' => 2, 'title' => 'List or browse', 'desc' => 'Sellers post products or services with photos and price. Buyers browse the corkboard by category or search.'],
        ['n' => 3, 'title' => 'Buy with a digital ticket', 'desc' => 'A unique ticket code is generated at purchase. Share it with the seller via WhatsApp or in person.'],
        ['n' => 4, 'title' => 'Redeem in person', 'desc' => 'The seller enters the code to confirm handover. Both parties earn trust points.'],
        ['n' => 5, 'title' => 'Rate & review', 'desc' => "After redemption, both parties leave a 1-5 star rating. Reviews build the seller's reputation."],
        ];
        foreach ($steps as $step) :
            $n = (int) $step['n'];
            $title = htmlspecialchars((string) $step['title'], ENT_QUOTES, 'UTF-8');
            $desc = htmlspecialchars((string) $step['desc'], ENT_QUOTES, 'UTF-8');
            ?>
        <div class="col-12 col-md-6 col-lg">
          <div class="card h-100 text-center">
            <div class="card-body">
              <span class="badge bg-primary mb-2 d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;font-size:1rem"><?= $n ?></span>
              <h3 class="h6 mt-2"><?= $title ?></h3>
              <p class="small text-on-surface-variant mb-0"><?= $desc ?></p>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
    </div>
  </div>
</section>
