<?php

namespace App\Http\Resources\Customer\Store;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use App\Http\Resources\Customer\Promotion\HappyHourResource;
use App\Services\Promotion\HappyHourCatalog;
use App\Services\Promotion\StorePromotionService;
use App\Services\System\DistanceService;
use Illuminate\Http\Request;

class StoreResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $config = $this->resource->storeConfig;

        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'logo_full_url' => $this->resource->logo_full_url,
            'cover_photo_full_url' => $this->resource->cover_photo_full_url,

            'address' => $this->resource->address,
            'latitude' => $this->resource->latitude,
            'longitude' => $this->resource->longitude,
            'zone_id' => $this->resource->zone_id,
            'module_id' => (int) $this->resource->module_id,

            // `stores.distance` is METRES, straight from ST_Distance_Sphere and selected by the
            // `withOpen(lng, lat)` scope every listing that reaches this resource already applies.
            // The three derived keys come from DistanceService so this card labels a distance the
            // same way StoreListResource does -- the happy-hour store list had the figure in hand
            // and dropped it, so that screen alone could not show how far a store was.
            //
            // 0 where the caller passed no coordinates, which is what the scope itself yields:
            // a wishlist read without a location is not a journey anyone can measure.
            'distance' => (float) ($this->resource->distance ?? 0),
            ...app(DistanceService::class)->keysFromMetres($this->resource->distance ?? 0),

            'delivery' => $this->resource->delivery,
            'take_away' => $this->resource->take_away,
            'free_delivery' => $this->resource->free_delivery,
            'schedule_order' => $this->resource->schedule_order,
            'delivery_time' => $this->resource->delivery_time,
            'minimum_order' => $this->resource->minimum_order,
            'minimum_shipping_charge' => $this->resource->minimum_shipping_charge,
            'maximum_shipping_charge' => $this->resource->maximum_shipping_charge,
            'per_km_shipping_charge' => $this->resource->per_km_shipping_charge,

            'veg' => (int) $this->resource->veg,
            'non_veg' => (int) $this->resource->non_veg,
            'active' => $this->resource->active,
            'open' => $this->resource->open,
            'announcement' => (int) $this->resource->announcement,
            'announcement_message' => $this->resource->announcement_message,

            'verified_seller' => (int) ($config?->verified_seller ?? 0),
            'extra_packaging_status' => (bool) ($config?->extra_packaging_status ?? false),
            'extra_packaging_amount' => $this->extraPackagingAmount($config),

            'store_discount' => $this->storeDiscount(),
            // The window itself, not just the rate `store_discount` collapses it into: a happy
            // hour has a banner, a countdown and a minimum of its own, and a card cannot draw any
            // of that from a number. Null when no window is open.
            'is_happy_hour_running' => (bool) $this->runningHappyHour(),
            'happy_hour' => $this->runningHappyHour()
                ? (new HappyHourResource($this->runningHappyHour()))->render()
                : null,
            // What customers are ACTUALLY charged, which `store_discount` does not say: a live
            // happy hour replaces the vendor's standing rate rather than adding to it, so the two
            // disagree exactly while a window is open.
            'active_discount' => (float) (Helpers::get_store_discount($this->resource)['discount'] ?? 0),
            // The three promotion strips, in the same shape and under the same keys
            // StoreListResource emits them -- a store card must not describe its offers one way
            // on a listing and another way here. Empty where the module cannot run that kind of
            // promotion; see StorePromotionService for which module gets which.
            //
            // A caller that already primed `bogo_offers` on the model (HappyHourController, via
            // BogoOfferCustomerService::primeStoreOffers()) keeps its own value rather than
            // paying for the lookup twice.
            'bogo_offers' => $this->resource->getAttribute('bogo_offers')
                ?? $this->promotion('bogo_offers'),
            'bundles' => $this->promotion('bundles'),
            'coupons' => $this->promotion('coupons'),
        ]);
    }

    /**
     * One promotion strip for this store.
     *
     * Resolved per store because this resource has no batch entry point of its own -- it is used
     * for a wishlist, a campaign's store list and an order's store, none of which render enough
     * cards at once for a page-wide pass to pay for itself. The three keys share one lookup.
     */
    private function promotion(string $key): array
    {
        $store = $this->resource;
        $zoneIds = json_decode((string) request()->header('zoneId'), true);

        $this->promotionMemo ??= app(StorePromotionService::class)->batchFor([$store], [
            'zone_ids' => is_array($zoneIds) ? $zoneIds : array_filter([$store->zone_id]),
            'module_id' => $store->module_id,
            // Off the STORE, not the request header: a wishlist or a campaign can hold stores
            // from several modules at once, and the header would test one store's promotions
            // against another store's module.
            'module_type' => $store->module_type
                ?? ($store->relationLoaded('module') ? $store->module?->module_type : null),
        ]);

        return $this->promotionMemo[$key][(int) $store->id] ?? [];
    }

    private ?array $promotionMemo = null;

    /**
     * The window this store is inside right now, resolved once per render.
     *
     * Reads the store's already-loaded happy hour enrolments; it queries only when a caller
     * forgot to eager-load them, which the listing services all do.
     *
     * Memoised behind a FLAG rather than behind the value: "no window open" is the common answer
     * and it is null, so a `??=` on the value alone would re-resolve on every one of the three
     * keys below, for every store in a listing.
     */
    protected function runningHappyHour(): mixed
    {
        if (! $this->happyHourResolved) {
            $this->happyHourResolved = true;
            $this->happyHourMemo = app(HappyHourCatalog::class)->runningHappyHour($this->resource);
        }

        return $this->happyHourMemo;
    }

    private bool $happyHourResolved = false;

    private mixed $happyHourMemo = null;

    private function extraPackagingAmount(mixed $config): float
    {
        if (! $config || $config->extra_packaging_status != 1) {
            return 0;
        }

        $enabledModules = Helpers::get_business_settings('extra_packaging_data');
        $moduleType = $this->resource->relationLoaded('module') ? $this->resource->module?->module_type : null;

        if (empty($enabledModules) || data_get($enabledModules, $moduleType) != '1') {
            return 0;
        }

        return (float) $config->extra_packaging_amount;
    }

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

}
