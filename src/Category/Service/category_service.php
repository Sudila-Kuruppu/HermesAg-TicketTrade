<?php

/**
 * TicketTrade — Category\Service\category_service
 *
 * Read-only in Phase 3. Sole-writer of categories is THIS class
 * (admin CRUD is Phase 8). Every public method returns the AD-16
 * failure envelope.
 */

declare(strict_types=1);

namespace App\Category\Service;

use App\Category\Model\category_model;
use App\Support\Db;
use App\Support\Error;

class category_service
{
    /**
     * Return all active categories, sorted by sort_order ASC.
     *
     * @return array{ok:bool,data:array,error:?array}
     */
    public static function listActive(): array
    {
        try {
            $rows = category_model::findAllActive();
        } catch (\Throwable $e) {
            return Error::envelope(false, null, [
                'code' => 'E_NOT_FOUND',
                'message' => 'Categories table is unavailable.',
            ]);
        }
        return Error::envelope(true, $rows, null);
    }

    /**
     * Look up a category by id. Returns a 404 envelope if not found OR
     * if is_active = FALSE (soft-deleted).
     *
     * @return array{ok:bool,data:?array,error:?array}
     */
    public static function getById(int $id): array
    {
        try {
            $row = category_model::findById($id);
        } catch (\Throwable $e) {
            return Error::envelope(false, null, [
                'code' => 'E_CATEGORY_NOT_FOUND',
                'message' => 'Category not found.',
            ]);
        }
        if ($row === null || (int) $row['is_active'] !== 1) {
            return Error::envelope(false, null, [
                'code' => 'E_CATEGORY_NOT_FOUND',
                'message' => 'Category not found.',
            ]);
        }
        return Error::envelope(true, $row, null);
    }
}
