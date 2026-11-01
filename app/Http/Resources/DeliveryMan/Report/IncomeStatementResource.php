<?php

namespace App\Http\Resources\DeliveryMan\Report;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class IncomeStatementResource extends BaseResource
{
    private const COLUMNS = [
        'id', 'vendor_id', 'delivery_man_id', 'order_id', 'order_amount', 'store_amount', 'admin_commission',
        'received_by', 'status', 'created_at', 'updated_at', 'delivery_charge', 'original_delivery_charge',
        'tax', 'zone_id', 'module_id', 'parcel_catgory_id', 'dm_tips', 'delivery_fee_comission',
        'admin_expense', 'store_expense', 'discount_amount_by_store', 'additional_charge',
        'extra_packaging_amount', 'ref_bonus_amount', 'commission_percentage', 'is_subscribed',
        'pro_discount', 'pro_delivery_discount',
    ];

    public function toArray(Request $request): array
    {
        $payload = [];

        foreach (self::COLUMNS as $column) {
            $payload[$column] = $this->resource->{$column};
        }

        return $payload;
    }
}
