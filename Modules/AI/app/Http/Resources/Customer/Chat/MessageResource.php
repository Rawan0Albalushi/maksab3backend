<?php

namespace Modules\AI\app\Http\Resources\Customer\Chat;

use App\Http\Resources\BaseResource;

class MessageResource extends BaseResource
{
    public function toArray($request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'conversation_id' => $this->resource->conversation_id,
            'role' => $this->resource->role,
            'content' => $this->resource->content,
            'tool_name' => $this->resource->tool_name,
            'metadata' => $this->metadata(),
            'created_at' => $this->resource->created_at,
        ]);
    }

    private function metadata(): mixed
    {
        $metadata = $this->resource->metadata;

        if (! is_array($metadata)) {
            return $metadata;
        }

        $cartItems = $metadata['cart_items'] ?? [];
        $metadata['cart'] = $this->cartByStore(is_array($cartItems) ? $cartItems : []);
        $encoded = json_encode($metadata);

        return $encoded === false ? $metadata : json_decode($encoded, false);
    }

    private function cartByStore(array $cartItems): array
    {
        $stores = [];
        $grandTotal = 0.0;
        $totalItems = 0;

        foreach ($cartItems as $row) {
            if (! is_array($row)) {
                continue;
            }

            $storeId = (int) ($row['store_id'] ?? 0);
            $lineTotal = round((float) ($row['line_total'] ?? 0), 2);

            $stores[$storeId] ??= [
                'store_id' => $storeId,
                'store_name' => $row['store_name'] ?? ('Store #'.$storeId),
                'items' => [],
                'store_subtotal' => 0.0,
            ];

            $stores[$storeId]['items'][] = [
                'cart_id' => $row['cart_id'] ?? null,
                'item_id' => (int) ($row['item_id'] ?? 0),
                'name' => $row['name'] ?? null,
                'variation' => $row['variation'] ?? '',
                'image' => $row['image'] ?? null,
                'image_full_url' => $row['image_full_url'] ?? null,
                'quantity' => (int) ($row['quantity'] ?? 0),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
                'line_total' => $lineTotal,
            ];
            $stores[$storeId]['store_subtotal'] = round($stores[$storeId]['store_subtotal'] + $lineTotal, 2);

            $grandTotal += $lineTotal;
            $totalItems++;
        }

        return [
            'stores' => array_values($stores),
            'grand_total' => round($grandTotal, 2),
            'total_items' => $totalItems,
        ];
    }
}
