<?php

namespace App\Observers;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Re-parenting a category changes which top-level category the items beneath it roll up to,
 * which the item write path cannot observe. Scoped to the moved category and its direct
 * children so the update stays bounded.
 */
class CategoryObserver
{
    public function updated(Category $category): void
    {
        if (! $category->wasChanged('parent_id')) {
            return;
        }

        self::syncItemTopCategories([$category->id]);
    }

    /**
     * Recompute top_category_id for items sitting in the given categories or in their direct
     * children. Called directly by the category bulk import, which writes with DB::table()
     * and so never reaches updated().
     */
    public static function syncItemTopCategories(array $categoryIds): void
    {
        $categoryIds = array_values(array_filter($categoryIds));

        if ($categoryIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

        DB::update("
            UPDATE items i
            JOIN categories c ON c.id = i.category_id
            SET i.top_category_id = IF(c.parent_id = 0, c.id, c.parent_id)
            WHERE i.category_id IN ({$placeholders}) OR c.parent_id IN ({$placeholders})",
            array_merge($categoryIds, $categoryIds));
    }
}
