<?php
/**
 * TicketTrade — Upload Storage Configuration
 *
 * Per D-13: dev uses public/uploads/listings/ (under webroot, but
 *           mediated by Support\ImageProxy so the public path is never
 *           directly accessible).
 *           Prod uses /var/www/uploads/listings/ (outside webroot).
 *
 * The shape is fixed by AD-14. Support\ImageUpload reads this once
 * at boot. The APP_ENV env var swaps dev/prod; default is dev.
 * UPLOAD_STORAGE_ROOT (env var) overrides storage_root for tests +
 * custom deployments.
 *
 * thumb/medium/full px are the canonical 3 sizes (D-13). webp_quality=80
 * matches the NFR-PER-003 contract. max_files=8 is the per-listing cap
 * (D-09). max_file_bytes=5MB and max_dim_px=4000px match AD-14.
 */

declare(strict_types=1);

$isProd = getenv('APP_ENV') === 'production';
$envRoot = getenv('UPLOAD_STORAGE_ROOT');

return [
    'storage_root'   => $envRoot !== false && $envRoot !== ''
        ? $envRoot
        : ($isProd ? '/var/www/uploads/listings' : __DIR__ . '/../public/uploads/listings'),
    'thumb_px'       => 200,
    'medium_px'      => 600,
    'full_px'        => 1200,
    'webp_quality'   => 80,
    'max_files'      => 8,
    'max_file_bytes' => 5_242_880, // 5 MiB
    'max_dim_px'     => 4000,
];
