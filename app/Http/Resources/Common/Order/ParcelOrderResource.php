<?php

namespace App\Http\Resources\Common\Order;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Common\Parcel\OrderParcelTierResource;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ParcelOrderResource extends BaseResource
{
    public function __construct(mixed $resource, private readonly array $extras = [], private readonly array $except = ['store'])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $order = $this->resource;

        return array_merge(
            Arr::except($order->toArray(), $this->except),
            ['delivery_address' => $this->decode($order->delivery_address)],
            // The two tiers beside the category, in the shape the customer and deliveryman order
            // resources use. This payload IS parcel order details for the rider and the vendor,
            // so leaving them out would mean the one screen that most needs to know what is being
            // collected was the one screen that could not say.
            [
                'weight' => OrderParcelTierResource::forWeight($order->weight),
                'dimension' => OrderParcelTierResource::forDimension($order->dimension),
            ],
            $order->prescription_order && $order->order_attachment
                ? ['order_attachment' => $this->decode($order->order_attachment)]
                : [],
            $this->extras
        );
    }

    private function decode(mixed $value): mixed
    {
        return is_array($value) ? $value : json_decode((string) $value, true);
    }
}
