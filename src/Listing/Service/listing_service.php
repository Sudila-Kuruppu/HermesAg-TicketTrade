<?php

/**
 * TicketTrade — Listing\Service\listing_service
 *
 * Sole-writer (AD-1) for `listings`, `listing_images`, `listing_revisions`.
 * Every public method returns the AD-16 failure envelope:
 *
 *   ['ok' => bool, 'data' => mixed, 'error' => ['code'=>string,'message'=>string,'fields'=>array|null]|null]
 *
 * Field-level validation lives in validateListingData(). Rate limit on
 * createDraft/saveDraft is enforced via Support\RateLimit ('listing_create',
 * 20/hr/user per CONTEXT D-09). Cross-context writes (e.g. points for
 * approved listings) are intentionally NOT in Phase 3 — Phase 6 wires
 * the points service.
 */

declare(strict_types=1);

namespace App\Listing\Service;

use App\Category\Service\category_service;
use App\Listing\Model\listing_image_model;
use App\Listing\Model\listing_model;
use App\Listing\Model\listing_revision_model;
use App\Support\Db;
use App\Support\Error;
use App\Support\ImageUpload;
use App\Support\Network;
use App\Support\RateLimit;
use PDO;

class listing_service
{
    /** Max title length per LST-01 (CONTEXT specific). */
    public const MAX_TITLE = 80;
    public const MAX_DESCRIPTION = 2000;
    public const MAX_AVAILABILITY = 500;
    public const MIN_QUANTITY = 1;
    public const MAX_QUANTITY = 999;
    public const MIN_PRICE_CENTS = 1;
    public const MAX_PRICE_CENTS = 100_000_00; // LKR 100,000

    /**
     * Create a new draft listing. Enforces the listing_create rate cap.
     */
    public static function createDraft(int $sellerId, array $data): array
    {
        $rl = self::enforceRateLimit($sellerId);
        if ($rl !== null) {
            return $rl;
        }

        $v = self::validateListingData($data);
        if ($v['ok'] === false) {
            return $v;
        }
        $clean = $v['data'];
        $clean['seller_id'] = $sellerId;
        $clean['status'] = 'draft';
        $clean['quantity_sold'] = 0;

        try {
            $id = listing_model::insert($clean);
        } catch (\Throwable $e) {
            return Error::envelope(false, null, [
                'code' => 'E_VALIDATION',
                'message' => 'Could not create listing.',
            ]);
        }

        $row = listing_model::findById($id);
        return Error::envelope(true, $row, null);
    }

    /**
     * Save edits to a draft / pending / rejected listing.
     */
    public static function saveDraft(int $listingId, int $sellerId, array $data): array
    {
        // No rate-limit on edits: only the createDraft / submitDraft
        // flows cap at 20/hr/user. Edits are bounded by the listing's
        // status state-machine.

        $load = self::loadForEdit($listingId, $sellerId);
        if ($load['ok'] === false) {
            return $load;
        }
        $beforeRow = $load['data'] ?? listing_model::findById($listingId);

        $v = self::validateListingData($data);
        if ($v['ok'] === false) {
            return $v;
        }
        $clean = $v['data'];

        try {
            $pdo = Db::pdo();
            $pdo->beginTransaction();

            // D-09: if the current status is `active`, capture a pre-edit
            // snapshot to listing_revisions and set review_flag=1 BEFORE
            // applying the update. This lets the admin soft-revert if
            // they reject the edit later.
            $flagged = false;
            if ($beforeRow !== null && (string) ($beforeRow['status'] ?? '') === 'active') {
                self::appendRevisionSnapshot(
                    $listingId,
                    $sellerId,
                    is_array($beforeRow) ? $beforeRow : []
                );
                listing_model::setReviewFlag($listingId, true);
                $flagged = true;
            }

            listing_model::update($listingId, $clean);
            $pdo->commit();
        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Error::envelope(false, null, [
                'code' => 'E_VALIDATION',
                'message' => 'Could not save listing.',
            ]);
        }

        $row = listing_model::findById($listingId);
        return Error::envelope(true, [
            'listing' => $row,
            'review_flagged' => $flagged,
        ], null);
    }

    /**
     * Move a draft to pending.
     */
    public static function submitDraft(int $listingId, int $sellerId): array
    {
        $load = self::loadForEdit($listingId, $sellerId);
        if ($load['ok'] === false) {
            return $load;
        }
        $row = $load['data'];

        if ($row['status'] !== 'draft' && $row['status'] !== 'rejected') {
            return Error::envelope(false, null, [
                'code' => 'E_LISTING_FORBIDDEN',
                'message' => 'Only drafts can be submitted.',
            ]);
        }

        // Approved-content fast-track: if relist source has approved_at,
        // skip pending and go straight to active.
        if (!empty($row['source_listing_id']) && !empty($row['approved_at'])) {
            listing_model::setStatus($listingId, 'active');
        } else {
            listing_model::setStatus($listingId, 'pending');
        }

        $row = listing_model::findById($listingId);
        return Error::envelope(true, $row, null);
    }

    /**
     * Copy a sold listing into a fresh draft so the seller can relist
     * with adjusted quantity. Copies title/description/price/category/type/
     * condition/service fields; resets quantity_sold = 0; sets
     * source_listing_id for the approved-content fast-track.
     */
    public static function relist(int $soldListingId, int $sellerId): array
    {
        $source = listing_model::findById($soldListingId);
        if ($source === null) {
            return Error::envelope(false, null, [
                'code' => 'E_LISTING_NOT_FOUND',
                'message' => 'Source listing not found.',
            ]);
        }
        if ((int) $source['seller_id'] !== $sellerId) {
            return Error::envelope(false, null, [
                'code' => 'E_LISTING_FORBIDDEN',
                'message' => 'You can only relist your own listings.',
            ]);
        }
        if ($source['status'] !== 'sold') {
            return Error::envelope(false, null, [
                'code' => 'E_LISTING_FORBIDDEN',
                'message' => 'Only sold listings can be relisted.',
            ]);
        }

        $newData = [
            'seller_id' => $sellerId,
            'category_id' => (int) $source['category_id'],
            'title' => (string) $source['title'],
            'description' => (string) $source['description'],
            'price_cents' => (int) $source['price_cents'],
            'type' => (string) $source['type'],
            'condition' => $source['condition'] ?? null,
            'duration_minutes' => $source['duration_minutes'] ?? null,
            'delivery_method' => $source['delivery_method'] ?? null,
            'availability' => $source['availability'] ?? null,
            'quantity' => (int) $source['quantity'],
            'status' => 'draft',
            'source_listing_id' => $soldListingId,
        ];

        $id = listing_model::insert($newData);
        $row = listing_model::findById($id);
        return Error::envelope(true, $row, null);
    }

    /**
     * Mark a listing as needing re-review (used when seller edits an
     // active listing).
     */
    public static function setReviewFlag(int $listingId, int $sellerId): array
    {
        $load = self::loadForEdit($listingId, $sellerId);
        if ($load['ok'] === false) {
            return $load;
        }
        listing_model::setReviewFlag($listingId, true);
        return Error::envelope(true, listing_model::findById($listingId), null);
    }

    /**
     * Process uploaded files through the 4-layer pipeline and insert the
     * listing_images rows. Enforces the 8-file cap with per-file errors.
     */
    public static function uploadImages(int $listingId, int $sellerId, array $files): array
    {
        $load = self::loadForEdit($listingId, $sellerId);
        if ($load['ok'] === false) {
            return $load;
        }

        $result = ImageUpload::process($listingId, $files);
        if ($result['ok'] === false) {
            return $result;
        }

        // Insert listing_images rows for the successful uploads.
        $isFirst = listing_image_model::countByListingId($listingId) === 0;
        // WR-01: prepared statement instead of string-concat (the
        // (int) cast neutralized injection but the anti-pattern was
        // flagged by review).
        $sortStmt = Db::pdo()->prepare('SELECT COALESCE(MAX(sort_order),0) FROM listing_images WHERE listing_id = ?');
        $sortStmt->execute([$listingId]);
        $baseSort = (int) $sortStmt->fetchColumn();
        $inserted = [];
        $firstRow = true;
        foreach ($result['data']['uploaded'] as $row) {
            $baseSort++;
            $isPrimary = ($isFirst && $firstRow) ? 1 : 0;
            try {
                foreach (['thumb', 'medium', 'full'] as $sizeName) {
                    listing_image_model::insert(
                        $listingId,
                        $row['sha256'],
                        $sizeName,
                        $isPrimary,
                        $baseSort
                    );
                }
                $inserted[] = $row;
                $firstRow = false;
            } catch (\Throwable $e) {
                // Per-row failure is recorded; loop continues.
                $result['data']['errors'][] = [
                    'index' => $row['index'] ?? -1,
                    'name' => $row['name'] ?? '',
                    'code' => 'E_IMAGE_INVALID',
                    'message' => 'Failed to register image in database.',
                ];
            }
        }

        return Error::envelope(true, [
            'uploaded' => $inserted,
            'errors' => $result['data']['errors'],
        ], null);
    }

    /**
     * Field-level validation. Returns AD-16 envelope.
     */
    public static function validateListingData(array $data): array
    {
        $errors = [];
        $clean = [];

        $title = isset($data['title']) ? trim((string) $data['title']) : '';
        if ($title === '') {
            $errors['title'] = 'Title is required.';
        } elseif (mb_strlen($title) > self::MAX_TITLE) {
            $errors['title'] = 'Title must be ' . self::MAX_TITLE . ' characters or fewer.';
        } else {
            $clean['title'] = $title;
        }

        $desc = isset($data['description']) ? trim((string) $data['description']) : '';
        if ($desc === '') {
            $errors['description'] = 'Description is required.';
        } elseif (mb_strlen($desc) > self::MAX_DESCRIPTION) {
            $errors['description'] = 'Description must be ' . self::MAX_DESCRIPTION . ' characters or fewer.';
        } else {
            $clean['description'] = $desc;
        }

        $price = isset($data['price_cents']) ? (int) $data['price_cents'] : 0;
        if ($price < self::MIN_PRICE_CENTS) {
            $errors['price_cents'] = 'Price must be greater than zero.';
        } elseif ($price > self::MAX_PRICE_CENTS) {
            $errors['price_cents'] = 'Price exceeds the maximum.';
        } else {
            $clean['price_cents'] = $price;
        }

        $type = (string) ($data['type'] ?? '');
        if (!in_array($type, ['product', 'service'], true)) {
            $errors['type'] = 'Type must be product or service.';
        } else {
            $clean['type'] = $type;
        }

        // category_id must exist and be active.
        $categoryId = (int) ($data['category_id'] ?? 0);
        if ($categoryId <= 0) {
            $errors['category_id'] = 'Category is required.';
        } else {
            $catCheck = category_service::getById($categoryId);
            if ($catCheck['ok'] === false) {
                $errors['category_id'] = 'Category not found.';
            } else {
                $clean['category_id'] = $categoryId;
            }
        }

        // quantity
        $quantity = (int) ($data['quantity'] ?? 0);
        if ($quantity < self::MIN_QUANTITY || $quantity > self::MAX_QUANTITY) {
            $errors['quantity'] = 'Quantity must be between ' . self::MIN_QUANTITY . ' and ' . self::MAX_QUANTITY . '.';
        } else {
            $clean['quantity'] = $quantity;
        }

        // Type-specific optional fields.
        if ($type === 'product') {
            $cond = (string) ($data['condition'] ?? '');
            if ($cond === '') {
                $clean['condition'] = null;
            } elseif (in_array($cond, ['new', 'like_new', 'good', 'fair'], true)) {
                $clean['condition'] = $cond;
            } else {
                $errors['condition'] = 'Condition is invalid.';
            }
        } elseif ($type === 'service') {
            $dur = (int) ($data['duration_minutes'] ?? 0);
            if ($dur > 0) {
                if ($dur < 1 || $dur > 600) {
                    $errors['duration_minutes'] = 'Duration must be between 1 and 600 minutes.';
                } else {
                    $clean['duration_minutes'] = $dur;
                }
            }
            $dm = (string) ($data['delivery_method'] ?? '');
            if ($dm !== '' && !in_array($dm, ['in_person', 'online', 'hybrid'], true)) {
                $errors['delivery_method'] = 'Delivery method is invalid.';
            } elseif ($dm !== '') {
                $clean['delivery_method'] = $dm;
            }
            $av = (string) ($data['availability'] ?? '');
            if ($av !== '') {
                if (mb_strlen($av) > self::MAX_AVAILABILITY) {
                    $errors['availability'] = 'Availability must be ' . self::MAX_AVAILABILITY . ' characters or fewer.';
                } else {
                    $clean['availability'] = $av;
                }
            }
        }

        if (!empty($errors)) {
            return Error::envelope(false, null, [
                'code' => 'E_VALIDATION',
                'message' => 'Validation failed.',
                'fields' => $errors,
            ]);
        }
        return Error::envelope(true, $clean, null);
    }

    /**
     * Read a listing with its images + category name.
     */
    public static function getWithImages(int $id): ?array
    {
        $row = listing_model::findById($id);
        if ($row === null) {
            return null;
        }
        $row['images'] = listing_image_model::findByListingId($id);
        $cat = category_service::getById((int) $row['category_id']);
        $row['category'] = $cat['ok'] ? $cat['data'] : null;
        return $row;
    }

    /**
     * Search the board. Returns AD-16 envelope with rows + pagination meta.
     */
    public static function getSearchResults(?string $q, ?int $categoryId, int $page = 1): array
    {
        $page = max(1, $page);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        try {
            $rows = listing_model::search($q, $categoryId, $offset, $limit);
            $total = listing_model::getSearchCount($q, $categoryId);
        } catch (\Throwable $e) {
            return Error::envelope(false, null, [
                'code' => 'E_NOT_FOUND',
                'message' => 'Search failed.',
            ]);
        }
        return Error::envelope(true, [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $limit)),
            'limit' => $limit,
        ], null);
    }

    /**
     * Fetch a seller's own listings, optionally filtered by status.
     */
    public static function getSellerListings(int $sellerId, ?string $status, int $page = 1): array
    {
        $page = max(1, $page);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $rows = listing_model::findBySellerId($sellerId, $status, $limit, $offset);
        return Error::envelope(true, [
            'rows' => $rows,
            'page' => $page,
        ], null);
    }

    /**
     * Load a listing for the owner; returns the AD-16 envelope with
     * `data` = the row OR an error envelope (E_LISTING_NOT_FOUND /
     * E_LISTING_FORBIDDEN). Public so Actions can render 404 cleanly.
     */
    public static function loadForOwner(int $listingId, int $sellerId): array
    {
        return self::loadForEdit($listingId, $sellerId);
    }

    /**
     * Internal helper. Loads a listing for edit by the owner; returns the
     * AD-16 failure envelope on error.
     */
    private static function loadForEdit(int $listingId, int $sellerId): array
    {
        $row = listing_model::findById($listingId);
        if ($row === null) {
            return Error::envelope(false, null, [
                'code' => 'E_LISTING_NOT_FOUND',
                'message' => 'Listing not found.',
            ]);
        }
        if ((int) $row['seller_id'] !== $sellerId) {
            return Error::envelope(false, null, [
                'code' => 'E_LISTING_FORBIDDEN',
                'message' => 'You do not have permission to modify this listing.',
            ]);
        }
        $allowed = ['draft', 'pending', 'active', 'rejected'];
        if (!in_array($row['status'], $allowed, true)) {
            return Error::envelope(false, null, [
                'code' => 'E_LISTING_FORBIDDEN',
                'message' => 'This listing cannot be edited in its current state.',
            ]);
        }
        return Error::envelope(true, $row, null);
    }

    /**
     * Run the auto-approve sweep (Plan 03-02 cron Action). Sets every
     * `pending` listing older than 24 hours to `active`, stamps
     * `approved_at = NOW()`, leaves `approved_by = NULL` (auto-approved).
     *
     * Returns the AD-16 envelope with `data.processed = $pdo->rowCount()`.
     * Idempotent: a sweep with no eligible rows returns processed=0.
     *
     * @return array{ok:bool,data:array,error:?array}
     */
    public static function runAutoApproveSweep(int $actorUserId): array
    {
        try {
            $pdo = Db::pdo();
            $sql = 'UPDATE listings SET status = \'active\', '
                . 'approved_at = NOW(), approved_by = NULL, '
                . 'updated_at = NOW() '
                . 'WHERE status = \'pending\' '
                . 'AND created_at <= NOW() - INTERVAL 24 HOUR';
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $processed = (int) $stmt->rowCount();

            self::writeCronLog(
                'listing.auto_approve',
                (int) $actorUserId,
                $processed,
                []
            );

            return Error::envelope(true, [
                'processed' => $processed,
                'errors' => [],
            ], null);
        } catch (\Throwable $e) {
            return Error::envelope(false, null, [
                'code' => 'E_INTERNAL',
                'message' => 'Auto-approve sweep failed.',
            ]);
        }
    }

    /**
     * Soft-delete a listing by flipping status to `removed`. The row
     * stays in the DB for audit; the seller dashboard hides it.
     *
     * Per D-14: caller must enforce ownership before calling.
     *
     * @return array{ok:bool,data:?int,error:?array}
     */
    public static function softDelete(int $listingId, int $sellerId): array
    {
        $row = listing_model::findById($listingId);
        if ($row === null) {
            return Error::envelope(false, null, [
                'code' => 'E_LISTING_NOT_FOUND',
                'message' => 'Listing not found.',
            ]);
        }
        if ((int) $row['seller_id'] !== $sellerId) {
            return Error::envelope(false, null, [
                'code' => 'E_LISTING_FORBIDDEN',
                'message' => 'You do not have permission to modify this listing.',
            ]);
        }
        if (!in_array($row['status'], ['active', 'rejected', 'sold'], true)) {
            return Error::envelope(false, null, [
                'code' => 'E_LISTING_FORBIDDEN',
                'message' => 'This listing cannot be removed in its current state.',
            ]);
        }
        try {
            listing_model::setStatus($listingId, 'removed');
        } catch (\Throwable $e) {
            return Error::envelope(false, null, [
                'code' => 'E_INTERNAL',
                'message' => 'Could not remove listing.',
            ]);
        }
        return Error::envelope(true, 1, null);
    }

    /**
     * Hard-delete a `draft` or `pending` listing (no audit trail needed
     * for never-published rows). Returns affected row count.
     *
     * @return array{ok:bool,data:?int,error:?array}
     */
    public static function hardDelete(int $listingId, int $sellerId): array
    {
        $row = listing_model::findById($listingId);
        if ($row === null) {
            return Error::envelope(false, null, [
                'code' => 'E_LISTING_NOT_FOUND',
                'message' => 'Listing not found.',
            ]);
        }
        if ((int) $row['seller_id'] !== $sellerId) {
            return Error::envelope(false, null, [
                'code' => 'E_LISTING_FORBIDDEN',
                'message' => 'You do not have permission to modify this listing.',
            ]);
        }
        if (!in_array($row['status'], ['draft', 'pending'], true)) {
            return Error::envelope(false, null, [
                'code' => 'E_LISTING_FORBIDDEN',
                'message' => 'Only drafts and pending listings can be hard-deleted.',
            ]);
        }
        try {
            $stmt = Db::pdo()->prepare('DELETE FROM listings WHERE id = ?');
            $stmt->execute([$listingId]);
            return Error::envelope(true, (int) $stmt->rowCount(), null);
        } catch (\Throwable $e) {
            return Error::envelope(false, null, [
                'code' => 'E_INTERNAL',
                'message' => 'Could not delete listing.',
            ]);
        }
    }

    /**
     * Internal helper. Append a structured run-log row to the cron_log
     * table (Plan 03-02 migration 012). Phase 9 will migrate this to
     * the hash-chained audit_log (AD-12).
     */
    private static function writeCronLog(string $jobName, int $actorUserId, int $processed, array $errors): void
    {
        try {
            $stmt = Db::pdo()->prepare(
                'INSERT INTO cron_log (job_name, run_at, processed_count, errors_json, actor_user_id, created_at) '
                . 'VALUES (?, NOW(), ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $jobName,
                $processed,
                json_encode($errors, JSON_UNESCAPED_UNICODE),
                $actorUserId > 0 ? $actorUserId : null,
            ]);
        } catch (\Throwable $e) {
            // Logging failures must not break the action.
            error_log('[cron_log] write failed: ' . $e->getMessage());
        }
    }

    /**
     * Internal: write the pre-edit snapshot to listing_revisions. The
     * snapshot is the full row JSON-encoded so a later admin "revert to
     * this version" can reconstruct the previous state (D-09).
     *
     * @param array<string,mixed> $beforeData The listing row BEFORE the update.
     */
    private static function appendRevisionSnapshot(int $listingId, int $by, array $beforeData): void
    {
        $json = json_encode($beforeData, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = '{}';
        }
        try {
            listing_revision_model::insert($listingId, $json, $by);
        } catch (\Throwable $e) {
            // A failed snapshot must not break the edit itself.
            error_log('[listing_revisions] snapshot failed: ' . $e->getMessage());
        }
    }

    /**
     * Apply the listing_create rate limit. Returns the failure envelope
     * if the limit is exceeded; returns null if the call is allowed.
     */
    private static function enforceRateLimit(int $userId): ?array
    {
        // CR-07: use Network::clientIp() to honor X-Forwarded-For only
        // when the request came from a trusted proxy (env-configured
        // via TT_TRUSTED_PROXIES). Falls back to REMOTE_ADDR otherwise
        // (no log spam, safe default). The userId is the bucket key —
        // 20/hr per user, regardless of which IP they came from.
        $ip = Network::clientIp();
        $rl = RateLimit::hit('listing_create', $ip, (string) $userId);
        if (!$rl['allowed']) {
            return Error::envelope(false, null, [
                'code' => 'E_RATE_LIMIT',
                'message' => 'Too many listings. Try again later.',
            ]);
        }
        return null;
    }
}
