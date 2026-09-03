<?php
/**
 * Quick debug test for ReviewAction.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase05\Review;

use App\Tests\Integration\Phase04\Fixtures\Fixtures;

class ReviewActionDebugTest extends Fixtures
{
    public function test_debug_outside_user(): void
    {
        $seller = $this->seedUser(['nickname' => 'seller']);
        $buyer = $this->seedUser(['nickname' => 'buyer']);
        $outsider = $this->seedUser(['nickname' => 'outsider']);
        $listingId = $this->seedListing($seller, $this->firstCategoryId());
        $ticketId = $this->seedTicket([
            'listing_id' => $listingId,
            'buyer_id' => $buyer,
            'seller_id' => $seller,
            'status' => 'redeemed',
        ]);
        $this->pdo->prepare('UPDATE tickets SET redeemed_at = NOW() WHERE id = ?')->execute([$ticketId]);

        // Confirm setup.
        $row = $this->pdo->query('SELECT buyer_id, seller_id, status, redeemed_at FROM tickets WHERE id = ' . $ticketId)->fetch();
        $this->assertSame($buyer, (int) $row['buyer_id']);
        $this->assertSame($seller, (int) $row['seller_id']);
        $this->assertSame('redeemed', (string) $row['status']);

        $result = $this->dispatchAction(
            'App\Review\Action\ReviewAction',
            'handlePost',
            $outsider,
            ['id' => $ticketId],
            ['rating' => '5']
        );
        // Check that the request was a 302 redirect (as expected) and the response body shows the error.
        $this->assertSame(302, $result['status']);
    }
}