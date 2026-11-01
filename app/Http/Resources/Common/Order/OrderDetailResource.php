<?php

namespace App\Http\Resources\Common\Order;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class OrderDetailResource extends BaseResource
{
    private array $images = [];

    private array $beforeImages = [];

    private array $afterImages = [];

    public static function renderList(mixed $details, array $images = [], array $afterImages = [], array $beforeImages = []): array
    {
        $rows = [];

        foreach ($details as $index => $detail) {
            $resource = new self($detail);
            $resource->images = $images;
            $resource->beforeImages = $index === 0 ? $beforeImages : [];
            $resource->afterImages = $index === 0 ? $afterImages : [];
            $rows[] = $resource->toArray(request());
        }

        return $rows;
    }

    public function toArray(Request $request): array
    {
        $detail = $this->resource;

        return array_merge(
            $detail->toArray(),
            [
                'add_ons' => $this->decode($detail->add_ons),
                'variation' => $this->decode($detail->variation),
                'item_details' => $this->decode($detail->item_details),
            ],
            $this->beforeImages,
            [
                'image_full_url' => $this->images[$detail->id]['image_full_url'] ?? null,
                'images_full_url' => $this->images[$detail->id]['images_full_url'] ?? null,
            ],
            $this->afterImages
        );
    }

    private function decode(mixed $value): mixed
    {
        return is_array($value) ? $value : json_decode((string) $value, true);
    }
}
