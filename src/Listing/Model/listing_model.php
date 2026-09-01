<?php

/**
 * TicketTrade — Listing\Model\listing_model
 *
 * Raw PDO access for the listings table. Sole-writer for the table is
 * App\Listing\Service\listing_service (AD-1). This Model exposes
 * only data-access helpers.
 *
 * All methods use prepared statements via Db::pdo(). No string
 * concatenation in SQL. All times are Asia/Colombo per AD-11.
 */

declare(strict_types=1);

namespace App\Listing\Model;

use App\Support\Db;
use DateTime;
use DateTimeZone;
use PDO;

class listing_model
{
    /**
     * Insert a new listing row. Returns the new id.
     *
     * @param array<string,mixed> $data Must include seller_id, category_id,
     *                                  title, description, price_cents,
     *                                  type, quantity.
     */
    public static function insert(array $data): int
    {
        $now = (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $sql = 'INSERT INTO listings ('
            . 'seller_id, category_id, title, description, price_cents, type, '
            . '`condition`, duration_minutes, delivery_method, availability, '
            . 'quantity, quantity_sold, status, source_listing_id, created_at, updated_at'
            . ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute([
            (int) $data['seller_id'],
            (int) $data['category_id'],
            (string) $data['title'],
            (string) $data['description'],
            (int) $data['price_cents'],
            (string) $data['type'],
            $data['condition'] ?? null,
            isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null,
            $data['delivery_method'] ?? null,
            $data['availability'] ?? null,
            (int) ($data['quantity'] ?? 1),
            0,
            (string) ($data['status'] ?? 'draft'),
            isset($data['source_listing_id']) ? (int) $data['source_listing_id'] : null,
            $now,
            $now,
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    /**
     * Update the row's mutable columns. Only the whitelisted columns are
     * touched; the caller must validate input. Returns rows affected.
     *
     * @param array<string,mixed> $data
     */
    public static function update(int $id, array $data): int
    {
        $allowed = ['title', 'description', 'price_cents', 'category_id',
            '`condition`', 'duration_minutes', 'delivery_method', 'availability',
            'quantity', 'status', 'rejection_reason', 'source_listing_id'];
        $sets = [];
        $vals = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $sets[] = "$k = ?";
                $vals[] = $data[$k];
            }
        }
        if (empty($sets)) {
            return 0;
        }
        $now = (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $sets[] = 'updated_at = ?';
        $vals[] = $now;
        $vals[] = $id;
        $sql = 'UPDATE listings SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($vals);
        return $stmt->rowCount();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM listings WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    /**
     * Fetch listings owned by a seller, optionally filtered by status.
     *
     * @return array<int, array<string,mixed>>
     */
    public static function findBySellerId(int $sellerId, ?string $status, int $limit, int $offset = 0): array
    {
        if ($status !== null) {
            $stmt = Db::pdo()->prepare(
                'SELECT * FROM listings WHERE seller_id = ? AND status = ? '
                . 'ORDER BY created_at DESC LIMIT ? OFFSET ?'
            );
            $stmt->bindValue(1, $sellerId, PDO::PARAM_INT);
            $stmt->bindValue(2, $status, PDO::PARAM_STR);
            $stmt->bindValue(3, max(0, $limit), PDO::PARAM_INT);
            $stmt->bindValue(4, max(0, $offset), PDO::PARAM_INT);
        } else {
            $stmt = Db::pdo()->prepare(
                'SELECT * FROM listings WHERE seller_id = ? '
                . 'ORDER BY created_at DESC LIMIT ? OFFSET ?'
            );
            $stmt->bindValue(1, $sellerId, PDO::PARAM_INT);
            $stmt->bindValue(2, max(0, $limit), PDO::PARAM_INT);
            $stmt->bindValue(3, max(0, $offset), PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * FULLTEXT search. Returns active listings matched by title/description
     * optionally filtered by category. Joins category name and the primary
     * image SHA.
     *
     * @return array<int, array<string,mixed>>
     */
    public static function search(?string $q, ?int $categoryId, int $offset, int $limit): array
    {
        $select = 'SELECT l.*, c.name AS category_name, '
            . '(SELECT li.sha256 FROM listing_images li '
            . 'WHERE li.listing_id = l.id AND li.is_primary = 1 LIMIT 1) AS primary_sha256';
        $from = ' FROM listings l JOIN categories c ON c.id = l.category_id';
        $where = ' WHERE l.status = \'active\' AND c.is_active = 1';
        $params = [];

        if ($q !== null && trim($q) !== '') {
            // BOOLEAN MODE suffix wildcard for prefix match.
            $where .= ' AND MATCH(l.title, l.description) AGAINST(? IN BOOLEAN MODE)';
            $params[] = trim($q) . '*';
        }
        if ($categoryId !== null) {
            $where .= ' AND l.category_id = ?';
            $params[] = $categoryId;
        }

        $order = ' ORDER BY l.created_at DESC LIMIT ? OFFSET ?';

        $stmt = Db::pdo()->prepare($select . $from . $where . $order);
        $idx = 1;
        foreach ($params as $p) {
            $stmt->bindValue($idx++, $p, is_int($p) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue($idx++, max(0, $limit), PDO::PARAM_INT);
        $stmt->bindValue($idx++, max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Count the rows that match the same WHERE clause as search().
     */
    public static function getSearchCount(?string $q, ?int $categoryId): int
    {
        $from = ' FROM listings l JOIN categories c ON c.id = l.category_id';
        $where = ' WHERE l.status = \'active\' AND c.is_active = 1';
        $params = [];
        if ($q !== null && trim($q) !== '') {
            $where .= ' AND MATCH(l.title, l.description) AGAINST(? IN BOOLEAN MODE)';
            $params[] = trim($q) . '*';
        }
        if ($categoryId !== null) {
            $where .= ' AND l.category_id = ?';
            $params[] = $categoryId;
        }
        $stmt = Db::pdo()->prepare('SELECT COUNT(*)' . $from . $where);
        $idx = 1;
        foreach ($params as $p) {
            $stmt->bindValue($idx++, $p, is_int($p) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Update status (and optionally approval/rejection columns).
     * Returns rows affected.
     */
    public static function setStatus(int $id, string $status, ?string $rejectionReason = null, ?int $approvedBy = null): int
    {
        $now = (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
        $sql = 'UPDATE listings SET status = ?, rejection_reason = ?, '
            . 'approved_at = ?, approved_by = ?, updated_at = ? WHERE id = ?';
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute([
            $status,
            $rejectionReason,
            ($status === 'active') ? $now : null,
            $approvedBy,
            $now,
            $id,
        ]);
        return $stmt->rowCount();
    }

    /**
     * Increment quantity_sold atomically. Used by Phase 4 ticket creation;
     * here it is exposed so the Service can use it for state-machine
     * bookkeeping if needed.
     */
    public static function incrementSold(int $id, int $n): int
    {
        $stmt = Db::pdo()->prepare('UPDATE listings SET quantity_sold = quantity_sold + ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$n, $id]);
        return $stmt->rowCount();
    }

    /**
     * Decrement quantity_sold atomically. Bounded by 0 (never negative).
     */
    public static function decrementSold(int $id, int $n): int
    {
        $stmt = Db::pdo()->prepare('UPDATE listings SET quantity_sold = GREATEST(0, quantity_sold - ?), updated_at = NOW() WHERE id = ?');
        $stmt->execute([$n, $id]);
        return $stmt->rowCount();
    }

    /**
     * Toggle the review_flag on an active listing edit.
     */
    public static function setReviewFlag(int $id, bool $on): int
    {
        if ($on) {
            $sql = 'UPDATE listings SET review_flag = 1, review_flag_at = NOW(), updated_at = NOW() WHERE id = ?';
        } else {
            $sql = 'UPDATE listings SET review_flag = 0, review_flag_at = NULL, updated_at = NOW() WHERE id = ?';
        }
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    /**
     * Append a snapshot row to listing_revisions before the change is
     * applied. Used by the Service on edit-to-active to support soft-revert
     * if admin rejects the edit.
     */
    public static function appendRevision(int $listingId, string $snapshotJson, int $createdBy): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO listing_revisions (listing_id, snapshot_json, created_by, created_at) '
            . 'VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$listingId, $snapshotJson, $createdBy]);
        return (int) Db::pdo()->lastInsertId();
    }

    /**
     * Group counts of listings by status for a given seller. Used by
     * MyListingsAction to render the 4 tab counts in a single query.
     *
     * Returns a PDOStatement the caller iterates; the Action only reads
     * rows with status in (active, pending, sold, draft).
     *
     * @return \PDOStatement
     */
    public static function groupCountsBySeller(int $sellerId): \PDOStatement
    {
        $stmt = Db::pdo()->prepare(
            'SELECT status, COUNT(*) AS n FROM listings '
            . "WHERE seller_id = ? AND status IN ('active', 'pending', 'sold', 'draft') "
            . 'GROUP BY status'
        );
        $stmt->execute([$sellerId]);
        return $stmt;
    }
}
