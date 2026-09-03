<?php
/**
 * TicketTrade — Bounded Contexts
 *
 * Defines the contexts (named groupings of Support, Service, Model, View,
 * Controller code) that the Support\Router validates route handlers against.
 *
 * Per AD-2: contexts are Auth, Listing, Ticket, Points, User, Category,
 * Report, Admin, Cron.
 */

declare(strict_types=1);

return [
    'Auth',
    'Listing',
    'Ticket',
    'Points',
    'User',
    'Category',
    'Report',
    'Admin',
    'Cron',
    'Review',
];
