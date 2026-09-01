<?php

/**
 * TicketTrade — Support\Action\ImageProxyAction
 *
 * Thin Action wrapper around Support\ImageProxy::serve(). The Router
 * places the matched path params in $GLOBALS['_tt_path_params'] under
 * 'listing_id' and 'size'. This Action validates, casts, and forwards.
 *
 * The route is registered with auth=false so guests can read thumbnails
 * and the proxy itself decides whether to gate 'full' reads by session.
 */

declare(strict_types=1);

namespace App\Support\Action;

use App\Support\Error;
use App\Support\ImageProxy;

class ImageProxyAction
{
    public function handle(): void
    {
        $params = $GLOBALS['_tt_path_params'] ?? [];
        $listingId = (int) ($params['listing_id'] ?? 0);
        $size = (string) ($params['size'] ?? '');
        if ($listingId <= 0 || !in_array($size, ImageProxy::SIZES, true)) {
            Error::not_found();
            return;
        }
        ImageProxy::serve($listingId, $size);
    }
}
