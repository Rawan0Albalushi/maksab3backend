<?php

namespace App\Http\Resources\Customer\Order;

use Illuminate\Http\Request;

class OrderParcelResource extends OrderResource
{
    private ?string $saverDeliveryTime = null;

    public function withSaverDeliveryTime(?string $saverDeliveryTime): static
    {
        $this->saverDeliveryTime = $saverDeliveryTime;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'saver_delivery_time' => $this->saverDeliveryTime,
            'order_attachment' => $this->decodeJsonColumn($this->resource->order_attachment),
        ]);
    }
}
