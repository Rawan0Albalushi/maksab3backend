<?php

namespace App\Http\Resources\Common\Item;

use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\Item\ProductVideoFormatTrait;
use Illuminate\Support\Str;
use App\Http\Resources\BaseResource;

class ProductResource extends BaseResource
{
    private const IMAGE_URLS = ['image_full_url', 'images_full_url', 'video_full_url'];

    use ProductVideoFormatTrait;

    protected const DROPPED_RELATIONS = ['taxVats', 'nutritions', 'allergies', 'generic', 'pharmacy_item_details', 'store', 'rating', 'flashSaleItems'];

    protected const INTERNAL_ATTRIBUTES = ['rating', 'resolved_add_ons', 'resolved_category_names', 'resolved_taxes', 'resolved_tags', 'resolved_nutritions', 'resolved_allergies', 'resolved_generics'];

    protected bool $single = true;

    protected bool $translate = false;

    protected bool $tempProduct = false;

    public function toArray(Request $request): array
    {
        $item = $this->resource;
        $store = $item->store;
        $flashSale = $this->runningFlashSale();
        $hasFlashStock = $flashSale && $flashSale->available_stock > 0;

        $data = $item->attributesToArray();
        $appends = [];
        foreach ($item->getAppends() as $append) {
            $appends[$append] = $data[$append] ?? null;
            unset($data[$append]);
        }

        foreach (self::IMAGE_URLS as $key) {
            $appends[$key] = $item->{$key};
        }

        $data['category_ids'] = $this->categoryIds();
        $data['add_ons'] = $this->addOns();
        $data['attributes'] = Helpers::decodeJsonToArray($item->attributes);
        $data['choice_options'] = Helpers::decodeJsonToArray($item->choice_options);
        $data['variations'] = $this->variations();
        $data['food_variations'] = $item->food_variations ? Helpers::decodeJsonToArray($item->food_variations) : '';
        $data['module_type'] = $item->module?->module_type;
        $data['store_name'] = $store?->name;

        if (! $this->translate) {
            $data['store_image_full_url'] = $store?->logo_full_url;
            $data['is_campaign'] = ($store?->campaigns_count ?? 0) > 0 ? 1 : 0;
        }

        $data['zone_id'] = $store?->zone_id;

        foreach ($this->cartFields($data) as $key => $value) {
            $data[$key] = $value;
        }

        $data['flash_sale'] = (int) (($this->translate ? (bool) $flashSale : $hasFlashStock) ? 1 : 0);
        $data['stock'] = $hasFlashStock ? $flashSale->available_stock : $data['stock'];

        // Read off the resolver's answer rather than off `$store->discount`, the same correction
        // ItemResource::activeStoreDiscount() carries and this one was missed by. Those were the
        // same thing until Happy Hour: a truthy result used to imply the vendor's standing discount
        // row existed. A happy hour makes it truthy with no such row, so `null->discount` threw --
        // which took down order placement itself at any store inside a window without its own
        // discount. It also reports the rate actually being charged: a window REPLACES the standing
        // discount, so the old line quoted the vendor's rate while the customer was billed the
        // window's.
        $storeWide = $hasFlashStock ? null : Helpers::get_store_discount($store);

        if ($this->translate) {
            $data['discount'] = $hasFlashStock ? $flashSale->discount : $data['discount'];
            $data['discount_type'] = $hasFlashStock ? $flashSale->discount_type : $data['discount_type'];
        } else {
            $discount = Helpers::product_discount_calculate($item, $item->price, $store, true);
            // Zero while ANY store-wide rate is in force -- the item is shown at base price.
            // Same rule ItemResource::toArray() applies; this resource had fallen out of step
            // with it and was showing whichever of (item's own discount, store's rate) happened
            // to be larger instead of suppressing the item's own figure outright.
            $data['discount'] = $storeWide !== null ? 0 : $discount['discount_percentage'];
            $data['discount_type'] = $discount['original_discount_type'];
        }

        $data['store_discount'] = (float) ($storeWide['discount'] ?? 0);
        $data['schedule_order'] = $store?->schedule_order;

        if (! $this->single) {
            $data['delivery_time'] = $store?->delivery_time;
            $data['free_delivery'] = $store?->free_delivery;
            $data['tax'] = 0;
            $data['unit'] = $item->unit;
            $data['recommended'] = (int) $item->recommended;
        }

        foreach ($this->ratingFields() as $key => $value) {
            $data[$key] = $value;
        }

        if (! $this->translate) {
            [$data['min_delivery_time'], $data['max_delivery_time']] = $this->deliveryWindow($store?->delivery_time);
        }

        foreach ($this->itemDetailFields() as $key => $value) {
            $data[$key] = $value;
        }

        if (! $this->translate) {
            $data['store_category_id'] = (int) $item->store_category_id;
            $data['store_category_name'] = $item->storeCategory?->name;
        }

        $data['nutritions_name'] = $this->taxonomyNames('nutritions', 'nutrition');
        $data['allergies_name'] = $this->taxonomyNames('allergies', 'allergy');
        $data['generic_name'] = $this->taxonomyNames('generic', 'generic_name');

        if ($this->tempProduct) {
            foreach ($this->tempTaxonomyRows() as $key => $value) {
                $data[$key] = $value;
            }
        }

        if ($this->translate) {
            $data = $this->applyTranslations($data);
            $data['tax_ids'] = $this->taxIds();
        } else {
            $data['tax_data'] = $this->taxRows();
        }

        if ($item->module?->module_type === 'ecommerce') {
            $data['meta_title'] = $item->seoData?->title;
            $data['meta_description'] = $item->seoData?->description;
            $data['meta_image'] = $item->seoData?->imageFullUrl;
            $data['meta_data'] = $item->seoData?->meta_data;
        }

        foreach ($this->productVideoFields($item) as $key => $value) {
            $data[$key] = $value;
        }

        foreach ($appends as $key => $value) {
            $data[$key] = $value;
        }

        foreach (self::INTERNAL_ATTRIBUTES as $key) {
            unset($data[$key]);
        }

        return $data + $this->relationRows();
    }

    protected function cartFields(array $data): array
    {
        return [];
    }

    protected function relationRows(): array
    {
        $relations = $this->resource->relationsToArray();

        foreach (self::DROPPED_RELATIONS as $relation) {
            unset($relations[Str::snake($relation)]);
        }

        if ($this->translate) {
            unset($relations['ecommerce_item_details']);

            if ($this->resource->module?->module_type !== 'ecommerce') {
                unset($relations['seo_data']);
            }
        }

        if (! $this->single) {
            unset($relations['unit']);
        }

        return $relations;
    }

    protected function runningFlashSale(): mixed
    {
        if (! $this->resource->relationLoaded('flashSaleItems')) {
            return null;
        }

        return $this->resource->getRelation('flashSaleItems')->last();
    }

    protected function categoryIds(): array
    {
        $names = $this->resource->resolved_category_names;
        $rows = [];

        foreach (Helpers::decodeJsonToArray($this->resource->category_ids) as $value) {
            $id = (string) data_get($value, 'id');
            $row = ['id' => $id, 'position' => data_get($value, 'position')];

            if (! $this->translate) {
                $row['name'] = $names?->get($id) ?? 'NA';
            }

            $rows[] = $row;
        }

        return $rows;
    }

    protected function addOns(): array
    {
        return collect($this->resource->resolved_add_ons ?? [])
            ->sortBy('id')
            ->map(function ($addOn) {
                $addOn = clone $addOn;
                $addOn->tax_ids = $addOn->taxVats->pluck('tax_id')->all();
                $addOn->unsetRelation('taxVats');

                return $addOn->toArray();
            })
            ->values()
            ->all();
    }

    protected function variations(): array
    {
        $rows = [];

        foreach (Helpers::decodeJsonToArray($this->resource->variations) as $variation) {
            $rows[] = [
                'type' => $variation['type'],
                'price' => (float) $variation['price'],
                'stock' => (int) ($variation['stock'] ?? 0),
            ];
        }

        return $rows;
    }

    protected function ratingFields(): array
    {
        $item = $this->resource;

        if ($this->translate) {
            return [
                'rating_count' => (int) ($item->rating ? array_sum(Helpers::decodeJsonToArray($item->rating)) : 0),
                'avg_rating' => (float) ($item->avg_rating ?: 0),
            ];
        }

        $summary = $item->relationLoaded('rating') ? $item->getRelation('rating')->first() : null;

        return [
            'rating_count' => (int) ($summary?->rating_count ?? 0),
        ] + ($this->single ? ['review_count' => (int) ($summary?->review_count ?? 0)] : [])
          + ['avg_rating' => (float) ($summary?->average ?? 0)];
    }

    protected function itemDetailFields(): array
    {
        $item = $this->resource;
        $store = $item->store;

        return [
            'common_condition_id' => (int) ($item->pharmacy_item_details?->common_condition_id ?? 0),
            'brand_id' => (int) ($item->ecommerce_item_details?->brand_id ?? 0),
            'brand_name' => $item->ecommerce_item_details?->brand?->name,
            'is_basic' => (int) ($item->pharmacy_item_details?->is_basic ?? 0),
            'is_prescription_required' => (int) ($item->pharmacy_item_details?->is_prescription_required ?? 0),
            'unit_value' => $this->tempProduct ? ($item->unit_value ?? null) : $item->pharmacy_item_details?->unit_value,
            'manufacturer' => $this->tempProduct ? ($item->manufacturer ?? null) : $item->pharmacy_item_details?->manufacturer,
            'halal_tag_status' => (int) ($store?->storeConfig?->halal_tag_status ?? 0),
            'verified_seller' => Helpers::get_verified_seller_status($store, $store?->storeConfig),
        ];
    }

    protected function taxonomyNames(string $relation, string $column): array
    {
        return collect($this->resource->getRelation($relation))
            ->sortBy('id')
            ->pluck($column)
            ->values()
            ->all();
    }

    protected function tempTaxonomyRows(): array
    {
        return [
            'tags' => $this->resource->resolved_tags ?? [],
            'nutritions_data' => $this->resource->resolved_nutritions ?? [],
            'allergies_data' => $this->resource->resolved_allergies ?? [],
            'generic_name_data' => $this->resource->resolved_generics ?? [],
        ];
    }

    protected function taxIds(): array
    {
        return $this->resource->taxVats->pluck('tax_id')->all();
    }

    protected function taxRows(): array
    {
        return collect($this->resource->resolved_taxes ?? [])
            ->unique('id')
            ->sortBy('id')
            ->map(fn ($tax) => $tax->attributesToArray())
            ->values()
            ->all();
    }

    protected function applyTranslations(array $data): array
    {
        foreach ($this->resource->translations ?? [] as $translation) {
            if ($translation['locale'] !== app()->getLocale()) {
                continue;
            }

            if (in_array($translation['key'], ['name', 'title'], true)) {
                $data['name'] = $translation['value'];
            }

            if ($translation['key'] === 'description') {
                $data['description'] = $translation['value'];
            }
        }

        return $data;
    }

}
