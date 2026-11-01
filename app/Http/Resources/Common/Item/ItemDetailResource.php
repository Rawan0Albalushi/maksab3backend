<?php

namespace App\Http\Resources\Common\Item;

use App\CentralLogics\Helpers;
use App\Traits\Item\ProductVideoFormatTrait;
use Illuminate\Http\Request;

class ItemDetailResource extends ItemResource
{
    use ProductVideoFormatTrait;

    private const INTERNAL_KEYS = [
        'resolved_add_ons', 'resolved_category_names', 'resolved_taxes', 'seoData', 'pharmacy_item_details', 'rating', 'store', 'flash_sale_items', 'flashSaleItems',
        'nutritions', 'allergies', 'generic', 'storeCategory', 'store_category',
        'ecommerce_item_details', 'pharmacy_item_details', 'taxVats', 'seo_data',
        'translations', 'storage', 'unit',
    ];

    private bool $legacyShape = false;

    public function legacy(bool $legacy = true): static
    {
        $this->legacyShape = $legacy;

        return $this;
    }

    public static function legacyCollection(mixed $resource): array
    {
        return collect($resource)
            ->map(fn ($item) => (new static($item))->legacy()->toArray(request()))
            ->all();
    }

    public function toArray(Request $request): array
    {
        $payload = array_merge(parent::toArray($request), $this->fullFields());

        if (! $this->legacyShape) {
            return $payload;
        }

        $base = $this->resource->toArray();
        foreach (self::INTERNAL_KEYS as $key) {
            unset($base[$key]);
        }

        return array_merge($base, $payload);
    }

    private function fullFields(): array
    {
        $item = $this->resource;
        $store = $item->store;
        $addOns = $this->addOns();
        [$minDelivery, $maxDelivery] = $this->deliveryWindow($store?->delivery_time);

        return [
            'title' => $item->title ?? $item->name,
            'description' => $item->description,
            'category_id' => $item->category_id,
            'add_ons' => $addOns,
            'addons' => $addOns,
            'total_stock_quantity' => (int) ($item->total_stock_quantity ?? $item->stock ?? 0),
            'recommended' => (int) $item->recommended,
            'order_count' => (int) ($item->order_count ?? 0),
            'nutritions_name' => $this->taxonomyNames('nutritions', 'nutrition'),
            'allergies_name' => $this->taxonomyNames('allergies', 'allergy'),
            'generic_name' => $this->taxonomyNames('generic', 'generic_name'),
            'available_date_starts' => $item->available_date_starts,
            'min_delivery_time' => $minDelivery,
            'max_delivery_time' => $maxDelivery,
            'delivery_time' => $store?->delivery_time,
            'module' => $this->legacyShape
                ? ($this->resource->relationLoaded('module') ? $this->resource->module?->toArray() : null)
                : $this->moduleFields(),
            'store_details' => $this->storeFields($store),
            'store_logo_full_url' => $store?->logo_full_url,
            'attributes' => Helpers::decodeJsonToArray($item->attributes),
            'choice_options' => Helpers::decodeJsonToArray($item->choice_options),
            'variations' => $this->variationRows(),
            'food_variations' => $item->food_variations ? Helpers::decodeJsonToArray($item->food_variations) : '',
            'category_ids' => $this->categoryIdRows(),
            'common_condition_id' => (int) ($item->pharmacy_item_details?->common_condition_id ?? 0),
            'brand_id' => (int) ($item->ecommerce_item_details?->brand_id ?? 0),
            'brand_name' => $item->ecommerce_item_details?->brand?->name,
            'is_basic' => (int) ($item->pharmacy_item_details?->is_basic ?? 0),
            'is_prescription_required' => (int) ($item->pharmacy_item_details?->is_prescription_required ?? 0),
            'unit_value' => $item->pharmacy_item_details?->unit_value,
            'manufacturer' => $item->pharmacy_item_details?->manufacturer,
            'store_category_id' => (int) ($item->store_category_id ?? 0),
            'store_category_name' => $item->storeCategory?->name,
            'is_campaign' => ($store?->campaigns_count ?? 0) > 0 ? 1 : 0,
            'tax_data' => $this->taxRows(),
        ] + $this->ecommerceMeta() + $this->productVideoFields($item);
    }

    private function variationRows(): array
    {
        $rows = [];
        foreach (Helpers::decodeJsonToArray($this->resource->variations) as $var) {
            $rows[] = [
                'type' => $var['type'],
                'price' => (float) $var['price'],
                'stock' => (int) ($var['stock'] ?? 0),
            ];
        }

        return $rows;
    }

    private function categoryIdRows(): array
    {
        $names = $this->resource->resolved_category_names ?? collect();
        $rows = [];

        foreach (Helpers::decodeJsonToArray($this->resource->category_ids) as $value) {
            $id = (string) data_get($value, 'id');
            $rows[] = [
                'id' => $id,
                'position' => data_get($value, 'position'),
                'name' => $names->get($id, 'NA'),
            ];
        }

        return $rows;
    }

    private function taxRows(): array
    {
        $taxes = $this->resource->resolved_taxes ?? collect();

        return $taxes->map(fn ($tax) => [
            'id' => (int) $tax->id,
            'name' => $tax->name,
            'tax_rate' => (float) $tax->tax_rate,
        ])->values()->all();
    }

    private function ecommerceMeta(): array
    {
        if ($this->resource->module?->module_type !== 'ecommerce') {
            return [];
        }

        return [
            'meta_title' => $this->resource->seoData?->title,
            'meta_description' => $this->resource->seoData?->description,
            'meta_image' => $this->resource->seoData?->imageFullUrl,
            'meta_data' => $this->resource->seoData?->meta_data,
        ];
    }

    private function addOns(): array
    {
        $addOns = [];

        foreach ($this->resource->resolved_add_ons ?? collect() as $addOn) {
            $addOn['tax_ids'] = $addOn?->taxVats?->pluck('tax_id')->toArray() ?? [];
            unset($addOn['taxVats']);
            $addOns[] = $addOn;
        }

        return $addOns;
    }

    private function taxonomyNames(string $relation, string $column): array
    {
        if (! $this->resource->relationLoaded($relation)) {
            return [];
        }

        return collect($this->resource->getRelation($relation))->pluck($column)->filter()->values()->all();
    }


}
