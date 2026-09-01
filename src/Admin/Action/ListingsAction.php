<?php

/**
 * TicketTrade — Admin\Action\ListingsAction
 *
 * Stub Action for Phase 2. Plan 02-01 ships the admin route guard so
 * non-admin access 404s (D-10). Phase 8 fills the real admin surface.
 */

declare(strict_types=1);

namespace App\Admin\Action;

class ListingsAction
{
    public function handle(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            . '<title>Admin · ListingsAction</title></head><body><main id="main" tabindex="-1">'
            . '<h1>Admin · ListingsAction</h1><p>Phase 8 fills this admin surface.</p>'
            . '</main></body></html>';
    }
}
