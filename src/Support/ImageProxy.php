<?php

/**
 * TicketTrade — Support\ImageProxy
 *
 * Per AD-14 + CONTEXT D-14: the ONLY path to read a stored listing
 * image. Direct file-system URLs are blocked at the webroot layer
 * (public/uploads/.htaccess) and at the proxy itself.
 *
 * Auth model:
 *   - thumb (200px) and medium (600px) — public, rate-limited at
 *     60/min/IP via Support\RateLimit ('img_thumb').
 *   - full (1200px) — requires session AND one of (seller, ticket
 *     holder, admin); missing auth returns 404, NOT 403 (so the
 *     resource's existence is not leaked).
 *
 * Rate-limit semantics:
 *   - thumb + medium: hit 'img_thumb' keyed by IP.
 *   - full: hit 'img_full' keyed by user_id (falls back to IP).
 *   - On 429: emit http_response_code(429), Retry-After header,
 *     Content-Type text/plain, body "Rate limit exceeded".
 *
 * Output:
 *   - Headers: Content-Type: image/webp,
 *              Cache-Control: public, max-age=86400,
 *              Content-Length: <size>.
 *   - Body: the WebP bytes via readfile().
 *
 * The companion Action (Support\Action\ImageProxyAction) is a thin
 * wrapper that calls ImageProxy::serve(). The Action sets the right
 * Router params.
 */

declare(strict_types=1);

namespace App\Support;

class ImageProxy
{
    public const SIZES = ['thumb', 'medium', 'full'];

    /**
     * Serve the requested image.
     *
     * @param int    $listingId The listing the image row belongs to.
     * @param string $size      One of thumb|medium|full.
     */
    public static function serve(int $listingId, string $size): void
    {
        // Defensive: only accept known sizes.
        if (!in_array($size, self::SIZES, true)) {
            http_response_code(404);
            return;
        }

        // Rate limit BEFORE the DB/file read.
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $limitName = ($size === 'full') ? 'img_full' : 'img_thumb';
        $limitKey = ($size === 'full' && $userId > 0) ? (string) $userId : $ip;
        $rl = RateLimit::hit($limitName, $limitKey);
        if (!$rl['allowed']) {
            self::emit429((int) ($rl['retry_after'] ?? 0));
            return;
        }

        // Look up the listing_images row.
        $row = self::findImageRow($listingId, $size);
        if ($row === null) {
            http_response_code(404);
            return;
        }

        // Auth check for full size.
        if ($size === 'full' && !self::fullSizeAuthorized($listingId, $userId)) {
            // Return 404, NOT 403 (AD-14 + CONTEXT D-14).
            http_response_code(404);
            return;
        }

        // Read the file from the storage root.
        $cfg = require APP_ROOT . '/config/uploads.php';
        $root = rtrim((string) $cfg['storage_root'], '/');
        $sha = (string) $row['sha256'];
        $path = sprintf('%s/%s_%s.webp', $root, $sha, $size);
        if (!is_file($path)) {
            http_response_code(404);
            return;
        }
        $bytes = filesize($path);
        if ($bytes === false || $bytes <= 0) {
            http_response_code(404);
            return;
        }

        header('Content-Type: image/webp');
        header('Cache-Control: public, max-age=86400');
        header('Content-Length: ' . (string) $bytes);
        readfile($path);
    }

    /**
     * Look up the listing_images row for (listing_id, size).
     * Returns null if the table or row is missing.
     */
    private static function findImageRow(int $listingId, string $size): ?array
    {
        try {
            $stmt = Db::pdo()->prepare(
                'SELECT id, listing_id, sha256, size, is_primary, sort_order '
                . 'FROM listing_images WHERE listing_id = ? AND size = ? LIMIT 1'
            );
            $stmt->execute([$listingId, $size]);
            $r = $stmt->fetch();
            return $r === false ? null : $r;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Authorize a full-size read.
     *
     * Allowed when:
     *   - user_id matches listings.seller_id for the listing
     *   - OR user has an active ticket (status='active' OR 'redeemed')
     *     for this listing
     *   - OR user is admin
     *   - OR user_id is 0 (guest) AND size != 'full' — but for full,
     *     a guest must be denied.
     *
     * Returns false on any DB error (defensive fail-closed).
     */
    private static function fullSizeAuthorized(int $listingId, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        try {
            // Check seller + admin in a single SELECT against listings JOIN users.
            $stmt = Db::pdo()->prepare(
                'SELECT u.is_admin '
                . 'FROM listings l JOIN users u ON u.user_id = l.seller_id '
                . 'WHERE l.id = ? LIMIT 1'
            );
            $stmt->execute([$listingId]);
            $r = $stmt->fetch();
            if ($r === false) {
                return false;
            }
            $isAdmin = (bool) ($r['is_admin'] ?? false);

            // Check seller.
            $stmt = Db::pdo()->prepare('SELECT seller_id FROM listings WHERE id = ? LIMIT 1');
            $stmt->execute([$listingId]);
            $l = $stmt->fetch();
            if ($l !== false && (int) $l['seller_id'] === $userId) {
                return true;
            }

            // Check admin.
            if ($isAdmin) {
                return true;
            }

            // Check ticket holder (active or redeemed).
            $stmt = Db::pdo()->prepare(
                'SELECT COUNT(*) FROM tickets WHERE listing_id = ? AND buyer_id = ? '
                . "AND status IN ('active','redeemed') LIMIT 1"
            );
            $stmt->execute([$listingId, $userId]);
            $count = (int) $stmt->fetchColumn();
            return $count > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Emit a 429 with the standard body.
     */
    private static function emit429(int $retryAfter): void
    {
        if ($retryAfter < 0) {
            $retryAfter = 0;
        }
        if ($retryAfter > 0) {
            header('Retry-After: ' . (string) $retryAfter);
        }
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(429);
        echo 'Rate limit exceeded';
    }
}
