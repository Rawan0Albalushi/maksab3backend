<?php

namespace App\Traits\Item;

trait ItemRelationsTrait
{
    protected function itemRelationSet(): array
    {
        return [
            'storage' => fn ($query) => $query->select(STORAGE_RELATION_COLUMNS),
            'rating' => fn ($query) => $query->where('status', 1),
            'module' => fn ($query) => $query->withoutGlobalScope('translate')->select('id', 'module_type'),
            'unit' => fn ($query) => $query->translateOnly(['unit'])->select('id', 'unit'),
            'ecommerce_item_details' => fn ($query) => $query->select('id', 'item_id', 'brand_id')
                ->with(['brand' => fn ($brand) => $brand->translateOnly(['name'])->select('id', 'name')]),
            'store' => fn ($query) => $query->translateOnly(['name'])
                ->select('id', 'name', 'slug', 'logo', 'module_id', 'zone_id', 'delivery_time', 'free_delivery', 'schedule_order')
                ->with([
                    'storage' => fn ($storage) => $storage->select(STORAGE_RELATION_COLUMNS),
                    'storeConfig' => fn ($config) => $config->select('store_id', 'verified_seller', 'halal_tag_status'),
                    'discount' => fn ($discount) => $discount->validate(),
                    'happyHourEnrollments.happyHour.dates',
                ]),
            'flashSaleItems' => fn ($query) => $query->active()
                ->whereHas('flashSale', fn ($sale) => $sale->active()->running())
                ->select('id', 'item_id', 'available_stock'),
        ];
    }
}
