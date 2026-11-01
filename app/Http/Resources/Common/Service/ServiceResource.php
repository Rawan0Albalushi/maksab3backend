<?php

namespace App\Http\Resources\Common\Service;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\BaseResource;

class ServiceResource extends BaseResource
{
    public function __construct(mixed $resource, private readonly array $favoriteIds = [])
    {
        parent::__construct($resource);
    }

    public static function renderList(mixed $services, array $favoriteIds = []): array
    {
        return collect($services)->map(fn ($service) => (new self($service, $favoriteIds))->toArray(request()))->all();
    }

    public function toArray(Request $request): array
    {
        $service = $this->resource;
        $translated = [];

        if ($service->relationLoaded('translations')) {
            foreach ($service->translations as $translation) {
                $translated[$translation['key']] = $translation['value'];
            }
        }

        $store = $service->relationLoaded('store') ? $service->store : null;

        return [
            'id' => $service->id,
            'name' => $translated['name'] ?? $service->name,
            'slug' => $service->slug,
            'short_description' => $translated['short_description'] ?? $service->short_description,
            'long_description' => $translated['long_description'] ?? $service->long_description,
            'thumbnail_full_url' => $service->thumbnail_full_url,
            'additional_images_full_url' => $service->additional_images_full_url,
            'base_price' => (float) $service->base_price,
            'discount' => (float) ($service->discount ?? 0),
            'discount_type' => $service->discount_type ?? 'percent',
            'is_favorite' => in_array((int) $service->id, $this->favoriteIds, true),
            'tax_ids' => $service->tax_ids,
            'tax_data' => $service->tax_data,
            'variations' => $service->variations ?? [],
            'tags' => $service->tags ?? [],
            'recommended' => (int) $service->recommended,
            'is_approved' => (int) $service->is_approved,
            'status' => (int) $service->status,
            'order_count' => (int) $service->order_count,
            'avg_rating' => (float) $service->avg_rating,
            'rating_count' => (int) $service->rating_count,
            'module_id' => $service->module_id,
            'module_type' => $service->relationLoaded('module') ? $service->module?->module_type : null,
            'store_id' => $service->store_id,
            'store_name' => $store?->name,
            'provider_name' => $store?->name,
            'provider_image_full_url' => $store?->logo_full_url,
            'verified_provider' => $store ? (int) ($store->storeConfig?->verified_seller ?? 0) : 0,
            'category_id' => $service->category_id,
            'sub_category_id' => $service->sub_category_id,
            'store_category_id' => $service->store_category_id,
            'category' => $this->taxonomy($service, 'category'),
            'sub_category' => $this->taxonomy($service, 'subCategory'),
            'store_category' => $this->taxonomy($service, 'storeCategory'),
            'distance' => isset($service->distance) ? (float) $service->distance : null,
            'distance_km' => isset($service->distance) ? round(((float) $service->distance) / 1000, 2) : null,
            'created_at' => $service->created_at,
            'updated_at' => $service->updated_at,
        ];
    }

    private function taxonomy(mixed $service, string $relation): ?array
    {
        if (! $service->relationLoaded($relation) || ! $service->{$relation}) {
            return null;
        }

        return [
            'id' => $service->{$relation}->id,
            'name' => $service->{$relation}->name,
            'slug' => $service->{$relation}->slug,
        ];
    }
}
