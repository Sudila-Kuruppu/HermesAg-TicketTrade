<?php
/**
 * TicketTrade — Application Bootstrap
 *
 * Loads composer autoload, sets the timezone, configures error reporting,
 * and prepares the Support namespace. Session start, auth guard, CSRF
 * check, and rate limit land in Phase 2 (per AD-13).
 *
 * This file is the entry point for every HTTP request via the front
 * controllers in public/.
 */

declare(strict_types=1);

// App root
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Composer autoload (when present)
$autoload = APP_ROOT . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

// Timezone: Asia/Colombo per AD-11 (NSBM is in Sri Lanka)
date_default_timezone_set('Asia/Colombo');

// Error reporting: full in dev, suppressed display in production
error_reporting(E_ALL);
ini_set('display_errors', getenv('APP_ENV') === 'production' ? '0' : '1');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/data/php-error.log');

// Phase 9 will define Support\ResponseHeaders::boot() with the real set.
// Phase 1 declares the class as a no-op so front controllers can reference
// the integration point without requiring Phase 9 to be live.
if (!class_exists('App\\Support\\ResponseHeaders', false)) {
    eval('namespace App\\Support; class ResponseHeaders { public static function boot(): void {} }');
}

// UTF-8 default for mb_* functions
mb_internal_encoding('UTF-8');
