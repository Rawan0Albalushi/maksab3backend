<?php

namespace App\Observers;

use App\Models\Category;
use App\Models\Item;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the derived items.top_category_id and items.zone_id current. Both only change with
 * the item's category or store, so the lookups are skipped otherwise.
 */
class ItemObserver
{
    public function saving(Item $item): void
    {
        if ($item->isDirty('category_id') || $item->top_category_id === null) {
            $item->top_category_id = self::topCategoryFor($item->category_id);
        }

        if ($item->isDirty('store_id') || $item->zone_id === null) {
            $item->zone_id = self::zoneFor($item->store_id);
        }
    }

    /**
     * Set both derived columns for the given items. For rows written outside Eloquent: the
     * bulk imports write with DB::table(), which fires no model events, so saving() never
     * runs for them. The queries reading these columns have no fallback and NULL matches
     * nothing, so without this an imported item is simply absent from category counts and
     * zone-scoped lists -- no error, just missing.
     *
     * One statement per call rather than hydrating and re-saving each row, which is the cost
     * those import paths exist to avoid. LEFT JOIN so an item whose category or store is
     * missing lands on NULL, matching the migration's backfill.
     */
    public static function syncDerived(array $itemIds): void
    {
        $itemIds = array_values(array_filter($itemIds));

        if ($itemIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));

        DB::update("
            UPDATE items i
            LEFT JOIN categories c ON c.id = i.category_id
            LEFT JOIN stores s ON s.id = i.store_id
            SET i.top_category_id = IF(c.parent_id = 0, c.id, c.parent_id),
                i.zone_id = s.zone_id
            WHERE i.id IN ({$placeholders})", $itemIds);
    }

    public static function zoneFor(mixed $storeId): ?int
    {
        if (! $storeId) {
            return null;
        }

        $zoneId = Store::withoutGlobalScopes()->where('id', $storeId)->value('zone_id');

        return $zoneId === null ? null : (int) $zoneId;
    }

    public static function topCategoryFor(mixed $categoryId): ?int
    {
        if (! $categoryId) {
            return null;
        }

        // Global scopes off: the translate scope would join rows this has no use for.
        $category = Category::withoutGlobalScopes()->select(['id', 'parent_id'])->find($categoryId);

        if (! $category) {
            return null;
        }

        return (int) ((int) $category->parent_id === 0 ? $category->id : $category->parent_id);
    }
}
