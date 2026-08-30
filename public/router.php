<?php
/**
 * TicketTrade — Dev Server Router
 *
 * Used by `php -S 127.0.0.1:18001 -t public public/router.php`.
 * Maps URL paths to the correct front controller and serves static
 * assets directly with the appropriate MIME type.
 *
 * Production uses Apache .htaccess (see public/.htaccess).
 */

// Helper: serve a static file with the correct MIME type
function tt_serve_static(string $absPath): bool {
    if (!is_file($absPath)) {
        return false;
    }
    $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'css'   => 'text/css; charset=utf-8',
        'js'    => 'application/javascript; charset=utf-8',
        'html'  => 'text/html; charset=utf-8',
        'json'  => 'application/json; charset=utf-8',
        'svg'   => 'image/svg+xml',
        'png'   => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'gif'   => 'image/gif',
        'ico'   => 'image/x-icon',
        'webp'  => 'image/webp',
        'woff', 'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'map'   => 'application/json',
        default => 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=0');
    readfile($absPath);
    return true;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$publicDir = __DIR__;

// 1. Map /admin/* to admin/index.php
if (str_starts_with($path, '/admin/') || $path === '/admin') {
    require $publicDir . '/admin/index.php';
    return true;
}

// 2. Serve static files (assets, mockups, favicon)
if ($path !== '/' && $path !== '/index.php') {
    $candidate = $publicDir . $path;
    // Resolve and prevent path traversal above public/
    $realPublic = realpath($publicDir);
    $realCandidate = realpath($candidate);
    if ($realCandidate !== false && $realPublic !== false
        && str_starts_with($realCandidate, $realPublic)
        && is_file($realCandidate)
    ) {
        return tt_serve_static($realCandidate);
    }
}

// 3. Default: route through student front controller
require $publicDir . '/index.php';
return true;
