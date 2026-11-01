<?php

namespace App\Http\Resources\Common\Chat;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class MessageResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'sender_id' => $this->resource->sender_id,
            'message' => $this->resource->message,
            'file_full_url' => $this->resource->file_full_url,
            'is_seen' => $this->resource->is_seen,
            'created_at' => $this->resource->created_at,
            'order' => $this->when(
                $this->resource->relationLoaded('order'),
                fn () => $this->order()
            ),
        ]);
    }

    private function order(): mixed
    {
        $order = $this->resource->getRelation('order');

        if (! $order) {
            return null;
        }

        return [
            'id' => (int) $order->id,
            'order_amount' => (float) $order->order_amount,
            'order_status' => $order->order_status,
            'created_at' => $order->created_at,
            'details_count' => (int) $order->details_count,
            'delivery_address' => is_string($order->delivery_address)
                ? json_decode($order->delivery_address, true)
                : $order->delivery_address,
        ];
    }
}
