<?php

namespace Modules\ReelsModule\Http\Resources;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use App\Models\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Rental\Entities\Vehicle;
use Modules\Service\Entities\Service;

abstract class BaseReelResource extends BaseResource
{
    protected const ITEM = Item::class;

    protected const VEHICLE = Vehicle::class;

    protected const SERVICE = Service::class;

    protected function productLabel(): ?string
    {
        return match ($this->resource->productable_type) {
            self::ITEM => 'item',
            self::VEHICLE => 'vehicle',
            self::SERVICE => 'service',
            null => null,
            default => Str::snake(class_basename($this->resource->productable_type)),
        };
    }

    protected function orderableProduct(): ?Model
    {
        $product = $this->resource->productable;

        return $product
            && (int) ($product->status ?? 1) === 1
            && (! isset($product->is_approved) || (int) $product->is_approved === 1)
            ? $product
            : null;
    }

    protected function productReferences(): array
    {
        $type = $this->resource->productable_type;

        return [
            'product_type' => $this->productLabel(),
            'product_id' => $this->resource->productable_id,
            'item_id' => $type === self::ITEM ? $this->resource->productable_id : null,
            'vehicle_id' => $type === self::VEHICLE ? $this->resource->productable_id : null,
            'service_id' => $type === self::SERVICE ? $this->resource->productable_id : null,
        ];
    }

    protected function productBlocks(bool $withStock = false): array
    {
        $product = $this->orderableProduct();
        $type = $this->resource->productable_type;

        return [
            'item' => $type === self::ITEM && $product ? array_merge([
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'image_full_url' => $product->image_full_url,
                'store_id' => $product->store_id,
            ], $withStock ? [
                'stock' => $product->stock,
                'maximum_cart_quantity' => $product->maximum_cart_quantity,
            ] : []) : null,
            'service' => $type === self::SERVICE && $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->base_price,
                'image_full_url' => $product->thumbnail_full_url,
                'store_id' => $product->store_id,
            ] : null,
            'vehicle' => $type === self::VEHICLE && $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'thumbnail_full_url' => $product->thumbnail_full_url,
                'hourly_price' => (float) $product->hourly_price,
                'day_wise_price' => (float) $product->day_wise_price,
                'distance_price' => (float) $product->distance_price,
                'provider_id' => $product->provider_id,
            ] : null,
        ];
    }

    protected function orderNowButton(): bool
    {
        return (bool) $this->resource->order_now_button && $this->orderableProduct() !== null;
    }

    protected function verifiedSeller(): int
    {
        return Helpers::get_verified_seller_status($this->resource->store, $this->resource->store?->storeConfig);
    }

    protected function counters(): array
    {
        return [
            'total_views' => (int) $this->resource->total_views,
            'total_likes' => (int) $this->resource->total_likes,
            'total_store_visits' => (int) $this->resource->total_store_visits,
            'total_sale' => (int) ($this->resource->order_count ?? 0),
            'total_sale_amount' => (float) ($this->resource->total_sale_amount ?? 0),
        ];
    }

    protected function translationRows(): mixed
    {
        return $this->whenLoaded('translations', fn () => $this->resource->translations->map(fn ($translation) => [
            'id' => $translation->id,
            'key' => $translation->key,
            'value' => $translation->value,
            'locale' => $translation->locale,
        ])->values(), []);
    }
}
