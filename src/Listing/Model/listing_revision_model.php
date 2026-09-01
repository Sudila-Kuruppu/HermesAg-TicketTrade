<?php
/**
 * TicketTrade — Listing\Model\listing_revision_model
 *
 * Data access for listing_revisions rows. Used by the Service to
 * capture pre-edit snapshots (D-09) and to soft-revert when admin
 * rejects an edit to an active listing.
 */

declare(strict_types=1);

namespace App\Listing\Model;

use App\Support\Db;

class listing_revision_model
{
    public static function insert(int $listingId, string $snapshotJson, int $createdBy): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO listing_revisions (listing_id, snapshot_json, created_by, created_at) '
            . 'VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$listingId, $snapshotJson, $createdBy]);
        return (int) Db::pdo()->lastInsertId();
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public static function findByListingId(int $listingId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM listing_revisions WHERE listing_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$listingId]);
        return $stmt->fetchAll();
    }
}
