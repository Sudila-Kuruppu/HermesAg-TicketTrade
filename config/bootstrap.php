<?php
/**
 * TicketTrade — Application Bootstrap
 *
 * Sets timezone, error reporting, autoload, session cookie params,
 * starts the session (cli-skipped), boots the Support\ResponseHeaders,
 * Support\Auth, and Support\Csrf services. The eval stub for
 * ResponseHeaders from Phase 1 is REPLACED by PSR-4 autoload.
 *
 * Per AD-13: ResponseHeaders::boot() MUST run before any body output
 * (and therefore before Auth::boot() reads the session cookie).
 *
 * Per D-21: cookie_secure=1 only when APP_ENV=production.
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

// Error reporting: full in dev, suppressed display in production.
// Safe-by-default: production hardening is opt-in. If APP_ENV is unset
// (a common config-drift case), we assume production and require an
// explicit APP_ENV=development to enable error display.
$isDev = getenv('APP_ENV') !== false && getenv('APP_ENV') === 'development';
error_reporting(E_ALL);
ini_set('display_errors', $isDev ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/data/php-error.log');

// UTF-8 default for mb_* functions
mb_internal_encoding('UTF-8');

// Session cookie params + strict session config (D-21 + AD-13).
// MUST run BEFORE session_start() so the cookie attributes are
// consistent with the security baseline. Sessions are skipped in
// CLI mode so the migrations runner does not start a session.
$secure = !$isDev;
session_set_cookie_params([
    'lifetime' => 7 * 24 * 60 * 60,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Strict',
]);
ini_set('session.use_strict_mode', '1');
ini_set('session.sid_length', '48');
ini_set('session.sid_bits_per_char', '5');
ini_set('session.gc_maxlifetime', '604800');

if (session_status() !== PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
    session_start();
}

// Security headers (CSP + X-Content-Type-Options + X-Frame-Options +
// Referrer-Policy). MUST be first so the headers attach even on 302.
\App\Support\ResponseHeaders::boot();

// Session guard: populates $GLOBALS['current_user'] from the session
// cookie + sessions table. 5-min idempotency window on last_seen.
\App\Support\Auth::boot();

// CSRF: 400 + E_CSRF envelope on POST/PUT/PATCH/DELETE without a
// matching token.
\App\Support\Csrf::verify();

// Flash-toast carry: a server-set flash message written to
// $_SESSION['_tt_flash_toast'] before a 302 redirect is consumed on
// the next request (D-02 + D-07). The flash is read once and unset.
if (PHP_SAPI !== 'cli' && !empty($_SESSION['_tt_flash_toast'])) {
    $GLOBALS['_tt_flash_toast'] = $_SESSION['_tt_flash_toast'];
    unset($_SESSION['_tt_flash_toast']);
}
