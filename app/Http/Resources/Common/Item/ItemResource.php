<?php

namespace App\Http\Resources\Common\Item;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $store = $this->store();
        $flashSale = $this->runningFlashSale();
        // Resolved ONCE for the three keys below. It walks the store's happy hour enrolments and
        // its discount window, and asking three times per item would triple that for every row of
        // every listing.
        $storeWide = $flashSale ? null : Helpers::get_store_discount($store);
        $discount = Helpers::product_discount_calculate($this->resource, $this->resource->price, $store, true);
        // No store-wide rate is shown ON the item. See storeWideRunning()'s note.
        $storeWideRunning = ! $flashSale && $storeWide !== null;
        $rating = $this->resource->relationLoaded('rating') ? $this->resource->getRelation('rating')->first() : null;
        $brand = $this->resource->relationLoaded('ecommerce_item_details')
            ? $this->resource->ecommerce_item_details?->brand
            : null;

        return array_merge(
            parent::toArray($request),
            [
                'id' => (int) $this->resource->id,
                'name' => $this->resource->name,
                'slug' => $this->resource->slug,
                'image_full_url' => $this->resource->image_full_url,
                'images_full_url' => $this->resource->images_full_url,

                'price' => (float) $this->resource->price,
                // Zero while ANY store-wide rate is in force -- the item is shown at base price.
                // The discount_TYPE is kept rather than blanked: at zero the two are arithmetically
                // identical and clients read the key unconditionally.
                'discount' => $storeWideRunning ? 0 : $discount['discount_percentage'],
                'discount_type' => $discount['original_discount_type'],
                // The STORE's rate, for its banner -- not a reduction on this item's price. It
                // stays populated precisely because the item's own `discount` above went to zero:
                // between them a client can render "base price, store is running 10% off" without
                // striking through a number the basket may not honour.
                'store_discount' => (float) ($storeWide['discount'] ?? 0),
                // The same rate again, split by WHICH promotion it is. `store_discount` says how
                // much; these say what to draw. A happy hour is a scheduled window with a banner
                // and a countdown of its own, a standing discount is not, and a client cannot tell
                // them apart from one number. Exactly one is ever non-zero -- a happy hour
                // replaces the vendor's rate rather than stacking with it.
                'happy_hour_discount' => $this->rateFrom($storeWide, 'happy_hour'),
                'vendor_store_discount' => $this->rateFrom($storeWide, 'store_discount'),
                'flash_sale' => $flashSale ? 1 : 0,

                'stock' => (int) ($flashSale ? $flashSale->available_stock : $this->resource->stock),
                'unit_type' => $this->resource->unit_type,
                'maximum_cart_quantity' => $this->resource->maximum_cart_quantity,

                'veg' => (int) $this->resource->veg,
                'organic' => (int) $this->resource->organic,
                'is_halal' => (int) $this->resource->is_halal,

                'avg_rating' => (float) ($rating->average ?? 0),
                'rating_count' => (int) ($rating->rating_count ?? 0),

                'available_time_starts' => $this->resource->available_time_starts,
                'available_time_ends' => $this->resource->available_time_ends,

                'brand_name' => $brand?->name,
                'category_ids' => Helpers::decodeJsonToArray($this->resource->category_ids),
            ],
            $this->moduleFields(),
            $this->storeFields($store),
        );
    }
    public function detailFields(): array
    {
        return [
            'variations' => $this->variations(),
            'food_variations' => Helpers::decodeJsonToArray($this->resource->food_variations),
            'choice_options' => Helpers::decodeJsonToArray($this->resource->choice_options),
            'available_date_starts' => $this->availableDateStarts(),
            'is_prescription_required' => $this->prescriptionRequired(),
            'generic_name' => $this->genericNames(),
        ];
    }
    protected function availableDateStarts(): ?string
    {
        return null;
    }
    protected function prescriptionRequired(): int
    {
        if (! $this->resource->relationLoaded('pharmacy_item_details')) {
            return 0;
        }

        return (int) ($this->resource->pharmacy_item_details?->is_prescription_required ?? 0);
    }
    protected function genericNames(): array
    {
        if (! $this->resource->relationLoaded('generic')) {
            return [];
        }

        return collect($this->resource->getRelation('generic'))->pluck('generic_name')->filter()->values()->all();
    }
    protected function store(): mixed
    {
        return $this->resource->relationLoaded('store') ? $this->resource->store : null;
    }
    protected function moduleFields(): array
    {
        return [
            'module_id' => (int) $this->resource->module_id,
            'module_type' => $this->resource->relationLoaded('module') ? $this->resource->module?->module_type : null,
        ];
    }
    protected function storeFields(mixed $store): array
    {
        return [
            'store_id' => (int) $this->resource->store_id,
            'store_name' => $store?->name,
            'store_slug' => $store?->slug,
            'store_image_full_url' => $store?->logo_full_url,
            'verified_seller' => (int) ($store?->storeConfig?->verified_seller ?? 0),
            'halal_tag_status' => (int) ($store?->storeConfig?->halal_tag_status ?? 0),
            'zone_id' => $store?->zone_id,
            'schedule_order' => $store?->schedule_order,
            'free_delivery' => $store?->free_delivery,
        ];
    }
    /**
     * The store-wide rate in force on this item right now.
     *
     * Read off the resolver's own answer rather than off `$store->discount`. Those were the same
     * thing until Happy Hour was added: a truthy result used to imply the vendor's standing
     * discount row existed, so `$store->discount->discount` was safe. A happy hour makes the
     * result truthy with no such row, which read `null->discount` -- a warning on every item of
     * every store inside a window, and a null where the rate belonged.
     *
     * Taking the number from the resolver also makes this report the rate actually being charged.
     * A happy hour REPLACES the standing discount rather than stacking with it, so during a
     * window the old line would have quoted the vendor's rate while the customer was billed the
     * window's.
     */
    protected function activeStoreDiscount(mixed $store): mixed
    {
        return (float) (Helpers::get_store_discount($store)['discount'] ?? 0);
    }

    /**
     * Why a store-wide rate shows nothing on an item.
     *
     * A store-wide rate -- a happy hour or the vendor's own standing discount -- comes off the
     * whole basket at CHECKOUT and carries its own minimum spend. It is not a promise a single
     * item can keep: striking out an item's price and then declining the reduction because the
     * basket came in under the minimum is worse than never having shown it. So while either is in
     * force every item reports its base price and a zero `discount`, and the reduction appears
     * once -- on the checkout summary, beside the Pro discount.
     *
     * This is StackFood's rule, verified against it side by side: there
     * product_discount_calculate_data() returns 0 whenever get_effective_restaurant_discount() is
     * truthy, for either promotion.
     *
     * `store_discount`, `happy_hour_discount` and `vendor_store_discount` still carry the rate.
     * They describe the STORE -- its banner, its countdown -- not a price on this item, and a
     * client that renders them as an item reduction is reading them wrongly.
     *
     * Two things this leaves alone:
     *   - the item's own discount, whenever no store-wide rate is in force
     *   - campaign items, which never reach this class (ItemCampaignResource builds its own array)
     *
     * And it changes DISPLAY only. Order placement resolves the rate separately -- it calls
     * product_discount_calculate() with $check_store_discount FALSE and applies the store-wide cut
     * to the whole basket afterwards -- so the customer is still charged the reduced price.
     */
    /** The already-resolved rate, but only when it came from the named promotion; 0 otherwise. */
    protected function rateFrom(?array $storeWide, string $source): float
    {
        return ($storeWide['source'] ?? null) === $source ? (float) $storeWide['discount'] : 0.0;
    }

    /**
     * Is any store-wide rate in force at this store right now?
     *
     * Shared with the subclasses that compute a price of their own -- ItemOfferResource's
     * `discounted_price`, for one -- so every item surface suppresses on the same signal rather
     * than each deciding for itself.
     */
    protected function storeWideRunning(mixed $store): bool
    {
        return Helpers::get_store_discount($store) !== null;
    }

    /** Narrower: a happy hour specifically, for the surfaces that treat the two differently. */
    protected function happyHourRunning(mixed $store): bool
    {
        return (Helpers::get_store_discount($store)['source'] ?? null) === 'happy_hour';
    }
    protected function variations(): array
    {
        $variations = [];

        foreach (Helpers::decodeJsonToArray($this->resource->variations) as $variation) {
            $variations[] = [
                'type' => $variation['type'] ?? null,
                'price' => (float) ($variation['price'] ?? 0),
                'stock' => (int) ($variation['stock'] ?? 0),
            ];
        }

        return $variations;
    }
    protected function formatTime(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return $value instanceof \DateTimeInterface ? $value->format('H:i') : (string) $value;
    }
    private function runningFlashSale(): mixed
    {
        if (! $this->resource->relationLoaded('flashSaleItems')) {
            return null;
        }

        return $this->resource->flashSaleItems->first(fn ($flashSaleItem) => $flashSaleItem->available_stock > 0);
    }
}
