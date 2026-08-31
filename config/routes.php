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
    'GET /sales'              => ['App\Ticket\Action\SalesAction',       'handle',     ['auth' => true,  'admin' => false, 'csrf' => false, 'rate_limit' => null]],
    'GET /purchases'          => ['App\Ticket\Action\PurchasesAction',   'handle',     ['auth' => true,  'admin' => false, 'csrf' => false, 'rate_limit' => null]],
];
