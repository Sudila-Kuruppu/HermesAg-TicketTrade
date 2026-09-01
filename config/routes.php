<?php
/**
 * TicketTrade — Student Route Map
 *
 * Per D-11. The format is ['METHOD PATH' => [Class, method, opts]] where
 * opts is [auth => bool, admin => bool, csrf => bool, rate_limit => string|null].
 * Support\Router::dispatch() consults opts after Support\Auth::boot()
 * has populated $GLOBALS['current_user'].
 *
 * Plan 02-01 ships the route map with stub Actions. Plan 02-02 and
 * 02-03 fill the Action bodies.
 *
 * Plan 03-01 ADDS:
 *   - GET /img/{listing_id}/{size} for Support\ImageProxy. auth=false
 *     (proxy decides per-size), csrf=false (read-only), rate_limit=null
 *     (proxy enforces its own per-IP / per-user limits).
 */

declare(strict_types=1);

return [
    'GET /'                   => ['App\Auth\Action\HomeAction',          'handle',     ['auth' => false, 'admin' => false, 'csrf' => false, 'rate_limit' => null]],
    'GET /login'              => ['App\Auth\Action\LoginAction',         'handle',     ['auth' => false, 'admin' => false, 'csrf' => false, 'rate_limit' => null]],
    'POST /login'             => ['App\Auth\Action\LoginAction',         'handlePost', ['auth' => false, 'admin' => false, 'csrf' => true,  'rate_limit' => 'login']],
    'GET /register'           => ['App\Auth\Action\RegisterAction',      'handle',     ['auth' => false, 'admin' => false, 'csrf' => false, 'rate_limit' => null]],
    'POST /register'          => ['App\Auth\Action\RegisterAction',      'handlePost', ['auth' => false, 'admin' => false, 'csrf' => true,  'rate_limit' => 'register']],
    'GET /verify'             => ['App\Auth\Action\VerifyAction',        'handle',     ['auth' => false, 'admin' => false, 'csrf' => false, 'rate_limit' => null]],
    'GET /forgot-password'    => ['App\Auth\Action\ForgotPasswordAction','handle',     ['auth' => false, 'admin' => false, 'csrf' => false, 'rate_limit' => null]],
    'POST /forgot-password'   => ['App\Auth\Action\ForgotPasswordAction','handlePost', ['auth' => false, 'admin' => false, 'csrf' => true,  'rate_limit' => 'forgot_password']],
    'GET /reset-password'     => ['App\Auth\Action\ResetPasswordAction', 'handle',     ['auth' => false, 'admin' => false, 'csrf' => false, 'rate_limit' => null]],
    'POST /reset-password'    => ['App\Auth\Action\ResetPasswordAction', 'handlePost', ['auth' => false, 'admin' => false, 'csrf' => true,  'rate_limit' => 'forgot_password']],
    'GET /board'              => ['App\Listing\Action\BrowseAction',     'handle',     ['auth' => false, 'admin' => false, 'csrf' => false, 'rate_limit' => null]],
    'GET /profile/{nickname}' => ['App\User\Action\PublicProfileAction', 'handle',     ['auth' => false, 'admin' => false, 'csrf' => false, 'rate_limit' => null]],
    'GET /profile'            => ['App\User\Action\ProfileAction',       'handle',     ['auth' => true,  'admin' => false, 'csrf' => false, 'rate_limit' => null]],
    'POST /profile'           => ['App\User\Action\ProfileAction',       'handlePost', ['auth' => true,  'admin' => false, 'csrf' => true,  'rate_limit' => 'profile_edit']],
    'POST /logout'            => ['App\Auth\Action\LogoutAction',        'handlePost', ['auth' => true,  'admin' => false, 'csrf' => true,  'rate_limit' => null]],
    'GET /settings'           => ['App\User\Action\SettingsAction',      'handle',     ['auth' => true,  'admin' => false, 'csrf' => false, 'rate_limit' => null]],
    'POST /settings'          => ['App\User\Action\SettingsAction',      'handlePost', ['auth' => true,  'admin' => false, 'csrf' => true,  'rate_limit' => 'profile_edit']],
    'GET /my-tickets'         => ['App\Ticket\Action\MyTicketsAction',   'handle',     ['auth' => true,  'admin' => false, 'csrf' => false, 'rate_limit' => null]],
    'GET /my-listings'        => ['App\Listing\Action\MyListingsAction', 'handle',     ['auth' => true,  'admin' => false, 'csrf' => false, 'rate_limit' => null]],
    'GET /listings/create'    => ['App\Listing\Action\CreateListingAction', 'handle',     ['auth' => true,  'admin' => false, 'csrf' => false, 'rate_limit' => null]],
    'POST /listings/create'   => ['App\Listing\Action\CreateListingAction', 'handlePost', ['auth' => true,  'admin' => false, 'csrf' => true,  'rate_limit' => 'listing_create']],
    'GET /listings/{id}/edit'  => ['App\Listing\Action\EditListingAction',  'handle',     ['auth' => true,  'admin' => false, 'csrf' => false, 'rate_limit' => null]],
    'POST /listings/{id}/edit' => ['App\Listing\Action\EditListingAction',  'handlePost', ['auth' => true,  'admin' => false, 'csrf' => true,  'rate_limit' => null]],
    'POST /listings/{id}/delete' => ['App\Listing\Action\DeleteListingAction', 'handle',  ['auth' => true,  'admin' => false, 'csrf' => true,  'rate_limit' => null]],
    'POST /listings/{id}/relist' => ['App\Listing\Action\RelistListingAction', 'handle',  ['auth' => true,  'admin' => false, 'csrf' => true,  'rate_limit' => null]],
    'POST /listings/{id}/submit' => ['App\Listing\Action\SubmitDraftAction', 'handle',   ['auth' => true,  'admin' => false, 'csrf' => true,  'rate_limit' => null]],
    'POST /admin/cron/ticket-expiry' => ['App\Listing\Action\ListingAutoApproveAction', 'handle', ['auth' => true, 'admin' => true, 'csrf' => true, 'rate_limit' => 'admin_cron']],
    'GET /sales'              => ['App\Ticket\Action\SalesAction',       'handle',     ['auth' => true,  'admin' => false, 'csrf' => false, 'rate_limit' => null]],
    'GET /purchases'          => ['App\Ticket\Action\PurchasesAction',   'handle',     ['auth' => true,  'admin' => false, 'csrf' => false, 'rate_limit' => null]],
    'GET /img/{listing_id}/{size}' => ['App\Support\Action\ImageProxyAction', 'handle', ['auth' => false, 'admin' => false, 'csrf' => false, 'rate_limit' => null]],
];
