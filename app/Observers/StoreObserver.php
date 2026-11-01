<?php

namespace App\Observers;

use App\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * A store moving zone changes the zone of every item it sells, which the item write path
 * cannot observe. Without this, those items linger in the old zone's listings.
 */
class StoreObserver
{
    public function updated(Store $store): void
    {
        if (! $store->wasChanged('zone_id')) {
            return;
        }

        self::syncItemZones([$store->id]);
    }

    /**
     * Re-point the given stores' items at their store's current zone. Called directly by the
     * store and provider bulk imports, which update stores with DB::table() and so never
     * reach updated(). Only rows whose zone is actually wrong are written.
     */
    public static function syncItemZones(array $storeIds): void
    {
        $storeIds = array_values(array_filter($storeIds));

        if ($storeIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($storeIds), '?'));

        DB::update("
            UPDATE items i
            JOIN stores s ON s.id = i.store_id
            SET i.zone_id = s.zone_id
            WHERE i.store_id IN ({$placeholders})
              AND (i.zone_id IS NULL OR i.zone_id <> s.zone_id)", $storeIds);
    }
}
