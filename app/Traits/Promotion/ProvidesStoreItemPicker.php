<?php

namespace App\Traits\Promotion;

use App\CentralLogics\Helpers;
use App\Models\AddOn;
use App\Models\Item;
use App\Scopes\StoreScope;
use App\Scopes\ZoneScope;
use Modules\Service\Entities\Service;

trait ProvidesStoreItemPicker
{
    use HandlesFrozenLines;
    protected function storeItemPickerOptions(int $storeId, ?string $search = null, ?int $moduleId = null): array
    {
        $items = Item::withoutGlobalScope(StoreScope::class)
            ->withoutGlobalScope(ZoneScope::class)
            ->where('store_id', $storeId)

            ->when($moduleId, fn ($q) => $q->where('module_id', $moduleId))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))

            ->with(['category:id,name', 'storage', 'module' => fn ($query) => $query->withoutTranslation()->select('id', 'module_type')])
            ->limit(50)
            ->get();

        $addOnIds = $items->flatMap(fn (Item $item) => Helpers::decodeJsonToArray($item->add_ons) ?? [])->unique()->all();
        $addOns = $addOnIds ? AddOn::whereIn('id', $addOnIds)->get()->keyBy('id') : collect();

        return $items->map(function (Item $item) use ($addOns) {
            $discount = $item->discount_type === 'percent'
                ? ((float) $item->price / 100) * (float) $item->discount
                : (float) $item->discount;

            return [
                'id' => $item->id,
                'name' => $item->name,

                'category_name' => $item->category->name ?? '',
                'description' => $item->description,
                'image_full_url' => $item->image_full_url,

                'price' => (float) $item->price,
                'discounted_price' => max(0, (float) $item->price - $discount),
                'discount' => (float) $item->discount,
                'discount_type' => $item->discount_type,
                'veg' => (int) $item->veg,
                'maximum_cart_quantity' => $item->maximum_cart_quantity,

                'variations' => Helpers::decodeJsonToArray($item->food_variations) ?? [],

                'choice_options' => Helpers::decodeJsonToArray($item->choice_options) ?? [],
                'variation_combinations' => Helpers::decodeJsonToArray($item->variations) ?? [],

                'uses_food_variations' => $this->usesFoodVariations($item),

                'stock' => $item->stock,
                'module_has_stock' => (bool) config('module.'.($item->module?->module_type ?? '').'.stock'),

                // Why the list still OFFERS these: an admin may legitimately build a bundle around
                // stock that lands tomorrow, and the availability check already refuses to serve or
                // sell a bundle whose member is switched off or short (see HandlesBundleCart). What
                // was missing was any sign of it at the point of choosing, so a bundle could be
                // assembled out of things that cannot currently sell with nothing said. These two
                // let the picker mark them; they do not filter anything.
                'is_active' => (bool) $item->status,
                'out_of_stock' => (bool) config('module.'.($item->module?->module_type ?? '').'.stock')
                    && (int) $item->stock <= 0,

                'add_ons' => collect(Helpers::decodeJsonToArray($item->add_ons) ?? [])
                    ->filter(fn ($id) => isset($addOns[$id]))
                    ->map(fn ($id) => [
                        'id' => $addOns[$id]->id,
                        'name' => $addOns[$id]->name,
                        'price' => (float) $addOns[$id]->price,
                    ])->values(),

                'is_available' => (bool) ($item->status && $item->is_approved),
            ];
        })->all();
    }

    protected function storeServicePickerOptions(int $storeId, ?string $search = null, ?int $moduleId = null): array
    {
        $services = Service::withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->when($moduleId, fn ($q) => $q->where('module_id', $moduleId))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->with('category:id,name')
            ->limit(50)
            ->get();

        return $services->map(function ($service) {
            $variants = collect(Helpers::decodeJsonToArray($service->variations) ?? [])
                ->map(fn ($variant) => [
                    'variant_key' => $variant['variant_key'] ?? null,
                    'name' => $variant['name'] ?? '',
                    'price' => (float) ($variant['price'] ?? 0),
                    'discount' => (float) ($variant['discount'] ?? 0),
                    'discount_type' => $variant['discount_type'] ?? 'percent',
                ])->values();

            $discount = $service->discount_type === 'percent'
                ? ((float) $service->base_price / 100) * (float) $service->discount
                : (float) $service->discount;

            return [
                'id' => $service->id,
                'name' => $service->name,
                'category_name' => $service->category->name ?? '',
                'description' => $service->short_description,
                'image_full_url' => $service->thumbnail_full_url ?? null,
                'price' => (float) $service->base_price,
                'discounted_price' => max(0, (float) $service->base_price - $discount),
                'discount' => (float) $service->discount,
                'discount_type' => $service->discount_type,
                'variants' => $variants,
                'requires_variant' => $variants->isNotEmpty(),
                'is_service' => true,
                'is_available' => (bool) ($service->status && $service->is_approved),
            ];
        })->all();
    }
}
