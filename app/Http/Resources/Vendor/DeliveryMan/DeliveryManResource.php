<?php

namespace App\Http\Resources\Vendor\DeliveryMan;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class DeliveryManResource extends BaseResource
{
    private const DROPPED_RELATIONS = ['rating', 'wallet'];

    private bool $decodeIdentityImage = false;

    public static function renderList(mixed $paginator): array
    {
        return collect($paginator->items())->map(function ($deliveryMan) {
            $resource = new self($deliveryMan);
            $resource->decodeIdentityImage = true;

            return $resource->toArray(request());
        })->all();
    }

    public function toArray(Request $request): array
    {
        $deliveryMan = $this->resource;

        return array_merge(
            Arr::except($deliveryMan->toArray(), self::DROPPED_RELATIONS),
            $this->decodeIdentityImage ? ['identity_image' => $this->decodedIdentityImage($deliveryMan)] : [],
            $deliveryMan->relationLoaded('rating') ? $this->aggregateFields($deliveryMan) : []
        );
    }

    private function decodedIdentityImage(mixed $deliveryMan): mixed
    {
        return is_array($deliveryMan->identity_image)
            ? $deliveryMan->identity_image
            : json_decode((string) $deliveryMan->identity_image);
    }

    private function aggregateFields(mixed $deliveryMan): array
    {
        $rating = $deliveryMan->rating[0] ?? null;

        return [
            'orders_count' => (float) $deliveryMan->orders_count,
            'avg_rating' => (float) ($rating->average ?? 0),
            'rating_count' => (float) ($rating->rating_count ?? 0),
            'cash_in_hands' => $deliveryMan->wallet ? $deliveryMan->wallet->collected_cash : 0,
        ];
    }
}
