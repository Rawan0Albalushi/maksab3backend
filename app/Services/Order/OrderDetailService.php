<?php

namespace App\Services\Order;

use App\Models\OrderDetail;
use App\Services\BaseService;

class OrderDetailService extends BaseService
{
    public function make(): OrderDetail
    {
        return new OrderDetail;
    }

    public function insertMany(array $rows): void
    {
        OrderDetail::insert($rows);
    }

    public function deleteByIds(array $ids, mixed $orderId): void
    {
        OrderDetail::whereIn('id', $ids)->where('order_id', $orderId)->delete();
    }

    public function updateForOrder(mixed $detailId, mixed $orderId, array $data): void
    {
        OrderDetail::where('id', $detailId)->where('order_id', $orderId)->update($data);
    }

    public function insertOne(array $row): void
    {
        OrderDetail::insert($row);
    }

    public function buildPreservedRow($detail): array
    {
        return [
            'cart_id' => $detail->id,
            'bogo_offer_id' => $detail->bogo_offer_id ?? null,
            'bogo_group_id' => $detail->bogo_group_id ?? null,
            'is_free_item' => $detail->is_free_item ?? 0,
            'bogo_free_value' => $detail->bogo_free_value ?? null,
            'item_id' => $detail->item_id,
            'item_campaign_id' => $detail->item_campaign_id,
            'item_details' => is_string($detail->item_details) ? $detail->item_details : json_encode($detail->item_details),
            'quantity' => $detail->quantity,
            'price' => $detail->price,
            'category_id' => $detail->category_id ?? null,
            'tax_amount' => $detail->tax_amount ?? 0,
            'tax_status' => $detail->tax_status ?? null,
            'discount_on_product_by' => $detail->discount_on_product_by ?? null,
            'discount_type' => $detail->discount_type ?? null,
            'discount_on_item' => $detail->discount_on_item ?? 0,
            'discount_percentage' => $detail->discount_percentage ?? 0,
            'variant' => is_string($detail->variant) ? $detail->variant : json_encode($detail->variant ?? []),
            'variation' => is_string($detail->variation) ? $detail->variation : json_encode($detail->variation ?? []),
            'add_ons' => is_string($detail->add_ons) ? $detail->add_ons : json_encode($detail->add_ons ?? []),
            'total_add_on_price' => $detail->total_add_on_price ?? 0,
            'addon_discount' => $detail->addon_discount ?? 0,
            'created_at' => $detail->created_at ?? now(),
            'updated_at' => now(),
        ];
    }
}
