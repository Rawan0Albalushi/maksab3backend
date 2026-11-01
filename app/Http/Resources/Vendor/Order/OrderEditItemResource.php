<?php

namespace App\Http\Resources\Vendor\Order;

use App\Support\Settings\BusinessRules;
use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class OrderEditItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $item = $this->resource;
        $moduleType = $item->module?->module_type;
        $isFood = $moduleType === 'food';
        $tracksStock = $moduleType ? (bool) data_get(config('module.'.$moduleType), 'stock', false) : false;
        $stock = $this->resolveStock($item, $tracksStock);

        return [
            'id' => $item->id,
            'name' => $item->name,
            'image' => $item->image_full_url,
            'formatted_price' => Helpers::format_currency($item->price - Helpers::product_discount_calculate($item, $item->price, $item->store)['discount_amount']),
            'original_price' => $item->discount > 0 ? Helpers::format_currency($item->price) : null,
            'has_variations' => $this->hasVariations($item, $isFood),
            'has_addons' => $this->hasAddons($item),
            'is_available' => $tracksStock && $stock <= 0 ? false : ($isFood ? $item->is_available_now : true),
            'available_time' => $isFood && $item->available_time_starts && $item->available_time_ends
                ? date(config('timeformat') ?? 'H:i', strtotime($item->available_time_starts)).' - '.date(config('timeformat') ?? 'H:i', strtotime($item->available_time_ends))
                : null,
            'tracks_stock' => $tracksStock,
            'stock' => $stock,
            'veg' => $isFood && BusinessRules::vegNonVegEnabled() && (bool) data_get(config('module.'.$moduleType), 'veg_non_veg', false)
                ? (int) $item->veg
                : null,
            'is_halal' => $item->is_halal == 1
                && (bool) data_get(config('module.'.$moduleType), 'halal', false)
                && (bool) ($item->store?->storeConfig?->halal_tag_status ?? 0),
        ];
    }

    private function decoded(mixed $value): array
    {
        $decoded = is_array($value) ? $value : json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function hasVariations(mixed $item, bool $isFood): bool
    {
        return count($this->decoded($isFood ? $item->food_variations : $item->choice_options)) > 0;
    }

    private function hasAddons(mixed $item): bool
    {
        $addons = $this->decoded($item->add_ons);

        return count($addons) > 0 && ! empty($addons[0]);
    }

    private function resolveStock(mixed $item, bool $tracksStock): ?int
    {
        if (! $tracksStock) {
            return null;
        }

        $variations = $this->decoded($item->variations);

        return $variations !== []
            ? (int) array_sum(array_map(fn ($v) => (int) ($v['stock'] ?? 0), $variations))
            : (int) $item->stock;
    }
}
