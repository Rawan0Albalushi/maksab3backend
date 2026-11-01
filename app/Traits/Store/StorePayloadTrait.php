<?php

namespace App\Traits\Store;

use App\Http\Resources\Common\Store\StoreDetailResource;
use App\Models\Item;
use App\Models\Review;
use App\Services\Promotion\StorePromotionService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Modules\Service\Entities\Service;

trait StorePayloadTrait
{
    protected const STORE_PAYLOAD_RELATIONS = ['storage', 'module.storage', 'schedules', 'store_sub', 'storeConfig'];

    public function loadStoreRelations(?Model $store): ?Model
    {
        if (! $store) {
            return null;
        }

        (new EloquentCollection([$store]))->loadMissing(self::STORE_PAYLOAD_RELATIONS);

        return $this->attachStorePromotions(
            $this->attachStoreAggregates(new EloquentCollection([$store]))
        )->first();
    }

    public function loadStoreRelationsForMany(EloquentCollection $stores): EloquentCollection
    {
        if ($stores->isEmpty()) {
            return $stores;
        }

        $stores->loadMissing(self::STORE_PAYLOAD_RELATIONS);

        return $this->attachStorePromotions($this->attachStoreAggregates($stores));
    }

    /**
     * Attach bogo_offers / bundles / coupons to a page of stores, as one pass for all of them.
     *
     * Here rather than inside StoreDetailResource because this is the only point on this path
     * that holds the whole page: a resource sees one store and would turn a fifty-provider list
     * into fifty promotion lookups (rule 11).
     *
     * The service module gets bundles and coupons but no BOGO -- StorePromotionService decides
     * that from the module type, so this call does not have to know which module it is serving
     * and cannot disagree with the store listings about it.
     */
    protected function attachStorePromotions(EloquentCollection $stores): EloquentCollection
    {
        if ($stores->isEmpty()) {
            return $stores;
        }

        $first = $stores->first();
        $zoneIds = json_decode((string) request()->header('zoneId'), true);

        $promotions = app(StorePromotionService::class)->batchFor($stores, [
            'zone_ids' => is_array($zoneIds) ? $zoneIds : array_filter([$first->zone_id]),
            'module_id' => $first->module_id,
            'module_type' => $first->module_type
                ?? ($first->relationLoaded('module') ? $first->module?->module_type : null),
        ]);

        foreach ($stores as $store) {
            $id = (int) $store->id;
            $store->setAttribute('bogo_offers', $promotions['bogo_offers'][$id] ?? []);
            $store->setAttribute('bundles', $promotions['bundles'][$id] ?? []);
            $store->setAttribute('coupons', $promotions['coupons'][$id] ?? []);
        }

        return $stores;
    }

    public function storePayload(?Model $store): ?array
    {
        $store = $this->loadStoreRelations($store);

        return $store ? (new StoreDetailResource($store))->resolve() : null;
    }

    public function storePayloadList(mixed $stores): array
    {
        $collection = $stores instanceof EloquentCollection
            ? $stores
            : new EloquentCollection(collect($stores)->filter()->values()->all());

        return $this->loadStoreRelationsForMany($collection)
            ->map(fn ($store) => (new StoreDetailResource($store))->resolve())
            ->values()->all();
    }

    protected function attachStoreAggregates(EloquentCollection $stores): EloquentCollection
    {
        $missing = $stores->filter(fn ($store) => ! isset($store['items_min_price']));

        if ($missing->isEmpty()) {
            return $stores;
        }

        $storeIds = $missing->pluck('id');

        $prices = Item::query()
            ->whereIn('store_id', $storeIds)
            ->active()
            ->groupBy('store_id')
            ->selectRaw('store_id, MIN(price) as min_price, MAX(price) as max_price')
            ->get()
            ->keyBy('store_id');

        $counts = Item::query()
            ->whereIn('store_id', $storeIds)
            ->approved()
            ->groupBy('store_id')
            ->selectRaw('store_id, COUNT(*) as total')
            ->pluck('total', 'store_id');

        $services = service_addon_active() && class_exists(Service::class)
            ? Service::query()
                ->whereIn('store_id', $storeIds)
                ->where('status', 1)
                ->where('is_approved', 1)
                ->groupBy('store_id')
                ->selectRaw('store_id, COUNT(*) as total')
                ->pluck('total', 'store_id')
            : collect();

        $reviews = Review::query()
            ->join('items', 'items.id', '=', 'reviews.item_id')
            ->whereIn('items.store_id', $storeIds)
            ->where('reviews.status', 1)
            ->groupBy('items.store_id')
            ->selectRaw('items.store_id as store_id, COUNT(reviews.id) as total')
            ->pluck('total', 'store_id');

        foreach ($missing as $store) {
            $row = $prices->get($store->id);
            $store->setAttribute('items_min_price', $row?->min_price ?? 0);
            $store->setAttribute('items_max_price', $row?->max_price ?? 0);
            $store->setAttribute('store_reviews_count', (int) ($reviews[$store->id] ?? 0));

            if (! isset($store['items_count'])) {
                $store->setAttribute('items_count', (int) ($counts[$store->id] ?? 0));
            }

            if (isset($services[$store->id])) {
                $store->setAttribute('services_count', (int) $services[$store->id]);
            }
        }

        return $stores;
    }
}
