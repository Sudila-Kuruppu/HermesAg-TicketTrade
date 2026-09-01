<?php

/**
 * TicketTrade — Category\Model\category_model
 *
 * Data access for the categories table. Read-only in Phase 3; admin
 * CRUD lands in Phase 8. findAllActive() filters by is_active=1 and
 * sorts by sort_order ASC; findById() returns inactive rows too so the
 * Service can decide whether to expose them.
 */

declare(strict_types=1);

namespace App\Category\Model;

use App\Support\Db;

class category_model
{
    /**
     * @return array<int, array<string,mixed>>
     */
    public static function findAllActive(): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT id, name, description, sort_order, is_active, created_at '
            . 'FROM categories WHERE is_active = 1 ORDER BY sort_order ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $r = $stmt->fetch();
        return $r === false ? null : $r;
    }

    public static function insert(string $name, int $sortOrder, ?string $description = null): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO categories (name, description, sort_order, is_active, created_at) '
            . 'VALUES (?, ?, ?, 1, NOW())'
        );
        $stmt->execute([$name, $description, $sortOrder]);
        return (int) Db::pdo()->lastInsertId();
    }

    public static function setActive(int $id, bool $active): int
    {
        $stmt = Db::pdo()->prepare('UPDATE categories SET is_active = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $id]);
        return $stmt->rowCount();
    }
}
