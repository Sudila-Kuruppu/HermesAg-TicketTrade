<?php

/**
 * TicketTrade — User\Action\PublicProfileAction
 *
 * Per D-11: GET /profile/{nickname} is a public route (no auth flag in
 * config/routes.php). Per D-14 / D-15 / D-16: the response is the
 * summary header for any registered user; banned, non-existent,
 * case-mismatched, and invalid-character URLs all return the same
 * generic 404 page (D-10, AD-14 — don't reveal the resource exists).
 *
 * The Router captures the path-param `nickname` into
 * $GLOBALS['_tt_path_params']['nickname'] per the Phase 2 Plan 02-01
 * Router implementation. The Action re-validates it against the
 * Plan 02-02 register-time regex (^[A-Za-z0-9_]{3,30}$) as a
 * defense-in-depth check; the Router already enforces the same regex
 * via its path-param matching, so this branch is unreachable in
 * practice but is the canonical hardening if the regex is ever loosened.
 *
 * Phase 5 Plan 05-02: extends handle() to fetch the review summary
 * (rating_avg, rating_count, rating_distribution, dispute_count) and
 * the paginated reviews-received list, injecting both into the View
 * per D-07/D-08/D-09.
 */

declare(strict_types=1);

namespace App\User\Action;

use App\Review\Service\review_service;
use App\Support\Error;
use App\Support\View;
use App\User\Service\user_service;

class PublicProfileAction
{
    /** Per D-08: Reviews tab paginates 10 per page. */
    private const REVIEWS_PER_PAGE = 10;

    /** Offset clamp ceiling — guards against absurd ?offset=100000 values. */
    private const REVIEWS_MAX_OFFSET = 1000;

    /**
     * GET /profile/{nickname}
     */
    public function handle(): void
    {
        $nickname = (string) ($GLOBALS['_tt_path_params']['nickname'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $nickname)) {
            Error::not_found();
            return;
        }

        $profile = user_service::getByNicknameForPublicProfile($nickname);
        if ($profile === null) {
            Error::not_found();
            return;
        }

        // is_owner toggles the Edit profile button. Guests see the same
        // header minus the button; authed users see the button when
        // they are looking at their own profile. Phase 2 placeholder
        // — the button is gated to the owner only, never to other
        // authed users.
        $currentUser = $GLOBALS['current_user'] ?? null;
        $isOwner = $currentUser !== null
            && isset($profile['user_id'], $currentUser['user_id'])
            && (int) $profile['user_id'] === (int) $currentUser['user_id'];

        // ---- Phase 5 Plan 05-02: rating aggregation + reviews list ----
        $userId = (int) $profile['user_id'];
        $summary = review_service::getSummaryForUser($userId);

        $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
        if ($offset < 0) {
            $offset = 0;
        } elseif ($offset > self::REVIEWS_MAX_OFFSET) {
            $offset = self::REVIEWS_MAX_OFFSET;
        }
        [$reviews, $total] = review_service::listReviewsForUser(
            $userId,
            self::REVIEWS_PER_PAGE,
            $offset
        );

        View::render(
            __DIR__ . '/../View/public_profile.php',
            [
                'profile' => $profile,
                'is_owner' => $isOwner,
                'summary' => $summary,
                'reviews' => $reviews,
                'reviews_total' => $total,
                'reviews_offset' => $offset,
                'reviews_per_page' => self::REVIEWS_PER_PAGE,
            ]
        );
    }
}
