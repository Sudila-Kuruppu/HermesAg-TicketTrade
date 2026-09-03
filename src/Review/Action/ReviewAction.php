<?php

/**
 * TicketTrade — Review\Action\ReviewAction
 *
 * Phase 5 Plan 05-01. POST /tickets/{id}/review.
 *
 * Per AD-1: thin Action. CSRF is enforced at bootstrap.
 * The Action:
 *   1. Checks current user (auth guard from Router).
 *   2. Reads rating (int 1..5) and comment (string|null, max 2000)
 *      from $_POST.
 *   3. Calls review_service::submitReview().
 *   4. On success: flash 'Review submitted.' + 302 to /purchases.
 *   5. On failure (E_REVIEW_NOT_ELIGIBLE / E_REVIEW_WINDOW_CLOSED /
 *      E_REVIEW_ALREADY_LEFT / E_REVIEW_FORBIDDEN /
 *      E_REVIEW_INVALID_RATING / E_REVIEW_NOT_FOUND): flash error +
 *      302 to /purchases so the buyer keeps context.
 *
 * Per D-04: no GET route for the form; the modal launches from the
 * Purchase History row.
 */

declare(strict_types=1);

namespace App\Review\Action;

use App\Support\Auth as AuthGuard;
use App\Support\Csrf;
use App\Support\RateLimit;
use App\Support\View;
use App\Review\Service\review_service;

class ReviewAction
{
    public function handlePost(): void
    {
        $user = AuthGuard::currentUser();
        if ($user === null) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => ['code' => 'E_AUTH_REQUIRED', 'message' => 'Authentication required.'],
            ]);
            exit;
        }
        $userId = (int) $user['user_id'];
        $ticketId = (int) ($GLOBALS['_tt_path_params']['id'] ?? 0);
        if ($ticketId <= 0) {
            $this->redirectWithError('/purchases', 'Invalid ticket.');
            return;
        }

        // CSRF is enforced at bootstrap; just read the token for the form.
        Csrf::token();

        // Rate limit per NFR-SEC-007 (10/hr/user). Bucket key is the
        // user id so different users share the route slot but the
        // 10/hr cap is per-account. Mirrors BuyAction.
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $rl = RateLimit::hit('review', $ip, (string) $userId);
        if (!$rl['allowed']) {
            $this->redirectWithError('/purchases', 'Too many review submissions. Try again later.');
            return;
        }

        // Parse + validate input.
        $rawRating = $_POST['rating'] ?? null;
        if (!is_string($rawRating) && !is_int($rawRating)) {
            $this->redirectWithError('/purchases', 'Rating is required.');
            return;
        }
        $rating = (int) $rawRating;
        $rawComment = $_POST['comment'] ?? null;
        $comment = null;
        if (is_string($rawComment) && $rawComment !== '') {
            $len = mb_strlen($rawComment);
            if ($len > review_service::COMMENT_MAX_CHARS) {
                $rawComment = mb_substr($rawComment, 0, review_service::COMMENT_MAX_CHARS);
            }
            $comment = $rawComment;
        }

        $result = review_service::submitReview($ticketId, $userId, $rating, $comment);
        if ($result['ok'] === true) {
            View::flash('success', 'Review submitted.');
            header('Location: /purchases');
            exit;
        }

        $this->redirectWithError(
            '/purchases',
            (string) ($result['error']['message'] ?? 'Could not submit review.')
        );
    }

    private function redirectWithError(string $path, string $message): void
    {
        View::flash('error', $message);
        header('Location: ' . $path);
        exit;
    }
}
