<?php

namespace App\Http\Resources\Common\Store;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use App\Http\Resources\Customer\Promotion\HappyHourResource;
use App\Services\Promotion\HappyHourCatalog;
use App\Services\Promotion\StorePromotionService;
use App\Services\System\DistanceService;
use App\Traits\Store\StoreDataTrait;
use Illuminate\Http\Request;

class StoreListResource extends BaseResource
{
    use StoreDataTrait;

    private array $options = [];

    public function withOptions(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public static function renderList(mixed $stores, array $options = []): array
    {
        $stores = collect($stores);
        $storeIds = $stores->pluck('id')->filter()->map('intval')->unique()->values()->all();

        $batch = new static(null);
        $offers = array_key_exists('offers', $options) || empty($storeIds)
            ? null
            : $batch->offersByStore($storeIds);
        $categories = array_key_exists('category_data', $options) || empty($storeIds)
            ? null
            : $batch->topCategories($storeIds, 5);

        // bogo_offers / bundles / coupons for the WHOLE page in one pass. Resolved here rather
        // than in toArray() because a listing renders fifty cards and a per-card lookup is fifty
        // round trips (rule 11); toArray() falls back to a single-store resolve only for the call
        // sites that build one card directly and never come through here.
        $promotions = empty($storeIds)
            ? null
            : app(StorePromotionService::class)->batchFor($stores, $batch->promotionContext($stores->first()));

        return $stores
            ->map(function ($store) use ($options, $offers, $categories, $promotions) {
                $id = (int) $store->id;

                if ($offers !== null) {
                    $options['offers'] = $offers[$id] ?? [];
                }

                if ($categories !== null) {
                    $options['category_data'] = $categories[$id] ?? [];
                }

                if ($promotions !== null) {
                    $options['bogo_offers'] = $promotions['bogo_offers'][$id] ?? [];
                    $options['bundles'] = $promotions['bundles'][$id] ?? [];
                    $options['coupons'] = $promotions['coupons'][$id] ?? [];
                }

                return (new static($store))->withOptions($options)->toArray(request());
            })
            ->values()
            ->all();
    }

    public function toArray(Request $request): array
    {
        $store = $this->resource;
        $topItems = $this->topItems();
        $withItems = $this->options['with_items'] ?? ($this->options['top_items'] ?? null) !== null;
        [$minDelivery, $maxDelivery] = $this->deliveryParts($store->delivery_time);
        $categories = $this->categoryData();

        $row = [
            'id' => (int) $store->id,
            'name' => $store->name,
            'slug' => $store->slug,
            'logo_full_url' => $store->logo_full_url,
            'cover_photo_full_url' => $store->cover_photo_full_url,
            'module_id' => (int) ($store->module_id ?? 0),
            'module_type' => $store->module_type ?? null,
            'avg_rating' => (float) ($store->avg_r ?? $store->top_rated_avg ?? $store->avg_rating_all ?? $store->store_rating_avg ?? 0),
            'reviews_count' => $this->reviewsCount(),
            'items_count' => (int) ($this->options['items_count'] ?? $store->items_count ?? 0),
            'category_ids' => $categories['ids'] ?? [],
            'category_names' => $categories['names'] ?? [],
            'delivery_time' => $store->delivery_time,
            'min_delivery_time' => $minDelivery,
            'max_delivery_time' => $maxDelivery,
            // `stores.distance` is METRES, straight from ST_Distance_Sphere, and stays as it was.
            'distance' => (float) ($store->distance ?? 0),
            // §3.4 — `distance_km` is unchanged and `distance_mi` and `distance_label` join it
            // (additive, N9). Both units are always emitted whatever `distance_unit` says, so a
            // client picks by what /config told it; only `distance_label` follows the setting.
            ...app(DistanceService::class)->keysFromMetres($store->distance ?? 0),
            'open' => (int) ($store->open ?? 0),
            'current_opening_time' => Helpers::getNextOpeningTime($store->schedules) ?? 'closed',
            'active' => (int) $store->active,
            'free_delivery' => (int) ($store->free_delivery ?? 0),
            'is_new' => (int) ($store->created_at && $store->created_at->greaterThanOrEqualTo($this->newThreshold())),
            'ad' => (int) in_array($store->id, $this->options['advertised_store_ids'] ?? []),
            'avg_item_discount_percentage' => $this->averageItemDiscount($withItems, $topItems),
            'store_discount' => $this->storeDiscount(),
            // The window itself, not just the rate. `store_discount` above is the VENDOR's own
            // standing discount and stays untouched; a live happy hour replaces it rather than
            // adding to it, so during a window the two disagree and `active_discount` is the one
            // a customer is actually charged.
            'is_happy_hour_running' => (bool) $this->runningHappyHour(),
            'happy_hour' => $this->runningHappyHour()
                ? (new HappyHourResource($this->runningHappyHour()))->render()
                : null,
            'active_discount' => (float) (Helpers::get_store_discount($this->resource)['discount'] ?? 0),
            'offers' => $this->offers(),
            // The three promotion strips a card draws. Always present, empty where the module
            // cannot run that promotion -- an absent key would make a client branch on whether
            // the platform supports the feature, an empty array just draws nothing (N9).
            'bogo_offers' => $this->promotion('bogo_offers'),
            'bundles' => $this->promotion('bundles'),
            'coupons' => $this->promotion('coupons'),
            'verified_seller' => (int) ($store->storeConfig?->verified_seller ?? 0),
        ];

        if ($withItems) {
            $row['top_items'] = $topItems;
        }

        return $row;
    }

    private function topItems(): array
    {
        $topItems = $this->options['top_items'] ?? data_get($this->resource, 'top_items');

        if ($topItems === null) {
            return [];
        }

        return is_array($topItems)
            ? $topItems
            : (method_exists($topItems, 'all') ? $topItems->all() : (array) $topItems);
    }

    private function deliveryParts(?string $deliveryTime): array
    {
        $parts = $deliveryTime ? explode('-', $deliveryTime) : [];

        return [
            isset($parts[0]) ? (int) $parts[0] : 0,
            isset($parts[1]) ? (int) preg_replace('/[^0-9]/', '', $parts[1]) : 0,
        ];
    }

    private function offers(): array
    {
        return array_key_exists('offers', $this->options)
            ? (array) $this->options['offers']
            : ($this->offersByStore([$this->resource->id])[(int) $this->resource->id] ?? []);
    }

    /**
     * One promotion strip, from the page's batch where renderList() built one.
     *
     * The fallback resolves this store alone, for the ten call sites that build a single card
     * directly (StoreService's six, AdvertisementService, StoreShowResource). Correct everywhere
     * at the cost of one query set per card, which is what those callers already pay for `offers`
     * and `category_data` on the same line.
     */
    private function promotion(string $key): array
    {
        if (array_key_exists($key, $this->options)) {
            return is_array($this->options[$key]) ? $this->options[$key] : [];
        }

        $this->promotionMemo ??= app(StorePromotionService::class)->batchFor(
            [$this->resource],
            $this->promotionContext($this->resource)
        );

        return $this->promotionMemo[$key][(int) $this->resource->id] ?? [];
    }

    private ?array $promotionMemo = null;

    /**
     * Zone, module and module type for a promotion lookup.
     *
     * The module comes off the STORE rather than the request: a listing is single-module, but a
     * wishlist or a campaign's store list is not, and reading the header there would test one
     * store's offers against another store's module. Zones stay with the request, because that is
     * where the customer is standing and a store's own zone cannot answer it.
     */
    protected function promotionContext(mixed $store): array
    {
        $zoneIds = json_decode((string) request()->header('zoneId'), true);

        return [
            'zone_ids' => is_array($zoneIds) ? $zoneIds : array_filter([$store?->zone_id]),
            'module_id' => $store?->module_id,
            'module_type' => $store?->module_type
                ?? ($store?->relationLoaded('module') ? $store->module?->module_type : null),
        ];
    }

    private function categoryData(): array
    {
        if (array_key_exists('category_data', $this->options)) {
            return is_array($this->options['category_data']) ? $this->options['category_data'] : [];
        }

        return $this->topCategories([$this->resource->id], 5)[$this->resource->id] ?? [];
    }

    private function newThreshold(): mixed
    {
        return $this->options['new_threshold']
            ?? now()->subDays((int) (Helpers::get_business_settings('new_store_tag_days') ?? 30));
    }

    private function reviewsCount(): ?int
    {
        if (isset($this->resource->reviews_count)) {
            return (int) $this->resource->reviews_count;
        }

        return isset($this->resource->reviews_comments_count)
            ? (int) $this->resource->reviews_comments_count
            : null;
    }

    /**
     * The window this store is inside right now, resolved once per render.
     *
     * Memoised behind a FLAG rather than the value: "no window open" is the common answer and it
     * is null, so a `??=` would re-resolve for each of the three keys above, on every store in
     * every listing.
     */
    private function runningHappyHour(): mixed
    {
        if (! $this->happyHourResolved) {
            $this->happyHourResolved = true;
            $this->happyHourMemo = app(HappyHourCatalog::class)->runningHappyHour($this->resource);
        }

        return $this->happyHourMemo;
    }

    private bool $happyHourResolved = false;

    private mixed $happyHourMemo = null;

    private function storeDiscount(): ?array
    {
        if (! $this->resource->relationLoaded('discount') || ! $this->resource->discount) {
            return null;
        }

        return [
            'discount' => (float) $this->resource->discount->discount,
            'discount_type' => $this->resource->discount->discount_type ?? 'percent',
        ];
    }

    private function averageItemDiscount(bool $withItems, array $topItems): float
    {
        if (! $withItems || empty($topItems)) {
            return 0.0;
        }

        $values = array_filter(
            array_map(fn ($item) => (float) ($item['discount'] ?? 0), $topItems),
            fn ($value) => $value > 0
        );

        return empty($values) ? 0.0 : round(array_sum($values) / count($values), 2);
    }
}
