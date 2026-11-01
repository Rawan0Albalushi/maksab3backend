<?php

namespace App\Traits\Api;

use App\Models\Order;

trait OrderPayloadTrait
{
    protected const ITEMS_PREVIEW_LIMIT = 3;

    protected function orderStoreCard(?object $store): ?array
    {
        if (! $store) {
            return null;
        }

        return [
            'id' => (int) $store->id,
            'module_id' => (int) ($store->module_id ?? 0),
            'name' => $store->name,
            'slug' => $store->slug,
            'phone' => $store->phone ?? null,
            'logo_full_url' => $store->logo_full_url,
            // 4.1 returned the whole formatted store here, so clients had this; trimming the
            // payload to a card dropped it. Kept because it decides who bears the commission,
            // which the app reads to work out what it may show about an order's charges.
            'store_business_model' => $store->store_business_model ?? null,
        ];
    }

    protected function orderDeliveryMan(?object $deliveryMan): ?array
    {
        if (! $deliveryMan) {
            return null;
        }

        $rating = $deliveryMan->relationLoaded('rating') ? $deliveryMan->rating : collect();
        $location = $deliveryMan->last_location;

        return [
            'id' => (int) $deliveryMan->id,
            'f_name' => $deliveryMan->f_name,
            'l_name' => $deliveryMan->l_name,
            'phone' => $deliveryMan->phone,
            'image_full_url' => $deliveryMan->image_full_url,
            'avg_rating' => (float) (count($rating) ? $rating[0]->average : 0),
            'rating_count' => (int) (count($rating) ? $rating[0]->rating_count : 0),
            'lat' => $location?->latitude,
            'lng' => $location?->longitude,
            'location' => $location?->location,
        ];
    }

    protected function orderItemRows(?object $order): array
    {
        return collect($order?->details ?? [])
            ->filter(fn ($detail) => (bool) $detail->item)
            ->map(fn ($detail) => [
                'id' => (int) $detail->item->id,
                'name' => $detail->item->name,
                'image_full_url' => $detail->item->image_full_url,
                'quantity' => (int) $detail->quantity,
            ])
            ->values()
            ->all();
    }

    protected function orderProDiscount(?object $order): array
    {
        $pro = $order?->orderProDiscount;

        return [
            'pro_discount' => (float) ($pro?->amount_saved ?? 0),
            'benefit_type' => $pro?->benefit_type,
            'delivery_fee_reduction_amount' => (float) ($pro?->delivery_fee_reduction_amount ?? 0),
        ];
    }

    protected function orderRefund(?object $refund): ?array
    {
        if (! $refund) {
            return null;
        }

        return [
            'image_full_url' => $refund->image_full_url,
            'customer_reason' => $refund->customer_reason,
            'customer_note' => $refund->customer_note,
            'admin_note' => $refund->admin_note,
        ];
    }

    protected function decodeJsonColumn(mixed $value): mixed
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return $value;
        }

        return json_decode((string) $value, true);
    }

    protected function orderDeliveryWindow(?Order $order): array
    {
        return $this->deliveryWindow($order?->store?->delivery_time);
    }
}
