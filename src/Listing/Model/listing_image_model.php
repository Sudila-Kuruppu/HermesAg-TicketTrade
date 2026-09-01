<?php

/**
 * TicketTrade — Listing\Model\listing_image_model
 *
 * Data access for listing_images rows. The sole writer of listing_images
 * is App\Listing\Service\listing_service (AD-1); this Model exposes
 * raw CRUD helpers for the Service.
 */

declare(strict_types=1);

namespace App\Listing\Model;

use App\Support\Db;
use PDO;

class listing_image_model
{
    /**
     * Insert one image row. Returns the new id.
     */
    public static function insert(int $listingId, string $sha256, string $size, bool $isPrimary, int $sortOrder): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO listing_images (listing_id, sha256, size, is_primary, sort_order, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $listingId,
            $sha256,
            $size,
            $isPrimary ? 1 : 0,
            $sortOrder,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    /**
     * Fetch all images for a listing, ordered by (sort_order, id).
     *
     * @return array<int, array<string,mixed>>
     */
    public static function findByListingId(int $listingId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id'
        );
        $stmt->execute([$listingId]);
        return $stmt->fetchAll();
    }

    public static function findOne(int $listingId, string $size): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM listing_images WHERE listing_id = ? AND size = ? LIMIT 1'
        );
        $stmt->execute([$listingId, $size]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    public static function deleteByListingId(int $listingId): int
    {
        $stmt = Db::pdo()->prepare('DELETE FROM listing_images WHERE listing_id = ?');
        $stmt->execute([$listingId]);
        return $stmt->rowCount();
    }

    /**
     * Bulk-update sort_order for the given list of image ids in their
     * submitted order.
     *
     * @param array<int, int> $idsInNewOrder
     */
    public static function updateSortOrder(array $idsInNewOrder): int
    {
        $pdo = Db::pdo();
        $count = 0;
        $stmt = $pdo->prepare('UPDATE listing_images SET sort_order = ? WHERE id = ?');
        foreach ($idsInNewOrder as $idx => $id) {
            $stmt->execute([$idx, (int) $id]);
            $count += $stmt->rowCount();
        }
        return $count;
    }

    public static function countByListingId(int $listingId): int
    {
        $stmt = Db::pdo()->prepare('SELECT COUNT(*) FROM listing_images WHERE listing_id = ?');
        $stmt->execute([$listingId]);
        return (int) $stmt->fetchColumn();
    }
}
